<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\ResellerRouterPermission;
use App\Models\ResellerTransaction;
use App\Models\AdminNotification;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ResellerPanelController extends Controller
{
    /**
     * لوحة تحكم الوكيل
     */
    public function dashboard()
    {
        $user = Auth::user();
        
        // Debug logging
        \Log::info('ResellerPanel Dashboard', [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_role' => $user->role,
            'is_reseller' => $user->isReseller(),
        ]);
        
        if (!$user->isReseller()) {
            abort(403, 'هذه الصفحة للوكلاء فقط');
        }

        $permissions = $user->resellerPermissions()->with('router')->get();
        
        \Log::info('ResellerPanel Permissions', [
            'permissions_count' => $permissions->count(),
            'permissions' => $permissions->map(fn($p) => [
                'router_id' => $p->router_id,
                'router_name' => $p->router?->name,
                'can_create_hotspot' => $p->can_create_hotspot,
            ])->toArray()
        ]);
        
        // إحصائيات عامة
        $stats = [
            'total_subscribers' => $user->resellerSubscribers()->count(),
            'active_subscribers' => $user->resellerSubscribers()->where('status', 'active')->count(),
            'balance' => $user->balance,
            'routers_count' => $permissions->count(),
        ];

        // آخر العمليات
        $recentSubscribers = $user->resellerSubscribers()
            ->with('router')
            ->latest()
            ->take(5)
            ->get();

        return view('reseller-panel.dashboard', compact('permissions', 'stats', 'recentSubscribers'));
    }

    /**
     * صفحة الهوتسبوت للوكيل
     */
    public function hotspot(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->isReseller()) {
            abort(403);
        }

        // الراوترات التي لديه صلاحية عليها
        $permissions = $user->resellerPermissions()
            ->where('can_create_hotspot', true)
            ->with('router')
            ->get();

        $routers = $permissions->pluck('router')->filter();
        
        $routerId = $request->router_id;
        $selectedRouter = $routerId ? Router::find($routerId) : $routers->first();
        
        // التحقق من الصلاحية
        if ($selectedRouter) {
            $perm = $user->getResellerPermission($selectedRouter);
            if (!$perm || !$perm->can_create_hotspot) {
                $selectedRouter = null;
            }
        }

        $subscribers = collect();
        $profiles = [];
        
        if ($selectedRouter) {
            $subscribers = Subscriber::where('router_id', $selectedRouter->id)
                ->where('type', 'hotspot')
                ->where('reseller_id', $user->id)
                ->latest()
                ->paginate(20);
            
            // جلب البروفايلات
            try {
                $service = new MikroTikService($selectedRouter);
                $service->connect();
                $profilesData = $service->getHotspotProfiles();
                $service->disconnect();
                
                // استخراج أسماء البروفايلات فقط
                $profiles = array_filter(array_map(function($p) {
                    return $p['name'] ?? null;
                }, $profilesData), function($name) {
                    return $name && $name !== 'default-trial';
                });
                $profiles = array_values($profiles);
            } catch (\Exception $e) {
                $profiles = [];
            }
        }

        return view('reseller-panel.hotspot', compact('routers', 'selectedRouter', 'subscribers', 'profiles', 'permissions'))
            ->with('canViewPassword', $user->can_view_hotspot_password ?? false);
    }

    /**
     * جلب بروفايلات الهوتسبوت للوكيل
     */
    public function getHotspotProfiles(Router $router)
    {
        $user = Auth::user();
        
        if (!$user->isReseller()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        // التحقق من صلاحية الوكيل على هذا الراوتر
        $perm = $user->getResellerPermission($router);
        if (!$perm || !$perm->can_create_hotspot) {
            return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية على هذا الراوتر'], 403);
        }

        try {
            $service = new MikroTikService($router);
            $service->connect();
            $profilesData = $service->getHotspotProfiles();
            $service->disconnect();

            // استخراج أسماء البروفايلات فقط
            $profileNames = array_map(function($p) {
                return $p['name'] ?? null;
            }, $profilesData);
            
            // إزالة القيم الفارغة و default-trial
            $profileNames = array_filter($profileNames, function($name) {
                return $name && $name !== 'default-trial';
            });

            return response()->json([
                'success' => true,
                'profiles' => array_values($profileNames),
                'price_per_gb' => $router->price_per_gb ?? 0
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'profiles' => []
            ], 500);
        }
    }

    /**
     * إنشاء بطاقة هوتسبوت
     */
    public function createHotspot(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->isReseller()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $request->validate([
            'router_id' => 'required|exists:routers,id',
            'username' => 'required|string|min:3',
            'password' => 'required|string|min:3',
            'profile' => 'nullable|string',
            'data_limit_gb' => 'required|numeric|min:0.1',
            'phone' => 'nullable|string|max:20',
        ]);

        $router = Router::findOrFail($request->router_id);
        $perm = $user->getResellerPermission($router);
        
        if (!$perm || !$perm->can_create_hotspot) {
            return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية إنشاء بطاقات على هذا الراوتر'], 403);
        }

        // حساب تكلفة البطاقة بناء على سعر الجيجا
        $pricePerGb = $router->price_per_gb ?? 0;
        $totalCost = $pricePerGb * $request->data_limit_gb;
        
        // التحقق من رصيد الوكيل
        if ($pricePerGb > 0 && $user->balance < $totalCost) {
            return response()->json([
                'success' => false, 
                'message' => 'رصيدك غير كافي! المطلوب: ' . number_format($totalCost) . ' ل.س - رصيدك: ' . number_format($user->balance) . ' ل.س'
            ], 400);
        }

        try {
            $service = new MikroTikService($router);
            $service->connect();

            // حساب حد البيانات بالبايت
            $limitBytes = (int)($request->data_limit_gb * 1073741824);

            // إنشاء المستخدم في MikroTik
            $userData = [
                'name' => $request->username,
                'password' => $request->password,
                'limit-bytes-total' => $limitBytes,
                'comment' => 'Created by reseller: ' . $user->name,
            ];
            
            if ($request->profile) {
                $userData['profile'] = $request->profile;
            }
            
            $result = $service->addHotspotUser($userData);

            $service->disconnect();
            
            // الحصول على ID من MikroTik
            $mikrotikId = $result['ret'] ?? null;

            // حفظ في قاعدة البيانات
            $subscriber = Subscriber::create([
                'router_id' => $router->id,
                'reseller_id' => $user->id,
                'created_by' => $user->id,
                'username' => $request->username,
                'password' => $request->password,
                'type' => 'hotspot',
                'status' => 'active',
                'profile' => $request->profile,
                'mikrotik_id' => $mikrotikId,
                'limit_bytes_total' => $limitBytes,
                'data_limit_gb' => $request->data_limit_gb,
                'subscription_price' => $totalCost,
                'phone' => $request->phone,
            ]);

            // خصم المبلغ من رصيد الوكيل
            if ($pricePerGb > 0 && $totalCost > 0) {
                $user->balance -= $totalCost;
                $user->save();
                
                // تسجيل العملية في سجل المعاملات
                \App\Models\ActivityLog::log(
                    'reseller.hotspot_created',
                    "إنشاء بطاقة هوتسبوت: {$request->username} ({$request->data_limit_gb} GB) - الخصم: " . number_format($totalCost) . " ل.س",
                    $user->id,
                    $router->id,
                    Subscriber::class,
                    $subscriber->id
                );
            }

            // إرسال إشعار للمدير
            AdminNotification::notifyHotspotCard($user, $subscriber, $request->data_limit_gb, $totalCost);

            // إرسال SMS تلقائي عبر ZTE
            $smsSent = false;
            if ($request->phone && !empty(trim($request->phone))) {
                try {
                    $smsService = new \App\Services\SmsService($router);
                    $smsMessage = "مرحباً بك في خدمة الانترنت 🌐\n"
                        . "بيانات الاتصال:\n"
                        . "👤 المستخدم: " . $request->username . "\n"
                        . "🔑 كلمة المرور: " . $request->password . "\n"
                        . "📊 حد البيانات: " . $request->data_limit_gb . " GB\n"
                        . "نتمنى لك تجربة ممتعة! ✨";
                    $smsService->sendSms($request->phone, $smsMessage, $subscriber->id, \App\Models\SmsLog::TYPE_MANUAL);
                    $smsSent = true;
                } catch (\Exception $smsEx) {
                    \Log::warning('Failed to send hotspot SMS via web: ' . $smsEx->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء البطاقة بنجاح' . ($totalCost > 0 ? ' - تم خصم ' . number_format($totalCost) . ' ل.س من رصيدك' : '') . (($smsSent ?? false) ? ' - تم إرسال SMS للمشترك' : ''),
                'subscriber' => $subscriber,
                'new_balance' => $user->balance,
                'cost' => $totalCost,
                'sms_sent' => $smsSent ?? false,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إنشاء البطاقة: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * صفحة تجديد اليوزر مانجر
     */
    public function usermanager(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->isReseller()) {
            abort(403);
        }

        // الراوترات التي لديه صلاحية تجديد
        $permissions = $user->resellerPermissions()
            ->where('can_renew_usermanager', true)
            ->with('router')
            ->get();

        $routers = $permissions->pluck('router')->filter();
        
        $routerId = $request->router_id;
        $selectedRouter = $routerId ? Router::find($routerId) : $routers->first();
        
        if ($selectedRouter) {
            $perm = $user->getResellerPermission($selectedRouter);
            if (!$perm || !$perm->can_renew_usermanager) {
                $selectedRouter = null;
            }
        }

        $subscribers = collect();
        $profiles = [];
        
        if ($selectedRouter) {
            // جلب مشتركي اليوزر مانجر (يمكن للوكيل تجديد أي مشترك وليس فقط من أضافه)
            $subscribers = Subscriber::where('router_id', $selectedRouter->id)
                ->where('type', 'usermanager')
                ->with('router')
                ->latest()
                ->get();
            
            // جلب البروفايلات
            try {
                $service = new MikroTikService($selectedRouter);
                $service->connect();
                $profiles = $service->getUserManagerProfiles();
                $service->disconnect();
            } catch (\Exception $e) {
                $profiles = [];
            }
        }

        $resellerBalance = $user->balance;

        return view('reseller-panel.usermanager', compact('routers', 'selectedRouter', 'subscribers', 'profiles', 'permissions', 'resellerBalance'));
    }

    /**
     * تجديد اشتراك يوزر مانجر
     */
    public function renewUsermanager(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->isReseller()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $request->validate([
            'subscriber_id' => 'required|exists:subscribers,id',
            'months' => 'required|integer|min:1|max:12',
        ]);

        $subscriber = Subscriber::with('router')->findOrFail($request->subscriber_id);
        $router = $subscriber->router;
        
        $perm = $user->getResellerPermission($router);
        
        if (!$perm || !$perm->can_renew_usermanager) {
            return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية التجديد على هذا الراوتر'], 403);
        }

        // التحقق من وجود مبلغ للتجديد
        $renewalAmount = $subscriber->subscription_price ?? 0;
        if ($renewalAmount <= 0) {
            return response()->json(['success' => false, 'message' => 'لا يوجد سعر محدد للباقة. يرجى التواصل مع المدير.'], 400);
        }

        // التحقق من رصيد الوكيل
        if ($user->balance < $renewalAmount) {
            return response()->json(['success' => false, 'message' => 'رصيدك غير كافي. المطلوب: ' . number_format($renewalAmount, 0) . ' IQD - رصيدك: ' . number_format($user->balance, 0) . ' IQD'], 400);
        }

        try {
            $service = new MikroTikService($router);
            $service->connect();

            // حساب تاريخ انتهاء جديد
            $currentExpiry = $subscriber->expiration_date ? \Carbon\Carbon::parse($subscriber->expiration_date) : now();
            if ($currentExpiry->isPast()) {
                $currentExpiry = now();
            }
            $newExpiry = $currentExpiry->addMonths($request->months);

            // استخدام حد البيانات المحدد من قبل المدير
            $dataLimitGB = $subscriber->data_limit_gb ?? 0;
            $limitBytes = $dataLimitGB > 0 ? (int)($dataLimitGB * 1073741824) : 0;

            // تحديث في MikroTik - تصفير البيانات وتعيين الحد الجديد
            $updateData = [
                'download' => '0',
                'upload' => '0',
            ];
            
            // إضافة حد البيانات إذا كان محدد
            if ($limitBytes > 0) {
                $updateData['limit-bytes-total'] = (string)$limitBytes;
            } else {
                $updateData['limit-bytes-total'] = '0'; // غير محدود
            }

            $service->updateUserManagerUser($subscriber->mikrotik_id, $updateData);

            $service->disconnect();

            // خصم المبلغ من رصيد الوكيل
            ResellerTransaction::createTransaction(
                $user,
                'purchase',
                $renewalAmount,
                "تجديد اشتراك: {$subscriber->username} - {$subscriber->profile}",
                null,
                'renewal-' . $subscriber->id . '-' . now()->timestamp,
                $subscriber
            );

            // تحديث قاعدة البيانات
            $subscriber->update([
                'expiration_date' => $newExpiry,
                'status' => 'active',
                'bytes_in' => 0,
                'bytes_out' => 0,
                'total_bytes' => 0,
                'limit_bytes_total' => $limitBytes,
                'is_throttled' => false,
                'usage_reset_at' => now(),
                'remaining_amount' => $renewalAmount, // تعيين المبلغ المترتب
                'is_paid' => false, // الحالة غير مدفوع
            ]);

            // إنشاء إشعار للمدير
            AdminNotification::notifyRenewal($user, $subscriber, $renewalAmount);

            $dataText = $dataLimitGB > 0 ? $dataLimitGB . ' GB' : 'غير محدود';
            
            return response()->json([
                'success' => true,
                'message' => 'تم التجديد بنجاح - تم خصم ' . number_format($renewalAmount, 0) . ' IQD - رصيدك: ' . number_format($user->fresh()->balance, 0) . ' IQD',
                'new_balance' => $user->fresh()->balance,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل التجديد: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * البحث عن مشترك للتجديد
     */
    public function searchSubscriber(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->isReseller()) {
            return response()->json(['success' => false], 403);
        }

        $request->validate([
            'router_id' => 'required|exists:routers,id',
            'search' => 'required|string|min:2',
        ]);

        $router = Router::findOrFail($request->router_id);
        $perm = $user->getResellerPermission($router);
        
        if (!$perm || !$perm->can_renew_usermanager) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $subscribers = Subscriber::where('router_id', $router->id)
            ->where('type', 'usermanager')
            ->where(function($q) use ($request) {
                $q->where('username', 'like', '%' . $request->search . '%')
                  ->orWhere('phone', 'like', '%' . $request->search . '%')
                  ->orWhere('full_name', 'like', '%' . $request->search . '%');
            })
            ->limit(10)
            ->get(['id', 'username', 'full_name', 'phone', 'profile', 'expiration_date', 'status', 'data_limit_gb', 'subscription_price', 'remaining_amount', 'is_paid']);

        return response()->json([
            'success' => true,
            'subscribers' => $subscribers,
            'reseller_balance' => $user->balance,
        ]);
    }
}
