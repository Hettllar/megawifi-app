<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\SmsSettings;
use App\Models\SmsLog;
use App\Services\SmsService;
use App\Services\ZteSmsGateway;
use Illuminate\Http\Request;
use Exception;

class SmsController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!auth()->user()->isSuperAdmin()) {
                abort(403, 'غير مصرح لك بالوصول إلى صفحة الرسائل');
            }
            return $next($request);
        });
    }

    /**
     * Display SMS settings page
     */
    public function index(Router $router)
    {
        $settings = SmsSettings::firstOrCreate(
            ['router_id' => $router->id],
            [
                'usb_port' => 'usb1',
                'country_code' => '+963',
                'is_enabled' => false,
                'reminder_days_before' => 3,
                'reminder_message' => SmsSettings::getDefaultMessage(),
                'welcome_message' => SmsSettings::getDefaultWelcomeMessage(),
                'welcome_enabled' => false,
                'send_time' => '09:00',
                'send_on_expiry' => true,
                'send_after_expiry' => false,
                'after_expiry_days' => 1,
            ]
        );

        // Check gateway modem status
        $gateway_status = [];
        try {
            $gateway = new ZteSmsGateway();
            $gateway->connect();
            $gateway_status = $gateway->checkModemStatus();
            $gateway->disconnect();
        } catch (Exception $e) {
            $gateway_status = ['connected' => false, 'error' => $e->getMessage()];
        }

        // Get statistics
        $stats = [
            'today' => $this->getStatsForPeriod($router->id, 'today'),
            'week' => $this->getStatsForPeriod($router->id, 'week'),
            'month' => $this->getStatsForPeriod($router->id, 'month'),
        ];

        // Global stats
        $globalStats = ZteSmsGateway::getGlobalStats();

        // Get recent logs
        $logs = SmsLog::where('router_id', $router->id)
            ->with('subscriber')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Get subscribers with phone numbers for manual/bulk SMS
        $subscribers = Subscriber::where('router_id', $router->id)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->select('id', 'username', 'full_name', 'phone', 'profile', 'status')
            ->orderBy('username')
            ->get();

        return view('sms.index', compact('router', 'settings', 'gateway_status', 'stats', 'globalStats', 'logs', 'subscribers'));
    }

    /**
     * Update SMS settings
     */
    public function updateSettings(Request $request, Router $router)
    {
        $validated = $request->validate([
            'country_code' => 'required|string|max:10',
            'is_enabled' => 'boolean',
            'reminder_days_before' => 'required|integer|min:1|max:30',
            'reminder_message' => 'required|string|max:500',
            'welcome_message' => 'nullable|string|max:500',
            'welcome_enabled' => 'boolean',
            'send_time' => 'required|date_format:H:i',
            'send_on_expiry' => 'boolean',
            'send_after_expiry' => 'boolean',
            'after_expiry_days' => 'nullable|integer|min:1|max:7',
        ]);

        $validated['is_enabled'] = $request->has('is_enabled');
        $validated['send_on_expiry'] = $request->has('send_on_expiry');
        $validated['send_after_expiry'] = $request->has('send_after_expiry');
        $validated['welcome_enabled'] = $request->has('welcome_enabled');

        SmsSettings::updateOrCreate(
            ['router_id' => $router->id],
            $validated
        );

        return redirect()->back()->with('success', 'تم حفظ إعدادات SMS بنجاح');
    }

    /**
     * Send test SMS
     */
    public function sendTest(Request $request, Router $router)
    {
        $request->validate([
            'phone' => 'required|string|min:9|max:15',
            'message' => 'nullable|string|max:500',
        ]);

        // Rate limit: 1 test per 30 seconds per phone
        $rateLimitKey = 'test_sms_' . preg_replace('/[^0-9]/', '', $request->phone);
        if (Cache::has($rateLimitKey)) {
            return redirect()->back()->with('error', 'انتظر 30 ثانية قبل إرسال رسالة تجريبية أخرى.');
        }
        Cache::put($rateLimitKey, true, 30);

        try {
            $smsService = new SmsService($router);

            $log = $smsService->testSms(
                $request->phone,
                $request->message
            );

            $smsService->disconnect();

            if ($log->status === SmsLog::STATUS_SENT) {
                return redirect()->back()->with('success', 'تم إرسال الرسالة الاختبارية بنجاح ✅');
            } else {
                return redirect()->back()->with('error', 'فشل إرسال الرسالة: ' . ($log->error_message ?? 'خطأ غير معروف'));
            }
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Send SMS to specific subscriber (manual SMS)
     */
    public function sendToSubscriber(Request $request, Router $router, Subscriber $subscriber)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        if (!$subscriber->phone) {
            return redirect()->back()->with('error', 'المشترك ليس لديه رقم هاتف');
        }

        try {
            $smsService = new SmsService($router);

            $log = $smsService->sendSms(
                $subscriber->phone,
                $request->message,
                $subscriber->id,
                SmsLog::TYPE_MANUAL
            );

            $smsService->disconnect();

            if ($log->status === SmsLog::STATUS_SENT) {
                return redirect()->back()->with('success', 'تم إرسال الرسالة بنجاح إلى ' . ($subscriber->full_name ?: $subscriber->username));
            } else {
                return redirect()->back()->with('error', 'فشل الإرسال: ' . ($log->error_message ?? 'خطأ غير معروف'));
            }
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Send bulk SMS to selected subscribers
     */
    public function sendBulk(Request $request, Router $router)
    {
        $request->validate([
            'subscribers' => 'required|array|min:1',
            'subscribers.*' => 'exists:subscribers,id',
            'message' => 'required|string|max:500',
        ]);

        // Prevent duplicate bulk sends - lock for 5 minutes
        $batchKey = 'bulk_sending_' . $router->id;
        if (Cache::has($batchKey)) {
            return redirect()->back()->with('warning', 'يتم بالفعل إرسال رسائل جماعية. انتظر حتى الانتهاء.');
        }
        Cache::put($batchKey, true, 300);

        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        try {
            $gateway = new ZteSmsGateway();
            $gateway->connect();

            foreach ($request->subscribers as $subscriberId) {
                $subscriber = Subscriber::find($subscriberId);

                if (!$subscriber || !$subscriber->phone) {
                    $results['skipped']++;
                    continue;
                }

                try {
                    $log = $gateway->sendSms(
                        $subscriber->phone,
                        $request->message,
                        $router->id,
                        $subscriber->id,
                        SmsLog::TYPE_MANUAL
                    );

                    if ($log->status === SmsLog::STATUS_SENT) {
                        $results['sent']++;
                    } else {
                        $results['failed']++;
                    }

                    sleep(ZteSmsGateway::DELAY_BETWEEN_SMS);

                } catch (Exception $e) {
                    $results['failed']++;
                }
            }

            $gateway->disconnect();
            Cache::forget($batchKey);

            return redirect()->back()->with('success',
                "تم إرسال {$results['sent']} رسالة بنجاح، فشل {$results['failed']}، تخطي {$results['skipped']}"
            );
        } catch (Exception $e) {
            Cache::forget($batchKey);
            return redirect()->back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Send to all subscribers of this router
     */
    public function sendToAll(Request $request, Router $router)
    {
        $request->validate([
            'message' => 'required|string|max:500',
        ]);

        try {
            $smsService = new SmsService($router);
            $smsService->connect();

            $results = $smsService->sendToAllUsers($request->message);

            $smsService->disconnect();

            return redirect()->back()->with('success',
                "تم إرسال {$results['sent']} رسالة بنجاح، فشل {$results['failed']}، تخطي {$results['skipped']}"
            );
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    /**
     * Send reminders manually
     */
    public function sendReminders(Router $router)
    {
        try {
            $smsService = new SmsService($router);
            $smsService->connect();

            $results = $smsService->sendExpiryReminders();

            $smsService->disconnect();

            return redirect()->back()->with('success',
                "تم إرسال {$results['sent']} تذكير، فشل {$results['failed']}، تخطي {$results['skipped']}"
            );
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'خطأ: ' . $e->getMessage());
        }
    }

    /**
     * View SMS logs with filters
     */
    public function logs(Request $request, Router $router)
    {
        $query = SmsLog::where('router_id', $router->id)
            ->with('subscriber');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('phone_number', 'like', '%' . $request->search . '%')
                  ->orWhere('message', 'like', '%' . $request->search . '%');
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);

        // Stats for log page
        $logStats = [
            'total' => SmsLog::where('router_id', $router->id)->count(),
            'sent' => SmsLog::where('router_id', $router->id)->where('status', SmsLog::STATUS_SENT)->count(),
            'failed' => SmsLog::where('router_id', $router->id)->where('status', SmsLog::STATUS_FAILED)->count(),
            'by_type' => [
                'manual' => SmsLog::where('router_id', $router->id)->where('type', SmsLog::TYPE_MANUAL)->count(),
                'reminder' => SmsLog::where('router_id', $router->id)->where('type', SmsLog::TYPE_REMINDER)->count(),
                'welcome' => SmsLog::where('router_id', $router->id)->where('type', SmsLog::TYPE_WELCOME)->count(),
                'renewal' => SmsLog::where('router_id', $router->id)->where('type', SmsLog::TYPE_RENEWAL)->count(),
            ],
        ];

        return view('sms.logs', compact('router', 'logs', 'logStats'));
    }

    /**
     * Delete SMS log
     */
    public function deleteLog(Router $router, SmsLog $log)
    {
        if ($log->router_id !== $router->id) {
            abort(403);
        }

        $log->delete();

        return redirect()->back()->with('success', 'تم حذف السجل');
    }

    /**
     * Check gateway modem status via AJAX
     */
    public function checkModem(Router $router)
    {
        try {
            $gateway = new ZteSmsGateway();
            $gateway->connect();
            $status = $gateway->checkModemStatus();
            $gateway->disconnect();

            $status['daily_sent'] = ZteSmsGateway::getGlobalStats()['today']['sent'];
            $status['daily_remaining'] = ZteSmsGateway::getGlobalStats()['today_remaining'];

            return response()->json($status);
        } catch (Exception $e) {
            return response()->json([
                'connected' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Global SMS statistics API endpoint
     */
    public function statistics()
    {
        $stats = ZteSmsGateway::getGlobalStats();

        // Per-router breakdown
        $routers = Router::all();
        $perRouter = [];
        foreach ($routers as $router) {
            $count = SmsLog::where('router_id', $router->id)->count();
            if ($count > 0) {
                $perRouter[] = [
                    'router' => $router->name,
                    'total' => $count,
                    'sent' => SmsLog::where('router_id', $router->id)->where('status', SmsLog::STATUS_SENT)->count(),
                    'failed' => SmsLog::where('router_id', $router->id)->where('status', SmsLog::STATUS_FAILED)->count(),
                ];
            }
        }

        $stats['per_router'] = $perRouter;

        // Daily chart data (last 7 days)
        $dailyData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dailyData[] = [
                'date' => $date->format('m/d'),
                'sent' => SmsLog::whereDate('created_at', $date)->where('status', SmsLog::STATUS_SENT)->count(),
                'failed' => SmsLog::whereDate('created_at', $date)->where('status', SmsLog::STATUS_FAILED)->count(),
            ];
        }
        $stats['daily_chart'] = $dailyData;

        return response()->json($stats);
    }

    /**
     * Get statistics for period
     */
    private function getStatsForPeriod(int $routerId, string $period): array
    {
        $query = SmsLog::where('router_id', $routerId);

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
        }

        return [
            'total' => (clone $query)->count(),
            'sent' => (clone $query)->where('status', SmsLog::STATUS_SENT)->count(),
            'failed' => (clone $query)->where('status', SmsLog::STATUS_FAILED)->count(),
        ];
    }

    /**
     * Update subscriber phone via AJAX
     */
    public function updateSubscriberPhone(Request $request, Subscriber $subscriber)
    {
        $request->validate([
            'phone' => 'nullable|string|max:20',
        ]);

        $subscriber->update([
            'phone' => $request->phone,
        ]);

        return response()->json(['success' => true]);
    }
}
