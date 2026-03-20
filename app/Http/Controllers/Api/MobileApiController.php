<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActiveSession;
use App\Models\Invoice;
use App\Models\Router;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\MikroTikService;
use App\Services\UserManagerService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MobileApiController extends Controller
{
    private function user(Request $request): User
    {
        return $request->attributes->get('api_user');
    }

    private function routerIds(User $user)
    {
        if ($user->isSuperAdmin()) {
            return Router::pluck('id')->toArray();
        }

        // For resellers, get router IDs from reseller_router_permissions
        if ($user->role === 'reseller') {
            $resellerRouterIds = \App\Models\ResellerRouterPermission::where('reseller_id', $user->id)
                ->pluck('router_id')->toArray();
            $adminRouterIds = $user->routers()->pluck('routers.id')->toArray();
            return array_unique(array_merge($adminRouterIds, $resellerRouterIds));
        }

        return $user->routers()->pluck('routers.id')->toArray();
    }

    // ==================== AUTH ====================

        public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'الحساب معطل',
            ], 403);
        }

        // Device lock check - allows 1 mobile + 1 desktop per account
        $deviceId = $request->input('device_id');
        $isDesktop = $deviceId && str_starts_with($deviceId, 'desktop-');
        
        if ($deviceId) {
            if ($isDesktop) {
                // Desktop device check
                if ($user->desktop_device_id && $user->desktop_device_id !== $deviceId) {
                    $lockedAt = $user->desktop_device_locked_at ? \Carbon\Carbon::parse($user->desktop_device_locked_at) : null;
                    if ($lockedAt && $lockedAt->diffInHours(now()) < 24) {
                        $remaining = 24 - $lockedAt->diffInHours(now());
                        $hours = floor($remaining);
                        $minutes = round(($remaining - $hours) * 60);
                        return response()->json([
                            'success' => false,
                            'message' => "هذا الحساب مرتبط بجهاز كمبيوتر آخر. يرجى الانتظار {$hours} ساعة و {$minutes} دقيقة للتبديل.",
                            'device_locked' => true,
                            'remaining_hours' => round($remaining, 1),
                        ], 403);
                    }
                    $user->desktop_device_id = $deviceId;
                    $user->desktop_device_locked_at = now();
                } elseif (!$user->desktop_device_id) {
                    $user->desktop_device_id = $deviceId;
                    $user->desktop_device_locked_at = now();
                }
            } else {
                // Mobile device check
                if ($user->device_id && $user->device_id !== $deviceId) {
                    $lockedAt = $user->device_locked_at ? \Carbon\Carbon::parse($user->device_locked_at) : null;
                    if ($lockedAt && $lockedAt->diffInHours(now()) < 24) {
                        $remaining = 24 - $lockedAt->diffInHours(now());
                        $hours = floor($remaining);
                        $minutes = round(($remaining - $hours) * 60);
                        return response()->json([
                            'success' => false,
                            'message' => "هذا الحساب مرتبط بجهاز موبايل آخر. يرجى الانتظار {$hours} ساعة و {$minutes} دقيقة للتبديل.",
                            'device_locked' => true,
                            'remaining_hours' => round($remaining, 1),
                        ], 403);
                    }
                    $user->device_id = $deviceId;
                    $user->device_locked_at = now();
                } elseif (!$user->device_id) {
                    $user->device_id = $deviceId;
                    $user->device_locked_at = now();
                }
            }
        }

        $plainToken = Str::random(60);
        $user->api_token = hash('sha256', $plainToken);
        $user->save();

        return response()->json([
            'success' => true,
            'token' => $plainToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'company_name' => $user->company_name,
                'phone' => $user->phone,
                'balance' => $user->balance,
                'sms_enabled' => (bool) $user->sms_enabled || \App\Models\SmsSettings::whereIn('router_id', $this->routerIds($user))->where('is_enabled', true)->exists(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $user = $this->user($request);
        $user->api_token = null;
        $user->save();

        return response()->json(['success' => true, 'message' => 'تم تسجيل الخروج']);
    }

    // ==================== DASHBOARD ====================

    public function dashboard(Request $request)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $routers = Router::whereIn('id', $routerIds)->get();

        $stats = [
            'total_routers' => $routers->count(),
            'online_routers' => $routers->where('status', 'online')->count(),
            'offline_routers' => $routers->where('status', 'offline')->count(),
            'total_subscribers' => Subscriber::whereIn('router_id', $routerIds)->count(),
            'active_subscribers' => Subscriber::whereIn('router_id', $routerIds)->where('status', 'active')->count(),
            'expired_subscribers' => Subscriber::whereIn('router_id', $routerIds)->where('status', 'expired')->count(),
            'online_users' => ActiveSession::whereIn('router_id', $routerIds)->count(),
            'ppp_users' => ActiveSession::whereIn('router_id', $routerIds)->where('type', 'ppp')->count(),
            'hotspot_users' => ActiveSession::whereIn('router_id', $routerIds)->where('type', 'hotspot')->count(),
        ];

        $expiring = Subscriber::whereIn('router_id', $routerIds)
            ->where('status', 'active')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', Carbon::now()->addDays(7))
            ->where('expiration_date', '>=', Carbon::now())
            ->with('router:id,name')
            ->orderBy('expiration_date')
            ->take(10)
            ->get(['id', 'username', 'full_name', 'expiration_date', 'profile', 'router_id']);

        // Calculate revenue from active subscribers
        $revenueData = Subscriber::whereIn('router_id', $routerIds)
            ->where('status', 'active')
            ->selectRaw('
                SUM(CASE WHEN is_paid = 1 THEN subscription_price ELSE 0 END) as paid,
                SUM(CASE WHEN is_paid = 0 THEN subscription_price ELSE 0 END) as unpaid,
                SUM(COALESCE(remaining_amount, 0)) as debt
            ')
            ->first();

        $paidRevenue = (float) ($revenueData->paid ?? 0);
        $unpaidRevenue = (float) ($revenueData->unpaid ?? 0);
        $debtRevenue = (float) ($revenueData->debt ?? 0);

        $traffic = Subscriber::whereIn('router_id', $routerIds)
            ->selectRaw('SUM(COALESCE(bytes_in, 0)) as upload, SUM(COALESCE(bytes_out, 0)) as download, SUM(COALESCE(total_bytes, 0)) as total')
            ->first();

        $routersList = $routers->map(function ($r) {
            return [
                'id' => $r->id,
                'name' => $r->name,
                'identity' => $r->identity,
                'status' => $r->status,
                'ip_address' => $r->ip_address,
                'cpu_load' => $r->cpu_load,
                'uptime' => $r->uptime,
            ];
        });

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'expiring_subscribers' => $expiring,
            'revenue' => ['paid' => $paidRevenue, 'unpaid' => $unpaidRevenue, 'debt' => $debtRevenue],
            'traffic' => [
                'upload' => (int) ($traffic->upload ?? 0),
                'download' => (int) ($traffic->download ?? 0),
                'total' => (int) ($traffic->total ?? 0),
            ],
            'routers' => $routersList,
            'user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->role],
        ]);
    }

    // ==================== SUBSCRIBERS (UserManager) ====================

    public function subscribers(Request $request)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $query = Subscriber::whereIn('router_id', $routerIds)
            ->where('type', 'usermanager')
            ->with('router:id,name');

        if ($request->filled('router_id')) {
            $query->where('router_id', $request->router_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('profile')) {
            $query->where('profile', $request->profile);
        }
        if ($request->filled('is_paid')) {
            $query->where('is_paid', $request->is_paid === 'true' || $request->is_paid === '1');
        }
        if ($request->filled('is_online')) {
            $query->where('is_online', true);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('national_id', 'like', "%{$search}%");
            });
        }

        $subscribers = $query->orderByRaw('COALESCE(total_bytes, 0) DESC')
            ->get(['id', 'router_id', 'username', 'full_name', 'phone', 'status', 'profile',
                    'expiration_date', 'is_online', 'is_paid', 'total_bytes', 'data_limit',
                    'data_limit_gb', 'subscription_price', 'balance', 'remaining_amount',
                    'last_login', 'whatsapp_number', 'is_throttled', 'national_id']);

        // Enrich is_online from active_sessions table for real-time accuracy
        $onlineSubscriberIds = \App\Models\ActiveSession::whereIn('router_id', $routerIds)
            ->whereNotNull('subscriber_id')
            ->pluck('subscriber_id')
            ->unique()
            ->toArray();

        // Also match by username for sessions without subscriber_id
        $onlineUsernames = \App\Models\ActiveSession::whereIn('router_id', $routerIds)
            ->whereNull('subscriber_id')
            ->pluck('username')
            ->unique()
            ->toArray();

        $onlineCount = 0;
        foreach ($subscribers as $sub) {
            $sub->is_online = in_array($sub->id, $onlineSubscriberIds)
                || in_array($sub->username, $onlineUsernames);
            if ($sub->is_online) $onlineCount++;
        }

        $statsQuery = Subscriber::whereIn('router_id', $routerIds)
            ->where('type', 'usermanager')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN is_paid = 0 OR is_paid IS NULL THEN 1 ELSE 0 END) as unpaid,
                SUM(CASE WHEN is_online = 1 THEN 1 ELSE 0 END) as online,
                SUM(CASE WHEN is_throttled = 1 THEN 1 ELSE 0 END) as throttled
            ")
            ->first();

        $routers = Router::whereIn('id', $routerIds)->get(['id', 'name', 'ip_address']);

        return response()->json([
            'success' => true,
            'subscribers' => $subscribers,
            'stats' => [
                'total' => (int) ($statsQuery->total ?? 0),
                'active' => (int) ($statsQuery->active ?? 0),
                'expired' => (int) ($statsQuery->expired ?? 0),
                'unpaid' => (int) ($statsQuery->unpaid ?? 0),
                'online' => $onlineCount,
                'throttled' => (int) ($statsQuery->throttled ?? 0),
            ],
            'routers' => $routers,
        ]);
    }

    public function subscriberDetail(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $subscriber = Subscriber::where('id', $id)
            ->whereIn('router_id', $routerIds)
            ->with('router:id,name,ip_address')
            ->first();

        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        // Fetch live IP from MikroTik active session
        $liveIp = null;
        $liveMac = null;
        $liveUptime = null;
        try {
            $router = \App\Models\Router::find($subscriber->router_id);
            if ($router) {
                $mikrotikService = new \App\Services\MikroTikService($router);
                if ($subscriber->type === 'hotspot') {
                    $active = $mikrotikService->command(['/ip/hotspot/active/print', '?user=' . $subscriber->username]);
                } else {
                    $active = $mikrotikService->command(['/ppp/active/print', '?name=' . $subscriber->username]);
                }
                if ($active) {
                    foreach ($active as $a) {
                        if (isset($a['address']) || isset($a['name']) || isset($a['user'])) {
                            $liveIp = $a['address'] ?? null;
                            $liveMac = $a['caller-id'] ?? null;
                            $liveUptime = $a['uptime'] ?? null;
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silent fail
        }

        $subData = $subscriber->toArray();
        $subData['router_name'] = $subscriber->router ? $subscriber->router->name : null;
        $subData['router_ip'] = $subscriber->router ? $subscriber->router->ip_address : null;
        $subData['live_ip'] = $liveIp;
        $subData['live_mac'] = $liveMac;
        $subData['live_uptime'] = $liveUptime;

        return response()->json([
            'success' => true,
            'subscriber' => $subData,
        ]);
    }

    public function toggleSubscriber(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $subscriber = Subscriber::where('id', $id)
            ->whereIn('router_id', $routerIds)
            ->first();

        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        $shouldDisable = $subscriber->status === 'active';
        $routerError = null;

        // Toggle on MikroTik router
        try {
            if ($subscriber->type === 'usermanager' && $subscriber->mikrotik_id && $subscriber->router) {
                $service = new \App\Services\UserManagerService($subscriber->router);
                $service->connect();
                $service->toggleUserStatus($subscriber->mikrotik_id, $shouldDisable);
                $service->disconnect();
            }
        } catch (\Exception $e) {
            $routerError = $e->getMessage();
        }

        $newStatus = $shouldDisable ? 'disabled' : 'active';
        $subscriber->status = $newStatus;

        if ($shouldDisable) {
            $subscriber->stopped_at = now();
            $subscriber->stop_reason = 'يدوي من التطبيق';
        } else {
            $subscriber->stopped_at = null;
            $subscriber->stop_reason = null;
        }

        $subscriber->save();

        $msg = $shouldDisable ? 'تم تعطيل المشترك' : 'تم تفعيل المشترك';
        if ($routerError) {
            $msg .= ' (تحذير: ' . $routerError . ')';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'status' => $newStatus,
        ]);
    }

    public function updateSubscriber(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $subscriber = Subscriber::where('id', $id)
            ->whereIn('router_id', $routerIds)
            ->first();

        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        $allowed = ['full_name', 'phone', 'email', 'address', 'whatsapp_number',
                     'subscription_price', 'remaining_amount', 'is_paid', 'national_id', 'profile'];
        $data = $request->only($allowed);
        $subscriber->update($data);

        // Update profile on router if changed
        if ($request->filled('profile') && $subscriber->mikrotik_id && $subscriber->type === 'usermanager') {
            try {
                $service = new UserManagerService($subscriber->router);
                $service->connect();
                $service->renewUser($subscriber->username, $request->input('profile'), null, null);
                $service->disconnect();
                // Save original profile if not set
                if (!$subscriber->original_profile) {
                    $subscriber->original_profile = $subscriber->profile;
                }
                $subscriber->profile = $request->input('profile');
                $subscriber->save();
            } catch (\Exception $e) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تحديث البيانات لكن فشل تغيير الباقة على الراوتر: ' . $e->getMessage(),
                    'subscriber' => $subscriber->fresh(),
                ]);
            }
        }

        // Update password on router if provided
        if ($request->filled('password') && $subscriber->mikrotik_id && $subscriber->type === 'usermanager') {
            try {
                $service = new UserManagerService($subscriber->router);
                $service->connect();
                $service->updateUser($subscriber->mikrotik_id, ['password' => $request->input('password')]);
                $service->disconnect();
            } catch (\Exception $e) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم تحديث البيانات لكن فشل تحديث كلمة المرور على الراوتر: ' . $e->getMessage(),
                    'subscriber' => $subscriber->fresh(),
                ]);
            }
        }

        // Sync full_name as comment to router
        if ($request->filled('full_name') && $subscriber->mikrotik_id && $subscriber->type === 'usermanager') {
            try {
                $service = new UserManagerService($subscriber->router);
                $service->connect();
                $service->updateUser($subscriber->mikrotik_id, ['comment' => $request->input('full_name')]);
                $service->disconnect();
            } catch (\Exception $e) {
                // Non-critical - don't fail the whole update
                \Log::warning('Failed to sync comment to router: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث بيانات المشترك',
            'subscriber' => $subscriber->fresh(),
        ]);
    }

    public function resetUsage(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $subscriber = Subscriber::where('id', $id)->whereIn('router_id', $routerIds)->first();
        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        try {
            $originalProfile = $subscriber->original_profile ?? $subscriber->profile;

            if ($subscriber->mikrotik_id && $subscriber->type === 'usermanager') {
                $service = new UserManagerService($subscriber->router);
                $service->connect();
                $result = $service->resetUserUsage($subscriber->username, $originalProfile);
                $service->disconnect();

                // reset-counters keeps the same user, no ID change
            }

            $subscriber->update([
                'bytes_in' => 0, 'bytes_out' => 0, 'total_bytes' => 0,
                'archived_bytes' => 0,
                'um_usage_offset' => 0,
                'uptime_used' => 0, 'usage_reset_at' => now(),
                'is_throttled' => false, 'throttled_at' => null,
                'profile' => $originalProfile,
            ]);

            return response()->json(['success' => true, 'message' => 'تم تصفير الاستهلاك بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'فشل تصفير الاستهلاك: ' . $e->getMessage()], 500);
        }
    }

    public function setDataLimit(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $subscriber = Subscriber::where('id', $id)->whereIn('router_id', $routerIds)->first();
        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        $request->validate(['data_limit_gb' => 'required|numeric|min:0']);

        $addGb = (float) $request->data_limit_gb;

        if (empty($subscriber->original_profile) && !empty($subscriber->profile)) {
            $subscriber->original_profile = $subscriber->profile;
        }

        // Add to existing limit (check both data_limit_gb and data_limit for current value)
        $currentLimit = (float) ($subscriber->data_limit_gb ?? 0);
        if ($currentLimit <= 0 && $subscriber->data_limit > 0) {
            $currentLimit = round($subscriber->data_limit / 1073741824, 2);
        }
        $newLimit = $addGb > 0 ? $currentLimit + $addGb : 0;

        // Calculate new limit in bytes for data_limit field (sync both fields)
        $newLimitBytes = $newLimit > 0 ? (int)($newLimit * 1073741824) : null;

        $updateData = [
            'data_limit_gb' => $newLimit > 0 ? $newLimit : null,
            'data_limit' => $newLimitBytes,
            'original_profile' => $subscriber->original_profile,
        ];

        // If subscriber is throttled and new limit > current usage, unthrottle immediately
        $unthrottled = false;
        if ($subscriber->is_throttled && $newLimit > 0) {
            $currentUsageBytes = (int) ($subscriber->total_bytes ?? 0);
            if ($currentUsageBytes < $newLimitBytes) {
                // New limit is higher than current usage - unthrottle on the router
                $originalProfile = $subscriber->original_profile ?? $subscriber->profile;
                if ($originalProfile && $originalProfile !== 'STOP') {
                    try {
                        $router = Router::find($subscriber->router_id);
                        if ($router) {
                            $service = new UserManagerService($router);
                            if ($service->connect()) {
                                $service->changeUserProfileByName($subscriber->username, $originalProfile);
                                $service->disconnect();
                                $updateData['profile'] = $originalProfile;
                                $updateData['is_throttled'] = false;
                                $updateData['throttled_at'] = null;
                                $unthrottled = true;
                                \Illuminate\Support\Facades\Log::info("setDataLimit: رفع التقييد عن {$subscriber->username} - الحد الجديد: {$newLimit}GB، المستهلك: " . round($currentUsageBytes / 1073741824, 1) . "GB");
                            }
                        }
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning("setDataLimit: فشل رفع التقييد عن {$subscriber->username}: " . $e->getMessage());
                    }
                }
            }
        }

        $subscriber->update($updateData);

        $message = $addGb > 0
            ? "تم إضافة {$addGb} جيجابايت (الحد الجديد: {$newLimit} GB)"
            : "تم إزالة حد الاستهلاك (غير محدود)";

        if ($unthrottled) {
            $message .= " - تم رفع التقييد تلقائياً";
        }

        return response()->json(['success' => true, 'message' => $message, 'new_limit_gb' => $newLimit, 'unthrottled' => $unthrottled]);
    }

    public function renewSubscription(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);
        $subscriber = Subscriber::where('id', $id)->whereIn('router_id', $routerIds)->first();
        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        try {
            $profileName = $request->input('profile') ?? $subscriber->original_profile ?? $subscriber->profile;
            $expiryDays = (int) $request->input('expiry_days', 30);
            $resetUsage = filter_var($request->input('reset_usage', false), FILTER_VALIDATE_BOOLEAN);
            $dataLimitGb = $request->input('data_limit_gb');
            $subscriptionPrice = $request->input('subscription_price');
            $isPaid = $request->input('is_paid');
            $remainingAmount = $request->input('remaining_amount');
            $routerWarnings = [];
            $syncData = [];

            // ============================================
            // STEP 0: AUTO-SYNC - مزامنة تلقائية قبل التجديد
            // ============================================
            if ($subscriber->mikrotik_id && $subscriber->type === 'usermanager') {
                try {
                    $umService = new \App\Services\UserManagerService($subscriber->router);
                    $umService->connect();

                    // جلب الاستخدام الحالي من الراوتر
                    $users = $umService->getUsers();
                    foreach ($users as $u) {
                        if (isset($u['name']) && $u['name'] === $subscriber->username) {
                            $syncData['download'] = $this->parseBytes($u['download'] ?? '0');
                            $syncData['upload'] = $this->parseBytes($u['upload'] ?? '0');
                            $syncData['uptime'] = $u['uptime'] ?? '0';
                            break;
                        }
                    }

                    // جلب بيانات الجلسات النشطة
                    try {
                        $sessions = $umService->getSessions();
                        $totalDown = 0;
                        $totalUp = 0;
                        foreach ($sessions as $s) {
                            if (isset($s['user']) && $s['user'] === $subscriber->username) {
                                $totalDown += $this->parseBytes($s['download'] ?? '0');
                                $totalUp += $this->parseBytes($s['upload'] ?? '0');
                            }
                        }
                        if ($totalDown > 0 || $totalUp > 0) {
                            $syncData['session_download'] = $totalDown;
                            $syncData['session_upload'] = $totalUp;
                        }
                    } catch (\Exception $e) {
                        // تجاهل أخطاء الجلسات
                    }

                    \Illuminate\Support\Facades\Log::info("UNIFIED_RENEW: Synced usage for {$subscriber->username} before renewal", $syncData);

                    // تحديث الاستخدام في قاعدة البيانات قبل التجديد
                    if (!empty($syncData)) {
                        $bytesIn = $syncData['download'] ?? $syncData['session_download'] ?? $subscriber->bytes_in;
                        $bytesOut = $syncData['upload'] ?? $syncData['session_upload'] ?? $subscriber->bytes_out;
                        $subscriber->bytes_in = $bytesIn;
                        $subscriber->bytes_out = $bytesOut;
                        $subscriber->total_bytes = $bytesIn + $bytesOut;
                    }

                    // ============================================
                    // STEP 1: RESET USAGE - تصفير الاستهلاك
                    // ============================================
                    if ($resetUsage) {
                        try {
                            $resetResult = $umService->resetUserUsage($subscriber->username, $profileName);
                            // reset-counters keeps the same user, no ID change needed
                        } catch (\Exception $e) {
                            $routerWarnings[] = "reset: " . $e->getMessage();
                        }
                    }

                    // ============================================
                    // STEP 2: UPDATE PROFILE - تحديث الباقة
                    // ============================================
                    try {
                        $umService->deleteUserProfiles($subscriber->username);
                    } catch (\Exception $e) {
                        $routerWarnings[] = "delete_profiles: " . $e->getMessage();
                    }

                    try {
                        $umService->assignProfileToUser($subscriber->username, $profileName);
                    } catch (\Exception $e) {
                        $routerWarnings[] = "add_profile: " . $e->getMessage();
                    }

                    // ============================================
                    // STEP 3: DATA LIMIT - تحديث حد البيانات
                    // ============================================
                    if ($dataLimitGb !== null) {
                        try {
                            if ((float) $dataLimitGb > 0) {
                                $umService->setUserDataLimit($subscriber->username, (float) $dataLimitGb);
                                \Illuminate\Support\Facades\Log::info("UNIFIED_RENEW: Set data limit {$dataLimitGb}GB for {$subscriber->username}");
                            } else {
                                // Remove data limit (unlimited) - set transfer-limit to 0 means no limit
                                $umService->setUserDataLimit($subscriber->username, 0);
                                \Illuminate\Support\Facades\Log::info("UNIFIED_RENEW: Removed data limit (unlimited) for {$subscriber->username}");
                            }
                        } catch (\Exception $e) {
                            $routerWarnings[] = "data_limit: " . $e->getMessage();
                        }
                    }

                    // ============================================
                    // STEP 4: RE-ENABLE - إعادة تفعيل المستخدم
                    // ============================================
                    try {
                        $umService->toggleUserStatus($subscriber->mikrotik_id, false);
                    } catch (\Exception $e) {
                        $routerWarnings[] = "enable: " . $e->getMessage();
                    }

                    $umService->disconnect();

                    // ============================================
                    // STEP 5: DISCONNECT SESSION - فصل الجلسة
                    // ============================================
                    try {
                        $mkService = new \App\Services\MikroTikService($subscriber->router);
                        $mkService->connect();
                        $pppActive = $mkService->getPPPActive();
                        foreach ($pppActive as $session) {
                            if (isset($session['name']) && $session['name'] === $subscriber->username) {
                                $mkService->disconnectPPPUser($session['.id']);
                                break;
                            }
                        }
                        $mkService->disconnect();
                    } catch (\Exception $e) {
                        // تجاهل
                    }

                } catch (\Exception $e) {
                    $routerWarnings[] = "um_connection: " . $e->getMessage();
                }
            }

            // PPP/Hotspot renewal
            if ($subscriber->mikrotik_id && $subscriber->type !== 'usermanager') {
                try {
                    $mkService = new \App\Services\MikroTikService($subscriber->router);
                    $mkService->connect();
                    if ($subscriber->type === 'ppp') {
                        $mkService->updatePPPSecret($subscriber->mikrotik_id, ['disabled' => false]);
                        $pppActive = $mkService->getPPPActive();
                        foreach ($pppActive as $session) {
                            if (isset($session['name']) && $session['name'] === $subscriber->username) {
                                $mkService->disconnectPPPUser($session['.id']);
                                break;
                            }
                        }
                    } elseif ($subscriber->type === 'hotspot') {
                        $mkService->updateHotspotUser($subscriber->mikrotik_id, ['disabled' => false]);
                    }
                    $mkService->disconnect();
                } catch (\Exception $e) {
                    $routerWarnings[] = "ppp_hotspot: " . $e->getMessage();
                }
            }

            // ============================================
            // STEP 6: DATABASE UPDATE - تحديث قاعدة البيانات
            // ============================================
            $newExpiration = \Carbon\Carbon::now()->addDays($expiryDays);
            $updateData = [
                'profile' => $profileName,
                'expiration_date' => $newExpiration,
                'status' => 'active',
                'stopped_at' => null,
                'stop_reason' => null,
                'is_throttled' => false,
                'throttled_at' => null,
            ];

            if ($resetUsage) {
                $updateData['um_usage_offset'] = 0;
                $updateData['bytes_in'] = 0;
                $updateData['bytes_out'] = 0;
                $updateData['total_bytes'] = 0;
                $updateData['uptime_used'] = 0;
                $updateData['usage_reset_at'] = now();
            }

            if ($dataLimitGb !== null) {
                $dlGb = (float) $dataLimitGb;
                $updateData['data_limit_gb'] = $dlGb > 0 ? $dlGb : null;
                $updateData['data_limit'] = $dlGb > 0 ? (int)($dlGb * 1073741824) : null;
                if (empty($subscriber->original_profile)) {
                    $updateData['original_profile'] = $subscriber->profile;
                }
            }

            if ($subscriptionPrice !== null) {
                $updateData['subscription_price'] = (float) $subscriptionPrice;
            }

            if ($isPaid !== null) {
                $updateData['is_paid'] = is_string($isPaid) 
                    ? ($isPaid === 'paid' || $isPaid === '1' || $isPaid === 'true') 
                    : (bool) $isPaid;
            }

            if ($remainingAmount !== null) {
                $updateData['remaining_amount'] = (float) $remainingAmount;
            }

            $subscriber->forceFill($updateData);
            $subscriber->save();

            // Verify DB update
            $dbExp = \Illuminate\Support\Facades\DB::table('subscribers')
                ->where('id', $subscriber->id)->value('expiration_date');
            if (!$dbExp || abs(\Carbon\Carbon::parse($dbExp)->diffInSeconds($newExpiration)) > 60) {
                \Illuminate\Support\Facades\DB::table('subscribers')
                    ->where('id', $subscriber->id)
                    ->update([
                        'expiration_date' => $newExpiration->format('Y-m-d H:i:s'),
                        'status' => 'active',
                        'profile' => $profileName,
                        'is_throttled' => false,
                        'throttled_at' => null,
                    ]);
            }

            // ============================================
            // STEP 7: SMS - إرسال رسالة تجديد (فقط للوكلاء، المدير يستخدم زر SMS)
            // ============================================
            try {
                if ($user->isReseller() && $subscriber->phone) {
                    $dlText = ($dataLimitGb && (float)$dataLimitGb > 0) ? "{$dataLimitGb}GB" : 'لامحدود';
                    $paidStatus = $subscriber->is_paid;
                    $remAmount = (float) $subscriber->remaining_amount;
                    $subPrice = (float) ($subscriptionPrice ?? $subscriber->subscription_price ?? 0);

                    $smsMsg = "تجديد اشتراكك ✅\n";
                    $smsMsg .= "الباقة: {$profileName}\n";
                    $smsMsg .= "انتهاء: " . $newExpiration->format('Y-m-d') . "\n";
                    $smsMsg .= "البيانات: {$dlText}\n";

                    if ($remAmount > 0) {
                        // فيه دين
                        $paidAmount = $subPrice - $remAmount;
                        $smsMsg .= "المدفوع: {$paidAmount}\n";
                        $smsMsg .= "المتبقي(دين): {$remAmount}\n";
                    } elseif (!$paidStatus) {
                        $smsMsg .= "الحالة: غير مدفوع\n";
                    } else {
                        $smsMsg .= "مدفوع بالكامل ✅\n";
                    }
                    $smsMsg .= "شكراً لثقتك 🙏";
                    
                    $smsRouter = \App\Models\Router::find($subscriber->router_id);
                    if ($smsRouter) {
                        $smsService = new \App\Services\SmsService($smsRouter);
                        $smsService->sendSms($subscriber->phone, $smsMsg, $subscriber->id, \App\Models\SmsLog::TYPE_RENEWAL);
                    }
                }
            } catch (\Exception $e) {
                // لا نوقف التجديد بسبب فشل SMS
            }

            $response = [
                'success' => true,
                'message' => 'تم تجديد الاشتراك وتحديث جميع البيانات بنجاح',
                'subscriber' => $subscriber->fresh(),
                'synced' => !empty($syncData),
            ];

            if (!empty($routerWarnings)) {
                $response['router_warnings'] = $routerWarnings;
                $response['message'] = 'تم التجديد مع تحذيرات من الراوتر';
            }

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false, 
                'message' => 'فشل التجديد: ' . $e->getMessage()
            ], 500);
        }
    }

    private function parseBytes($value): int
    {
        if (is_numeric($value)) return (int) $value;
        $value = strtoupper(trim($value));
        $units = ['K' => 1024, 'M' => 1048576, 'G' => 1073741824, 'T' => 1099511627776];
        foreach ($units as $unit => $multiplier) {
            if (str_contains($value, $unit)) {
                return (int) ((float) str_replace($unit, '', $value) * $multiplier);
            }
        }
        return (int) $value;
    }

    public function createSubscriber(Request $request)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $routerId = (int) $request->input('router_id');
        if (!in_array($routerId, $routerIds)) {
            return response()->json(['success' => false, 'message' => '?????????????? ?????? ????????'], 403);
        }

        $router = \App\Models\Router::find($routerId);
        if (!$router) {
            return response()->json(['success' => false, 'message' => '?????????????? ?????? ??????????'], 404);
        }

        try {
            $username = $request->input('username');
            $password = $request->input('password');
            $profileName = $request->input('profile');
            $fullName = $request->input('full_name', '');
            $phone = $request->input('phone', '');
            $dataLimitGb = $request->input('data_limit_gb');
            $expiryDays = (int) $request->input('expiry_days', 30);
            $subscriptionPrice = $request->input('subscription_price');

            if (!$username || !$password || !$profileName) {
                return response()->json(['success' => false, 'message' => '?????? ???????????????? ?????????? ???????????? ?????????????? ????????????'], 422);
            }

            // Check if username already exists on this router
            $exists = \App\Models\Subscriber::where('router_id', $routerId)
                ->where('username', $username)->exists();
            if ($exists) {
                return response()->json(['success' => false, 'message' => '?????? ???????????????? ?????????? ????????????'], 422);
            }

            // Add user to router via UserManager
            $service = new \App\Services\UserManagerService($router);
            $service->connect();

            // If user already exists on router (e.g., previous delete failed), remove first
            try {
                $existingUser = $service->findUserByName($username);
                if ($existingUser && isset($existingUser['.id'])) {
                    $service->deleteUser($existingUser['.id']);
                    $service->deleteUserProfiles($username);
                }
            } catch (\Exception $cleanupEx) {
                // Ignore cleanup errors
            }


            $downloadLimit = null;
            if ($dataLimitGb && $dataLimitGb > 0) {
                $downloadLimit = $dataLimitGb * 1024 * 1024 * 1024;
            }

            $result = $service->addUser([
                'name' => $username,
                'password' => $password,
                'profile' => $profileName,
                'comment' => $fullName,
                'download-limit' => $downloadLimit,
            ]);

            $service->disconnect();

            if ($result['success'] || !empty($result['ret']) || !empty($result['.id']) || !empty($result['user_id'])) {
                $newExpiration = \Carbon\Carbon::now()->addDays($expiryDays);

                $subscriber = \App\Models\Subscriber::create([
                    'router_id' => $routerId,
                    'mikrotik_id' => $result['user_id'] ?? $result['.id'] ?? $result['ret'] ?? null,
                    'username' => $username,
                    'password' => $password,
                    'profile' => $profileName,
                    'full_name' => $fullName,
                    'phone' => $phone,
                    'type' => 'usermanager',
                    'status' => 'active',
                    'expiration_date' => $newExpiration,
                    'data_limit_gb' => ($dataLimitGb && $dataLimitGb > 0) ? $dataLimitGb : null,
                    'subscription_price' => $subscriptionPrice ? (float) $subscriptionPrice : null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => '???? ?????????? ?????????????? ??????????',
                    'subscriber' => $subscriber,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? '?????? ?????????? ???????????????? ?????? ??????????????',
                ], 500);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("CREATE_SUB_ERROR: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => '??????: ' . $e->getMessage()], 500);
        }
    }

        public function deleteSubscriber(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $subscriber = Subscriber::where('id', $id)->whereIn('router_id', $routerIds)->first();
        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        try {
            if ($subscriber->mikrotik_id) {
                if ($subscriber->type === 'usermanager') {
                    $service = new UserManagerService($subscriber->router);
                    $service->connect();
                    $service->deleteUser($subscriber->mikrotik_id);
                    $service->disconnect();
                } elseif ($subscriber->type === 'hotspot') {
                    $service = new MikroTikService($subscriber->router);
                    $service->connect();
                    $service->deleteHotspotUser($subscriber->mikrotik_id);
                    $service->disconnect();
                }
            }

            $subscriber->delete();
            return response()->json(['success' => true, 'message' => 'تم حذف المشترك بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'فشل الحذف: ' . $e->getMessage()], 500);
        }
    }

// ==================== TRANSFER ====================

    public function transferSubscriber(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $request->validate(['target_router_id' => 'required|integer']);

        $subscriber = Subscriber::where('id', $id)->whereIn('router_id', $routerIds)->first();
        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        $targetRouterId = $request->target_router_id;
        if (!in_array($targetRouterId, $routerIds)) {
            return response()->json(['success' => false, 'message' => 'الراوتر المستهدف غير مصرح'], 403);
        }

        $targetRouter = Router::find($targetRouterId);
        if (!$targetRouter) {
            return response()->json(['success' => false, 'message' => 'الراوتر المستهدف غير موجود'], 404);
        }

        $sourceRouter = $subscriber->router;

        if ($sourceRouter && $sourceRouter->id === $targetRouter->id) {
            return response()->json(['success' => false, 'message' => 'المشترك موجود بالفعل على هذا الراوتر'], 400);
        }

        // Check if username already exists on target router
        $existingOnTarget = Subscriber::where('router_id', $targetRouter->id)
            ->where('username', $subscriber->username)
            ->exists();
        if ($existingOnTarget) {
            return response()->json(['success' => false, 'message' => 'اسم المستخدم موجود بالفعل على الراوتر الهدف'], 409);
        }

        try {
            // Step 1: Get user data from source router
            $sourceService = new UserManagerService($sourceRouter);
            $sourceService->connect();
            $userData = $sourceService->getUserByUsername($subscriber->username);

            // Step 2: Create user on target router FIRST (safer)
            $targetService = new UserManagerService($targetRouter);
            $targetService->connect();

            // Check if profile exists on target router
            $targetProfiles = $targetService->getProfiles();
            $profileNames = array_column($targetProfiles, 'name');
            $profileToUse = $subscriber->profile;

            if ($profileToUse && !in_array($profileToUse, $profileNames)) {
                $targetService->disconnect();
                $sourceService->disconnect();
                return response()->json(['success' => false, 'message' => 'الباقة "' . $profileToUse . '" غير موجودة على الراوتر الهدف'], 400);
            }

            $addResult = $targetService->addUser([
                'name' => $subscriber->username,
                'password' => $subscriber->password,
                'profile' => $profileToUse,
                'comment' => $userData['comment'] ?? '',
                'shared-users' => $userData['shared-users'] ?? null,
            ]);

            if (!$addResult['success'] && empty($addResult['user_id'])) {
                $targetService->disconnect();
                $sourceService->disconnect();
                return response()->json(['success' => false, 'message' => 'فشل إنشاء المشترك على الراوتر الهدف: ' . ($addResult['message'] ?? 'خطأ غير معروف')], 500);
            }

            $newMikrotikId = $addResult['user_id'] ?? $addResult['.id'] ?? $addResult['ret'] ?? null;

            // Step 3: Delete from source router
            if ($subscriber->mikrotik_id) {
                $sourceService->deleteUser($subscriber->mikrotik_id);
            }

            $sourceService->disconnect();
            $targetService->disconnect();

            // Step 4: Update database
            $oldRouterName = $sourceRouter->name;
            $subscriber->update([
                'router_id' => $targetRouterId,
                'mikrotik_id' => $newMikrotikId,
                'total_bytes' => 0,
                'archived_bytes' => 0,
                'bytes_in' => 0,
                'bytes_out' => 0,
                'last_synced_at' => now(),
            ]);

            return response()->json(['success' => true, 'message' => 'تم نقل المشترك "' . $subscriber->username . '" من ' . $oldRouterName . ' إلى ' . $targetRouter->name]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'فشل النقل: ' . $e->getMessage()], 500);
        }
    }

    // ==================== IPTV ====================

    public function toggleIptv(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $subscriber = Subscriber::where('id', $id)->whereIn('router_id', $routerIds)->first();
        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        $newStatus = !$subscriber->iptv_enabled;
        $subscriber->update(['iptv_enabled' => $newStatus]);

        // Create IPTV subscription if enabled and does not exist
        if ($newStatus && !$subscriber->iptvSubscription) {
            $iptvUsername = 'iptv_sub_' . $subscriber->id;
            $iptvPassword = \Illuminate\Support\Str::random(12);
            \App\Models\IptvSubscription::create([
                'subscriber_id' => $subscriber->id,
                'user_id' => null,
                'username' => $iptvUsername,
                'password' => $iptvPassword,
                'expires_at' => now()->addYear(),
                'is_active' => 1,
                'max_connections' => 2,
                'notes' => 'اشتراك IPTV - تم التفعيل من التطبيق',
            ]);
        }
        // Deactivate IPTV subscription if disabled
        if (!$newStatus && $subscriber->iptvSubscription) {
            $subscriber->iptvSubscription->update(['is_active' => 0]);
        }

        return response()->json([
            'success' => true,
            'iptv_enabled' => $newStatus,
            'message' => $newStatus ? 'تم تفعيل IPTV' : 'تم إلغاء IPTV',
        ]);
    }

    // ==================== SESSIONS ====================

    public function subscriberSessions(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $subscriber = Subscriber::where('id', $id)->whereIn('router_id', $routerIds)->first();
        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        $sessions = ActiveSession::where('subscriber_id', $subscriber->id)
            ->get(['id', 'ip_address', 'mac_address', 'started_at', 'uptime', 'bytes_in', 'bytes_out']);

        return response()->json([
            'success' => true,
            'sessions' => $sessions,
        ]);
    }

    // ==================== HOTSPOT ====================

    public function hotspotList(Request $request)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        // If only routers requested, return early without loading cards
        if ($request->filled("routers_only")) {
            $routers = \App\Models\Router::whereIn("id", $routerIds)->get(["id", "name", "ip_address"]);
            return response()->json([
                "success" => true,
                "routers" => $routers,
                "hotspots" => [],
                "stats" => ["total" => 0, "active" => 0, "disabled" => 0, "online" => 0, "unused" => 0, "consumed" => 0, "in_use" => 0]
            ]);
        }

        $query = Subscriber::whereIn('router_id', $routerIds)
            ->where('type', 'hotspot')
            ->with('router:id,name,ip_address');

        if ($request->filled('router_id')) {
            $query->where('router_id', $request->router_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $statsBase = Subscriber::whereIn('router_id', $routerIds)->where('type', 'hotspot');

        if ($request->filled('router_id')) {
            $statsBase = $statsBase->where('router_id', $request->router_id);
        }

        $unusedCount = (clone $statsBase)
            ->where(function($q) { $q->whereNull('bytes_in')->orWhere('bytes_in', 0); })
            ->where(function($q) { $q->whereNull('bytes_out')->orWhere('bytes_out', 0); })
            ->count();

        $fullyConsumedCount = (clone $statsBase)
            ->whereNotNull('limit_bytes_total')
            ->where('limit_bytes_total', '>', 0)
            ->whereRaw('(COALESCE(bytes_in, 0) + COALESCE(bytes_out, 0)) >= limit_bytes_total')
            ->count();

        $inUseCount = (clone $statsBase)
            ->where(function($q) { $q->where('bytes_in', '>', 0)->orWhere('bytes_out', '>', 0); })
            ->where(function($q) {
                $q->whereNull('limit_bytes_total')
                  ->orWhere('limit_bytes_total', 0)
                  ->orWhereRaw('(COALESCE(bytes_in, 0) + COALESCE(bytes_out, 0)) < limit_bytes_total');
            })
            ->count();

        $stats = [
            'total' => (clone $statsBase)->count(),
            'active' => (clone $statsBase)->where('status', 'active')->count(),
            'disabled' => (clone $statsBase)->where('status', 'disabled')->count(),
            'online' => 0, // will be updated below with live count
            'unused' => $unusedCount,
            'consumed' => $fullyConsumedCount,
            'in_use' => $inUseCount,
        ];

        // Fetch LIVE active hotspot count from routers (with cache + timeout)
        try {
            $liveOnlineCount = 0;
            $targetRouterIds = $request->filled('router_id') ? [$request->router_id] : $routerIds;
            $cacheKey = 'hotspot_active_' . md5(implode(',', $targetRouterIds));
            
            // Cache for 30 seconds to avoid slow repeated queries
            $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if ($cached !== null) {
                $stats['online'] = $cached;
            } else {
                $routers_for_active = Router::whereIn('id', $targetRouterIds)->where('is_active', true)->get();
                $hotspotUsernames = (clone $statsBase)->pluck('username')->toArray();
                $usernameSet = array_flip($hotspotUsernames); // faster lookup
                
                foreach ($routers_for_active as $r) {
                    try {
                        $svc = new MikroTikService($r);
                        $svc->setTimeout(5);
                        $svc->connect();
                        $activeList = $svc->command(['/ip/hotspot/active/print']);
                        $svc->disconnect();
                        
                        if ($activeList && is_array($activeList)) {
                            foreach ($activeList as $a) {
                                $user = $a['user'] ?? '';
                                if ($user !== '' && isset($usernameSet[$user])) {
                                    $liveOnlineCount++;
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        continue;
                    }
                }
                $stats['online'] = $liveOnlineCount;
                \Illuminate\Support\Facades\Cache::put($cacheKey, $liveOnlineCount, 30);
            }
        } catch (\Exception $e) {
            // Fallback: keep 0
        }

        $hotspots = $query->orderByRaw('(COALESCE(bytes_in, 0) + COALESCE(bytes_out, 0)) DESC')
            ->get(['id', 'router_id', 'username', 'password', 'full_name', 'phone', 'status',
                    'profile', 'bytes_in', 'bytes_out', 'total_bytes', 'limit_bytes_total',
                    'mikrotik_id', 'created_at', 'expiration_date']);

        $routers = Router::whereIn('id', $routerIds)->get(['id', 'name', 'ip_address']);

        // Fetch live IPs for all hotspot users
        $liveIps = [];
        try {
            $targetRouterIds2 = $request->filled('router_id') ? [$request->router_id] : $routerIds;
            $activeRouters = Router::whereIn('id', $targetRouterIds2)->where('is_active', true)->get();
            foreach ($activeRouters as $r) {
                try {
                    $svc = new MikroTikService($r);
                    $svc->setTimeout(5);
                    $svc->connect();
                    $activeList = $svc->command(['/ip/hotspot/active/print']);
                    $svc->disconnect();
                    if ($activeList && is_array($activeList)) {
                        foreach ($activeList as $a) {
                            $user = $a['user'] ?? '';
                            if ($user !== '') {
                                $liveIps[$user] = $a['address'] ?? null;
                            }
                        }
                    }
                } catch (\Exception $e) { continue; }
            }
        } catch (\Exception $e) {}

        // Add live_ip to each hotspot
        $hotspotsArr = $hotspots->toArray();
        foreach ($hotspotsArr as &$h) {
            $h['live_ip'] = $liveIps[$h['username']] ?? null;
        }

        return response()->json([
            'success' => true,
            'hotspots' => $hotspotsArr,
            'stats' => $stats,
            'routers' => $routers,
        ]);
    }

    public function hotspotDetail(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $hotspot = Subscriber::where('id', $id)
            ->where('type', 'hotspot')
            ->whereIn('router_id', $routerIds)
            ->with('router:id,name')
            ->first();

        if (!$hotspot) {
            return response()->json(['success' => false, 'message' => 'البطاقة غير موجودة'], 404);
        }

        return response()->json([
            'success' => true,
            'hotspot' => $hotspot,
        ]);
    }

    public function hotspotToggle(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $hotspot = Subscriber::where('id', $id)
            ->where('type', 'hotspot')
            ->whereIn('router_id', $routerIds)
            ->first();

        if (!$hotspot) {
            return response()->json(['success' => false, 'message' => 'البطاقة غير موجودة'], 404);
        }

        try {
            $service = new MikroTikService($hotspot->router);
            $service->connect();
            $newStatus = $hotspot->status === 'active' ? 'disabled' : 'active';
            $service->toggleHotspotUser($hotspot->mikrotik_id, $newStatus === 'disabled');
            $service->disconnect();

            $hotspot->update(['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => $newStatus === 'active' ? 'تم تفعيل البطاقة' : 'تم تعطيل البطاقة',
                'status' => $newStatus,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'فشلت العملية: ' . $e->getMessage()], 500);
        }
    }

    public function hotspotDisconnect(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $hotspot = Subscriber::where('id', $id)
            ->where('type', 'hotspot')
            ->whereIn('router_id', $routerIds)
            ->first();

        if (!$hotspot) {
            return response()->json(['success' => false, 'message' => 'البطاقة غير موجودة'], 404);
        }

        try {
            $service = new MikroTikService($hotspot->router);
            $service->connect();
            $service->disconnectHotspotUser($hotspot->username);
            $service->disconnect();

            ActiveSession::where('subscriber_id', $hotspot->id)->delete();

            return response()->json(['success' => true, 'message' => 'تم قطع الاتصال بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'فشل قطع الاتصال: ' . $e->getMessage()], 500);
        }
    }

    public function hotspotSync(Request $request, $routerId)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $router = Router::where('id', $routerId)->whereIn('id', $routerIds)->first();
        if (!$router) {
            return response()->json(['success' => false, 'message' => 'الراوتر غير موجود'], 404);
        }

        try {
            $service = new MikroTikService($router);
            $service->connect();
            $result = $service->syncHotspotUsers($router);
            $service->disconnect();

            return response()->json([
                'success' => true,
                'message' => "تمت المزامنة! {$result['synced']} جديد، {$result['updated']} محدث",
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'فشلت المزامنة: ' . $e->getMessage()], 500);
        }
    }


    public function hotspotEdit(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);
        $hotspot = Subscriber::where('id', $id)->whereIn('router_id', $routerIds)->where('type', 'hotspot')->firstOrFail();

        if ($request->filled('password')) {
            $hotspot->password = $request->password;
        }

        if ($request->filled('profile')) {
            $hotspot->profile = $request->input('profile');
        }

        if ($request->filled('add_days') && is_numeric($request->add_days)) {
            $current = $hotspot->expiration_date ? \Carbon\Carbon::parse($hotspot->expiration_date) : now();
            $hotspot->expiration_date = $current->addDays((int)$request->add_days);
        }

        $hotspot->save();

                // Update on router
        $routerWarning = null;
        try {
            $router = Router::find($hotspot->router_id);
            $service = new MikroTikService($router);
            $service->connect();
        
            // Find correct mikrotik_id by username (fix stale ID)
            $routerUsers = $service->getHotspotUsers();
            $realId = null;
            foreach ($routerUsers as $ru) {
                if (isset($ru['name']) && $ru['name'] === $hotspot->username) {
                    $realId = $ru['.id'] ?? null;
                    break;
                }
            }
        
            if ($realId) {
                if ($hotspot->mikrotik_id !== $realId) {
                    $hotspot->mikrotik_id = $realId;
                    $hotspot->save();
                }
        
                $setCmd = ['/ip/hotspot/user/set', '=.id=' . $realId];
                $hasUpdate = false;
        
                if ($request->filled('password')) {
                    $setCmd[] = '=password=' . $request->password;
                    $hasUpdate = true;
                }
                if ($request->filled('profile')) {
                    $setCmd[] = '=profile=' . $request->input('profile');
                    $hasUpdate = true;
                }
        
                if ($hasUpdate) {
                    $result = $service->command($setCmd);
                    if (isset($result['!trap'])) {
                        $routerWarning = 'Router error: ' . ($result['!trap'][0]['message'] ?? 'unknown');
                        \Log::warning("HotspotEdit router error for {$hotspot->username}: {$routerWarning}");
                    }
                }
            } else {
                $routerWarning = 'المستخدم غير موجود على الراوتر';
                \Log::warning("HotspotEdit: user {$hotspot->username} not found on router {$hotspot->router_id}");
            }
        
            $service->disconnect();
        } catch (\Exception $e) {
            $routerWarning = $e->getMessage();
            \Log::warning("HotspotEdit router sync failed for {$hotspot->username}: " . $e->getMessage());
        }
        
        $msg = 'تم تعديل البطاقة';
        if ($routerWarning) {
            $msg .= ' (تحذير: ' . $routerWarning . ')';
        }
        
        return response()->json(['success' => true, 'message' => $msg, 'router_warning' => $routerWarning]);
    }

    public function hotspotReset(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);
        $hotspot = Subscriber::where('id', $id)->whereIn('router_id', $routerIds)->where('type', 'hotspot')->firstOrFail();

        $hotspot->um_usage_offset = 0;
        $hotspot->bytes_in = 0;
        $hotspot->bytes_out = 0;
        $hotspot->total_bytes = 0;
        $hotspot->archived_bytes = 0;
        $hotspot->save();

        try {
            $router = Router::find($hotspot->router_id);
            $service = new MikroTikService($router);
            $service->connect();
            if ($hotspot->mikrotik_id) {
                $service->command(['/ip/hotspot/user/reset-counters', '=.id=' . $hotspot->mikrotik_id]);
            }
            $service->disconnect();
        } catch (\Exception $e) {}

        return response()->json(['success' => true, 'message' => 'تم تصفير الاستهلاك']);
    }

    public function hotspotDelete(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);
        $hotspot = Subscriber::where('id', $id)->whereIn('router_id', $routerIds)->where('type', 'hotspot')->firstOrFail();

        try {
            $router = Router::find($hotspot->router_id);
            $service = new MikroTikService($router);
            $service->connect();
            if ($hotspot->mikrotik_id) {
                $service->deleteHotspotUser($hotspot->mikrotik_id);
            }
            $service->disconnect();
        } catch (\Exception $e) {}

        $hotspot->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف البطاقة']);
    }


    public function hotspotTransfer(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $request->validate([
            "target_router_id" => "required|integer",
        ]);

        $targetRouterId = $request->target_router_id;

        $hotspot = Subscriber::where("id", $id)->whereIn("router_id", $routerIds)->where("type", "hotspot")->firstOrFail();

        if (!in_array($targetRouterId, $routerIds)) {
            return response()->json(["success" => false, "message" => "Unauthorized"], 403);
        }

        if ($hotspot->router_id == $targetRouterId) {
            return response()->json(["success" => false, "message" => "Same router"], 422);
        }

        $targetRouter = Router::findOrFail($targetRouterId);
        $sourceRouter = Router::find($hotspot->router_id);

        try {
            if ($sourceRouter && $hotspot->mikrotik_id) {
                $s = new \App\Services\MikroTikService($sourceRouter);
                $s->connect();
                $s->deleteHotspotUser($hotspot->mikrotik_id);
                $s->disconnect();
            }
        } catch (\Exception $e) {}

        try {
            $t = new \App\Services\MikroTikService($targetRouter);
            $t->connect();
            $ud = ["name" => $hotspot->username, "password" => $hotspot->password, "profile" => $hotspot->profile ?? "default", "comment" => "MegaWiFi Transferred"];
            if ($hotspot->limit_bytes_total > 0) { $ud["limit-bytes-total"] = (string)$hotspot->limit_bytes_total; }
            $result = $t->addHotspotUser($ud);
            $t->disconnect();
            $newId = $result["after"]["ret"] ?? null;
        } catch (\Exception $e) {
            return response()->json(["success" => false, "message" => $e->getMessage()], 500);
        }

        $oldName = $sourceRouter ? $sourceRouter->name : "?";
        $hotspot->router_id = $targetRouterId;
        $hotspot->mikrotik_id = $newId ?? null;
        $hotspot->um_usage_offset = 0;
        $hotspot->bytes_in = 0;
        $hotspot->bytes_out = 0;
        $hotspot->total_bytes = 0;
        $hotspot->archived_bytes = 0;
        $hotspot->save();

        return response()->json(["success" => true, "message" => "Transfer OK: " . $oldName . " -> " . $targetRouter->name]);
    }

    public function hotspotDeleteUsed(Request $request)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $query = Subscriber::whereIn('router_id', $routerIds)
            ->where('type', 'hotspot')
            ->whereNotNull('limit_bytes_total')
            ->where('limit_bytes_total', '>', 0)
            ->whereRaw('(COALESCE(bytes_in, 0) + COALESCE(bytes_out, 0)) >= limit_bytes_total');

        if ($request->filled('router_id')) {
            $routerId = (int)$request->router_id;
            if (!in_array($routerId, $routerIds)) {
                return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
            }
            $query->where('router_id', $routerId);
        }

        $consumed = $query->get();
        $count = 0;

        // Group by router for efficient deletion
        $grouped = $consumed->groupBy('router_id');
        foreach ($grouped as $rId => $cards) {
            $router = Router::find($rId);
            if (!$router) {
                foreach ($cards as $h) { $h->delete(); $count++; }
                continue;
            }
            try {
                $service = new MikroTikService($router);
                $service->connect();
                $routerUsers = $service->getHotspotUsers();
                $userMap = [];
                foreach ($routerUsers as $u) {
                    if (isset($u['name'], $u['.id'])) $userMap[$u['name']] = $u['.id'];
                }
                foreach ($cards as $h) {
                    if (isset($userMap[$h->username])) {
                        try { $service->deleteHotspotUser($userMap[$h->username]); } catch (\Exception $e) {}
                    }
                    $h->delete();
                    $count++;
                }
                $service->disconnect();
            } catch (\Exception $e) {
                foreach ($cards as $h) { $h->delete(); $count++; }
            }
        }

        return response()->json(['success' => true, 'message' => "تم حذف {$count} بطاقة مستهلكة"]);
    }

    // ==================== ROUTERS ====================

        public function rebootRouter(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $router = Router::where('id', $id)->whereIn('id', $routerIds)->first();
        if (!$router) {
            return response()->json(['success' => false, 'message' => 'الراوتر غير موجود'], 404);
        }

        try {
            $service = new MikroTikService($router);
            $service->connect();
            $service->command(['/system/reboot']);
            // No disconnect needed - router is rebooting

            return response()->json(['success' => true, 'message' => 'تم إعادة تشغيل الراوتر بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'فشل في إعادة التشغيل: ' . $e->getMessage()], 500);
        }
    }

    public function syncRouter(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $router = Router::where('id', $id)->whereIn('id', $routerIds)->first();
        if (!$router) {
            return response()->json(['success' => false, 'message' => 'الراوتر غير موجود'], 404);
        }

        try {
            $controller = app(\App\Http\Controllers\UserManagerController::class);
            $controller->sync($router);

            return response()->json(['success' => true, 'message' => 'تمت المزامنة بنجاح']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'فشل في المزامنة: ' . $e->getMessage()], 500);
        }
    }

    public function profiles(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $router = Router::where('id', $id)->whereIn('id', $routerIds)->first();
        if (!$router) {
            return response()->json(['success' => false, 'message' => 'الراوتر غير موجود'], 404);
        }

        try {
            $service = new UserManagerService($router);
            $service->connect();
            $routerProfiles = $service->getProfiles();
            $service->disconnect();

            $profiles = collect($routerProfiles)->map(function ($p) {
                return [
                    'name' => $p['name'] ?? $p['.id'] ?? 'unknown',
                    'name_for_users' => $p['name-for-users'] ?? $p['name'] ?? '',
                    'price' => $p['price'] ?? '0',
                    'validity' => $p['validity'] ?? '',
                    'override_shared_users' => $p['override-shared-users'] ?? '',
                ];
            })->values();

            return response()->json(['success' => true, 'profiles' => $profiles]);
        } catch (\Exception $e) {
            // Fallback to database profiles
            $profiles = Subscriber::where('router_id', $id)
                ->whereNotNull('profile')
                ->select('profile')
                ->distinct()
                ->pluck('profile')
                ->map(function ($name) {
                    return ['name' => $name];
                });

            return response()->json(['success' => true, 'profiles' => $profiles, 'source' => 'database']);
        }
    }

    public function hotspotProfiles(Request $request, $routerId)
    {
        $router = \App\Models\Router::findOrFail($routerId);
        $service = new \App\Services\MikroTikService($router);
        $profiles = $service->getHotspotProfiles();
        $profileList = [];
        foreach ($profiles as $p) {
            if (isset($p['name']) && !str_contains(strtolower($p['name']), 'default')) {
                $profileList[] = [
                    'name' => $p['name'],
                    'rate_limit' => $p['rate-limit'] ?? '',
                    'shared_users' => $p['shared-users'] ?? '1',
                ];
            }
        }
        return response()->json(['success' => true, 'profiles' => $profileList]);
    }

    public function hotspotGenerateCards(Request $request)
    {
        $request->validate([
            'router_id' => 'required|integer',
            'profile' => 'required|string',
            'count' => 'required|integer|min:1|max:100',
            'prefix' => 'nullable|string|max:10',
            'password_length' => 'nullable|integer|min:3|max:16',
            'data_limit_mb' => 'required|numeric|min:1',
            'comment' => 'nullable|string|max:100',
        ]);
        $router = \App\Models\Router::findOrFail($request->router_id);
        $service = new \App\Services\MikroTikService($router);
        $count = $request->count;
        $prefix = $request->prefix ?? '';
        $passLength = $request->password_length ?? 8;
        $profile = $request->profile;
        $dataLimitMb = $request->data_limit_mb;
        $comment = $request->comment ?? 'MegaWiFi Generated';
        $users = [];
        $generatedCards = [];
        for ($i = 0; $i < $count; $i++) {
            $username = $prefix . $this->generateVoucherCode(6);
            $password = $this->generateVoucherCode($passLength);
            $userData = ['name' => $username, 'password' => $password, 'profile' => $profile, 'comment' => $comment];
            if ($dataLimitMb && $dataLimitMb > 0) {
                $userData['limit-bytes-total'] = (int)($dataLimitMb * 1024 * 1024);
            }
            $users[] = $userData;
            $generatedCards[] = ['username' => $username, 'password' => $password, 'profile' => $profile, 'data_limit_mb' => $dataLimitMb];
        }
        $result = $service->addHotspotUsersBatch($users);
        $saved = 0;
        foreach ($users as $user) {
            try {
                \App\Models\Subscriber::create([
                    'router_id' => $router->id, 'username' => $user['name'], 'password' => $user['password'],
                    'profile' => $profile, 'type' => 'hotspot', 'status' => 'active',
                    'limit_bytes_total' => $user['limit-bytes-total'] ?? null,
                    'bytes_in' => 0, 'bytes_out' => 0, 'total_bytes' => 0,
                    'archived_bytes' => 0,
                ]);
                $saved++;
            } catch (\Exception $e) {
                \Log::warning("hotspot card DB fail: " . $e->getMessage());
            }
        }
        return response()->json(['success' => true, 'message' => "Generated {$saved} cards", 'cards' => $generatedCards,
            'stats' => ['requested' => $count, 'added_to_router' => $result['added'] ?? $count, 'saved_to_db' => $saved]]);
    }

    public function hotspotAddCard(Request $request)
    {
        $request->validate([
            'router_id' => 'required|integer', 'username' => 'required|string',
            'password' => 'required|string', 'profile' => 'required|string',
            'data_limit_mb' => 'required|numeric|min:1', 'comment' => 'nullable|string|max:100',
        ]);
        $router = \App\Models\Router::findOrFail($request->router_id);
        $service = new \App\Services\MikroTikService($router);
        $existing = \App\Models\Subscriber::where('router_id', $router->id)->where('username', $request->username)->where('type', 'hotspot')->first();
        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Username already exists'], 422);
        }
        $userData = ['name' => $request->username, 'password' => $request->password, 'profile' => $request->profile, 'comment' => $request->comment ?? 'MegaWiFi'];
        if ($request->data_limit_mb && $request->data_limit_mb > 0) {
            $userData['limit-bytes-total'] = (int)($request->data_limit_mb * 1024 * 1024);
        }
        $result = $service->addHotspotUser($userData);
        if (isset($result['!trap'])) {
            return response()->json(['success' => false, 'message' => 'Router error: ' . ($result['!trap'][0]['message'] ?? 'Unknown')], 500);
        }
        $subscriber = \App\Models\Subscriber::create([
            'router_id' => $router->id, 'username' => $request->username, 'password' => $request->password,
            'profile' => $request->profile, 'type' => 'hotspot', 'status' => 'active',
            'limit_bytes_total' => $userData['limit-bytes-total'] ?? null,
            'bytes_in' => 0, 'bytes_out' => 0, 'total_bytes' => 0,
            'archived_bytes' => 0,
        ]);
        return response()->json(['success' => true, 'message' => 'Card added successfully',
            'card' => ['id' => $subscriber->id, 'username' => $subscriber->username, 'password' => $subscriber->password, 'profile' => $subscriber->profile]]);
    }

    private function generateVoucherCode($length = 8)
    {
        $chars = '0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $str;
    }



    // ==================== INTERFACE MONITORING ====================

    public function routerInterfaces(Request $request, $routerId)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        if (!in_array($routerId, $routerIds)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $router = Router::find($routerId);
        if (!$router) {
            return response()->json(['success' => false, 'message' => 'Router not found'], 404);
        }

        try {
            $mikrotik = new \App\Services\MikroTikService($router);
            $interfaces = $mikrotik->command(['/interface/print']);

            $result = [];
            foreach ($interfaces as $iface) {
                if (isset($iface['name'])) {
                    $result[] = [
                        'name' => $iface['name'],
                        'type' => $iface['type'] ?? 'unknown',
                        'running' => ($iface['running'] ?? 'false') === 'true',
                        'disabled' => ($iface['disabled'] ?? 'false') === 'true',
                    ];
                }
            }

            return response()->json(['success' => true, 'interfaces' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function interfaceTraffic(Request $request, $routerId)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        if (!in_array($routerId, $routerIds)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $router = Router::find($routerId);
        if (!$router) {
            return response()->json(['success' => false, 'message' => 'Router not found'], 404);
        }

        $interface = $request->query('interface', 'ether1');

        try {
            $mikrotik = new \App\Services\MikroTikService($router);

            // Get byte counters
            $ifaceData = $mikrotik->command(['/interface/print', '?name=' . $interface]);
            $txBytes = 0;
            $rxBytes = 0;
            if (!empty($ifaceData)) {
                $txBytes = (int) ($ifaceData[0]['tx-byte'] ?? 0);
                $rxBytes = (int) ($ifaceData[0]['rx-byte'] ?? 0);
            }

            // Get real-time speed
            $monitor = $mikrotik->command(['/interface/monitor-traffic', '=interface=' . $interface, '=once=']);
            $txSpeed = 0;
            $rxSpeed = 0;
            if (!empty($monitor)) {
                $txSpeed = (int) ($monitor[0]['tx-bits-per-second'] ?? 0);
                $rxSpeed = (int) ($monitor[0]['rx-bits-per-second'] ?? 0);
            }

            return response()->json([
                'success' => true,
                'tx_bytes' => $txBytes,
                'rx_bytes' => $rxBytes,
                'tx_speed_bps' => $txSpeed,
                'rx_speed_bps' => $rxSpeed,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function routerNeighbors(Request $request, $routerId)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        if (!in_array($routerId, $routerIds)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $router = Router::find($routerId);
        if (!$router) {
            return response()->json(['success' => false, 'message' => 'Router not found'], 404);
        }

        try {
            $mikrotik = new \App\Services\MikroTikService($router);

            // Get IP Neighbor list
            $neighbors = $mikrotik->command(['/ip/neighbor/print']);

            $result = [];
            foreach ($neighbors as $n) {
                $status = 'reachable';
                $result[] = [
                    'identity' => $n['identity'] ?? '',
                    'address' => $n['address'] ?? '',
                    'address4' => $n['address'] ?? '',
                    'mac_address' => $n['mac-address'] ?? '',
                    'interface' => $n['interface'] ?? '',
                    'platform' => $n['platform'] ?? '',
                    'board' => $n['board'] ?? '',
                    'version' => $n['version'] ?? '',
                    'uptime' => $n['uptime'] ?? '',
                    'system_description' => $n['system-description'] ?? '',
                    'status' => $status,
                    'active_address' => $n['address'] ?? '',
                    'active_mac' => $n['mac-address'] ?? '',
                    'comment' => $n['comment'] ?? '',
                    'dynamic' => ($n['dynamic'] ?? 'false') === 'true',
                    'blocked' => false,
                    'disabled' => false,
                ];
            }

            return response()->json(['success' => true, 'neighbors' => $result, 'router_name' => $router->name]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * Refresh single subscriber usage (disable/enable toggle like web)
     */
    /**
     * Get real-time traffic speed for a subscriber from MikroTik queues
     * Returns current upload/download rate in bits per second
     */
    public function liveUsage(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $subscriber = Subscriber::where('id', $id)
            ->whereIn('router_id', $routerIds)
            ->first();

        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'not found'], 404);
        }

        $router = Router::find($subscriber->router_id);
        if (!$router) {
            return response()->json(['success' => false], 404);
        }

        try {
            $mikrotik = new \App\Services\MikroTikService($router);
            $mikrotik->connect();

            // Search for queue by PPPoE name
            $queueName = '<pppoe-' . $subscriber->username . '>';
            $queues = $mikrotik->command(['/queue/simple/print', '?name=' . $queueName]);

            $found = false;
            $uploadRate = 0;
            $downloadRate = 0;

            foreach ($queues as $q) {
                if (is_array($q) && isset($q['.id']) && isset($q['rate'])) {
                    $rateParts = explode('/', $q['rate']);
                    if (count($rateParts) === 2) {
                        $uploadRate = (int)$rateParts[0];
                        $downloadRate = (int)$rateParts[1];
                    }
                    $found = true;
                    break;
                }
            }

            // If not found by PPPoE name, try hotspot queue by target IP
            if (!$found && $subscriber->ip_address) {
                $targetIp = $subscriber->ip_address . '/32';
                $queues = $mikrotik->command(['/queue/simple/print', '?target=' . $targetIp]);
                foreach ($queues as $q) {
                    if (is_array($q) && isset($q['.id']) && isset($q['rate'])) {
                        $rateParts = explode('/', $q['rate']);
                        if (count($rateParts) === 2) {
                            $uploadRate = (int)$rateParts[0];
                            $downloadRate = (int)$rateParts[1];
                        }
                        $found = true;
                        break;
                    }
                }
            }

            $mikrotik->disconnect();

            return response()->json([
                'success' => true,
                'active' => $found,
                'upload_rate' => $uploadRate,
                'download_rate' => $downloadRate,
                'total_rate' => $uploadRate + $downloadRate,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
    public function refreshSubscriberUsage(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $subscriber = Subscriber::where('id', $id)
            ->whereIn('router_id', $routerIds)
            ->first();

        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        if (!$subscriber->mikrotik_id) {
            return response()->json(['success' => false, 'message' => 'المشترك غير مرتبط بالراوتر (لا يوجد mikrotik_id)'], 400);
        }

        $router = Router::find($subscriber->router_id);
        if (!$router) {
            return response()->json(['success' => false, 'message' => 'الراوتر غير موجود'], 404);
        }

        try {
            if (!UserManagerService::isRouterReachable($router)) {
                return response()->json(['success' => false, 'message' => 'الراوتر غير متصل أو غير متاح حالياً'], 503);
            }

            $service = new UserManagerService($router);
            $service->connect();

            // Look up user by username to get real .id
            $routerUser = $service->getUserByUsername($subscriber->username);
            if (!$routerUser || !isset($routerUser['.id'])) {
                $service->disconnect();
                return response()->json(['success' => false, 'message' => 'المستخدم غير موجود على الراوتر'], 404);
            }
            $realId = $routerUser['.id'];

            // Disable then enable to force session close
            $service->toggleUserStatus($realId, true);
            usleep(300000);
            $service->toggleUserStatus($realId, false);

            // Get fresh usage from UM7 monitor
            $monitor = $service->getSingleUserMonitor($realId);
            $service->disconnect();

            if (!$monitor) {
                return response()->json(['success' => false, 'message' => 'لم يتم العثور على بيانات المستخدم'], 404);
            }

            $totalDownload = $monitor['total-download'];
            $totalUpload = $monitor['total-upload'];
            $totalBytes = $totalDownload + $totalUpload;

            $subscriber->update([
                'bytes_in' => $totalUpload,
                'bytes_out' => $totalDownload,
                'total_bytes' => $totalBytes,
                'mikrotik_id' => $realId,
                'last_seen' => now(),
            ]);

            // Reload subscriber with router
            $subscriber->load('router:id,name');

            return response()->json([
                'success' => true,
                'message' => 'تمت المزامنة بنجاح',
                'subscriber' => $subscriber,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'فشل المزامنة: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get ALL sessions for a subscriber from MikroTik (not just active)
     */
    public function subscriberAllSessions(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $subscriber = \App\Models\Subscriber::where('id', $id)
            ->whereIn('router_id', $routerIds)->first();

        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        $sessions = [];

        try {
            $router = \App\Models\Router::find($subscriber->router_id);
            if (!$router) {
                return response()->json(['success' => false, 'message' => 'الراوتر غير موجود'], 404);
            }

            if ($subscriber->type === 'usermanager') {
                // Get sessions from User Manager
                $umService = new \App\Services\UserManagerService($router);
                $umSessions = $umService->getUserSessions($subscriber->username);

                foreach ($umSessions as $s) {
                    $sessions[] = [
                        'session_id' => $s['acct-session-id'] ?? '',
                        'ip_address' => $s['user-address'] ?? '',
                        'mac_address' => $s['calling-station-id'] ?? '',
                        'started' => $s['started'] ?? '',
                        'ended' => $s['ended'] ?? null,
                        'uptime' => $s['uptime'] ?? '0s',
                        'download' => (int)($s['download'] ?? 0),
                        'upload' => (int)($s['upload'] ?? 0),
                        'active' => ($s['active'] ?? 'false') === 'true',
                        'terminate_cause' => $s['terminate-cause'] ?? null,
                    ];
                }
            } else {
                // For PPP/hotspot - get active sessions from MikroTik + DB history
                $mikrotikService = new \App\Services\MikroTikService($router);

                if ($subscriber->type === 'ppp') {
                    $active = $mikrotikService->command(['/ppp/active/print', '?name=' . $subscriber->username]);
                } else {
                    $active = $mikrotikService->command(['/ip/hotspot/active/print', '?user=' . $subscriber->username]);
                }

                if ($active) {
                    foreach ($active as $s) {
                        if (isset($s['name']) || isset($s['user'])) {
                            $sessions[] = [
                                'session_id' => $s['session-id'] ?? '',
                                'ip_address' => $s['address'] ?? '',
                                'mac_address' => $s['caller-id'] ?? '',
                                'started' => null,
                                'ended' => null,
                                'uptime' => $s['uptime'] ?? '0s',
                                'download' => 0,
                                'upload' => 0,
                                'active' => true,
                                'terminate_cause' => null,
                            ];
                        }
                    }
                }

                // Also add from active_sessions table
                $dbSessions = \App\Models\ActiveSession::where('subscriber_id', $subscriber->id)->get();
                foreach ($dbSessions as $ds) {
                    $sessions[] = [
                        'session_id' => $ds->session_id ?? '',
                        'ip_address' => $ds->ip_address ?? '',
                        'mac_address' => $ds->mac_address ?? '',
                        'started' => $ds->started_at ? $ds->started_at->format('Y-m-d H:i:s') : null,
                        'ended' => null,
                        'uptime' => $ds->uptime ? $this->formatUptime($ds->uptime) : '0s',
                        'download' => (int)($ds->bytes_out ?? 0),
                        'upload' => (int)($ds->bytes_in ?? 0),
                        'active' => true,
                        'terminate_cause' => null,
                    ];
                }
            }

            // Sort by started date descending (newest first)
            usort($sessions, function($a, $b) {
                return strcmp($b['started'] ?? '', $a['started'] ?? '');
            });

        } catch (\Exception $e) {
            \Log::error("Failed to get all sessions for subscriber {$id}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل في جلب الجلسات: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'success' => true,
            'total' => count($sessions),
            'sessions' => $sessions,
        ]);
    }

    private function formatUptime($seconds)
    {
        if ($seconds <= 0) return '0s';
        $d = floor($seconds / 86400);
        $h = floor(($seconds % 86400) / 3600);
        $m = floor(($seconds % 3600) / 60);
        $s = $seconds % 60;
        $parts = [];
        if ($d > 0) $parts[] = $d . 'd';
        if ($h > 0) $parts[] = $h . 'h';
        if ($m > 0) $parts[] = $m . 'm';
        if ($s > 0 || empty($parts)) $parts[] = $s . 's';
        return implode('', $parts);
    }

    /**
     * Sync usage for all routers (bulk)
     * Uses UserManager session data - lightweight, no toggle
     */
    public function syncAllUsage(Request $request)
    {
        try {
            $user = $this->user($request);
            $routerIds = $this->routerIds($user);
            $routers = Router::whereIn('id', $routerIds)->where('is_active', true)->get();
            $results = [];
            $totalSynced = 0;
            $totalFailed = 0;
            $totalRouters = 0;
            $errors = [];

            foreach ($routers as $router) {
                try {
                    // Check if router is reachable
                    $service = new UserManagerService($router);
                    $service->connect();
                    $result = $service->syncUsage();
                    $service->disconnect();

                    $totalSynced += $result['synced'] ?? 0;
                    $totalFailed += $result['failed'] ?? 0;
                    $totalRouters++;

                    $results[] = [
                        'router' => $router->name,
                        'synced' => $result['synced'] ?? 0,
                        'failed' => $result['failed'] ?? 0,
                        'duration' => $result['duration'] ?? 0,
                    ];
                } catch (\Exception $e) {
                    $errors[] = $router->name . ': ' . $e->getMessage();
                    \Log::error("Sync usage failed for router {$router->name}: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => "تمت مزامنة {$totalSynced} مشترك من {$totalRouters} راوتر",
                'total_synced' => $totalSynced,
                'total_failed' => $totalFailed,
                'routers_processed' => $totalRouters,
                'routers' => $results,
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل في مزامنة الاستهلاك: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ==================== NOTIFICATIONS ====================
    
    public function notifications(Request $request)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);
        
        $query = \App\Models\AdminNotification::query();
        
        // Super admin sees all, others see only their router notifications
        if ($user->role !== 'super_admin') {
            if ($user->role === 'reseller') {
                // Resellers see: their own operations + system notifications for their routers
                $query->where(function($q) use ($routerIds, $user) {
                    $q->where('user_id', $user->id)
                      ->orWhere(function($q2) use ($routerIds) {
                          $q2->whereIn('router_id', $routerIds)
                             ->whereNull('user_id');
                      })
                      ->orWhere(function($q2) {
                          $q2->whereNull('router_id')
                             ->whereNull('user_id');
                      });
                });
            } else {
                // Admins see: all operations on their routers + their own operations
                $query->where(function($q) use ($routerIds, $user) {
                    $q->whereIn('router_id', $routerIds)
                      ->orWhere('user_id', $user->id)
                      ->orWhere(function($q2) {
                          $q2->whereNull('router_id')
                             ->whereNull('user_id');
                      });
                });
            }
        }
        
        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 30));
        
                $unreadCount = \App\Models\AdminNotification::query();
        if ($user->role !== 'super_admin') {
            if ($user->role === 'reseller') {
                $unreadCount->where(function($q) use ($routerIds, $user) {
                    $q->where('user_id', $user->id)
                      ->orWhere(function($q2) use ($routerIds) {
                          $q2->whereIn('router_id', $routerIds)
                             ->whereNull('user_id');
                      })
                      ->orWhere(function($q2) {
                          $q2->whereNull('router_id')
                             ->whereNull('user_id');
                      });
                });
            } else {
                $unreadCount->where(function($q) use ($routerIds, $user) {
                    $q->whereIn('router_id', $routerIds)
                      ->orWhere('user_id', $user->id)
                      ->orWhere(function($q2) {
                          $q2->whereNull('router_id')
                             ->whereNull('user_id');
                      });
                });
            }
        }
        $unreadCount = $unreadCount->where('is_read', false)->count();
        
        return response()->json([
            'success' => true,
            'notifications' => $notifications->items(),
            'unread_count' => $unreadCount,
            'total' => $notifications->total(),
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
        ]);
    }
    
    public function unreadNotifications(Request $request)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);
        
        $query = \App\Models\AdminNotification::where('is_read', false);
        
        if ($user->role !== 'super_admin') {
            if ($user->role === 'reseller') {
                // Resellers see: their own operations + system notifications for their routers
                $query->where(function($q) use ($routerIds, $user) {
                    $q->where('user_id', $user->id)
                      ->orWhere(function($q2) use ($routerIds) {
                          $q2->whereIn('router_id', $routerIds)
                             ->whereNull('user_id');
                      })
                      ->orWhere(function($q2) {
                          $q2->whereNull('router_id')
                             ->whereNull('user_id');
                      });
                });
            } else {
                // Admins see: all operations on their routers + their own operations
                $query->where(function($q) use ($routerIds, $user) {
                    $q->whereIn('router_id', $routerIds)
                      ->orWhere('user_id', $user->id)
                      ->orWhere(function($q2) {
                          $q2->whereNull('router_id')
                             ->whereNull('user_id');
                      });
                });
            }
        }
        
        $notifications = $query->orderBy('created_at', 'desc')->limit(20)->get();
        
        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $notifications->count(),
        ]);
    }
    
    public function markNotificationRead(Request $request, $id)
    {
        $notification = \App\Models\AdminNotification::findOrFail($id);
        $notification->update(['is_read' => true, 'read_at' => now()]);
        
        return response()->json(['success' => true]);
    }
    
    public function markAllNotificationsRead(Request $request)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);
        
        $query = \App\Models\AdminNotification::where('is_read', false);
        if ($user->role !== 'super_admin') {
            if ($user->role === 'reseller') {
                // Resellers see: their own operations + system notifications for their routers
                $query->where(function($q) use ($routerIds, $user) {
                    $q->where('user_id', $user->id)
                      ->orWhere(function($q2) use ($routerIds) {
                          $q2->whereIn('router_id', $routerIds)
                             ->whereNull('user_id');
                      })
                      ->orWhere(function($q2) {
                          $q2->whereNull('router_id')
                             ->whereNull('user_id');
                      });
                });
            } else {
                // Admins see: all operations on their routers + their own operations
                $query->where(function($q) use ($routerIds, $user) {
                    $q->whereIn('router_id', $routerIds)
                      ->orWhere('user_id', $user->id)
                      ->orWhere(function($q2) {
                          $q2->whereNull('router_id')
                             ->whereNull('user_id');
                      });
                });
            }
        }
        $query->update(['is_read' => true, 'read_at' => now()]);
        
        return response()->json(['success' => true]);
    }
    
    public function deleteNotification(Request $request, $id)
    {
        $notification = \App\Models\AdminNotification::find($id);
        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'الإشعار غير موجود'], 404);
        }
        $notification->delete();

        return response()->json(['success' => true, 'message' => 'تم حذف الإشعار']);
    }

    public function deleteAllNotifications(Request $request)
    {
        $user = $this->user($request);

        if ($user->isSuperAdmin()) {
            $count = \App\Models\AdminNotification::count();
            \App\Models\AdminNotification::query()->delete();
        } else {
            $routerIds = $user->routers()->pluck('routers.id')->toArray();
            $query = \App\Models\AdminNotification::where(function($q) use ($user, $routerIds) {
                $q->where('user_id', $user->id)
                  ->orWhereIn('router_id', $routerIds);
            });
            $count = (clone $query)->count();
            $query->delete();
        }

        return response()->json(['success' => true, 'message' => 'تم حذف جميع الإشعارات', 'deleted_count' => $count]);
    }


    


    // ==================== ADMIN: RESELLER BALANCE ====================

    /**
     * List all resellers (for admin recharge dropdown)
     */
    public function listResellers(Request $request)
    {
        $user = $this->user($request);
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        // Filter by admin_reseller pivot (super_admin sees all)
        $query = \App\Models\User::where('role', 'reseller')
            ->select('id', 'name', 'email', 'balance', 'phone');

        if (!$user->isSuperAdmin()) {
            $resellerIds = \DB::table('admin_reseller')
                ->where('admin_id', $user->id)
                ->pluck('reseller_id');
            $query->whereIn('id', $resellerIds);
        }

        $resellers = $query->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'resellers' => $resellers,
        ]);
    }

    /**
     * Recharge reseller balance (admin only)
     */
    public function rechargeReseller(Request $request)
    {
        $user = $this->user($request);
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $request->validate([
            'reseller_id' => 'required|integer|exists:users,id',
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        $reseller = \App\Models\User::where('id', $request->reseller_id)
            ->where('role', 'reseller')
            ->first();

        if (!$reseller) {
            return response()->json(['success' => false, 'message' => 'الوكيل غير موجود'], 404);
        }

        // Check admin has access to this reseller
        if (!$user->isSuperAdmin()) {
            $hasAccess = \DB::table('admin_reseller')
                ->where('admin_id', $user->id)
                ->where('reseller_id', $reseller->id)
                ->exists();
            if (!$hasAccess) {
                return response()->json(['success' => false, 'message' => 'لا تملك صلاحية لهذا الوكيل'], 403);
            }
        }

        $amount = (float) $request->amount;
        $notes = $request->input('notes', '');
        $description = 'شحن رصيد بواسطة الأدمن ' . $user->name;
        if ($notes) {
            $description .= ' - ' . $notes;
        }

        // Create transaction (this also updates balance)
        $transaction = \App\Models\ResellerTransaction::createTransaction(
            $reseller,
            'deposit',
            $amount,
            $description,
            $user, // admin
            'admin_recharge_' . now()->timestamp,
            null // no subscriber
        );

        // Create notification for recharge history list
        \App\Models\AdminNotification::notify('balance_recharge', 'شحن رصيد وكيل', 'تم شحن رصيد الوكيل ' . $reseller->name . ' بمبلغ ' . number_format($amount) . ' ل.س بواسطة ' . $user->name, [
            'icon' => 'fa-wallet',
            'color' => 'green',
            'user_id' => $reseller->id,
            'data' => json_encode([
                'admin_id' => $user->id,
                'admin_name' => $user->name,
                'reseller_id' => $reseller->id,
                'reseller_name' => $reseller->name,
                'amount' => $amount,
                'balance_before' => $transaction->balance_before,
                'balance_after' => $transaction->balance_after,
                'notes' => $notes,
            ]),
        ]);

        // Create a second notification visible to the reseller
        \App\Models\AdminNotification::notify('balance_received', 'تم شحن رصيدك', 'تم إضافة ' . number_format($amount) . ' ل.س إلى رصيدك بواسطة الإدارة. رصيدك الجديد: ' . number_format($reseller->fresh()->balance) . ' ل.س', [
            'icon' => 'fa-money-bill-wave',
            'color' => 'emerald',
            'user_id' => $reseller->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم شحن رصيد ' . $reseller->name . ' بمبلغ ' . number_format($amount) . ' ل.س',
            'new_balance' => $reseller->fresh()->balance,
            'transaction' => [
                'id' => $transaction->id,
                'amount' => $transaction->amount,
                'balance_before' => $transaction->balance_before,
                'balance_after' => $transaction->balance_after,
                'created_at' => $transaction->created_at->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    /**
     * Get recharge history for admin
     */
    public function rechargeHistory(Request $request)
    {
        $user = $this->user($request);
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $transactions = \App\Models\ResellerTransaction::where('type', 'deposit')
            ->whereNotNull('admin_id')
            ->when(!$user->isSuperAdmin(), function ($q) use ($user) {
                $resellerIds = \DB::table('admin_reseller')->where('admin_id', $user->id)->pluck('reseller_id');
                $q->whereIn('reseller_id', $resellerIds);
            })
            ->with(['reseller:id,name,email,phone', 'admin:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($t) {
                return [
                    'id' => $t->id,
                    'reseller_name' => $t->reseller?->name ?? 'غير معروف',
                    'admin_name' => $t->admin?->name ?? 'غير معروف',
                    'amount' => $t->amount,
                    'balance_before' => $t->balance_before,
                    'balance_after' => $t->balance_after,
                    'description' => $t->description,
                    'created_at' => $t->created_at->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'success' => true,
            'transactions' => $transactions,
        ]);
    }

    public function adminOptimize(Request $request)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $results = [];

        try {
            \Artisan::call('cache:clear');
            $results[] = 'cache cleared';
        } catch (\Throwable $e) {
            $results[] = 'cache: ' . $e->getMessage();
        }

        try {
            \Artisan::call('config:clear');
            $results[] = 'config cleared';
        } catch (\Throwable $e) {
            $results[] = 'config: ' . $e->getMessage();
        }

        try {
            \Artisan::call('route:clear');
            $results[] = 'routes cleared';
        } catch (\Throwable $e) {
            $results[] = 'routes: ' . $e->getMessage();
        }

        try {
            \Artisan::call('view:clear');
            \Artisan::call('view:cache');
            $results[] = 'views cleared';
        } catch (\Throwable $e) {
            $results[] = 'views: ' . $e->getMessage();
        }

        try {
            \Artisan::call('optimize');
            $results[] = 'optimized';
        } catch (\Throwable $e) {
            $results[] = 'optimize: ' . $e->getMessage();
        }

        // Truncate logs
        $logPath = storage_path('logs/laravel.log');
        if (file_exists($logPath)) {
            file_put_contents($logPath, '');
            $results[] = 'logs cleared';
        }

        // Restart PHP-FPM for OPcache
        try {
            exec('systemctl restart php8.4-fpm 2>&1', $fpmOutput, $fpmRet);
            if ($fpmRet === 0) {
                $results[] = 'php-fpm restarted';
            }
        } catch (\Throwable $e) {}

        return response()->json([
            'success' => true,
            'message' => 'تم مسح الكاش وتحسين الأداء بنجاح',
            'details' => $results
        ]);
    }

    // ==================== RESELLER ENDPOINTS ====================

    public function resellerDashboard(Request $request)
    {
        $user = $this->user($request);
        if ($user->role !== 'reseller') {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $routerIds = $this->routerIds($user);

        // Get permissions
        $permissions = \App\Models\ResellerRouterPermission::where('reseller_id', $user->id)
            ->whereIn('router_id', $routerIds)
            ->get();

        $routers = \App\Models\Router::whereIn('id', $routerIds)->get(['id', 'name', 'status']);

        // Stats
        $totalSubscribers = \App\Models\Subscriber::where('reseller_id', $user->id)->count();
        $activeSubscribers = \App\Models\Subscriber::where('reseller_id', $user->id)->where('status', 'active')->count();

        // Recent subscribers
        $recentSubs = \App\Models\Subscriber::where('reseller_id', $user->id)
            ->with('router:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'username', 'full_name', 'profile', 'status', 'router_id', 'created_at']);

        // Group permissions by router
        $routerPermissions = [];
        foreach ($permissions as $perm) {
            $routerPermissions[$perm->router_id] = [
                'can_create_hotspot' => (bool)$perm->can_create_hotspot,
                'can_edit_hotspot' => (bool)$perm->can_edit_hotspot,
                'can_delete_hotspot' => (bool)$perm->can_delete_hotspot,
                'can_create_usermanager' => (bool)$perm->can_create_usermanager,
                'can_edit_usermanager' => (bool)$perm->can_edit_usermanager,
                'can_delete_usermanager' => (bool)$perm->can_delete_usermanager,
                'can_renew_usermanager' => (bool)$perm->can_renew_usermanager,
                'can_generate_vouchers' => (bool)$perm->can_generate_vouchers,
                'can_view_reports' => (bool)$perm->can_view_reports,
            ];
        }

        // Check global access
        $hasHotspotAccess = $permissions->contains(fn($p) => $p->can_create_hotspot || $p->can_edit_hotspot || $p->can_delete_hotspot);
        $hasUmAccess = $permissions->contains(fn($p) => $p->can_create_usermanager || $p->can_edit_usermanager || $p->can_renew_usermanager);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'balance' => (float)$user->balance,
                'commission_rate' => (float)($user->commission_rate ?? 0),
                'max_subscribers' => $user->max_subscribers,
                'company_name' => $user->company_name,
            ],
            'stats' => [
                'total_subscribers' => $totalSubscribers,
                'active_subscribers' => $activeSubscribers,
                'balance' => (float)$user->balance,
                'routers_count' => count($routerIds),
            ],
            'routers' => $routers,
            'permissions' => $routerPermissions,
            'access' => [
                'hotspot' => $hasHotspotAccess,
                'usermanager' => $hasUmAccess,
            ],
            'recent_subscribers' => $recentSubs,
        ]);
    }

    public function resellerSubscribers(Request $request)
    {
        $user = $this->user($request);
        if ($user->role !== 'reseller') {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $routerIds = $this->routerIds($user);

        $subscribers = \App\Models\Subscriber::whereIn('router_id', $routerIds)
            ->where('type', 'usermanager')
            ->with('router:id,name')
            ->orderByRaw('COALESCE(total_bytes, 0) DESC')
            ->get(['id', 'router_id', 'username', 'full_name', 'phone', 'status', 'profile',
                    'expiration_date', 'is_online', 'is_paid', 'total_bytes', 'data_limit',
                    'data_limit_gb', 'subscription_price', 'remaining_amount',
                    'whatsapp_number', 'is_throttled']);

        return response()->json([
            'success' => true,
            'subscribers' => $subscribers,
        ]);
    }

    public function resellerRenew(Request $request)

    {

        $user = $this->user($request);

        if ($user->role !== 'reseller') {

            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);

        }



        $request->validate([

            'subscriber_id' => 'required|integer',

        ]);



        $subscriber = \App\Models\Subscriber::findOrFail($request->subscriber_id);

        $routerIds = $this->routerIds($user);



        if (!in_array($subscriber->router_id, $routerIds)) {

            return response()->json(['success' => false, 'message' => 'غير مصرح بهذا الراوتر'], 403);

        }



        // Check permission

        $perm = \App\Models\ResellerRouterPermission::where('reseller_id', $user->id)

            ->where('router_id', $subscriber->router_id)->first();

        if (!$perm || !$perm->can_renew_usermanager) {

            return response()->json(['success' => false, 'message' => 'لا تملك صلاحية التجديد'], 403);

        }



        // Cost = subscription_price, fallback to service_plans price
        $cost = $subscriber->subscription_price ?? 0;
        if ($cost <= 0) {
            $plan = \App\Models\ServicePlan::where('router_id', $subscriber->router_id)
                ->whereRaw('LOWER(mikrotik_profile_name) = ?', [strtolower($subscriber->profile)])
                ->first();
            if ($plan && $plan->price > 0) {
                $cost = (float) $plan->price;
                // Update subscriber with the correct price
                $subscriber->subscription_price = $cost;
            }
        }

        if ($cost <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم تحديد سعر لهذه الباقة (' . $subscriber->profile . '). يرجى مراجعة المدير لتحديد الأسعار.',
            ], 400);
        }

        // Check balance

        if ($user->balance < $cost) {

            return response()->json([

                'success' => false,

                'message' => 'رصيدك غير كافي. المطلوب: ' . number_format($cost, 0) . ' ل.س، رصيدك: ' . number_format($user->balance, 0) . ' ل.س',

            ], 400);

        }



        // Deduct balance

        $user->balance -= $cost;

        $user->save();



        // Renew subscriber: 30 days from now, same profile, reset data

        $newExpiry = now()->addDays(30);

        $subscriber->expiration_date = $newExpiry;

        $subscriber->status = 'active';

        $subscriber->is_paid = true;

        $subscriber->remaining_amount = 0;

        // Reset usage data

        $subscriber->bytes_in = 0;

        $subscriber->bytes_out = 0;

        $subscriber->um_usage_offset = 0;
        $subscriber->total_bytes = 0;
        $subscriber->archived_bytes = 0;

        $subscriber->uptime_used = 0;

        $subscriber->usage_reset_at = now();

        $subscriber->save();



        // Sync to MikroTik: reset counters + enable + keep same profile

        try {

            $router = \App\Models\Router::find($subscriber->router_id);

            if ($router) {

                $api = new \App\Services\MikroTikService($router);



                if ($subscriber->type === 'usermanager' && $subscriber->mikrotik_id) {

                    // Reset user-manager user: enable, reset counters

                    $api->updateUserManagerUser($subscriber->mikrotik_id, [

                        'disabled' => 'no',

                    ]);



                    // Remove active sessions to force re-auth with fresh counters

                    try {

                        $sessions = $api->command([

                            '/user-manager/session/print',

                            '?user=' . $subscriber->username,

                        ]);

                        foreach ($sessions as $session) {

                            if (isset($session['.id'])) {

                                $api->command([

                                    '/user-manager/session/remove',

                                    '=.id=' . $session['.id'],

                                ]);

                            }

                        }

                    } catch (\Exception $e) {}

                } elseif ($subscriber->mikrotik_id) {

                    // Hotspot user

                    $api->command([

                        '/ip/hotspot/user/set',

                        '=.id=' . $subscriber->mikrotik_id,

                        '=disabled=no',

                    ]);

                }

            }

        } catch (\Exception $e) {

            \Log::error('Reseller renew MikroTik error: ' . $e->getMessage());

        }



        // Log activity

        try {

            \App\Models\ActivityLog::create([

                'user_id' => $user->id,

                'action' => 'subscriber_renew',

                'description' => 'تجديد اشتراك: ' . $subscriber->username . ' (' . $subscriber->profile . ') - ' . number_format($cost, 0) . ' ل.س',

                'router_id' => $subscriber->router_id,

                'model_type' => 'App\\Models\\Subscriber',

                'model_id' => $subscriber->id,

            ]);

        } catch (\Exception $e) {}



        // Create notification

        try {

            \App\Models\AdminNotification::notifyRenewal($user, $subscriber, $cost);

        } catch (\Exception $e) {}

            // Save/update phone if provided
            if ($request->phone && !empty(trim($request->phone))) {
                $subscriber->phone = $request->phone;
                $subscriber->save();
            }

            // Send SMS notification for renewal
            $smsSent = false;
            if ($subscriber->phone && !empty(trim($subscriber->phone))) {
                try {
                    $router = \App\Models\Router::find($subscriber->router_id);
                    if ($router) {
                        $smsService = new \App\Services\SmsService($router);
                        $dataLimitGB = $subscriber->data_limit_gb ?? 0;
                        $smsMessage = "تم تجديد اشتراكك بنجاح ✅\n"
                            . "📋 الباقة: " . ($subscriber->profile ?? 'غير محدد') . "\n"
                            . "📅 ينتهي في: " . $newExpiry->format('Y-m-d') . "\n"
                            . "📊 حد البيانات: " . ($dataLimitGB > 0 ? $dataLimitGB . ' GB' : 'غير محدود') . "\n"
                            . "شكراً لثقتك بنا 🙏";
                        $smsService->sendSms($subscriber->phone, $smsMessage, $subscriber->id, \App\Models\SmsLog::TYPE_MANUAL);
                        $smsSent = true;
                    }
                } catch (\Exception $smsEx) {
                    \Log::warning('Failed to send renewal SMS: ' . $smsEx->getMessage());
                }
            }



        return response()->json([

            'success' => true,

            'message' => 'تم التجديد بنجاح لمدة 30 يوم',

            'new_expiry' => $newExpiry->format('Y-m-d'),

            'new_balance' => (float)$user->balance,

            'cost' => (float)$cost,

            'profile' => $subscriber->profile,

            'subscriber_name' => $subscriber->full_name ?? $subscriber->username,

            'sms_sent' => $smsSent ?? false,
                'can_view_hotspot_password' => (bool) $user->can_view_hotspot_password,
        ]);

    }


        public function resellerHotspotProfiles(Request $request, $routerId)
    {
        $user = $this->user($request);
        if ($user->role !== 'reseller') {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $perm = \App\Models\ResellerRouterPermission::where('reseller_id', $user->id)
            ->where('router_id', $routerId)->first();
        if (!$perm || !$perm->can_create_hotspot) {
            return response()->json(['success' => false, 'message' => 'لا تملك صلاحية'], 403);
        }

        $router = \App\Models\Router::findOrFail($routerId);
        try {
            $api = new \App\Services\MikroTikService($router);
            $rawProfiles = $api->getHotspotProfiles();

            $profiles = collect($rawProfiles)->map(function ($p) {
                $name = $p['name'] ?? $p['.id'] ?? 'unknown';
                $rateLimit = $p['rate-limit'] ?? '';

                // Parse rate-limit (format: "upload/download" e.g. "2M/5M")
                $downloadSpeed = '';
                if (!empty($rateLimit)) {
                    $parts = explode('/', $rateLimit);
                    $downloadSpeed = count($parts) >= 2 ? trim($parts[1]) : trim($parts[0]);
                }

                // Format speed for display
                $speedDisplay = '';
                if (!empty($downloadSpeed)) {
                    $speedDisplay = str_replace(['k', 'K'], ' Kbps', $downloadSpeed);
                    $speedDisplay = str_replace(['m', 'M'], ' Mbps', $speedDisplay);
                    $speedDisplay = str_replace(['g', 'G'], ' Gbps', $speedDisplay);
                    // If no unit suffix was replaced, add raw
                    if ($speedDisplay === $downloadSpeed) {
                        $speedDisplay = $downloadSpeed;
                    }
                }

                return [
                    'name' => $name,
                    'rate_limit' => $rateLimit,
                    'download_speed' => $downloadSpeed,
                    'speed_display' => $speedDisplay ?: 'غير محدد',
                ];
            })->filter(function ($p) {
                return $p['name'] !== 'default' || true; // include all
            })->values();

            return response()->json(['success' => true, 'profiles' => $profiles]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'فشل الاتصال بالراوتر: ' . $e->getMessage()], 500);
        }
    }


    // ==================== RESELLER CREATE HOTSPOT ====================

    public function resellerCreateHotspot(Request $request)
    {
        $user = $this->user($request);
        if ($user->role !== 'reseller') {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $request->validate([
            'router_id' => 'required|integer',
            'data_limit_gb' => 'required|numeric|min:0.1',
            'profile' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
        ]);

        $routerId = $request->router_id;
        $perm = \App\Models\ResellerRouterPermission::where('reseller_id', $user->id)
            ->where('router_id', $routerId)->first();
        if (!$perm || !$perm->can_create_hotspot) {
            return response()->json(['success' => false, 'message' => 'لا تملك صلاحية'], 403);
        }

        $router = \App\Models\Router::findOrFail($routerId);
        $dataLimitGb = (float) $request->data_limit_gb;
        $pricePerGb = $router->price_per_gb ?? 0;
        $cost = $dataLimitGb * $pricePerGb;

        if ($user->balance < $cost) {
            return response()->json([
                'success' => false,
                'message' => 'رصيدك غير كافي. المطلوب: ' . number_format($cost, 0) . ' ل.س',
            ], 400);
        }

        // Username: 6 digits, Password: 3 digits
        $username = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $password = str_pad(mt_rand(0, 999), 3, '0', STR_PAD_LEFT);

        // Ensure username is unique
        $attempts = 0;
        while (\App\Models\Subscriber::where('username', $username)->where('router_id', $routerId)->exists() && $attempts < 10) {
            $username = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $attempts++;
        }

        // Profile from request or default
        $profile = $request->input('profile', 'default');

        // Calculate data limit in bytes for MikroTik (same as admin)
        $limitBytes = (int)($dataLimitGb * 1024 * 1024 * 1024);

        try {
            $service = new \App\Services\MikroTikService($router);

            // Same params as admin hotspotAddCard - no limit-uptime
            $userData = [
                'name' => $username,
                'password' => $password,
                'profile' => $profile,
                'comment' => 'MegaWiFi',
            ];
            if ($limitBytes > 0) {
                $userData['limit-bytes-total'] = $limitBytes;
            }

            $result = $service->addHotspotUser($userData);

            // Check for MikroTik error (same as admin)
            if (isset($result['!trap'])) {
                return response()->json(['success' => false, 'message' => 'خطأ من الراوتر: ' . ($result['!trap'][0]['message'] ?? 'Unknown')], 500);
            }

            // Save to DB (same fields as admin + reseller extras)
            $subscriber = \App\Models\Subscriber::create([
                'router_id' => $router->id,
                'username' => $username,
                'password' => $password,
                'profile' => $profile,
                'type' => 'hotspot',
                'status' => 'active',
                'limit_bytes_total' => $limitBytes > 0 ? $limitBytes : null,
                'data_limit_gb' => $dataLimitGb,
                'subscription_price' => $cost,
                'reseller_id' => $user->id,
                'is_paid' => true,
                'bytes_in' => 0,
                'bytes_out' => 0,
                'total_bytes' => 0,
                'archived_bytes' => 0,
                'expiration_date' => now()->addDays(30),
                'phone' => $request->phone,
            ]);

            // Deduct balance
            $user->balance -= $cost;
            $user->save();

            // Log activity
            try {
                \App\Models\ActivityLog::create([
                    'user_id' => $user->id,
                    'action' => 'hotspot_create',
                    'description' => 'إنشاء كرت هوتسبوت: ' . $username . ' (' . $dataLimitGb . ' GB) على راوتر ' . $router->name,
                    'router_id' => $routerId,
                    'model_type' => 'App\Models\Subscriber',
                    'model_id' => $subscriber->id,
                ]);
            } catch (\Exception $e) {}

            // Notification
            try {
                \App\Models\AdminNotification::notifyHotspotCard($user, $subscriber, $dataLimitGb, $cost);
            } catch (\Exception $e) {}

            // Send SMS with card details
            $smsSent = false;
            if ($request->phone && !empty(trim($request->phone))) {
                try {
                    $smsService = new \App\Services\SmsService($router);
                    $smsMessage = "مرحباً بك في خدمة الانترنت 🌐\n"
                        . "بيانات الاتصال:\n"
                        . "👤 المستخدم: " . $username . "\n"
                        . "🔑 كلمة المرور: " . $password . "\n"
                        . "📊 حد البيانات: " . $dataLimitGb . " GB\n"
                        . "نتمنى لك تجربة ممتعة! ✨";
                    $smsService->sendSms($request->phone, $smsMessage, $subscriber->id, \App\Models\SmsLog::TYPE_MANUAL);
                    $smsSent = true;
                } catch (\Exception $smsEx) {
                    \Log::warning('Failed to send hotspot SMS: ' . $smsEx->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء كرت الهوتسبوت بنجاح',
                'username' => $username,
                'password' => $password,
                'data_limit_gb' => $dataLimitGb,
                'days' => 30,
                'profile' => $profile,
                'cost' => (float)$cost,
                'new_balance' => (float)$user->balance,
                'router_name' => $router->name,
                'sms_sent' => $smsSent ?? false,
                'can_view_hotspot_password' => (bool) $user->can_view_hotspot_password,
            ]);
        } catch (\Exception $e) {
            \Log::error('Reseller hotspot create error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'فشل الإنشاء: ' . $e->getMessage()], 500);
        }
    }


    public function resellerOperations(Request $request)
    {
        $user = $this->user($request);
        if ($user->role !== 'reseller') {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $activities = \App\Models\ActivityLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'operations' => $activities->items(),
            'total' => $activities->total(),
            'current_page' => $activities->currentPage(),
            'last_page' => $activities->lastPage(),
        ]);
    }

    public function resellerSearchSubscriber(Request $request)
    {
        $user = $this->user($request);
        if ($user->role !== 'reseller') {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $phone = $request->input('phone', '');
        if (empty($phone)) {
            return response()->json(['success' => false, 'message' => 'أدخل رقم الهاتف'], 400);
        }

        // Get router IDs the reseller has permission for
        $routerIds = \App\Models\ResellerRouterPermission::where('reseller_id', $user->id)
            ->pluck('router_id')->toArray();

        $subscribers = \App\Models\Subscriber::whereIn('router_id', $routerIds)
            ->where('type', 'usermanager')
            ->where('phone', 'like', "%{$phone}%")
            ->with('router:id,name')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Enrich subscribers with plan price if subscription_price is 0
        $subscribers->each(function ($sub) {
            if (($sub->subscription_price ?? 0) <= 0) {
                $plan = \App\Models\ServicePlan::where('router_id', $sub->router_id)
                    ->whereRaw('LOWER(mikrotik_profile_name) = ?', [strtolower($sub->profile)])
                    ->first();
                if ($plan && $plan->price > 0) {
                    $sub->subscription_price = (float) $plan->price;
                }
            }
        });

        return response()->json([
            'success' => true,
            'subscribers' => $subscribers,
        ]);
    }

    // ==================== RESELLER IPTV ====================

    public function resellerIptvSearch(Request $request)
    {
        $user = $this->user($request);
        if (!$user || $user->role !== 'reseller') {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $phone = $request->input('phone');
        if (empty($phone)) {
            return response()->json(['success' => false, 'message' => 'أدخل رقم الهاتف'], 400);
        }

        // Get reseller's permitted router IDs
        $routerIds = \App\Models\ResellerRouterPermission::where('reseller_id', $user->id)
            ->pluck('router_id')->toArray();

        if (empty($routerIds)) {
            return response()->json(['success' => false, 'message' => 'لا توجد راوترات مرتبطة بحسابك'], 400);
        }

        $subscribers = \App\Models\Subscriber::where('phone', 'like', '%' . $phone . '%')
            ->whereIn('router_id', $routerIds)
            ->get();

        if ($subscribers->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'لا يوجد مشترك بهذا الرقم'], 404);
        }

        $results = [];
        foreach ($subscribers as $subscriber) {
            $results[] = [
                'id' => $subscriber->id,
                'username' => $subscriber->username,
                'full_name' => $subscriber->full_name ?? $subscriber->username,
                'phone' => $subscriber->phone,
                'profile' => $subscriber->profile,
                'status' => $subscriber->status,
                'iptv_enabled' => (bool) $subscriber->iptv_enabled,
                'type' => $subscriber->type,
            ];
        }

        return response()->json([
            'success' => true,
            'subscribers' => $results,
        ]);
    }

    public function resellerIptvToggle(Request $request)
    {
        $user = $this->user($request);
        if (!$user || $user->role !== 'reseller') {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $subscriberId = $request->input('subscriber_id');
        if (empty($subscriberId)) {
            return response()->json(['success' => false, 'message' => 'معرف المشترك مطلوب'], 400);
        }

        // Get reseller's permitted router IDs
        $routerIds = \App\Models\ResellerRouterPermission::where('reseller_id', $user->id)
            ->pluck('router_id')->toArray();

        $subscriber = \App\Models\Subscriber::where('id', $subscriberId)
            ->whereIn('router_id', $routerIds)
            ->first();

        if (!$subscriber) {
            return response()->json(['success' => false, 'message' => 'المشترك غير موجود'], 404);
        }

        $newStatus = !$subscriber->iptv_enabled;
        $subscriber->update(['iptv_enabled' => $newStatus]);

        // Create IPTV subscription if enabled and does not exist
        if ($newStatus && !$subscriber->iptvSubscription) {
            $iptvUsername = 'iptv_sub_' . $subscriber->id;
            $iptvPassword = \Illuminate\Support\Str::random(12);
            \App\Models\IptvSubscription::create([
                'subscriber_id' => $subscriber->id,
                'user_id' => null,
                'username' => $iptvUsername,
                'password' => $iptvPassword,
                'expires_at' => now()->addYear(),
                'is_active' => 1,
                'max_connections' => 2,
                'notes' => 'اشتراك IPTV - تم التفعيل من التطبيق',
            ]);
        }
        // Deactivate IPTV subscription if disabled
        if (!$newStatus && $subscriber->iptvSubscription) {
            $subscriber->iptvSubscription->update(['is_active' => 0]);
        }

        // Log activity
        \App\Models\ActivityLog::create([
            'user_id' => $user->id,
            'type' => 'iptv',
            'action' => $newStatus ? 'تفعيل IPTV' : 'إلغاء IPTV',
            'description' => ($newStatus ? 'تم تفعيل IPTV' : 'تم إلغاء IPTV') . ' للمشترك ' . ($subscriber->full_name ?? $subscriber->username),
            'subscriber_id' => $subscriber->id,
            'router_id' => $subscriber->router_id,
        ]);

        return response()->json([
            'success' => true,
            'iptv_enabled' => $newStatus,
            'message' => $newStatus ? 'تم تفعيل IPTV بنجاح ✅' : 'تم إلغاء IPTV ❌',
        ]);
    }



    // ==================== USER MANAGEMENT (Super Admin) ====================

    public function updateRouterPricing(Request $request)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin()) {
            return response()->json(["error" => "غير مصرح"], 403);
        }

        $request->validate([
            "prices" => "required|array",
            "prices.*.router_id" => "required|integer",
            "prices.*.price_per_gb" => "required|numeric|min:0",
        ]);

        $updated = 0;
        foreach ($request->prices as $item) {
            $router = \App\Models\Router::find($item["router_id"]);
            if ($router) {
                $router->price_per_gb = $item["price_per_gb"];
                $router->save();
                $updated++;
            }
        }

        return response()->json([
            "success" => true,
            "message" => "تم تحديث أسعار " . $updated . " راوتر بنجاح",
        ]);
    }

                public function listUsers(Request $request)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return response()->json(["error" => "\xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x85\xD8\xB5\xD8\xB1\xD8\xAD"], 403);
        }

        $query = User::with("routers");


        // Non-super_admin: only see themselves + their linked resellers
        if (!$user->isSuperAdmin()) {
            $myResellerIds = \DB::table('admin_reseller')
                ->where('admin_id', $user->id)
                ->pluck('reseller_id')->toArray();
            $allowedIds = array_merge([$user->id], $myResellerIds);
            $query->whereIn('id', $allowedIds);
        }

        if ($request->filled("role")) {
            $query->where("role", $request->role);
        }

        if ($request->filled("search")) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where("name", "like", "%{$search}%")
                  ->orWhere("email", "like", "%{$search}%");
            });
        }

        $users = $query->orderBy("name")->get();

        return response()->json([
            "users" => $users->map(function ($u) {
                return [
                    "id" => $u->id,
                    "name" => $u->name,
                    "email" => $u->email,
                    "role" => $u->role,
                    "role_label" => $u->role_label,
                    "phone" => $u->phone,
                    "company_name" => $u->company_name,
                    "balance" => (float) $u->balance,
                    "is_active" => $u->is_active,
                    "routers_count" => $u->routers->count(),
                    "routers" => $u->routers->map(function ($r) {
                        return ["id" => $r->id, "name" => $r->name];
                    })->values()->toArray(),
                    "expires_at" => $u->expires_at?->format("Y-m-d H:i"),
                    "remaining_days" => $u->expires_at ? (int)now()->diffInDays($u->expires_at, false) : null,
                    "created_at" => $u->created_at?->format("Y-m-d H:i"),
                    "last_login_at" => $u->last_login_at?->format("Y-m-d H:i"),
                    "sms_enabled" => (bool) $u->sms_enabled,
                    "linked_reseller_ids" => in_array($u->role, ["admin", "super_admin"]) ? \DB::table("admin_reseller")->where("admin_id", $u->id)->pluck("reseller_id")->toArray() : [],
                ];
            }),
        ]);
    }

    public function createUser(Request $request)
    {
        $currentUser = $this->user($request);
        if (!$currentUser->isSuperAdmin()) {
            return response()->json(["error" => "\xD9\x81\xD9\x82\xD8\xB7 \xD8\xA7\xD9\x84\xD9\x85\xD8\xAF\xD9\x8A\xD8\xB1 \xD8\xA7\xD9\x84\xD8\xB9\xD8\xA7\xD9\x85 \xD9\x8A\xD9\x85\xD9\x83\xD9\x86\xD9\x87 \xD8\xA5\xD9\x86\xD8\xB4\xD8\xA7\xD8\xA1 \xD9\x85\xD8\xB3\xD8\xAA\xD8\xAE\xD8\xAF\xD9\x85\xD9\x8A\xD9\x86"], 403);
        }

        $validated = $request->validate([
            "name" => "required|string|max:255",
            "email" => "required|string|email|max:255|unique:users",
            "password" => "required|string|min:6",
            "role" => "required|in:admin,reseller",
            "phone" => "nullable|string|max:20",
            "company_name" => "nullable|string|max:255",
            "expiration_days" => "nullable|integer|min:1",
        ]);

        $user = User::create([
            "name" => $validated["name"],
            "email" => $validated["email"],
            "password" => \Illuminate\Support\Facades\Hash::make($validated["password"]),
            "role" => $validated["role"],
            "phone" => $validated["phone"] ?? null,
            "company_name" => $validated["company_name"] ?? null,
            "is_active" => true,
            "parent_id" => $validated["role"] === "reseller" ? $currentUser->id : null,
        ]);

        return response()->json([
            "message" => "\xD8\xAA\xD9\x85 \xD8\xA5\xD9\x86\xD8\xB4\xD8\xA7\xD8\xA1 \xD8\xA7\xD9\x84\xD9\x85\xD8\xB3\xD8\xAA\xD8\xAE\xD8\xAF\xD9\x85 \xD8\xA8\xD9\x86\xD8\xAC\xD8\xA7\xD8\xAD",
            "user" => [
                "id" => $user->id,
                "name" => $user->name,
                "email" => $user->email,
                "role" => $user->role,
                "role_label" => $user->role_label,
                "is_active" => $user->is_active,
            ]
        ], 201);
    }

    public function toggleUser(Request $request, $id)
    {
        $currentUser = $this->user($request);
        if (!$currentUser->isSuperAdmin()) {
            return response()->json(["error" => "\xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x85\xD8\xB5\xD8\xB1\xD8\xAD"], 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $currentUser->id) {
            return response()->json(["error" => "\xD9\x84\xD8\xA7 \xD9\x8A\xD9\x85\xD9\x83\xD9\x86\xD9\x83 \xD8\xAA\xD8\xB9\xD8\xB7\xD9\x8A\xD9\x84 \xD8\xAD\xD8\xB3\xD8\xA7\xD8\xA8\xD9\x83 \xD8\xA7\xD9\x84\xD8\xAE\xD8\xA7\xD8\xB5"], 400);
        }

        $user->update(["is_active" => !$user->is_active]);

        $status = $user->is_active ? "\xD8\xAA\xD9\x81\xD8\xB9\xD9\x8A\xD9\x84" : "\xD8\xAA\xD8\xB9\xD8\xB7\xD9\x8A\xD9\x84";

        return response()->json([
            "message" => "\xD8\xAA\xD9\x85 {$status} \xD8\xA7\xD9\x84\xD9\x85\xD8\xB3\xD8\xAA\xD8\xAE\xD8\xAF\xD9\x85 \xD8\xA8\xD9\x86\xD8\xAC\xD8\xA7\xD8\xAD",
            "is_active" => $user->is_active,
        ]);
    }

    public function deleteUser(Request $request, $id)
    {
        $currentUser = $this->user($request);
        if (!$currentUser->isSuperAdmin()) {
            return response()->json(["error" => "\xD8\xBA\xD9\x8A\xD8\xB1 \xD9\x85\xD8\xB5\xD8\xB1\xD8\xAD"], 403);
        }

        $user = User::findOrFail($id);

        if ($user->id === $currentUser->id) {
            return response()->json(["error" => "\xD9\x84\xD8\xA7 \xD9\x8A\xD9\x85\xD9\x83\xD9\x86\xD9\x83 \xD8\xAD\xD8\xB0\xD9\x81 \xD8\xAD\xD8\xB3\xD8\xA7\xD8\xA8\xD9\x83 \xD8\xA7\xD9\x84\xD8\xAE\xD8\xA7\xD8\xB5"], 400);
        }

        $userName = $user->name;
        $user->delete();

        return response()->json([
            "message" => "\xD8\xAA\xD9\x85 \xD8\xAD\xD8\xB0\xD9\x81 \xD8\xA7\xD9\x84\xD9\x85\xD8\xB3\xD8\xAA\xD8\xAE\xD8\xAF\xD9\x85 {$userName} \xD8\xA8\xD9\x86\xD8\xAC\xD8\xA7\xD8\xAD",
        ]);
    }


    public function listAllRouters(Request $request)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return response()->json(["message" => "Unauthorized"], 403);
        }

        $routerIds = $this->routerIds($user);
        $routers = \App\Models\Router::whereIn("id", $routerIds)
            ->select("id", "name", "identity", "ip_address", "status", "price_per_gb")
            ->orderBy("name")
            ->get();

        return response()->json(["routers" => $routers]);
    }

    public function updateUser(Request $request, $id)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin()) {
            return response()->json(["message" => "Unauthorized"], 403);
        }

        $target = \App\Models\User::find($id);
        if (!$target) {
            return response()->json(["message" => "User not found"], 404);
        }

        $data = $request->only(["name", "email", "role", "phone", "company_name"]);
        
        // Handle expiration days
        if ($request->has("expiration_days")) {
            $days = (int)$request->input("expiration_days");
            $data["expires_at"] = $days > 0 ? \Carbon\Carbon::now()->addDays($days) : null;
        }
        if ($request->filled("password")) {
            $data["password"] = bcrypt($request->input("password"));
        }

        // Handle sms_enabled permission
        if ($request->has("sms_enabled")) {
            $target->sms_enabled = (bool) $request->input("sms_enabled");
        }

        $target->update($data);

        // Sync routers if provided
        if ($request->has("routers")) {
            $routerIds = $request->input("routers", []);
            $target->routers()->sync($routerIds);

            // Auto-create ResellerRouterPermission for resellers
            if ($target->role === "reseller") {
                \App\Models\ResellerRouterPermission::where("reseller_id", $target->id)
                    ->whereNotIn("router_id", $routerIds)
                    ->delete();

                foreach ($routerIds as $routerId) {
                    \App\Models\ResellerRouterPermission::firstOrCreate(
                        ["reseller_id" => $target->id, "router_id" => $routerId],
                        [
                            "can_create_hotspot" => true,
                            "can_edit_hotspot" => true,
                            "can_delete_hotspot" => true,
                            "can_enable_disable_hotspot" => true,
                            "can_create_ppp" => true,
                            "can_edit_ppp" => true,
                            "can_delete_ppp" => true,
                            "can_enable_disable_ppp" => true,
                            "can_create_usermanager" => true,
                            "can_edit_usermanager" => true,
                            "can_delete_usermanager" => true,
                            "can_renew_usermanager" => true,
                            "can_enable_disable_usermanager" => true,
                            "can_view_reports" => true,
                            "can_generate_vouchers" => true,
                        ]
                    );
                }
            }
        }

        return response()->json(["message" => "User updated successfully", "user" => $target->fresh()]);
    }


    /**
     * Reset device binding for a user (allows login from new device)
     */
    public function resetDevice(Request $request, $id)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $target = \App\Models\User::find($id);
        if (!$target) {
            return response()->json(['success' => false, 'message' => 'المستخدم غير موجود'], 404);
        }

        $target->device_id = null;
        $target->device_locked_at = null;
        $target->desktop_device_id = null;
        $target->desktop_device_locked_at = null;
        $target->api_token = null;
        $target->save();

        return response()->json([
            'success' => true,
            'message' => 'تم تصفير الجهاز بنجاح. يمكن للمستخدم تسجيل الدخول من جهاز جديد.',
        ]);
    }



    // ==================== WHATSAPP SETTINGS ====================
    
    public function getWhatsAppSettings(Request $request, $routerId)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return response()->json(["message" => "Unauthorized"], 403);
        }

        $router = \App\Models\Router::find($routerId);
        if (!$router) {
            return response()->json(["message" => "Router not found"], 404);
        }

        $shamcashQrUrl = null;
        if ($router->shamcash_qr) {
            $shamcashQrUrl = "https://megawifi.site/storage/" . $router->shamcash_qr;
        }

        return response()->json([
            "whatsapp_type" => $router->whatsapp_type ?? "regular",
            "brand_name" => $router->brand_name ?? "MegaWiFi",
            "wa_renewal_message" => $router->wa_renewal_message ?? "",
            "shamcash_qr_url" => $shamcashQrUrl,
            "price_per_gb" => $router->price_per_gb,
        ]);
    }

    public function updateWhatsAppSettings(Request $request, $routerId)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return response()->json(["message" => "Unauthorized"], 403);
        }

        $router = \App\Models\Router::find($routerId);
        if (!$router) {
            return response()->json(["message" => "Router not found"], 404);
        }

        $data = [];
        if ($request->has("whatsapp_type")) {
            $data["whatsapp_type"] = $request->input("whatsapp_type");
        }
        if ($request->has("brand_name")) {
            $data["brand_name"] = $request->input("brand_name");
        }
        if ($request->has("wa_renewal_message")) {
            $data["wa_renewal_message"] = $request->input("wa_renewal_message");
        }

        $router->update($data);

        return response()->json([
            "message" => "تم تحديث إعدادات الواتساب بنجاح",
            "whatsapp_type" => $router->whatsapp_type,
            "brand_name" => $router->brand_name,
            "wa_renewal_message" => $router->wa_renewal_message,
        ]);
    }

    public function uploadShamCashQr(Request $request, $routerId)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return response()->json(["message" => "Unauthorized"], 403);
        }

        $router = \App\Models\Router::find($routerId);
        if (!$router) {
            return response()->json(["message" => "Router not found"], 404);
        }

        if (!$request->hasFile("shamcash_qr")) {
            return response()->json(["message" => "No image provided"], 422);
        }

        $file = $request->file("shamcash_qr");
        
        if ($router->shamcash_qr) {
            \Illuminate\Support\Facades\Storage::disk("public")->delete($router->shamcash_qr);
        }

        $path = $file->store("shamcash-qr", "public");
        $router->update(["shamcash_qr" => $path]);

        return response()->json([
            "message" => "تم رفع صورة شام كاش بنجاح",
            "shamcash_qr_url" => "https://megawifi.site/storage/" . $path,
        ]);
    }

    public function deleteShamCashQr(Request $request, $routerId)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return response()->json(["message" => "Unauthorized"], 403);
        }

        $router = \App\Models\Router::find($routerId);
        if (!$router) {
            return response()->json(["message" => "Router not found"], 404);
        }

        if ($router->shamcash_qr) {
            \Illuminate\Support\Facades\Storage::disk("public")->delete($router->shamcash_qr);
            $router->update(["shamcash_qr" => null]);
        }

        return response()->json(["message" => "تم حذف صورة شام كاش"]);
    }


    // ==================== ADMIN-RESELLER LINKING ====================

    public function linkReseller(Request $request)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'فقط المدير العام يمكنه ربط الوكلاء'], 403);
        }

        $request->validate([
            'admin_id' => 'required|integer|exists:users,id',
            'reseller_id' => 'required|integer|exists:users,id',
        ]);

        $admin = \App\Models\User::find($request->admin_id);
        $reseller = \App\Models\User::find($request->reseller_id);

        if (!$admin || !in_array($admin->role, ['admin', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'المستخدم ليس مديراً'], 400);
        }
        if (!$reseller || $reseller->role !== 'reseller') {
            return response()->json(['success' => false, 'message' => 'المستخدم ليس وكيلاً'], 400);
        }

        \DB::table('admin_reseller')->insertOrIgnore([
            'admin_id' => $admin->id,
            'reseller_id' => $reseller->id,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم ربط الوكيل ' . $reseller->name . ' بالمدير ' . $admin->name,
        ]);
    }

    public function unlinkReseller(Request $request)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'فقط المدير العام يمكنه إلغاء ربط الوكلاء'], 403);
        }

        $request->validate([
            'admin_id' => 'required|integer',
            'reseller_id' => 'required|integer',
        ]);

        $deleted = \DB::table('admin_reseller')
            ->where('admin_id', $request->admin_id)
            ->where('reseller_id', $request->reseller_id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => $deleted ? 'تم إلغاء الربط' : 'لا يوجد ربط',
        ]);
    }

    public function resellerAdmins(Request $request)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $resellerId = $request->input('reseller_id');
        $links = \DB::table('admin_reseller')
            ->join('users', 'users.id', '=', 'admin_reseller.admin_id')
            ->where('admin_reseller.reseller_id', $resellerId)
            ->select('users.id', 'users.name', 'users.email')
            ->get();

        return response()->json(['success' => true, 'admins' => $links]);
    }

    public function adminResellers(Request $request)
    {
        $user = $this->user($request);
        if (!$user->isSuperAdmin() && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $adminId = $request->input('admin_id', $user->id);
        // Non-super_admin can only query their own links
        if (!$user->isSuperAdmin()) $adminId = $user->id;

        $links = \DB::table('admin_reseller')
            ->join('users', 'users.id', '=', 'admin_reseller.reseller_id')
            ->where('admin_reseller.admin_id', $adminId)
            ->select('users.id', 'users.name', 'users.email', 'users.balance', 'users.phone')
            ->get();

        return response()->json(['success' => true, 'resellers' => $links]);
    }

    public function syncResellers(Request $request)
    {
        $user = $request->attributes->get('api_user');
        if (!$user || $user->role !== 'super_admin') {
            return response()->json(['error' => 'Only super_admin can sync resellers'], 403);
        }

        $adminId = $request->input('admin_id');
        $resellerIds = $request->input('reseller_ids', []);

        if (!$adminId) {
            return response()->json(['error' => 'admin_id is required'], 422);
        }

        // Verify admin exists
        $admin = \DB::table('users')->where('id', $adminId)->whereIn('role', ['admin', 'super_admin'])->first();
        if (!$admin) {
            return response()->json(['error' => 'Admin not found'], 404);
        }

        // Delete existing links
        \DB::table('admin_reseller')->where('admin_id', $adminId)->delete();

        // Insert new links
        $inserted = 0;
        foreach ($resellerIds as $rid) {
            $reseller = \DB::table('users')->where('id', $rid)->where('role', 'reseller')->first();
            if ($reseller) {
                \DB::table('admin_reseller')->insert([
                    'admin_id' => $adminId,
                    'reseller_id' => $rid,
                    'created_at' => now(),
                ]);
                $inserted++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Synced $inserted resellers for admin $adminId",
        ]);
    }


    // ===== VPN Configuration API =====

    /**
     * Get VPN config for current user
     */
    public function getVpnConfig(Request $request)
    {
        try {
            $user = $request->attributes->get('api_user');
            $config = \App\Models\VpnConfig::where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد إعداد VPN'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'vpn' => [
                    'id' => $config->id,
                    'name' => $config->name,
                    'client_private_key' => $config->client_private_key,
                    'client_public_key' => $config->client_public_key,
                    'client_ip' => $config->client_ip,
                    'server_public_key' => $config->server_public_key,
                    'server_endpoint' => $config->server_endpoint,
                    'dns' => $config->dns,
                    'allowed_ips' => $config->allowed_ips,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin creates VPN config for a user
     */
    public function createVpnConfig(Request $request)
    {
        try {
            $user = $request->attributes->get('api_user');

            // Only super_admin can create VPN configs
            if ($user->role !== 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مسموح'
                ], 403);
            }

            $targetUserId = $request->input('user_id', $user->id);
            $name = $request->input('name', 'Mobile VPN');
            $dns = $request->input('dns', '8.8.8.8');
            $allowedIps = $request->input('allowed_ips', '10.10.0.0/24');

            // Check if user already has a config
            $existing = \App\Models\VpnConfig::where('user_id', $targetUserId)
                ->where('is_active', true)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'يوجد إعداد VPN مسبقاً لهذا المستخدم'
                ], 409);
            }

            // Get server WireGuard info
            $serverPublicKey = trim(shell_exec('wg show wg0 public-key 2>/dev/null') ?? '');
            $serverPort = trim(shell_exec("wg show wg0 listen-port 2>/dev/null") ?? '51820');
            $serverEndpoint = config('wireguard.endpoint', '152.53.128.114:51820');

            // Use correct endpoint with actual port
            $endpointParts = explode(':', $serverEndpoint);
            $serverIp = $endpointParts[0];
            $serverEndpoint = $serverIp . ':' . $serverPort;

            if (empty($serverPublicKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'WireGuard غير مفعل على السيرفر'
                ], 500);
            }

            // Generate client keys using wg command
            $clientPrivateKey = trim(shell_exec('wg genkey 2>/dev/null') ?? '');
            $clientPublicKey = trim(shell_exec("echo '$clientPrivateKey' | wg pubkey 2>/dev/null") ?? '');

            if (empty($clientPrivateKey) || empty($clientPublicKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل توليد المفاتيح'
                ], 500);
            }

            // Find next available IP in 10.10.0.x range
            $usedIps = \App\Models\VpnConfig::pluck('client_ip')
                ->map(fn($ip) => explode('/', $ip)[0])
                ->toArray();

            // Also get router WG IPs
            $routerIps = \App\Models\Router::whereNotNull('wg_client_ip')
                ->pluck('wg_client_ip')
                ->toArray();

            $allUsedIps = array_merge($usedIps, $routerIps);

            $clientIp = null;
            // Start from 10.10.0.100 for mobile clients (routers use lower IPs)
            for ($i = 100; $i <= 254; $i++) {
                $testIp = "10.10.0.$i";
                if (!in_array($testIp, $allUsedIps)) {
                    $clientIp = "$testIp/32";
                    break;
                }
            }

            if (!$clientIp) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد عناوين IP متاحة'
                ], 500);
            }

            // Add peer to server's WireGuard
            $addPeerCmd = "wg set wg0 peer '$clientPublicKey' allowed-ips '" . explode('/', $clientIp)[0] . "/32' 2>&1";
            $peerResult = shell_exec($addPeerCmd);

            // Save WireGuard config
            shell_exec('wg-quick save wg0 2>/dev/null');

            // Save to database
            $vpnConfig = \App\Models\VpnConfig::create([
                'user_id' => $targetUserId,
                'name' => $name,
                'client_private_key' => $clientPrivateKey,
                'client_public_key' => $clientPublicKey,
                'client_ip' => $clientIp,
                'server_public_key' => $serverPublicKey,
                'server_endpoint' => $serverEndpoint,
                'dns' => $dns,
                'allowed_ips' => $allowedIps,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء إعداد VPN بنجاح',
                'vpn' => [
                    'id' => $vpnConfig->id,
                    'name' => $vpnConfig->name,
                    'client_ip' => $vpnConfig->client_ip,
                    'client_public_key' => $vpnConfig->client_public_key,
                    'server_endpoint' => $vpnConfig->server_endpoint,
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('VPN create error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin deletes VPN config
     */
    public function deleteVpnConfig(Request $request, $id)
    {
        try {
            $user = $request->attributes->get('api_user');

            if ($user->role !== 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مسموح'
                ], 403);
            }

            $config = \App\Models\VpnConfig::findOrFail($id);

            // Remove peer from WireGuard
            $publicKey = $config->client_public_key;
            shell_exec("wg set wg0 peer '$publicKey' remove 2>/dev/null");
            shell_exec('wg-quick save wg0 2>/dev/null');

            $config->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف إعداد VPN'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin lists all VPN configs
     */
    public function listVpnConfigs(Request $request)
    {
        try {
            $user = $request->attributes->get('api_user');

            if ($user->role !== 'super_admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'غير مسموح'
                ], 403);
            }

            $configs = \App\Models\VpnConfig::with('user:id,name,email')
                ->get()
                ->map(function ($config) {
                    return [
                        'id' => $config->id,
                        'user_id' => $config->user_id,
                        'user_name' => $config->user->name ?? 'N/A',
                        'user_email' => $config->user->email ?? 'N/A',
                        'name' => $config->name,
                        'client_ip' => $config->client_ip,
                        'is_active' => $config->is_active,
                        'created_at' => $config->created_at->format('Y-m-d H:i'),
                    ];
                });

            return response()->json([
                'success' => true,
                'configs' => $configs
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Setup WireGuard on a specific router and create a peer for mobile access.
     */
    public function setupRouterWireguard(Request $request, $routerId)
    {
        try {
            $user = $request->attributes->get('api_user');
            if (!in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
            }

            $router = \App\Models\Router::findOrFail($routerId);
            $api = $this->connectToRouterApi($router);
            if (!$api) {
                return response()->json(['success' => false, 'message' => 'لا يمكن الاتصال بالراوتر'], 500);
            }

            $address = $request->input('address', '10.10.10.1/24');
            $port = $request->input('port', 51820);

            $existing = $api->comm(['/interface/wireguard/print']);
            if (!empty($existing) && !(isset($existing[0][0]) && $existing[0][0] === '!trap')) {
                $api->disconnect();
                return response()->json(['success' => false, 'message' => 'WireGuard موجود مسبقاً']);
            }

            $api->comm(['/interface/wireguard/add', '=name=wireguard1', '=listen-port=' . $port, '=mtu=1420']);
            $api->comm(['/ip/address/add', '=address=' . $address, '=interface=wireguard1']);
            $api->comm(['/ip/firewall/filter/add', '=chain=input', '=protocol=udp', '=dst-port=' . $port, '=action=accept', '=comment=WireGuard VPN', '=place-before=0']);

            $network = explode('/', $address)[0];
            $networkPrefix = implode('.', array_slice(explode('.', $network), 0, 3)) . '.0/24';
            $api->comm(['/ip/firewall/nat/add', '=chain=srcnat', '=src-address=' . $networkPrefix, '=action=masquerade', '=comment=WireGuard NAT']);

            $api->disconnect();
            return response()->json(['success' => true, 'message' => 'تم إعداد WireGuard بنجاح']);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Router WG setup: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get WireGuard status for a specific router.
     */
    public function routerWireguardStatus(Request $request, $routerId)
    {
        try {
            $user = $request->attributes->get('api_user');
            if (!in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
            }

            $router = \App\Models\Router::findOrFail($routerId);
            $api = $this->connectToRouterApi($router);
            if (!$api) {
                return response()->json(['success' => false, 'active' => false, 'message' => 'لا يمكن الاتصال بالراوتر']);
            }

            $interfaces = $api->comm(['/interface/wireguard/print']);
            if (empty($interfaces) || (isset($interfaces[0][0]) && $interfaces[0][0] === '!trap')) {
                $api->disconnect();
                return response()->json(['success' => true, 'active' => false, 'message' => 'WireGuard غير مفعل']);
            }

            $wgInterface = $interfaces[0] ?? [];
            $publicKey = $wgInterface['public-key'] ?? '';
            $listenPort = $wgInterface['listen-port'] ?? 51820;
            $wgName = $wgInterface['name'] ?? 'wireguard1';

            $publicIp = $router->ip_address;
            try {
                $cloud = $api->comm(['/ip/cloud/print']);
                if (!empty($cloud) && isset($cloud[0]['public-address'])) {
                    $publicIp = $cloud[0]['public-address'];
                }
            } catch (\Exception $e) {}

            $peers = $api->comm(['/interface/wireguard/peers/print', '?interface=' . $wgName]);
            if (!is_array($peers) || (isset($peers[0][0]) && $peers[0][0] === '!trap')) {
                $peers = [];
            }
            $api->disconnect();

            return response()->json([
                'success' => true,
                'active' => true,
                'interface' => $wgName,
                'public_key' => $publicKey,
                'listen_port' => $listenPort,
                'endpoint' => $publicIp . ':' . $listenPort,
                'peers_count' => count($peers)
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'active' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a WireGuard peer on a router for mobile access and return config.
     */
    public function addRouterWireguardPeer(Request $request, $routerId)
    {
        try {
            $user = $request->attributes->get('api_user');
            if (!in_array($user->role, ['super_admin', 'admin'])) {
                return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
            }

            $router = \App\Models\Router::findOrFail($routerId);
            $api = $this->connectToRouterApi($router);
            if (!$api) {
                return response()->json(['success' => false, 'message' => 'لا يمكن الاتصال بالراوتر'], 500);
            }

            $peerName = $request->input('name', 'MegaWiFi-Mobile-' . $user->id);
            $peerIp = $request->input('ip', '10.10.10.2/32');

            $interfaces = $api->comm(['/interface/wireguard/print']);
            if (empty($interfaces) || (isset($interfaces[0][0]) && $interfaces[0][0] === '!trap')) {
                $api->disconnect();
                return response()->json(['success' => false, 'message' => 'WireGuard غير مفعل على هذا الراوتر']);
            }

            $wgInterface = $interfaces[0] ?? [];
            $serverPublicKey = $wgInterface['public-key'] ?? '';
            $listenPort = $wgInterface['listen-port'] ?? 51820;
            $wgName = $wgInterface['name'] ?? 'wireguard1';

            // Generate keys via temp WireGuard interface on router
            $tempName = 'wg_temp_' . time();
            $api->comm(['/interface/wireguard/add', '=name=' . $tempName]);
            $tempInterfaces = $api->comm(['/interface/wireguard/print', '?name=' . $tempName]);
            $peerPrivateKey = '';
            $peerPublicKey = '';

            if (!empty($tempInterfaces) && isset($tempInterfaces[0])) {
                $peerPrivateKey = $tempInterfaces[0]['private-key'] ?? '';
                $peerPublicKey = $tempInterfaces[0]['public-key'] ?? '';
                if (isset($tempInterfaces[0]['.id'])) {
                    $api->comm(['/interface/wireguard/remove', '=.id=' . $tempInterfaces[0]['.id']]);
                }
            }

            if (empty($peerPrivateKey) || empty($peerPublicKey)) {
                $key = random_bytes(32);
                $key[0] = chr(ord($key[0]) & 248);
                $key[31] = chr((ord($key[31]) & 127) | 64);
                $peerPrivateKey = base64_encode($key);
                if (function_exists('sodium_crypto_scalarmult_base')) {
                    $peerPublicKey = base64_encode(sodium_crypto_scalarmult_base(base64_decode($peerPrivateKey)));
                }
            }

            $api->comm([
                '/interface/wireguard/peers/add',
                '=interface=' . $wgName,
                '=public-key=' . $peerPublicKey,
                '=allowed-address=' . $peerIp,
                '=comment=' . $peerName
            ]);

            $publicIp = $router->ip_address;
            try {
                $cloud = $api->comm(['/ip/cloud/print']);
                if (!empty($cloud) && isset($cloud[0]['public-address'])) {
                    $publicIp = $cloud[0]['public-address'];
                }
            } catch (\Exception $e) {}

            $addresses = $api->comm(['/ip/address/print', '?interface=' . $wgName]);
            $dnsServer = '8.8.8.8';
            if (!empty($addresses) && isset($addresses[0])) {
                $dnsServer = explode('/', $addresses[0]['address'] ?? '8.8.8.8')[0];
            }

            $api->disconnect();

            $endpoint = $publicIp . ':' . $listenPort;
            $configText = "[Interface]\nPrivateKey = {$peerPrivateKey}\nAddress = {$peerIp}\nDNS = {$dnsServer}\n\n[Peer]\nPublicKey = {$serverPublicKey}\nAllowedIPs = 0.0.0.0/0\nEndpoint = {$endpoint}\nPersistentKeepalive = 25\n";

            return response()->json([
                'success' => true,
                'config' => $configText,
                'router_id' => (int)$routerId,
                'router_name' => $router->name,
                'client_private_key' => $peerPrivateKey,
                'client_public_key' => $peerPublicKey,
                'client_ip' => $peerIp,
                'server_public_key' => $serverPublicKey,
                'server_endpoint' => $endpoint,
                'dns' => $dnsServer,
                'allowed_ips' => '0.0.0.0/0'
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Router WG add peer: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Helper: Connect to router via MikroTik API
     */
    private function connectToRouterApi($router)
    {
        try {
            $connectionIP = $router->wg_enabled && $router->wg_client_ip
                ? $router->wg_client_ip
                : $router->ip_address;
            $api = new \App\Services\MikroTikAPI(
                $connectionIP,
                $router->api_port ?? 8728,
                $router->api_username ?? 'admin',
                $router->api_password ?? ''
            );
            $api->connect();
            return $api;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Router API connect fail: ' . $e->getMessage());
            return null;
        }
    }


        public function batchAddDays(Request $request)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $ids = $request->input('ids', []);
        $days = (int) $request->input('days', 0);

        if (empty($ids) || !is_array($ids)) {
            return response()->json(['success' => false, 'message' => 'لم يتم تحديد مشتركين'], 400);
        }
        if ($days <= 0 || $days > 365) {
            return response()->json(['success' => false, 'message' => 'عدد الأيام غير صالح (1-365)'], 400);
        }

        $subscribers = Subscriber::whereIn('id', $ids)
            ->whereIn('router_id', $routerIds)
            ->get();

        if ($subscribers->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'لم يتم العثور على مشتركين'], 404);
        }

        $renewed = 0;
        $errors = [];

        // Group by router for efficiency (one connection per router)
        $grouped = $subscribers->groupBy('router_id');

        foreach ($grouped as $routerId => $group) {
            $router = $group->first()->router;
            $service = null;
            $mkService = null;

            try {
                if ($router && $group->first()->type === 'usermanager') {
                    $service = new \App\Services\UserManagerService($router);
                    $service->connect();
                }
            } catch (\Exception $e) {
                $errors[] = $router ? $router->name . ': اتصال - ' . $e->getMessage() : $e->getMessage();
                $service = null;
            }

            foreach ($group as $subscriber) {
                try {
                    $profileName = $subscriber->original_profile ?? $subscriber->profile;

                    // Step 1: Renew on MikroTik (same as individual renewal)
                    if ($service && $subscriber->mikrotik_id && $subscriber->type === 'usermanager') {
                        // renewUser: deletes old user-profile + adds new one with same profile
                        $service->renewUser(
                            $subscriber->username,
                            $profileName,
                            null,
                            $days
                        );

                        // Re-enable if disabled
                        try {
                            $service->toggleUserStatus($subscriber->mikrotik_id, false);
                        } catch (\Exception $e) {
                            // ignore re-enable errors
                        }
                    }

                    // Step 2: Update database (same as individual renewal)
                    $newExpiration = \Carbon\Carbon::now()->addDays($days);
                    
                    $subscriber->forceFill([
                        'expiration_date' => $newExpiration,
                        'status' => 'active',
                        'stopped_at' => null,
                        'stop_reason' => null,
                        'is_throttled' => false,
                        'throttled_at' => null,
                    ]);
                    $subscriber->save();
                    $renewed++;

                    \Illuminate\Support\Facades\Log::info("BATCH-ADD-DAYS: Renewed {$subscriber->username} with {$days} days, new expiry: {$newExpiration}");
                } catch (\Exception $e) {
                    $errors[] = $subscriber->username . ': ' . $e->getMessage();
                    \Illuminate\Support\Facades\Log::warning("BATCH-ADD-DAYS error for {$subscriber->username}: " . $e->getMessage());
                }
            }

            if ($service) {
                try { $service->disconnect(); } catch (\Exception $e) {}
            }
        }

        $msg = "تم تجديد {$renewed} مشترك بـ {$days} يوم";
        if (!empty($errors)) {
            $msg .= ' (تحذيرات: ' . implode(', ', array_slice($errors, 0, 3)) . ')';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'renewed_count' => $renewed,
            'days_added' => $days,
            'total_requested' => count($ids),
        ]);
    }
    public function batchDisable(Request $request)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $ids = $request->input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return response()->json(['success' => false, 'message' => 'لم يتم تحديد مشتركين'], 400);
        }

        $subscribers = Subscriber::whereIn('id', $ids)
            ->whereIn('router_id', $routerIds)
            ->where('status', 'active')
            ->get();

        if ($subscribers->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'لم يتم العثور على مشتركين نشطين'], 404);
        }

        $disabled = 0;
        $errors = [];

        // Group by router for efficiency
        $grouped = $subscribers->groupBy('router_id');

        foreach ($grouped as $routerId => $group) {
            $router = $group->first()->router;
            $service = null;

            try {
                if ($router && $group->first()->type === 'usermanager') {
                    $service = new \App\Services\UserManagerService($router);
                    $service->connect();
                }
            } catch (\Exception $e) {
                $errors[] = $router ? $router->name . ': ' . $e->getMessage() : $e->getMessage();
                $service = null;
            }

            foreach ($group as $subscriber) {
                try {
                    if ($service && $subscriber->mikrotik_id) {
                        $service->toggleUserStatus($subscriber->mikrotik_id, true);
                    }
                } catch (\Exception $e) {
                    $errors[] = $subscriber->username . ': ' . $e->getMessage();
                }

                $subscriber->status = 'disabled';
                $subscriber->stopped_at = now();
                $subscriber->stop_reason = 'تعطيل جماعي من التطبيق';
                $subscriber->save();
                $disabled++;
            }

            if ($service) {
                try { $service->disconnect(); } catch (\Exception $e) {}
            }
        }

        $msg = "تم تعطيل {$disabled} مشترك";
        if (!empty($errors)) {
            $msg .= ' (تحذيرات: ' . implode(', ', array_slice($errors, 0, 3)) . ')';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'disabled_count' => $disabled,
            'total_requested' => count($ids),
        ]);
    }

    public function batchEnable(Request $request)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        $ids = $request->input('ids', []);
        if (empty($ids) || !is_array($ids)) {
            return response()->json(['success' => false, 'message' => 'لم يتم تحديد مشتركين'], 400);
        }

        $subscribers = Subscriber::whereIn('id', $ids)
            ->whereIn('router_id', $routerIds)
            ->where('status', '!=', 'active')
            ->get();

        if ($subscribers->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'لم يتم العثور على مشتركين معطلين'], 404);
        }

        $enabled = 0;
        $errors = [];

        $grouped = $subscribers->groupBy('router_id');

        foreach ($grouped as $routerId => $group) {
            $router = $group->first()->router;
            $service = null;

            try {
                if ($router && $group->first()->type === 'usermanager') {
                    $service = new \App\Services\UserManagerService($router);
                    $service->connect();
                }
            } catch (\Exception $e) {
                $errors[] = $router ? $router->name . ': ' . $e->getMessage() : $e->getMessage();
                $service = null;
            }

            foreach ($group as $subscriber) {
                try {
                    if ($service && $subscriber->mikrotik_id) {
                        $service->toggleUserStatus($subscriber->mikrotik_id, false);
                    }
                } catch (\Exception $e) {
                    $errors[] = $subscriber->username . ': ' . $e->getMessage();
                }

                $subscriber->status = 'active';
                $subscriber->stopped_at = null;
                $subscriber->stop_reason = null;
                $subscriber->save();
                $enabled++;
            }

            if ($service) {
                try { $service->disconnect(); } catch (\Exception $e) {}
            }
        }

        $msg = "تم تفعيل {$enabled} مشترك";
        if (!empty($errors)) {
            $msg .= ' (تحذيرات: ' . implode(', ', array_slice($errors, 0, 3)) . ')';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'enabled_count' => $enabled,
            'total_requested' => count($ids),
        ]);
    }

    // ===== ZeroTier Configuration API =====

    /**
     * Setup ZeroTier on a router
     */
    public function setupRouterZerotier(Request $request, $routerId)
    {
        try {
            $user = $this->user($request);
            if ($user->role !== 'super_admin') {
                return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
            }

            $router = \App\Models\Router::findOrFail($routerId);

            $request->validate([
                'network_id' => 'required|string|size:16',
                'zt_ip' => 'required|ip',
                'member_id' => 'nullable|string',
            ]);

            $router->update([
                'connection_type' => 'zerotier',
                'zt_network_id' => $request->network_id,
                'zt_member_id' => $request->member_id,
                'zt_ip' => $request->zt_ip,
                'zt_enabled' => true,
                'zt_last_seen' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تفعيل ZeroTier بنجاح',
                'data' => [
                    'network_id' => $router->zt_network_id,
                    'zt_ip' => $router->zt_ip,
                    'connection_type' => 'zerotier',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إعداد ZeroTier: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ZeroTier status for a router
     */
    public function routerZerotierStatus(Request $request, $routerId)
    {
        try {
            $router = \App\Models\Router::findOrFail($routerId);

            return response()->json([
                'success' => true,
                'data' => [
                    'zt_enabled' => $router->zt_enabled,
                    'zt_network_id' => $router->zt_network_id,
                    'zt_member_id' => $router->zt_member_id,
                    'zt_ip' => $router->zt_ip,
                    'zt_connected' => $router->zt_connected,
                    'zt_last_seen' => $router->zt_last_seen?->toDateTimeString(),
                    'connection_method' => $router->connection_method,
                    'effective_ip' => $router->effective_ip,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disable ZeroTier on a router
     */
    public function disableRouterZerotier(Request $request, $routerId)
    {
        try {
            $user = $this->user($request);
            if ($user->role !== 'super_admin') {
                return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
            }

            $router = \App\Models\Router::findOrFail($routerId);
            $router->update([
                'zt_enabled' => false,
                'connection_type' => $router->wg_enabled ? 'wireguard' : 'direct',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تعطيل ZeroTier',
                'data' => [
                    'connection_method' => $router->fresh()->connection_method,
                    'effective_ip' => $router->fresh()->effective_ip,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update ZeroTier IP (heartbeat from router)
     */
    public function updateZerotierHeartbeat(Request $request, $routerId)
    {
        try {
            $router = \App\Models\Router::findOrFail($routerId);

            $router->update([
                'zt_last_seen' => now(),
                'zt_ip' => $request->input('zt_ip', $router->zt_ip),
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false], 500);
        }
    }


    /**
     * إنشاء نسخة احتياطية للراوتر وإرجاع معلوماتها
     */
    public function createRouterBackup(Request $request, $routerId)
    {
        try {
            $router = Router::findOrFail($routerId);
            
            // تحقق من الصلاحيات
            $user = $this->user($request);
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                $assignedRouters = $user->routers->pluck('id')->toArray();
                if (!in_array($router->id, $assignedRouters)) {
                    return response()->json(['message' => 'غير مصرح'], 403);
                }
            }

            $service = new \App\Services\MikroTikService($router);
            if (!$service->connect()) {
                return response()->json(['message' => 'فشل الاتصال بالراوتر'], 500);
            }

            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $router->name);
            $date = now()->format('Y-m-d_His');
            $backupName = "megawifi_{$safeName}_{$date}";

            // إنشاء النسخة الاحتياطية على الراوتر
            $result = $service->command([
                '/system/backup/save',
                '=name=' . $backupName,
                '=dont-encrypt=yes'
            ]);

            // انتظار إنشاء الملف
            sleep(3);

            // التحقق من وجود الملف
            $fileInfo = $service->command([
                '/file/print',
                '?name=' . $backupName . '.backup'
            ]);

            // إنشاء export نصي أيضاً
            $exportResult = $service->command(['/export']);
            $exportText = '';
            if (!empty($exportResult)) {
                // بعض الإصدارات ترجع النتيجة بشكل مختلف
                if (isset($exportResult[0]['ret'])) {
                    $exportText = $exportResult[0]['ret'];
                } elseif (isset($exportResult['!done'])) {
                    $exportText = $exportResult['!done'];
                }
            }

            // حفظ على السيرفر
            $dir = "backups/routers/{$router->id}";
            \Illuminate\Support\Facades\Storage::disk('local')->makeDirectory($dir);

            // حفظ معلومات النسخة
            $backupInfo = [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'backup_date' => now()->toIso8601String(),
                'backup_file' => $backupName . '.backup',
                'file_size' => isset($fileInfo[0]['size']) ? $fileInfo[0]['size'] : 'unknown',
                'status' => 'on_router',
                'created_by' => $user->name,
            ];

            \Illuminate\Support\Facades\Storage::disk('local')->put(
                "{$dir}/{$backupName}.info",
                json_encode($backupInfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            // حفظ export على السيرفر
            if ($exportText) {
                \Illuminate\Support\Facades\Storage::disk('local')->put(
                    "{$dir}/{$backupName}.rsc",
                    $exportText
                );
                $backupInfo['export_saved'] = true;
            }

            $service->disconnect();

            return response()->json([
                'success' => true,
                'message' => 'تم إنشاء النسخة الاحتياطية بنجاح',
                'backup' => $backupInfo
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Backup error for router {$routerId}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * تحميل ملف export النصي للراوتر
     */
    public function downloadRouterExport(Request $request, $routerId)
    {
        try {
            $router = Router::findOrFail($routerId);

            // تحقق من الصلاحيات
            $user = $this->user($request);
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                $assignedRouters = $user->routers->pluck('id')->toArray();
                if (!in_array($router->id, $assignedRouters)) {
                    return response()->json(['message' => 'غير مصرح'], 403);
                }
            }

            $service = new \App\Services\MikroTikService($router);
            if (!$service->connect()) {
                return response()->json(['message' => 'فشل الاتصال بالراوتر'], 500);
            }

            // إنشاء export نصي مباشر
            $exportResult = $service->command(['/export']);
            $exportText = '';

            if (!empty($exportResult)) {
                if (isset($exportResult[0]['ret'])) {
                    $exportText = $exportResult[0]['ret'];
                } elseif (is_string($exportResult)) {
                    $exportText = $exportResult;
                }
            }

            // إذا فشل export العادي، نجمع الإعدادات يدوياً
            if (empty($exportText)) {
                $exportText = $this->generateManualExport($service, $router);
            }

            $service->disconnect();

            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $router->name);
            $fileName = "export_{$safeName}_" . now()->format('Y-m-d_His') . ".rsc";

            return response()->json([
                'success' => true,
                'filename' => $fileName,
                'content' => $exportText,
                'size' => strlen($exportText),
                'router_name' => $router->name,
                'date' => now()->toIso8601String()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * جمع الإعدادات المهمة يدوياً من الراوتر
     */
    private function generateManualExport(\App\Services\MikroTikService $service, Router $router): string
    {
        $export = "# MegaWiFi Manual Export\n";
        $export .= "# Router: {$router->name}\n";
        $export .= "# Date: " . now()->toIso8601String() . "\n\n";

        // PPP Secrets
        $secrets = $service->command(['/ppp/secret/print']);
        if (!empty($secrets)) {
            $export .= "# === PPP Secrets ({count} users) ===\n";
            $export = str_replace('{count}', count($secrets), $export);
            foreach ($secrets as $s) {
                if (isset($s['!trap'])) continue;
                $name = $s['name'] ?? '';
                $password = $s['password'] ?? '';
                $profile = $s['profile'] ?? 'default';
                $disabled = isset($s['disabled']) && $s['disabled'] === 'true' ? ' disabled=yes' : '';
                $comment = isset($s['comment']) ? " comment=\"{$s['comment']}\"" : '';
                $export .= "/ppp secret add name=\"{$name}\" password=\"{$password}\" profile=\"{$profile}\"{$disabled}{$comment}\n";
            }
        }

        // PPP Profiles
        $profiles = $service->command(['/ppp/profile/print']);
        if (!empty($profiles)) {
            $export .= "\n# === PPP Profiles ===\n";
            foreach ($profiles as $p) {
                if (isset($p['!trap']) || ($p['name'] ?? '') === 'default' || ($p['name'] ?? '') === 'default-encryption') continue;
                $name = $p['name'] ?? '';
                $rateLimit = isset($p['rate-limit']) ? " rate-limit=\"{$p['rate-limit']}\"" : '';
                $localAddress = isset($p['local-address']) ? " local-address={$p['local-address']}" : '';
                $remoteAddress = isset($p['remote-address']) ? " remote-address={$p['remote-address']}" : '';
                $export .= "/ppp profile add name=\"{$name}\"{$rateLimit}{$localAddress}{$remoteAddress}\n";
            }
        }

        // UserManager Users
        try {
            $umUsers = $service->command(['/user-manager/user/print']);
            if (!empty($umUsers)) {
                $export .= "\n# === UserManager Users ({$count} users) ===\n";
                $export = str_replace('{$count}', count($umUsers), $export);
                foreach ($umUsers as $u) {
                    if (isset($u['!trap'])) continue;
                    $name = $u['name'] ?? '';
                    $password = $u['password'] ?? '';
                    $group = $u['group'] ?? '';
                    $disabled = isset($u['disabled']) && $u['disabled'] === 'true' ? ' disabled=yes' : '';
                    $export .= "/user-manager user add name=\"{$name}\" password=\"{$password}\" group=\"{$group}\"{$disabled}\n";
                }
            }
        } catch (\Exception $e) {
            $export .= "\n# UserManager: N/A\n";
        }

        // IP Addresses
        $addresses = $service->command(['/ip/address/print']);
        if (!empty($addresses)) {
            $export .= "\n# === IP Addresses ===\n";
            foreach ($addresses as $a) {
                if (isset($a['!trap'])) continue;
                $addr = $a['address'] ?? '';
                $iface = $a['interface'] ?? '';
                $export .= "/ip address add address={$addr} interface={$iface}\n";
            }
        }

        // DNS
        $dns = $service->command(['/ip/dns/print']);
        if (!empty($dns[0])) {
            $servers = $dns[0]['servers'] ?? '';
            $export .= "\n# === DNS ===\n";
            $export .= "/ip dns set servers={$servers}\n";
        }

        return $export;
    }

    /**
     * قائمة النسخ الاحتياطية المتوفرة لراوتر
     */
    public function listRouterBackups(Request $request, $routerId)
    {
        try {
            $router = Router::findOrFail($routerId);

            $user = $this->user($request);
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                $assignedRouters = $user->routers->pluck('id')->toArray();
                if (!in_array($router->id, $assignedRouters)) {
                    return response()->json(['message' => 'غير مصرح'], 403);
                }
            }

            $dir = "backups/routers/{$router->id}";
            $backups = [];

            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($dir)) {
                $files = \Illuminate\Support\Facades\Storage::disk('local')->files($dir);
                foreach ($files as $file) {
                    if (str_ends_with($file, '.info')) {
                        $info = json_decode(\Illuminate\Support\Facades\Storage::disk('local')->get($file), true);
                        if ($info) {
                            $info['has_export'] = \Illuminate\Support\Facades\Storage::disk('local')->exists(
                                str_replace('.info', '.rsc', $file)
                            );
                            $backups[] = $info;
                        }
                    }
                }
            }

            // ترتيب بالأحدث أولاً
            usort($backups, fn($a, $b) => ($b['backup_date'] ?? '') <=> ($a['backup_date'] ?? ''));

            return response()->json([
                'success' => true,
                'router_name' => $router->name,
                'backups' => $backups
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }


    /**
     * قائمة ملفات الباكاب الموجودة على الراوتر
     */
    public function listRouterBackupFiles(Request $request, $routerId)
    {
        try {
            $router = Router::findOrFail($routerId);
            $user = $this->user($request);
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                return response()->json(['message' => 'غير مصرح'], 403);
            }

            $service = new \App\Services\MikroTikService($router);
            if (!$service->connect()) {
                return response()->json(['message' => 'فشل الاتصال بالراوتر'], 500);
            }

            $files = $service->command(['/file/print']);
            $backupFiles = [];

            if (is_array($files)) {
                foreach ($files as $file_item) {
                    if (!isset($file_item['name'])) continue;
                    $name = $file_item['name'];
                    if (str_ends_with($name, '.backup')) {
                        $backupFiles[] = [
                            'name' => $name,
                            'size' => $file_item['size'] ?? 'unknown',
                            'date' => $file_item['creation-time'] ?? 'unknown',
                            'type' => 'backup',
                        ];
                    }
                }
            }

            // ترتيب بالأحدث
            usort($backupFiles, fn($a, $b) => ($b['date'] ?? '') <=> ($a['date'] ?? ''));

            $service->disconnect();

            return response()->json([
                'success' => true,
                'router_name' => $router->name,
                'files' => $backupFiles
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * استعادة نسخة احتياطية على الراوتر
     */
    public function restoreRouterBackup(Request $request, $routerId)
    {
        try {
            $router = Router::findOrFail($routerId);
            $user = $this->user($request);

            // فقط super_admin و admin يمكنهم الاستعادة
            if (!in_array($user->role, ['admin', 'super_admin'])) {
                return response()->json(['message' => 'غير مصرح - الاستعادة متاحة للمدير فقط'], 403);
            }

            $filename = $request->input('filename');
            if (!$filename) {
                return response()->json(['message' => 'اسم الملف مطلوب'], 400);
            }

            // تنظيف اسم الملف لمنع path traversal
            $filename = basename($filename);
            if (!str_ends_with($filename, '.backup')) {
                return response()->json(['message' => 'نوع الملف غير مدعوم - يجب أن يكون .backup'], 400);
            }

            $service = new \App\Services\MikroTikService($router);
            if (!$service->connect()) {
                return response()->json(['message' => 'فشل الاتصال بالراوتر'], 500);
            }

            // التحقق من وجود الملف على الراوتر
            $fileCheck = $service->command(['/file/print', '?name=' . $filename]);
            if (empty($fileCheck) || !isset($fileCheck[0]['name'])) {
                $service->disconnect();
                return response()->json(['message' => 'الملف غير موجود على الراوتر: ' . $filename], 404);
            }

            $fileSize = $fileCheck[0]['size'] ?? 'unknown';

            // تسجيل عملية الاستعادة
            \Illuminate\Support\Facades\Log::info("Router backup restore initiated", [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'filename' => $filename,
                'user' => $user->name,
                'file_size' => $fileSize,
            ]);

            // تنفيذ الاستعادة - الراوتر سيعيد التشغيل تلقائياً
            $backupNameWithoutExt = str_replace('.backup', '', $filename);
            $result = $service->command([
                '/system/backup/load',
                '=name=' . $backupNameWithoutExt,
                '=password=',
            ]);

            // لا نحتاج disconnect لأن الراوتر سيعيد التشغيل

            return response()->json([
                'success' => true,
                'message' => 'تم بدء استعادة النسخة الاحتياطية. الراوتر سيعيد التشغيل تلقائياً خلال دقيقة.',
                'details' => [
                    'router' => $router->name,
                    'filename' => $filename,
                    'file_size' => $fileSize,
                    'restored_by' => $user->name,
                    'restored_at' => now()->toIso8601String(),
                ]
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Restore error for router {$routerId}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage()
            ], 500);
        }
    }


    // ==================== ROUTER MONITORING ====================

    /**
     * Get real-time monitoring data for all routers
     */
    public function monitorRouters(Request $request)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);
        $routers = \App\Models\Router::whereIn('id', $routerIds)->get();        

        $monitorData = [];

        foreach ($routers as $router) {
            $activeUsers = \App\Models\ActiveSession::where('router_id', $router->id)->count();
            $pppUsers = \App\Models\ActiveSession::where('router_id', $router->id)->where('type', 'ppp')->count();
            $hotspotUsers = \App\Models\ActiveSession::where('router_id', $router->id)->where('type', 'hotspot')->count();
            $totalSubscribers = \App\Models\Subscriber::where('router_id', $router->id)->count();

            // Try live connection for fresh CPU/RAM data
            $cpuLoad = (int) ($router->cpu_load ?? 0);
            $totalMemory = (int) ($router->total_memory ?? 0);
            $freeMemory = (int) ($router->free_memory ?? 0);
            $uptimeSec = (int) ($router->uptime ?? 0);
            $status = $router->status ?? 'unknown';
            $version = $router->version ?? '';
            $boardName = $router->board_name ?? '';
            $lastSeen = $router->last_seen;

            try {
                $service = new \App\Services\MikroTikService($router);
                $service->connect();
                $info = $service->getSystemInfo();
                $service->disconnect();

                $cpuLoad = (int) ($info['cpu_load'] ?? $cpuLoad);
                $totalMemory = (int) ($info['total_memory'] ?? $totalMemory);
                $freeMemory = (int) ($info['free_memory'] ?? $freeMemory);
                $uptimeSec = (int) ($info['uptime'] ?? $uptimeSec);
                $version = $info['version'] ?? $version;
                $boardName = $info['board_name'] ?? $boardName;
                $status = 'online';
                $lastSeen = now();

                $router->update([
                    'cpu_load' => $cpuLoad,
                    'total_memory' => $totalMemory,
                    'free_memory' => $freeMemory,
                    'uptime' => $uptimeSec,
                    'version' => $version,
                    'board_name' => $boardName,
                    'last_seen' => $lastSeen,
                    'status' => 'online',
                ]);
            } catch (\Exception $e) {
                if ($status === 'online') {
                    $status = 'offline';
                    $router->update(['status' => 'offline']);
                }
            }

            $memoryUsage = 0;
            if ($totalMemory > 0) {
                $memoryUsage = round((($totalMemory - $freeMemory) / $totalMemory) * 100);
            }

            // Format uptime
            $uptimeStr = 'N/A';
            if ($uptimeSec) {
                $days = floor($uptimeSec / 86400);
                $hours = floor(($uptimeSec % 86400) / 3600);
                $minutes = floor(($uptimeSec % 3600) / 60);
                if ($days > 0) $uptimeStr = $days . 'd ' . $hours . 'h';
                elseif ($hours > 0) $uptimeStr = $hours . 'h ' . $minutes . 'm';
                else $uptimeStr = $minutes . 'm';
            }

            $monitorData[] = [
                'id' => $router->id,
                'name' => $router->name,
                'identity' => $router->identity,
                'ip_address' => $router->ip_address,
                'status' => $status,
                'cpu_load' => $cpuLoad,
                'memory_usage' => $memoryUsage,
                'total_memory' => $totalMemory,
                'free_memory' => $freeMemory,
                'uptime' => $uptimeStr,
                'uptime_seconds' => $uptimeSec,
                'active_users' => $activeUsers,
                'ppp_users' => $pppUsers,
                'hotspot_users' => $hotspotUsers,
                'total_subscribers' => $totalSubscribers,
                'last_seen' => $lastSeen ? (is_string($lastSeen) ? $lastSeen : $lastSeen->format('Y-m-d H:i:s')) : null,
                'version' => $version,
                'board_name' => $boardName,
            ];
        }

        // Sort: online first, then by name
        usort($monitorData, function ($a, $b) {
            if ($a['status'] === 'online' && $b['status'] !== 'online') return -1;
            if ($a['status'] !== 'online' && $b['status'] === 'online') return 1;
            return strcmp($a['name'], $b['name']);
        });

        return response()->json([
            'success' => true,
            'routers' => $monitorData,
            'summary' => [
                'total' => count($monitorData),
                'online' => count(array_filter($monitorData, fn($r) => $r['status'] === 'online')),
                'offline' => count(array_filter($monitorData, fn($r) => $r['status'] !== 'online')),
                'total_active_users' => array_sum(array_column($monitorData, 'active_users')),
            ],
        ]);
    }

    /**
     * Get live monitoring data for a single router (fresh from router)
     */
    public function monitorRouterLive(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        if (!in_array($id, $routerIds)) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $router = \App\Models\Router::findOrFail($id);

        try {
            $service = new \App\Services\MikroTikService($router);
            $service->connect();

            // Get fresh system info
            $info = $service->getSystemInfo();

            // Get interfaces
            $interfaces = $service->command(['/interface/print']);

            // Get active PPP
            $pppActive = $service->command(['/ppp/active/print']);

            // Get active hotspot
            $hotspotActive = $service->command(['/ip/hotspot/active/print']);

            // Get DHCP leases
            $dhcpLeases = $service->command(['/ip/dhcp-server/lease/print']);

            // Get neighbors
            $neighbors = $service->command(['/ip/neighbor/print']);

            $service->disconnect();

            // Update router record
            $router->update([
                'cpu_load' => $info['cpu_load'] ?? $router->cpu_load,
                'total_memory' => $info['total_memory'] ?? $router->total_memory,
                'free_memory' => $info['free_memory'] ?? $router->free_memory,
                'uptime' => $info['uptime'] ?? $router->uptime,
                'last_seen' => now(),
                'status' => 'online',
            ]);

            $memoryUsage = 0;
            if (($info['total_memory'] ?? 0) > 0) {
                $memoryUsage = round((($info['total_memory'] - ($info['free_memory'] ?? 0)) / $info['total_memory']) * 100);
            }

            // Format interfaces
            $ifaceData = [];
            foreach ($interfaces as $iface) {
                if (isset($iface['!trap'])) continue;
                $ifaceData[] = [
                    'name' => $iface['name'] ?? '',
                    'type' => $iface['type'] ?? '',
                    'running' => ($iface['running'] ?? '') === 'true',
                    'disabled' => ($iface['disabled'] ?? '') === 'true',
                    'rx_byte' => (int) ($iface['rx-byte'] ?? 0),
                    'tx_byte' => (int) ($iface['tx-byte'] ?? 0),
                ];
            }

            // Format uptime
            $uptimeStr = 'N/A';
            $uptimeSec = $info['uptime'] ?? 0;
            if ($uptimeSec) {
                $days = floor($uptimeSec / 86400);
                $hours = floor(($uptimeSec % 86400) / 3600);
                $minutes = floor(($uptimeSec % 3600) / 60);
                if ($days > 0) $uptimeStr = $days . 'd ' . $hours . 'h';
                elseif ($hours > 0) $uptimeStr = $hours . 'h ' . $minutes . 'm';
                else $uptimeStr = $minutes . 'm';
            }

            return response()->json([
                'success' => true,
                'router' => [
                    'id' => $router->id,
                    'name' => $router->name,
                    'status' => 'online',
                    'cpu_load' => (int) ($info['cpu_load'] ?? 0),
                    'memory_usage' => $memoryUsage,
                    'total_memory' => (int) ($info['total_memory'] ?? 0),
                    'free_memory' => (int) ($info['free_memory'] ?? 0),
                    'uptime' => $uptimeStr,
                    'uptime_seconds' => (int) $uptimeSec,
                    'version' => $info['version'] ?? '',
                    'board_name' => $info['board_name'] ?? '',
                    'identity' => $info['identity'] ?? $router->identity,
                    'ppp_active' => count(array_filter($pppActive, fn($p) => !isset($p['!trap']))),
                    'hotspot_active' => count(array_filter($hotspotActive, fn($p) => !isset($p['!trap']))),
                    'dhcp_leases' => count(array_filter($dhcpLeases, fn($l) => !isset($l['!trap']))),
                    'neighbor_count' => count(array_filter($neighbors, fn($n) => !isset($n['!trap']))),
                ],
                'interfaces' => $ifaceData,
                'neighbors' => array_values(array_filter(array_map(function ($n) {
                    if (isset($n['!trap'])) return null;
                    return [
                        'identity' => $n['identity'] ?? '',
                        'address' => $n['address'] ?? '',
                        'mac_address' => $n['mac-address'] ?? '',
                        'interface' => str_contains($n['interface'] ?? '', ',') ? explode(',', $n['interface'])[0] : ($n['interface'] ?? ''),
                        'platform' => $n['platform'] ?? '',
                        'board' => $n['board'] ?? '',
                    ];
                }, $neighbors))),
            ]);

        } catch (\Exception $e) {
            $router->update(['status' => 'offline']);
            return response()->json([
                'success' => false,
                'message' => 'فشل الاتصال بالراوتر: ' . $e->getMessage(),
                'router' => [
                    'id' => $router->id,
                    'name' => $router->name,
                    'status' => 'offline',
                ],
            ]);
        }
    }

    /**
     * GET /routers/{id}/log
     * Returns last 100 log entries from a MikroTik router
     */
    public function routerLog(Request $request, $id)
    {
        $user = $this->user($request);
        $routerIds = $this->routerIds($user);

        if (!in_array($id, $routerIds)) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $router = \App\Models\Router::findOrFail($id);

        try {
            $service = new \App\Services\MikroTikService($router);
            $service->connect();
            $logs = $service->getRouterLog(100);
            $service->disconnect();

            return response()->json([
                'success' => true,
                'router_id' => (int) $id,
                'logs' => $logs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل الاتصال: ' . $e->getMessage(),
            ], 500);
        }
    }
}
