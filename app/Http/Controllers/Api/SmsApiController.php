<?php

namespace App\Http\Controllers\Api;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\SmsSettings;
use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Exception;
use App\Http\Controllers\Controller;

class SmsApiController extends Controller
{
    private function user(Request $request) {
        return $request->attributes->get('api_user');
    }

    private function routerIds($user) {
        if ($user && $user->isSuperAdmin()) {
            return Router::pluck('id')->toArray();
        }
        if ($user && $user->role === 'reseller') {
            $resellerRouterIds = \App\Models\ResellerRouterPermission::where('reseller_id', $user->id)
                ->pluck('router_id')->toArray();
            $adminRouterIds = $user->routers()->pluck('routers.id')->toArray();
            return array_unique(array_merge($adminRouterIds, $resellerRouterIds));
        }
        if ($user) {
            return $user->routers()->pluck('routers.id')->toArray();
        }
        return [];
    }

    /**
     * Dashboard stats - all routers
     */

    /**
     * Check if user has SMS access
     */
    private function checkSmsAccess($user)
    {
        if ($user->isSuperAdmin()) return true;
        return (bool) $user->sms_enabled;
    }

    public function dashboard(Request $request)
    {
        $user = $this->user($request);
        $allowedIds = $this->routerIds($user);
        $routers = Router::whereIn('id', $allowedIds)->orderBy('name')->get();

        $routerStats = [];
        foreach ($routers as $router) {
            $settings = SmsSettings::where('router_id', $router->id)->first();

            $todayQuery = SmsLog::where('router_id', $router->id)->whereDate('created_at', today());
            $monthQuery = SmsLog::where('router_id', $router->id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);

            $routerStats[] = [
                'id' => $router->id,
                'name' => $router->name,
                'ip' => $router->ip_address,
                'sms_enabled' => $settings ? $settings->is_enabled : false,
                'country_code' => $settings ? $settings->country_code : '+963',
                'welcome_enabled' => $settings ? ($settings->welcome_enabled ?? false) : false,
                'today' => [
                    'total' => (clone $todayQuery)->count(),
                    'sent' => (clone $todayQuery)->where('status', 'sent')->count(),
                    'failed' => (clone $todayQuery)->where('status', 'failed')->count(),
                ],
                'month' => [
                    'total' => (clone $monthQuery)->count(),
                    'sent' => (clone $monthQuery)->where('status', 'sent')->count(),
                    'failed' => (clone $monthQuery)->where('status', 'failed')->count(),
                ],
                'subscribers_with_phone' => Subscriber::where('router_id', $router->id)
                    ->whereNotNull('phone')->where('phone', '!=', '')->count(),
                'total_subscribers' => Subscriber::where('router_id', $router->id)->count(),
            ];
        }

        // Global stats (filtered by user's routers)
        $globalToday = SmsLog::whereIn('router_id', $allowedIds)->whereDate('created_at', today());
        $globalMonth = SmsLog::whereIn('router_id', $allowedIds)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);

        return response()->json([
            'routers' => $routerStats,
            'global' => [
                'total_routers' => count($routerStats),
                'enabled_routers' => collect($routerStats)->where('sms_enabled', true)->count(),
                'today' => [
                    'total' => (clone $globalToday)->count(),
                    'sent' => (clone $globalToday)->where('status', 'sent')->count(),
                    'failed' => (clone $globalToday)->where('status', 'failed')->count(),
                ],
                'month' => [
                    'total' => (clone $globalMonth)->count(),
                    'sent' => (clone $globalMonth)->where('status', 'sent')->count(),
                    'failed' => (clone $globalMonth)->where('status', 'failed')->count(),
                ],
            ],
        ]);
    }

    /**
     * Get settings for a specific router
     */
    public function getSettings(Request $request, $routerId)
    {
        $router = Router::findOrFail($routerId);
        $settings = SmsSettings::firstOrCreate(
            ['router_id' => $router->id],
            [
                'country_code' => '+963',
                'is_enabled' => false,
                'reminder_days_before' => 3,
                'reminder_message' => SmsSettings::getDefaultMessage(),
                'send_time' => '09:00',
                'send_on_expiry' => true,
                'send_after_expiry' => false,
                'after_expiry_days' => 1,
                'welcome_enabled' => false,
                'welcome_message' => null,
            ]
        );

        return response()->json([
            'router' => ['id' => $router->id, 'name' => $router->name],
            'settings' => $settings,
        ]);
    }

    /**
     * Update settings for a router
     */
    public function updateSettings(Request $request, $routerId)
    {
        $router = Router::findOrFail($routerId);

        $validated = $request->validate([
            'country_code' => 'required|string|max:10',
            'is_enabled' => 'required|boolean',
            'reminder_days_before' => 'required|integer|min:1|max:30',
            'reminder_message' => 'required|string|max:500',
            'send_time' => 'required|string',
            'send_on_expiry' => 'required|boolean',
            'send_after_expiry' => 'required|boolean',
            'after_expiry_days' => 'nullable|integer|min:1|max:7',
            'welcome_enabled' => 'nullable|boolean',
            'welcome_message' => 'nullable|string|max:500',
        ]);

        $settings = SmsSettings::updateOrCreate(
            ['router_id' => $router->id],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ الإعدادات بنجاح',
            'settings' => $settings,
        ]);
    }

    /**
     * Check modem status (HiLink gateway)
     */
    public function checkModem(Request $request, $routerId)
    {
        $router = Router::findOrFail($routerId);

        try {
            $smsService = new SmsService($router);
            $status = $smsService->checkModemStatus();
            return response()->json($status);
        } catch (Exception $e) {
            return response()->json([
                'connected' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send test SMS
     */
    public function sendTest(Request $request, $routerId)
    {
        $router = Router::findOrFail($routerId);
        $request->validate([
            'phone' => 'required|string|min:9|max:15',
            'message' => 'nullable|string|max:500',
        ]);

        try {
            $smsService = new SmsService($router);
            $log = $smsService->testSms($request->phone, $request->message);

            return response()->json([
                'success' => $log->status === 'sent',
                'log' => $log,
                'message' => $log->status === 'sent' ? 'تم إرسال الرسالة بنجاح' : 'فشل الإرسال: ' . $log->error_message,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send SMS to a specific subscriber
     */
    public function sendToSubscriber(Request $request, $routerId, $subscriberId)
    {
        $router = Router::findOrFail($routerId);
        $subscriber = Subscriber::findOrFail($subscriberId);

        $request->validate(['message' => 'required|string|max:500']);

        if (!$subscriber->phone) {
            return response()->json(['error' => 'المشترك ليس لديه رقم هاتف'], 422);
        }

        try {
            $smsService = new SmsService($router);
            $log = $smsService->sendSms($subscriber->phone, $request->message, $subscriber->id, SmsLog::TYPE_MANUAL);

            return response()->json([
                'success' => $log->status === 'sent',
                'log' => $log,
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send bulk SMS
     */
    public function sendBulk(Request $request, $routerId)
    {
        $router = Router::findOrFail($routerId);
        $request->validate(['message' => 'required|string|max:500']);
        
        $filter = $request->input('filter', 'all'); // all | unpaid

        try {
            $smsService = new SmsService($router);
            $smsService->connect();
            
            if ($filter === 'unpaid') {
                $results = $smsService->sendToUnpaidUsers($request->message);
            } else {
                $results = $smsService->sendToAllUsers($request->message);
            }
            
            $smsService->disconnect();

            return response()->json([
                'success' => true,
                'results' => $results,
                'message' => "تم إرسال {$results['sent']} رسالة، فشل {$results['failed']}، تخطي {$results['skipped']}",
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Send expiry reminders
     */
    public function sendReminders(Request $request, $routerId)
    {
        $router = Router::findOrFail($routerId);

        try {
            $smsService = new SmsService($router);
            $smsService->connect();
            $results = $smsService->sendExpiryReminders();
            $smsService->disconnect();

            return response()->json([
                'success' => true,
                'results' => $results,
                'message' => "تذكيرات: أُرسل {$results['sent']}، فشل {$results['failed']}، تخطي {$results['skipped']}",
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get SMS logs
     */
    public function getLogs(Request $request, $routerId)
    {
        $router = Router::findOrFail($routerId);
        $query = SmsLog::where('router_id', $router->id)->with('subscriber:id,username,full_name,phone');

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('type')) $query->where('type', $request->type);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('phone_number', 'like', "%{$s}%")
                  ->orWhere('message', 'like', "%{$s}%");
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->limit($request->input('limit', 100))->get();

        return response()->json([
            'logs' => $logs->map(function($log) {
                return [
                    'id' => $log->id,
                    'phone' => $log->phone_number,
                    'message' => $log->message,
                    'status' => $log->status,
                    'type' => $log->type,
                    'error' => $log->error_message,
                    'subscriber' => $log->subscriber ? $log->subscriber->full_name ?: $log->subscriber->username : null,
                    'sent_at' => $log->sent_at?->format('Y-m-d H:i'),
                    'created_at' => $log->created_at->format('Y-m-d H:i'),
                ];
            }),
            'total' => SmsLog::where('router_id', $router->id)->count(),
        ]);
    }

    /**
     * Get all logs across all routers
     */
    public function getAllLogs(Request $request)
    {
        $user = $this->user($request);
        $allowedIds = $this->routerIds($user);
        $query = SmsLog::whereIn('router_id', $allowedIds)->with(['subscriber:id,username,full_name,phone', 'router:id,name']);

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('type')) $query->where('type', $request->type);
        if ($request->filled('router_id')) $query->where('router_id', $request->router_id);

        $logs = $query->orderBy('created_at', 'desc')->limit($request->input('limit', 200))->get();

        return response()->json([
            'logs' => $logs->map(function($log) {
                return [
                    'id' => $log->id,
                    'router' => $log->router ? $log->router->name : null,
                    'router_id' => $log->router_id,
                    'phone' => $log->phone_number,
                    'message' => $log->message,
                    'status' => $log->status,
                    'type' => $log->type,
                    'error' => $log->error_message,
                    'subscriber' => $log->subscriber ? $log->subscriber->full_name ?: $log->subscriber->username : null,
                    'sent_at' => $log->sent_at?->format('Y-m-d H:i'),
                    'created_at' => $log->created_at->format('Y-m-d H:i'),
                ];
            }),
        ]);
    }

    /**
     * Get subscribers list with phone info for a router
     */
    public function getSubscribers(Request $request, $routerId)
    {
        $router = Router::findOrFail($routerId);
        $query = Subscriber::where('router_id', $router->id)
            ->select('id', 'username', 'full_name', 'phone', 'whatsapp_number', 'sms_enabled', 'status', 'profile', 'expiration_date');

        if ($request->filled('has_phone')) {
            $query->whereNotNull('phone')->where('phone', '!=', '');
        }

        // Filter unpaid subscribers only
        if ($request->filled('unpaid')) {
            $query->where(function($q) {
                $q->where('is_paid', false)
                  ->orWhere('remaining_amount', '>', 0);
            });
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function($q) use ($s) {
                $q->where('username', 'like', "%{$s}%")
                  ->orWhere('full_name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        $subs = $query->orderBy('full_name')->limit($request->input('limit', 200))->get();

        return response()->json([
            'subscribers' => $subs,
            'total' => Subscriber::where('router_id', $router->id)->count(),
            'with_phone' => Subscriber::where('router_id', $router->id)->whereNotNull('phone')->where('phone', '!=', '')->count(),
        ]);
    }

    /**
     * Update subscriber phone
     */
    public function updateSubscriberPhone(Request $request, $subscriberId)
    {
        $subscriber = Subscriber::findOrFail($subscriberId);
        $request->validate([
            'phone' => 'nullable|string|max:20',
            'sms_enabled' => 'nullable|boolean',
        ]);

        $data = [];
        if ($request->has('phone')) $data['phone'] = $request->phone;
        if ($request->has('sms_enabled')) $data['sms_enabled'] = $request->sms_enabled;

        $subscriber->update($data);

        return response()->json(['success' => true, 'subscriber' => $subscriber->fresh()]);
    }

    /**
     * Delete a log entry
     */
    public function deleteLog(Request $request, $logId)
    {
        $log = SmsLog::findOrFail($logId);
        $log->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Clear all logs for a router
     */
    public function clearLogs(Request $request, $routerId)
    {
        $router = Router::findOrFail($routerId);
        $deleted = SmsLog::where('router_id', $router->id)->delete();
        return response()->json(['success' => true, 'deleted' => $deleted]);
    }

    /**
     * Log a single phone SMS (sent from mobile device)
     */
    public function logPhoneSms(Request $request, $routerId)
    {
        $user = $this->user($request);
        if (!$this->checkSmsAccess($user)) return response()->json(['error' => 'No access'], 403);

        $router = \App\Models\Router::find($routerId);
        if (!$router) return response()->json(['error' => 'Router not found'], 404);

        try {
            \App\Models\SmsLog::create([
                'router_id' => $routerId,
                'subscriber_id' => null,
                'phone_number' => $request->input('phone', ''),
                'message' => $request->input('message', ''),
                'status' => $request->input('status', 'sent'),
                'type' => 'phone_sms',
                'error_message' => $request->input('status') === 'failed' ? $request->input('info', '') : null,
                'sent_at' => now(),
            ]);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Log bulk phone SMS results (sent from mobile device)
     */
    public function logPhoneSmsBlulk(Request $request, $routerId)
    {
        $user = $this->user($request);
        if (!$this->checkSmsAccess($user)) return response()->json(['error' => 'No access'], 403);

        $router = \App\Models\Router::find($routerId);
        if (!$router) return response()->json(['error' => 'Router not found'], 404);

        try {
            $results = $request->input('results', []);
            $logged = 0;

            foreach ($results as $result) {
                $phone = $result['phone'] ?? '';
                $success = $result['success'] ?? false;
                $name = $result['name'] ?? '';
                $error = $result['error'] ?? '';

                \App\Models\SmsLog::create([
                    'router_id' => $routerId,
                    'subscriber_id' => null,
                    'phone_number' => $phone,
                    'message' => '[إرسال جماعي من الهاتف] ' . ($request->input('template', 'manual')),
                    'status' => $success ? 'sent' : 'failed',
                    'type' => 'phone_sms',
                    'error_message' => !$success ? $error : null,
                    'sent_at' => now(),
                ]);
                $logged++;
            }

            return response()->json(['success' => true, 'logged' => $logged]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
