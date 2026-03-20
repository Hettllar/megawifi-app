<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\ActiveSession;
use App\Services\UserManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Log;

class UserManagerController extends Controller
{
    /**
     * Sanitize data for JSON encoding - fix malformed UTF-8
     */
    private function sanitizeForJson($data)
    {
        if (is_string($data)) {
            // Convert to UTF-8, replacing invalid sequences
            $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            // Remove any remaining invalid UTF-8 bytes
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $data);
            return $data ?: '';
        }
        if (is_array($data)) {
            return array_map([$this, 'sanitizeForJson'], $data);
        }
        return $data;
    }

    /**
     * Display UserManager dashboard
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id')->toArray()
            : $user->routers()->pluck('routers.id')->toArray();

        // If no routers, return empty view
        $routers = Router::whereIn('id', $routerIds)->get();
        
        if (empty($routerIds) || $routers->isEmpty()) {
            return view('usermanager.index', [
                'routers' => collect(),
                'subscribers' => collect(),
                'stats' => ['total' => 0, 'active' => 0, 'expired' => 0, 'online' => 0, 'unpaid' => 0],
                'lastSync' => null,
                'syncInterval' => 5
            ]);
        }

        $isSuperAdmin = $user->isSuperAdmin();
        $noRouterSelected = $isSuperAdmin && !$request->filled('router_id') && !$request->filled('search');

        // Super Admin: don't load all subscribers until a router is selected
        if ($noRouterSelected) {
            $subscribers = collect();
            
            // Load stats for all routers (lightweight query)
            $statsQuery = Subscriber::whereIn('router_id', $routerIds)
                ->where('type', 'usermanager')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                    SUM(CASE WHEN is_paid = 0 OR is_paid IS NULL THEN 1 ELSE 0 END) as unpaid,
                    SUM(CASE WHEN is_online = 1 THEN 1 ELSE 0 END) as online,
                    MAX(last_synced_at) as last_sync
                ")
                ->first();
            
            $stats = [
                'total' => $statsQuery->total ?? 0,
                'active' => $statsQuery->active ?? 0,
                'expired' => $statsQuery->expired ?? 0,
                'unpaid' => $statsQuery->unpaid ?? 0,
                'online' => $statsQuery->online ?? 0,
            ];
            $lastSync = $statsQuery->last_sync;
            $syncInterval = Router::whereIn('id', $routerIds)
                ->where('is_active', true)
                ->min('sync_interval') ?? 5;

            return response()
                ->view('usermanager.index', compact('subscribers', 'routers', 'stats', 'lastSync', 'syncInterval', 'isSuperAdmin', 'noRouterSelected'))
                ->header('Content-Type', 'text/html; charset=utf-8');
        }
        $query = Subscriber::whereIn('router_id', $routerIds)
            ->where('type', 'usermanager')
            ->with('router');

        // Filter by router
        if ($request->filled('router_id')) {
            $query->where('router_id', $request->router_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by profile
        if ($request->filled('profile')) {
            $query->where('profile', $request->profile);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Get all subscribers - ordered by usage (total_bytes) descending
        $subscribers = $query->orderByRaw('COALESCE(total_bytes, 0) DESC')->get();

        // Stats - استعلام واحد محسن بدلاً من 4 استعلامات
        $statsQuery = Subscriber::whereIn('router_id', $routerIds)
            ->where('type', 'usermanager')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN is_paid = 0 OR is_paid IS NULL THEN 1 ELSE 0 END) as unpaid,
                SUM(CASE WHEN is_online = 1 THEN 1 ELSE 0 END) as online,
                MAX(last_synced_at) as last_sync
            ")
            ->first();
        
        $stats = [
            'total' => $statsQuery->total ?? 0,
            'active' => $statsQuery->active ?? 0,
            'expired' => $statsQuery->expired ?? 0,
            'unpaid' => $statsQuery->unpaid ?? 0,
            'online' => $statsQuery->online ?? 0,
        ];
        
        // Get last sync time from the same query
        $lastSync = $statsQuery->last_sync;

        // Get minimum sync interval from routers
        $syncInterval = Router::whereIn('id', $routerIds)
            ->where('is_active', true)
            ->min('sync_interval') ?? 5;

        return response()
            ->view('usermanager.index', compact('subscribers', 'routers', 'stats', 'lastSync', 'syncInterval', 'isSuperAdmin', 'noRouterSelected'))
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Sync all routers at once - get usage from sessions for all users
     */
    public function syncAllRouters()
    {
        try {
            $routers = Router::where('is_active', true)->get();
            
            $totalStats = [
                'routers' => 0,
                'users_synced' => 0,
                'usage_updated' => 0,
                'errors' => [],
            ];

            foreach ($routers as $router) {
                try {
                    if (!UserManagerService::isRouterReachable($router)) {
                        $totalStats['errors'][] = $router->name . ' (غير متاح)';
                        continue;
                    }

                    $service = new UserManagerService($router);
                    if (!$service->connect()) {
                        $totalStats['errors'][] = $router->name . ' (فشل الاتصال)';
                        continue;
                    }

                    // First sync users
                    $syncResult = $service->syncUsers();
                    $totalStats['users_synced'] += $syncResult['synced'] ?? 0;

                    // Then get all sessions and calculate usage
                    $allSessions = $service->getAllSessions();
                    
                    // Calculate usage per user from sessions
                    $usageByUser = [];
                    foreach ($allSessions as $session) {
                        if (!isset($session['user'])) continue;
                        
                        $username = $session['user'];
                        if (!isset($usageByUser[$username])) {
                            $usageByUser[$username] = [
                                'download' => 0,
                                'upload' => 0,
                                'uptime' => 0,
                                'sessions' => 0,
                            ];
                        }
                        
                        $usageByUser[$username]['download'] += (int)($session['download'] ?? 0);
                        $usageByUser[$username]['upload'] += (int)($session['upload'] ?? 0);
                        $usageByUser[$username]['uptime'] += $this->parseUptimeToSeconds($session['uptime'] ?? '0s');
                        $usageByUser[$username]['sessions']++;
                    }

                    // تحميل جميع المشتركين لهذا الراوتر مرة واحدة (محسن)
                    $subscribersByUsername = Subscriber::where('router_id', $router->id)
                        ->whereIn('username', array_keys($usageByUser))
                        ->get()
                        ->keyBy('username');

                    // Update local database for each user using bulk update
                    $updateBatch = [];
                    foreach ($usageByUser as $username => $usage) {
                        $subscriber = $subscribersByUsername[$username] ?? null;
                        if (!$subscriber) continue;

                        $totalBytes = $usage['download'] + $usage['upload'];
                        
                        $subscriber->update([
                            'bytes_in' => $usage['upload'],
                            'bytes_out' => $usage['download'],
                            'total_bytes' => $totalBytes,
                            'um_data' => json_encode([
                                'download_used' => $usage['download'],
                                'upload_used' => $usage['upload'],
                                'total_uptime' => $this->formatUptime($usage['uptime']),
                                'sessions_count' => $usage['sessions'],
                                'last_sync' => now()->format('Y-m-d H:i:s'),
                            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
                            'last_seen' => now(),
                        ]);
                        
                        $totalStats['usage_updated']++;
                    }

                    $service->disconnect();
                    $totalStats['routers']++;

                } catch (Exception $e) {
                    $totalStats['errors'][] = $router->name . ' (' . $e->getMessage() . ')';
                }
            }

            return response()->json([
                'success' => true,
                'message' => "تم مزامنة {$totalStats['routers']} راوتر، {$totalStats['users_synced']} مستخدم، تحديث استهلاك {$totalStats['usage_updated']}",
                'data' => $totalStats,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت المزامنة: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تحديث استهلاك جميع المشتركين يدوياً
     * يقوم بفصل وإعادة تفعيل كل مشترك لتحديث بيانات الاستهلاك
     */
    public function refreshAllUsage()
    {
        try {
            // تعطيل وقت التنفيذ للعمليات الطويلة
            set_time_limit(3600);

            $routers = Router::where('is_active', true)->get();
            
            $stats = [
                'routers_processed' => 0,
                'routers_failed' => 0,
                'users_refreshed' => 0,
                'users_failed' => 0,
                'total_download' => 0,
                'total_upload' => 0,
                'errors' => [],
            ];

            \Log::info('ManualRefreshUsage: ========== بدء التحديث اليدوي لجميع المشتركين ==========');

            foreach ($routers as $router) {
                try {
                    \Log::info("ManualRefreshUsage: جاري معالجة الراوتر {$router->name}...");

                    if (!UserManagerService::isRouterReachable($router)) {
                        \Log::warning("ManualRefreshUsage: الراوتر {$router->name} غير متاح");
                        $stats['routers_failed']++;
                        $stats['errors'][] = $router->name . ' (غير متاح)';
                        continue;
                    }

                    // جلب جميع المشتركين النشطين
                    $subscribers = Subscriber::where('router_id', $router->id)
                        ->where('type', 'usermanager')
                        ->whereIn('status', ['active', 'limited'])
                        ->whereNotNull('mikrotik_id')
                        ->get();

                    if ($subscribers->isEmpty()) {
                        \Log::info("ManualRefreshUsage: لا يوجد مشتركين نشطين في {$router->name}");
                        $stats['routers_processed']++;
                        continue;
                    }

                    \Log::info("ManualRefreshUsage: وجد {$subscribers->count()} مشترك في {$router->name}");

                    $service = new UserManagerService($router);
                    if (!$service->connect()) {
                        $stats['routers_failed']++;
                        $stats['errors'][] = $router->name . ' (فشل الاتصال)';
                        continue;
                    }

                    foreach ($subscribers as $subscriber) {
                        try {
                            // Get real .id from router
                            $routerUser = $service->getUserByUsername($subscriber->username);
                            if (!$routerUser || !isset($routerUser['.id'])) continue;
                            $realId = $routerUser['.id'];

                            // Toggle: فصل ثم إعادة تفعيل
                            $service->toggleUserStatus($realId, true);
                            usleep(500000);
                            $service->toggleUserStatus($realId, false);
                            usleep(200000);

                            // Get fresh usage from UM7 monitor
                            $monitor = $service->getSingleUserMonitor($realId);

                            if ($monitor) {
                                $download = $monitor['total-download'];
                                $upload = $monitor['total-upload'];
                                $totalBytes = $download + $upload;

                                $subscriber->update([
                                    'bytes_in' => $upload,
                                    'bytes_out' => $download,
                                    'total_bytes' => $totalBytes,
                                'mikrotik_id' => $realId,
                                    'last_seen' => now(),
                                ]);

                                $stats['users_refreshed']++;
                                $stats['total_download'] += $download;
                                $stats['total_upload'] += $upload;
                            }
                        } catch (\Exception $e) {
                            \Log::warning("ManualRefreshUsage: فشل تحديث {$subscriber->username}: " . $e->getMessage());
                            $stats['users_failed']++;
                        }

                        usleep(100000); // تأخير 100ms بين المشتركين
                    }

                    $service->disconnect();
                    $stats['routers_processed']++;

                    \Log::info("ManualRefreshUsage: انتهى الراوتر {$router->name}");

                } catch (\Exception $e) {
                    \Log::error("ManualRefreshUsage: فشل في الراوتر {$router->name}: " . $e->getMessage());
                    $stats['routers_failed']++;
                    $stats['errors'][] = $router->name . ' (' . $e->getMessage() . ')';
                }
            }

            $totalGB = number_format(($stats['total_download'] + $stats['total_upload']) / 1073741824, 2);

            \Log::info("ManualRefreshUsage: ========== اكتمل التحديث اليدوي ==========");
            \Log::info("ManualRefreshUsage: الراوترات: {$stats['routers_processed']} ناجح، {$stats['routers_failed']} فاشل");
            \Log::info("ManualRefreshUsage: المشتركين: {$stats['users_refreshed']} ناجح، {$stats['users_failed']} فاشل");

            return response()->json([
                'success' => true,
                'message' => "تم تحديث استهلاك {$stats['users_refreshed']} مشترك من {$stats['routers_processed']} راوتر ({$totalGB} GB)",
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            \Log::error('ManualRefreshUsage: خطأ عام: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'فشل التحديث: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تحويل صيغة الاستهلاك من MikroTik إلى bytes
     */
    private function parseBytes(string $value): int
    {
        $value = trim($value);
        if (empty($value) || $value === '0') {
            return 0;
        }

        if (is_numeric($value)) {
            return (int)$value;
        }

        $units = [
            'T' => 1099511627776,
            'G' => 1073741824,
            'M' => 1048576,
            'K' => 1024,
        ];

        foreach ($units as $unit => $multiplier) {
            if (preg_match('/^([\d.]+)\s*' . $unit . '/i', $value, $matches)) {
                return (int)((float)$matches[1] * $multiplier);
            }
        }

        return (int)$value;
    }

    /**
     * تحديد تاريخ انتهاء جماعي لجميع المستخدمين
     */
    public function bulkExpiry(Request $request)
    {
        $request->validate([
            'expiry_date' => 'required|date',
            'router_id' => 'required|exists:routers,id',
        ]);

        try {
            $expiryDate = \Carbon\Carbon::parse($request->expiry_date);
            
            // جلب المشتركين
            $query = Subscriber::where('type', 'usermanager');
            
            $query->where('router_id', $request->router_id);
            
            $subscribers = $query->get();
            $updatedCount = 0;
            $errorCount = 0;
            
            // تحديث قاعدة البيانات المحلية فقط
            foreach ($subscribers as $subscriber) {
                try {
                    $subscriber->update([
                        'expiration_date' => $expiryDate,
                    ]);
                    $updatedCount++;
                } catch (\Exception $e) {
                    $errorCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "تم تحديث تاريخ الانتهاء لـ {$updatedCount} مستخدم" . ($errorCount > 0 ? " (فشل {$errorCount})" : ""),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل التحديث: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync UserManager users from router
     */
    public function sync(Router $router)
    {
        try {
            $this->authorize('update', $router);

            // Quick check if router is reachable
            if (!UserManagerService::isRouterReachable($router)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الراوتر غير متصل أو غير متاح حالياً',
                ], 503);
            }

            $service = new UserManagerService($router);
            $service->connect();

            $result = $service->syncUsers();
            $service->syncSessions();
            
            $service->disconnect();

            return response()->json([
                'success' => true,
                'message' => "تمت المزامنة بنجاح! تم مزامنة {$result['synced']} مستخدم",
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت المزامنة: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync only usage/consumption data from router
     */
    public function syncUsage(Router $router)
    {
        try {
            $this->authorize('update', $router);

            // Quick check if router is reachable
            if (!UserManagerService::isRouterReachable($router)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الراوتر غير متصل أو غير متاح حالياً',
                ], 503);
            }

            $service = new UserManagerService($router);
            $service->connect();

            $result = $service->syncUsage();
            
            $service->disconnect();

            return response()->json([
                'success' => true,
                'message' => "تمت مزامنة الاستهلاك! تم تحديث {$result['synced']} مستخدم",
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت مزامنة الاستهلاك: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Refresh single user usage by toggling (disable then enable)
     * This forces UserManager to update usage data
     */
    public function refreshUserUsage(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $request->validate([
                'mikrotik_id' => 'required|string',
                'subscriber_id' => 'required|integer',
            ]);

            if (!UserManagerService::isRouterReachable($router)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الراوتر غير متصل أو غير متاح حالياً',
                ], 503);
            }

            $subscriber = Subscriber::find($request->subscriber_id);
            if (!$subscriber) {
                return response()->json([
                    'success' => false,
                    'message' => 'المشترك غير موجود',
                ], 404);
            }

            $service = new UserManagerService($router);
            $service->connect();

            // Lookup real .id from router by username (DB mikrotik_id may be stale)
            $routerUser = $service->getUserByUsername($subscriber->username);
            if (!$routerUser) {
                $service->disconnect();
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود على الراوتر',
                ], 404);
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
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على بيانات المستخدم في المونيتور',
                ], 404);
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

            $totalUsedGb = number_format($totalBytes / 1073741824, 2);
            $downloadGb = number_format($totalDownload / 1073741824, 2);
            $uploadGb = number_format($totalUpload / 1073741824, 2);

            return response()->json([
                'success' => true,
                'message' => "تم التحديث: ↓{$downloadGb} ↑{$uploadGb} = {$totalUsedGb} GB",
                'data' => [
                    'download_bytes' => $totalDownload,
                    'upload_bytes' => $totalUpload,
                    'total_bytes' => $totalBytes,
                    'total_gb' => $totalUsedGb,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل التحديث: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get subscriber data for AJAX refresh (without page reload)
     */
    public function getSubscriberData(Router $router, Subscriber $subscriber)
    {
        try {
            $this->authorize('view', $router);
            
            // التأكد من أن المشترك ينتمي لهذا الراوتر
            if ($subscriber->router_id !== $router->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'المشترك لا ينتمي لهذا الراوتر'
                ], 404);
            }
            
            // إعادة تحميل المشترك من قاعدة البيانات
            $subscriber->refresh();
            
            return response()->json([
                'success' => true,
                'subscriber' => [
                    'id' => $subscriber->id,
                    'username' => $subscriber->username,
                    'full_name' => $subscriber->full_name,
                    'phone' => $subscriber->phone,
                    'whatsapp_number' => $subscriber->whatsapp_number,
                    'profile' => $subscriber->profile,
                    'status' => $subscriber->status,
                    'is_online' => $subscriber->is_online,
                    'is_throttled' => $subscriber->is_throttled,
                    'is_paid' => $subscriber->is_paid,
                    'total_bytes' => $subscriber->total_bytes,
                    'bytes_in' => $subscriber->bytes_in,
                    'bytes_out' => $subscriber->bytes_out,
                    'data_limit' => $subscriber->data_limit,
                    'expiration_date' => $subscriber->expiration_date?->format('Y-m-d'),
                    'subscription_price' => $subscriber->subscription_price,
                    'remaining_amount' => $subscriber->remaining_amount,
                    'last_seen' => $subscriber->last_seen?->diffForHumans(),
                    'shamcash_qr_url' => $router->shamcash_qr ? url('storage/' . $router->shamcash_qr) : '',
                    'brand_name' => $router->brand_name ?? 'MegaWiFi',
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse uptime string to seconds (e.g., "2d6h10m4s" => seconds)
     */
    private function parseUptimeToSeconds($uptime)
    {
        if (empty($uptime) || $uptime === '0s') return 0;
        
        $seconds = 0;
        
        // Match days
        if (preg_match('/(\d+)d/', $uptime, $m)) {
            $seconds += (int)$m[1] * 86400;
        }
        // Match hours
        if (preg_match('/(\d+)h/', $uptime, $m)) {
            $seconds += (int)$m[1] * 3600;
        }
        // Match minutes
        if (preg_match('/(\d+)m/', $uptime, $m)) {
            $seconds += (int)$m[1] * 60;
        }
        // Match seconds
        if (preg_match('/(\d+)s/', $uptime, $m)) {
            $seconds += (int)$m[1];
        }
        
        return $seconds;
    }

    /**
     * Format seconds to human readable uptime
     */
    private function formatUptime($seconds)
    {
        if ($seconds < 60) return $seconds . 's';
        
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        $result = '';
        if ($days > 0) $result .= $days . 'd';
        if ($hours > 0) $result .= $hours . 'h';
        if ($minutes > 0) $result .= $minutes . 'm';
        if ($secs > 0) $result .= $secs . 's';
        
        return $result ?: '0s';
    }

    /**
     * Parse UserManager bytes string (e.g., "1.5GiB" or "500MiB")
     */
    private function parseUserManagerBytes($value)
    {
        if (empty($value) || $value === '0') return 0;
        
        $value = strtolower(trim($value));
        
        // If already a number
        if (is_numeric($value)) {
            return (int)$value;
        }
        
        // Parse with unit
        preg_match('/^([\d.]+)\s*(gib|mib|kib|tib|gb|mb|kb|tb|g|m|k|t|b)?$/i', $value, $matches);
        
        if (empty($matches)) return 0;
        
        $num = (float)$matches[1];
        $unit = strtolower($matches[2] ?? 'b');
        
        $multipliers = [
            'tib' => 1099511627776, 'tb' => 1099511627776, 't' => 1099511627776,
            'gib' => 1073741824, 'gb' => 1073741824, 'g' => 1073741824,
            'mib' => 1048576, 'mb' => 1048576, 'm' => 1048576,
            'kib' => 1024, 'kb' => 1024, 'k' => 1024,
            'b' => 1,
        ];
        
        return (int)($num * ($multipliers[$unit] ?? 1));
    }

    /**
     * Add a new user to UserManager
     */
    public function addUser(Request $request, Router $router)
    {
        try {
            $this->authorize('update', $router);

            $request->validate([
                'username' => 'required|string|max:50',
                'password' => 'required|string|max:50',
                'profile' => 'required|string',
                'data_limit_gb' => 'nullable|numeric|min:0',
                'comment' => 'nullable|string|max:255',
            ]);

            // Quick check if router is reachable
            if (!UserManagerService::isRouterReachable($router)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الراوتر غير متصل أو غير متاح حالياً',
                ], 503);
            }

            $service = new UserManagerService($router);
            $service->connect();

            // Calculate data limit in bytes if specified
            $downloadLimit = null;
            if ($request->data_limit_gb && $request->data_limit_gb > 0) {
                $downloadLimit = $request->data_limit_gb * 1024 * 1024 * 1024; // Convert GB to bytes
            }

            // Add user to UserManager
            $result = $service->addUser([
                'name' => $request->username,
                'password' => $request->password,
                'profile' => $request->profile,
                'comment' => $request->comment ?? '',
                'download-limit' => $downloadLimit,
            ]);

            $service->disconnect();

            if ($result['success'] || !empty($result['ret']) || !empty($result['.id']) || !empty($result['user_id'])) {
                // Save to local database
                $subscriber = Subscriber::create([
                    'router_id' => $router->id,
                    'mikrotik_id' => $result['user_id'] ?? $result['.id'] ?? $result['ret'] ?? null,
                    'username' => $request->username,
                    'password' => $request->password,
                    'profile' => $request->profile,
                    'type' => 'usermanager',
                    'status' => 'active',
                    'comment' => $request->comment,
                    'data_limit' => $downloadLimit,
                    'disabled' => false,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تم إضافة المشترك بنجاح',
                    'subscriber' => $subscriber,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'فشل إضافة المستخدم على الراوتر',
                ], 500);
            }
        } catch (Exception $e) {
            Log::error("Error adding user: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'خطأ: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Renew user subscription with a profile
     */
    public function renewUser(Request $request, Router $router)
    {
        try {
            $this->authorize('update', $router);

            // Log incoming request data for debugging
            \Log::info("renewUser request data: ", [
                'user_id' => $request->user_id,
                'username' => $request->username,
                'profile' => $request->profile,
                'subscription_price' => $request->subscription_price,
                'remaining_amount' => $request->remaining_amount,
                'is_paid' => $request->is_paid,
            ]);

            $request->validate([
                'user_id' => 'required|string',
                'username' => 'required|string',
                'subscriber_id' => 'required|integer',
                'profile' => 'required|string',
                'data_limit_gb' => 'nullable|numeric|min:0',
                'expiry_days' => 'nullable|integer|min:1',
                'reset_usage' => 'boolean',
                'subscription_price' => 'nullable|numeric|min:0',
                'remaining_amount' => 'nullable|numeric|min:0',
                'is_paid' => 'nullable|boolean',
            ]);

            $service = new UserManagerService($router);
            $service->connect();

            // Get subscriber first
            $subscriber = Subscriber::where('router_id', $router->id)
                ->where('id', $request->subscriber_id)
                ->first();

            // If reset_usage is requested, we need to delete and recreate the user on router
            // This is the ONLY way to truly reset usage in UserManager
            if ($request->boolean('reset_usage')) {
                \Log::info("Reset usage requested - deleting and recreating user on router");
                
                // Use resetUserUsage to delete and recreate user with original profile
                $resetResult = $service->resetUserUsage($request->username, $request->profile);
                
                // Update mikrotik_id if changed
                if (isset($resetResult['new_user_id']) && $subscriber) {
                    $subscriber->update(['mikrotik_id' => $resetResult['new_user_id']]);
                }
                
                $result = $resetResult;
            } else {
                // Just renew without resetting usage
                $dataLimitGb = $request->data_limit_gb ? (float)$request->data_limit_gb : null;
                $expiryDays = $request->expiry_days ? (int)$request->expiry_days : null;
                
                $result = $service->renewUser(
                    $request->username, 
                    $request->profile, 
                    $dataLimitGb, 
                    $expiryDays
                );
            }


            // Fetch updated data from router after renewal
            $updatedUserData = null;
            try {
                $updatedUserData = $service->getUserByUsername($request->username);
                \Log::info("Fetched updated user data from router: ", ['user' => $request->username, 'data' => $updatedUserData]);
            } catch (Exception $e) {
                \Log::warning("Could not fetch updated user data from router: " . $e->getMessage());
            }

            // Update local database
            $subscriber = Subscriber::where('router_id', $router->id)
                ->where('id', $request->subscriber_id)
                ->first();

            if ($subscriber) {
                $updateData = [
                    'profile' => $request->profile,
                    'status' => 'active',
                    'last_synced_at' => now(),
                    'is_throttled' => false,
                    'throttled_at' => null,
                    'stopped_at' => null,
                    'stop_reason' => null,
                ];
                
                // Update with fresh data from router if available
                if ($updatedUserData) {
                    // Update actual speed from router
                    if (isset($updatedUserData['actual-profile'])) {
                        $updateData['profile'] = $updatedUserData['actual-profile'];
                    }
                    
                    // Update usage data from router
                    if (isset($updatedUserData['bytes-in'])) {
                        $updateData['bytes_in'] = (int) $updatedUserData['bytes-in'];
                    }
                    if (isset($updatedUserData['bytes-out'])) {
                        $updateData['bytes_out'] = (int) $updatedUserData['bytes-out'];
                    }
                    if (isset($updatedUserData['bytes-total'])) {
                        $updateData['total_bytes'] = (int) $updatedUserData['bytes-total'];
                    }
                    if (isset($updatedUserData['uptime-used'])) {
                        $updateData['uptime_used'] = (int) $updatedUserData['uptime-used'];
                    }
                    
                    \Log::info("Updated subscriber with fresh router data");
                }
                
                // Update expiry date if days provided - this overrides any bulk expiry
                if ($request->expiry_days && (int) $request->expiry_days > 0) {
                    $updateData['expiration_date'] = now()->addDays((int) $request->expiry_days)->endOfDay();
                }
                
                // Save data limit if specified (sync both fields)
                if ($request->data_limit_gb) {
                    $dlGb = (float)$request->data_limit_gb;
                    $updateData['data_limit_gb'] = $dlGb > 0 ? $dlGb : null;
                    $updateData['data_limit'] = $dlGb > 0 ? (int)($dlGb * 1073741824) : null;
                    $updateData['original_profile'] = $request->profile; // Save profile for restore after throttle
                }
                
                // Add payment info if provided (allow 0 values)
                if ($request->has('subscription_price') && $request->input('subscription_price') !== null) {
                    $updateData['subscription_price'] = (float) $request->input('subscription_price');
                }
                if ($request->has('remaining_amount') && $request->input('remaining_amount') !== null) {
                    $updateData['remaining_amount'] = (float) $request->input('remaining_amount');
                }
                
                // Handle is_paid - check multiple formats
                if ($request->has('is_paid')) {
                    $isPaidValue = $request->input('is_paid');
                    // Handle both boolean and string representations
                    $updateData['is_paid'] = filter_var($isPaidValue, FILTER_VALIDATE_BOOLEAN);
                }
                
                \Log::info("Updating subscriber {$subscriber->id} with data: ", $updateData);
                
                $subscriber->update($updateData);

                // Reset local usage if requested
                if ($request->boolean('reset_usage')) {
                    $subscriber->update([
                        'bytes_in' => 0,
                        'bytes_out' => 0,
                        'total_bytes' => 0,
                        'uptime_used' => 0,
                        'um_usage_offset' => 0,
                        'usage_reset_at' => now(),
                        'is_throttled' => false,
                        'throttled_at' => null,
                        'data_limit' => $request->data_limit_gb ? (int)((float)$request->data_limit_gb * 1073741824) : null,
                        'data_limit_gb' => $request->data_limit_gb ? (float)$request->data_limit_gb : null,
                        'um_data' => json_encode(array_merge(
                            json_decode($subscriber->um_data ?? '{}', true) ?: [],
                            ['download_used' => 0, 'upload_used' => 0, 'uptime_used' => 0]
                        ), JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
                    ]);
                    
                    // Delete session history when resetting usage
                    \App\Models\SessionHistory::where('subscriber_id', $subscriber->id)->delete();
                    \App\Models\TrafficHistory::where('subscriber_id', $subscriber->id)->delete();
                } else {
                    // حتى بدون تصفير - نحدث تاريخ التجديد
                    $subscriber->update(['usage_reset_at' => now()]);
                }
            }

            $service->disconnect();

            return response()->json([
                'success' => true,
                'message' => 'تم تجديد الاشتراك بنجاح',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تجديد الاشتراك: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show UserManager groups/profiles
     */
    public function groups(Router $router)
    {
        try {
            $this->authorize('view', $router);

            $service = new UserManagerService($router);
            $service->connect();
            
            $groups = $service->getGroups();
            
            $service->disconnect();

            return view('usermanager.groups', compact('router', 'groups'));
        } catch (Exception $e) {
            return back()->with('error', 'فشل جلب المجموعات: ' . $e->getMessage());
        }
    }

    /**
     * Show active sessions
     */
    public function sessions(Router $router)
    {
        try {
            $this->authorize('view', $router);

            $sessions = ActiveSession::where('router_id', $router->id)
                ->where('type', 'usermanager')
                ->with('subscriber')
                ->get();

            return view('usermanager.sessions', compact('router', 'sessions'));
        } catch (Exception $e) {
            return back()->with('error', 'فشل جلب الجلسات: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect session
     */
    public function disconnectSession(Router $router, $sessionId)
    {
        try {
            $this->authorize('view', $router);

            $session = ActiveSession::where('router_id', $router->id)
                ->where('type', 'usermanager')
                ->where('id', $sessionId)
                ->firstOrFail();

            if ($session->session_id) {
                $service = new UserManagerService($router);
                $service->connect();
                $service->disconnectSession($session->session_id);
                $service->disconnect();
            }

            $session->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم قطع الاتصال بنجاح',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل قطع الاتصال: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate vouchers
     */
    public function generateVouchers(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $validated = $request->validate([
                'group' => 'required|string',
                'count' => 'required|integer|min:1|max:100',
                'prefix' => 'nullable|string|max:10',
                'length' => 'nullable|integer|min:4|max:12',
                'validity' => 'nullable|string',
                'uptime_limit' => 'nullable|string',
            ]);

            $service = new UserManagerService($router);
            $service->connect();

            $options = [];
            if ($request->filled('prefix')) $options['prefix'] = $request->prefix;
            if ($request->filled('length')) $options['length'] = $request->length;
            if ($request->filled('validity')) $options['validity'] = $request->validity;
            if ($request->filled('uptime_limit')) $options['uptime-limit'] = $request->uptime_limit;

            $result = $service->generateVouchers(
                $validated['group'],
                $validated['count'],
                $options
            );

            // Sync new users
            $service->syncUsers();
            $service->disconnect();

            return response()->json([
                'success' => true,
                'message' => "تم توليد {$validated['count']} كود بنجاح",
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل توليد الأكواد: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show voucher generator form
     */
    public function showVoucherGenerator(Router $router)
    {
        try {
            $this->authorize('view', $router);

            $service = new UserManagerService($router);
            $service->connect();
            $groups = $service->getGroups();
            $service->disconnect();

            return view('usermanager.vouchers', compact('router', 'groups'));
        } catch (Exception $e) {
            return back()->with('error', 'فشل تحميل الصفحة: ' . $e->getMessage());
        }
    }

    /**
     * Show subscriber details
     */
    public function show(Subscriber $subscriber)
    {
        $this->authorize('view', $subscriber->router);

        if ($subscriber->type !== 'usermanager') {
            abort(404);
        }

        $umData = $subscriber->um_data ?? [];

        // Get active sessions
        $sessions = ActiveSession::where('subscriber_id', $subscriber->id)
            ->where('type', 'usermanager')
            ->get();

        return view('usermanager.show', compact('subscriber', 'umData', 'sessions'));
    }

    /**
     * Update subscriber
     */
    public function update(Request $request, Subscriber $subscriber)
    {
        try {
            $this->authorize('view', $subscriber->router);

            if ($subscriber->type !== 'usermanager') {
                abort(404);
            }

            $validated = $request->validate([
                'password' => 'nullable|string|min:4',
                'full_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:50',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string|max:255',
                'comment' => 'nullable|string',
                'iptv_allowed_ips' => 'nullable|string|max:500',
            ]);

            $subscriber->update($validated);

            // Update on MikroTik if mikrotik_id exists
            if ($subscriber->mikrotik_id && $request->filled('password')) {
                $service = new UserManagerService($subscriber->router);
                $service->connect();
                $service->updateUser($subscriber->mikrotik_id, [
                    'password' => $validated['password'],
                ]);
                $service->disconnect();
            }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'تم تحديث المشترك بنجاح']);
            }

            return back()->with('success', 'تم تحديث المشترك بنجاح');
        } catch (Exception $e) {
            return back()->with('error', 'فشل التحديث: ' . $e->getMessage());
        }
    }

    /**
     * Reset user usage counters
     */
    public function resetUsage(Subscriber $subscriber)
    {
        try {
            $this->authorize('view', $subscriber->router);

            if ($subscriber->type !== 'usermanager') {
                abort(404);
            }

            if (!$subscriber->mikrotik_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود على الراوتر',
                ], 400);
            }

            // Save throttle state before resetting
            $wasThrottled = $subscriber->is_throttled;
            $originalProfile = $subscriber->original_profile ?? $subscriber->profile;

            $service = new UserManagerService($subscriber->router);
            $service->connect();
            
            // Reset on MikroTik (deletes and recreates the user with original profile)
            // Pass original profile to ensure throttled profile is not used
            $result = $service->resetUserUsage($subscriber->username, $originalProfile);
            
            $service->disconnect();

            // Update mikrotik_id if a new one was returned
            if (isset($result['new_user_id'])) {
                $subscriber->update(['mikrotik_id' => $result['new_user_id']]);
            }

            // Reset local database counters AND remove throttle
            $subscriber->update([
                'bytes_in' => 0,
                'bytes_out' => 0,
                'total_bytes' => 0,
                'uptime_used' => 0,
                'um_usage_offset' => 0,
                'usage_reset_at' => now(),
                'is_throttled' => false,
                'throttled_at' => null,
                'profile' => $originalProfile,
            ]);

            // Reset um_data usage fields
            $umData = $subscriber->um_data;
            if (is_string($umData)) {
                $umData = json_decode($umData, true) ?? [];
            }
            $umData['download_used'] = 0;
            $umData['upload_used'] = 0;
            $umData['uptime_used'] = 0;
            $subscriber->update(['um_data' => json_encode($umData, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE)]);

            return response()->json([
                'success' => true,
                'message' => 'تم تصفير الاستهلاك بنجاح على الراوتر وقاعدة البيانات',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تصفير الاستهلاك: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set data limit for subscriber
     * النهج الجديد: التخزين في قاعدة البيانات فقط
     * Job CheckUsageLimit سيتولى التقييد التلقائي
     */
    public function setDataLimit(Request $request, Subscriber $subscriber)
    {
        try {
            $this->authorize('view', $subscriber->router);

            if ($subscriber->type !== 'usermanager') {
                abort(404);
            }

            $validated = $request->validate([
                'data_limit_gb' => 'required|numeric|min:0',
            ]);

            $dataLimitGb = (float) $validated['data_limit_gb'];

            // حفظ البروفايل الأصلي إذا لم يكن محفوظ
            if (empty($subscriber->original_profile) && !empty($subscriber->profile)) {
                $subscriber->original_profile = $subscriber->profile;
            }

            // تحديث قاعدة البيانات
            $subscriber->update([
                'data_limit_gb' => $dataLimitGb > 0 ? $dataLimitGb : null,
                'data_limit' => $dataLimitGb > 0 ? (int)($dataLimitGb * 1073741824) : null,
                'original_profile' => $subscriber->original_profile,
            ]);

            $message = $dataLimitGb > 0 
                ? "تم تحديد حد الاستهلاك بـ {$dataLimitGb} جيجابايت بنجاح. سيتم تقييد السرعة تلقائياً عند الوصول للحد."
                : "تم إزالة حد الاستهلاك (غير محدود)";

            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تحديد الحد: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * تجديد الاشتراك مع إعادة تعيين الاستهلاك ورفع التقييد
     */
    public function renewSubscription(Request $request, Subscriber $subscriber)
    {
        try {
            $this->authorize('view', $subscriber->router);

            if ($subscriber->type !== 'usermanager') {
                abort(404);
            }

            if (!$subscriber->mikrotik_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'المستخدم غير موجود على الراوتر',
                ], 400);
            }

            // البروفايل المراد استخدامه (الأصلي أو المحدد)
            $profileName = $request->input('profile') ?? $subscriber->original_profile ?? $subscriber->profile;

            $service = new UserManagerService($subscriber->router);
            $service->connect();
            
            // تجديد وإعادة تعيين الاستهلاك
            $result = $service->renewAndResetUsage($subscriber->mikrotik_id, $profileName);
            
            $service->disconnect();

            // تحديث قاعدة البيانات
            $subscriber->update([
                'mikrotik_id' => $result['new_user_id'] ?? $subscriber->mikrotik_id,
                'profile' => $profileName,
                'bytes_in' => 0,
                'bytes_out' => 0,
                'total_bytes' => 0,
                'uptime_used' => 0,
                'um_usage_offset' => 0,
                'is_throttled' => false,
                'throttled_at' => null,
                'usage_reset_at' => now(),
                'status' => 'active',
                'stopped_at' => null,
                'stop_reason' => null,
            ]);

            // تصفير um_data
            $umData = $subscriber->um_data;
            if (is_string($umData)) {
                $umData = json_decode($umData, true) ?? [];
            }
            $umData['download_used'] = 0;
            $umData['upload_used'] = 0;
            $umData['uptime_used'] = 0;
            $umData['actual_download'] = 0;
            $umData['actual_upload'] = 0;
            $subscriber->update(['um_data' => json_encode($umData, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE)]);

            return response()->json([
                'success' => true,
                'message' => 'تم تجديد الاشتراك وتصفير الاستهلاك بنجاح',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل التجديد: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * إنشاء بروفايل التقييد (throttled) على الراوتر
     */
    public function createThrottledProfile(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $profileName = $request->input('profile_name', config('mikrotik.throttle.profile', 'throttled'));
            $speed = $request->input('speed', '1k');

            $service = new UserManagerService($router);
            $service->connect();
            
            $result = $service->createThrottledProfile($profileName, $speed);
            
            $service->disconnect();

            return response()->json($result);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إنشاء البروفايل: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete subscriber
     */
    public function destroy(Subscriber $subscriber)
    {
        try {
            $this->authorize('view', $subscriber->router);

            if ($subscriber->type !== 'usermanager') {
                abort(404);
            }

            // Delete from MikroTik
            if ($subscriber->mikrotik_id) {
                $service = new UserManagerService($subscriber->router);
                $service->connect();
                $service->deleteUser($subscriber->mikrotik_id);
                $service->disconnect();
            }

            $subscriber->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المشترك بنجاح',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل الحذف: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update subscriber info (full_name, phone, national_id, address, payment info)
     */
    public function updateSubscriberInfo(Request $request, Subscriber $subscriber)
    {
        try {
            $this->authorize('view', $subscriber->router);

            $subscriber->update([
                'full_name' => $request->input('full_name'),
                'phone' => $request->input('phone'),
                'national_id' => $request->input('national_id'),
                'address' => $request->input('address'),
                'whatsapp_number' => $request->input('whatsapp_number'),
                'subscription_price' => $request->input('subscription_price', 0),
                'remaining_amount' => $request->input('remaining_amount', 0),
                'is_paid' => $request->boolean('is_paid'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث بيانات المشترك بنجاح',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل التحديث: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Transfer subscriber from one router to another
     */
    public function transferSubscriber(Request $request, Subscriber $subscriber)
    {
        try {
            $this->authorize('view', $subscriber->router);

            if ($subscriber->type !== 'usermanager') {
                return response()->json([
                    'success' => false,
                    'message' => 'هذه العملية متاحة فقط لمشتركي UserManager',
                ], 400);
            }

            $request->validate([
                'target_router_id' => 'required|exists:routers,id',
            ]);

            $sourceRouter = $subscriber->router;
            $targetRouter = Router::findOrFail($request->target_router_id);

            // Verify user has access to target router
            $this->authorize('view', $targetRouter);

            // Cannot transfer to same router
            if ($sourceRouter->id === $targetRouter->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'المشترك موجود بالفعل على هذا الراوتر',
                ], 400);
            }

            // Check both routers are reachable
            if (!UserManagerService::isRouterReachable($sourceRouter)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الراوتر المصدر (' . $sourceRouter->name . ') غير متصل',
                ], 503);
            }

            if (!UserManagerService::isRouterReachable($targetRouter)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الراوتر الهدف (' . $targetRouter->name . ') غير متصل',
                ], 503);
            }

            // Check if username already exists on target router
            $existingOnTarget = Subscriber::where('router_id', $targetRouter->id)
                ->where('username', $subscriber->username)
                ->exists();

            if ($existingOnTarget) {
                return response()->json([
                    'success' => false,
                    'message' => 'اسم المستخدم "' . $subscriber->username . '" موجود بالفعل على الراوتر الهدف',
                ], 409);
            }

            // Step 1: Get user data from source router
            $sourceService = new UserManagerService($sourceRouter);
            $sourceService->connect();

            $userData = $sourceService->getUserByUsername($subscriber->username);
            if (!$userData) {
                $sourceService->disconnect();
                return response()->json([
                    'success' => false,
                    'message' => 'لم يتم العثور على المشترك في الراوتر المصدر',
                ], 404);
            }

            // Step 2: Create user on target router
            $targetService = new UserManagerService($targetRouter);
            $targetService->connect();

            // Check if profile exists on target router
            $targetProfiles = $targetService->getProfiles();
            $profileNames = array_column($targetProfiles, 'name');
            $profileToUse = $subscriber->profile;

            if (!in_array($profileToUse, $profileNames)) {
                $targetService->disconnect();
                $sourceService->disconnect();
                return response()->json([
                    'success' => false,
                    'message' => 'الباقة "' . $profileToUse . '" غير موجودة على الراوتر الهدف. يرجى إنشاءها أولاً.',
                ], 400);
            }

            // Create user on target
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
                return response()->json([
                    'success' => false,
                    'message' => 'فشل إنشاء المشترك على الراوتر الهدف: ' . ($addResult['message'] ?? 'خطأ غير معروف'),
                ], 500);
            }

            $newMikrotikId = $addResult['user_id'] ?? $addResult['.id'] ?? $addResult['ret'] ?? null;

            // Step 3: Delete user from source router
            if ($subscriber->mikrotik_id) {
                $sourceService->deleteUser($subscriber->mikrotik_id);
            }

            $sourceService->disconnect();
            $targetService->disconnect();

            // Step 4: Update database
            $oldRouterName = $sourceRouter->name;
            $subscriber->update([
                'router_id' => $targetRouter->id,
                'mikrotik_id' => $newMikrotikId,
                'total_bytes' => 0,
                'bytes_in' => 0,
                'bytes_out' => 0,
                'last_synced_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم نقل المشترك "' . $subscriber->username . '" من ' . $oldRouterName . ' إلى ' . $targetRouter->name . ' بنجاح',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل نقل المشترك: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show migration page - select router
     */
    public function showMigrationPage()
    {
        $user = Auth::user();
        
        $routers = $user->isSuperAdmin() 
            ? Router::all()
            : $user->routers()->get();

        return view('usermanager.migrate-select', compact('routers'));
    }

    /**
     * Get PPP users from router (AJAX)
     */
    public function getPPPUsers(Router $router)
    {
        $user = Auth::user();
        if (!$user->isSuperAdmin() && !$user->routers()->where('routers.id', $router->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        try {
            // Get PPP secrets from router
            $mikrotikService = new \App\Services\MikroTikService($router);
            $mikrotikService->connect();
            $pppSecrets = $mikrotikService->getPPPSecrets();
            $mikrotikService->disconnect();
            
            $pppUsers = [];
            foreach ($pppSecrets as $secret) {
                $pppUsers[] = [
                    'id' => $secret['.id'] ?? '',
                    'name' => $secret['name'] ?? '',
                    'password' => $secret['password'] ?? '',
                    'profile' => $secret['profile'] ?? 'default',
                    'comment' => $secret['comment'] ?? '',
                    'disabled' => ($secret['disabled'] ?? 'false') === 'true',
                ];
            }
            
            // Get User Manager groups
            $service = new UserManagerService($router);
            $service->connect();
            $groups = $service->getGroups();
            $service->disconnect();

            return response()->json([
                'success' => true,
                'pppUsers' => $pppUsers,
                'groups' => $groups,
                'pppCount' => count($pppUsers),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Show migration form - migrate PPP users to UserManager
     */
        public function showMigrationForm(Router $router)
    {
        try {
            $this->authorize('view', $router);

            // Get PPP secrets from router using MikroTik API
            $mikrotikService = new \App\Services\MikroTikService($router);
            $mikrotikService->connect();
            $pppSecrets = $mikrotikService->getPPPSecrets();
            $mikrotikService->disconnect();

            $pppUsers = [];
            foreach ($pppSecrets as $secret) {
                $pppUsers[] = [
                    'id' => $secret['.id'] ?? '',
                    'name' => $secret['name'] ?? '',
                    'password' => $secret['password'] ?? '',
                    'profile' => $secret['profile'] ?? 'default',
                    'comment' => $secret['comment'] ?? '',
                    'disabled' => ($secret['disabled'] ?? 'false') === 'true',
                ];
            }

            // Get UserManager groups
            $service = new UserManagerService($router);
            $service->connect();
            $groups = $service->getGroups();
            $service->disconnect();

            return view('usermanager.migrate', compact('router', 'groups', 'pppUsers'));
        } catch (Exception $e) {
            return back()->with('error', 'فشل تحميل الصفحة: ' . $e->getMessage());
        }
    }

    /**
     * Migrate PPP users to UserManager
     */
    public function migratePPPUsers(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $validated = $request->validate([
                'group' => 'required|string',
                'usernames' => 'nullable|array',
                'usernames.*' => 'nullable',
                'delete_ppp' => 'nullable|boolean',
                'delete_from_ppp' => 'nullable|boolean',
            ]);

            $service = new UserManagerService($router);
            $service->connect();

            $usernames = $request->input('usernames', []);
            $deletePPP = $request->boolean('delete_from_ppp', $request->boolean('delete_ppp', false));

            $result = $service->migratePPPUsers(
                $validated['group'],
                $deletePPP,
                $usernames
            );

            // Sync UserManager users after migration
            if ($result['migrated'] > 0) {
                $service->syncUsers();
            }

            $service->disconnect();

            $message = "تم نقل {$result['migrated']} مستخدم بنجاح";
            if ($result['failed'] > 0) {
                $message .= " (فشل {$result['failed']})";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل النقل: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get profiles for a router
     */
    public function getProfiles(Router $router)
    {
        try {
            $this->authorize('view', $router);

            $service = new UserManagerService($router);
            $service->connect();
            
            $profiles = $service->getProfiles();
            
            $service->disconnect();

            return response()->json([
                'success' => true,
                'profiles' => $profiles,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل جلب الـ Profiles: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign profile to all users
     */
    public function assignProfileToAll(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $validated = $request->validate([
                'profile' => 'required|string',
            ]);

            $service = new UserManagerService($router);
            $service->connect();

            $result = $service->assignProfileToAllUsers($validated['profile']);

            $service->disconnect();

            $message = "تم ربط {$result['assigned']} مستخدم بالـ Profile بنجاح";
            if ($result['failed'] > 0) {
                $message .= " (فشل {$result['failed']})";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل الربط: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change profile for a single user
     */
    public function changeUserProfile(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $validated = $request->validate([
                'user_id' => 'required|integer',
                'mikrotik_id' => 'required|string',
                'profile' => 'required|string',
            ]);

            $service = new UserManagerService($router);
            $service->connect();

            // Update user profile on MikroTik
            $result = $service->changeUserProfile($validated['mikrotik_id'], $validated['profile']);

            $service->disconnect();

            // Update local database
            if ($result) {
                Subscriber::where('id', $validated['user_id'])
                    ->update(['profile' => $validated['profile']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تغيير الباقة بنجاح',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تغيير الباقة: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Throttle/Disable a user - change to throttled-limit profile
     */
    public function throttleUser(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $validated = $request->validate([
                'user_id' => 'required|integer',
                'mikrotik_id' => 'required|string',
            ]);

            $subscriber = Subscriber::find($validated['user_id']);
            if (!$subscriber) {
                return response()->json([
                    'success' => false,
                    'message' => 'المشترك غير موجود',
                ], 404);
            }

            // Save original profile before throttling
            $originalProfile = $subscriber->profile;
            
            $service = new UserManagerService($router);
            $service->connect();

            // Change to STOP profile
            $result = $service->changeUserProfile($validated['mikrotik_id'], 'STOP');

            $service->disconnect();

            // Update local database
            if ($result) {
                $subscriber->update([
                    'original_profile' => $originalProfile,
                    'profile' => 'STOP',
                    'is_throttled' => true,
                    'throttled_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تعطيل المشترك بنجاح',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تعطيل المشترك: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enable/Unthrottle a user - restore original profile
     */
    public function enableUser(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $validated = $request->validate([
                'user_id' => 'required|integer',
                'mikrotik_id' => 'required|string',
            ]);

            $subscriber = Subscriber::find($validated['user_id']);
            if (!$subscriber) {
                return response()->json([
                    'success' => false,
                    'message' => 'المشترك غير موجود',
                ], 404);
            }

            // Get original profile
            $originalProfile = $subscriber->original_profile;
            if (!$originalProfile) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا يوجد باقة أصلية محفوظة. استخدم التجديد لاختيار باقة جديدة.',
                ], 400);
            }

            $service = new UserManagerService($router);
            $service->connect();

            // Restore original profile
            $result = $service->changeUserProfile($validated['mikrotik_id'], $originalProfile);

            $service->disconnect();

            // Update local database
            if ($result) {
                $subscriber->update([
                    'profile' => $originalProfile,
                    'is_throttled' => false,
                    'throttled_at' => null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم تمكين المشترك بنجاح',
                'profile' => $originalProfile,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل تمكين المشترك: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show packages router selection page
     */
    public function packagesIndex()
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id')->toArray()
            : $user->routers()->pluck('routers.id')->toArray();

        $routers = Router::whereIn('id', $routerIds)->get();
        
        // Check connection status for each router
        $routersWithStatus = $routers->map(function($router) {
            $router->is_online = UserManagerService::isRouterReachable($router);
            return $router;
        });

        return view('usermanager.packages-select', [
            'routers' => $routersWithStatus
        ]);
    }

    /**
     * Show packages/groups management page
     */
    public function packages(Router $router)
    {
        try {
            $this->authorize('view', $router);

            // Quick check if router is reachable
            if (!UserManagerService::isRouterReachable($router)) {
                return redirect()->route('usermanager.index')
                    ->with('error', 'الراوتر "' . $router->name . '" غير متصل أو غير متاح حالياً');
            }

            $service = new UserManagerService($router);
            $service->connect();
            
            $profiles = $service->getProfiles();
            $limitations = $service->getLimitations();
            $profileLimitations = $service->getProfileLimitations();
            $userGroups = $service->getUserGroups();
            $attributes = $service->getAttributes();
            
            $service->disconnect();

            return view('usermanager.packages', compact('router', 'profiles', 'limitations', 'profileLimitations', 'userGroups', 'attributes'));
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return redirect()->route('usermanager.index')
                ->with('error', 'ليس لديك صلاحية الوصول لهذا الراوتر');
        } catch (Exception $e) {
            return redirect()->route('usermanager.index')
                ->with('error', 'فشل تحميل الباقات: ' . $e->getMessage());
        }
    }

    /**
     * Get packages data as JSON
     */
    public function getPackages(Router $router)
    {
        try {
            $this->authorize('view', $router);

            // Quick check if router is reachable
            if (!UserManagerService::isRouterReachable($router)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الراوتر غير متصل أو غير متاح حالياً',
                ], 503);
            }

            $service = new UserManagerService($router);
            $service->connect();
            
            $profiles = $service->getProfiles();
            $limitations = $service->getLimitations();
            
            $service->disconnect();

            return response()->json([
                'success' => true,
                'profiles' => $profiles,
                'limitations' => $limitations,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل جلب البيانات: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new package (Limitation + Profile)
     */
    public function createPackage(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $validated = $request->validate([
                'name' => 'required|string|max:50',
                'download_speed' => 'required|numeric|min:1',
                'upload_speed' => 'required|numeric|min:1',
                'speed_unit' => 'required|in:K,M',
                'data_limit' => 'nullable|numeric|min:0',
                'data_unit' => 'nullable|in:MB,GB',
                'validity_value' => 'nullable|numeric|min:1',
                'validity_unit' => 'nullable|in:h,d,w,m',
                'price' => 'nullable|numeric|min:0',
                'shared_users' => 'nullable|integer|min:1|max:10',
            ]);

            // Build rate limit string (e.g., "10M/20M")
            $downloadSpeed = $validated['download_speed'] . $validated['speed_unit'];
            $uploadSpeed = $validated['upload_speed'] . $validated['speed_unit'];
            $rateLimit = "{$uploadSpeed}/{$downloadSpeed}";

            // Calculate data limit in bytes
            $dataLimit = null;
            if (!empty($validated['data_limit']) && $validated['data_limit'] > 0) {
                $dataLimit = $validated['data_limit'];
                if ($validated['data_unit'] === 'GB') {
                    $dataLimit = $dataLimit * 1024 * 1024 * 1024;
                } else {
                    $dataLimit = $dataLimit * 1024 * 1024;
                }
            }

            // Build validity string
            $validity = null;
            if (!empty($validated['validity_value'])) {
                $validity = $validated['validity_value'] . $validated['validity_unit'];
            }

            // Quick check if router is reachable
            if (!UserManagerService::isRouterReachable($router)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الراوتر غير متصل أو غير متاح حالياً',
                ], 503);
            }

            $service = new UserManagerService($router);
            $service->connect();

            $result = $service->createPackage([
                'name' => $validated['name'],
                'rate_limit' => $rateLimit,
                'data_limit' => $dataLimit,
                'validity' => $validity,
                'price' => $validated['price'] ?? 0,
                'shared_users' => $validated['shared_users'] ?? 1,
            ]);

            $service->disconnect();

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => "تم إنشاء الباقة '{$validated['name']}' بنجاح",
                    'data' => $result,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل إنشاء الباقة: ' . implode(', ', $result['errors']),
                ], 500);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إنشاء الباقة: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create multiple quick profiles at once
     */
    public function createQuickProfiles(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $validated = $request->validate([
                'profiles' => 'required|array|min:1',
                'profiles.*.name' => 'required|string|max:50',
                'profiles.*.download' => 'required|string',
                'profiles.*.upload' => 'required|string',
                'profiles.*.price' => 'nullable|numeric|min:0',
                'profiles.*.validity' => 'nullable|string',
            ]);

            // Quick check if router is reachable
            if (!UserManagerService::isRouterReachable($router)) {
                return response()->json([
                    'success' => false,
                    'message' => 'الراوتر غير متصل أو غير متاح حالياً',
                ], 503);
            }

            $service = new UserManagerService($router);
            $service->connect();

            // Get existing profiles and limitations with their IDs for deletion
            $existingProfiles = collect($service->getProfiles())->keyBy('name')->toArray();
            $existingLimitations = collect($service->getLimitations())->keyBy('name')->toArray();
            $existingLinks = $service->getProfileLimitations();

            $created = 0;
            $replaced = 0;
            $errors = [];

            // Add throttled-limit profile to the list (for user suspension/throttling)
            $profilesToCreate = $validated['profiles'];
            $profilesToCreate[] = [
                'name' => 'throttled-limit',
                'download' => '1k',
                'upload' => '1k',
                'price' => 0,
                'validity' => '365d',
            ];

            foreach ($profilesToCreate as $profile) {
                $name = $profile['name'];
                
                // Delete existing profile and limitation if they exist (force replace)
                try {
                    // Delete profile-limitation links first
                    foreach ($existingLinks as $link) {
                        if (($link['profile'] ?? '') === $name) {
                            $service->deleteProfileLimitation($link['.id']);
                        }
                    }
                    
                    // Delete existing profile
                    if (isset($existingProfiles[$name])) {
                        $service->deleteProfile($existingProfiles[$name]['.id']);
                        $replaced++;
                    }
                    
                    // Delete existing limitation
                    if (isset($existingLimitations[$name])) {
                        $service->deleteLimitation($existingLimitations[$name]['.id']);
                    }
                } catch (Exception $e) {
                    Log::warning("Could not delete existing profile/limitation {$name}: " . $e->getMessage());
                }

                try {
                    // Build rate limit string
                    $download = $profile['download'];
                    $upload = $profile['upload'];
                    
                    // Handle unlimited (0 speed means no limit)
                    if ($download === '0' || $upload === '0') {
                        $rateLimit = null; // No rate limit for unlimited
                    } else {
                        $rateLimit = "{$upload}/{$download}";
                    }

                    $result = $service->createPackage([
                        'name' => $name,
                        'rate_limit' => $rateLimit,
                        'data_limit' => null,
                        'validity' => $profile['validity'] ?? '30d',
                        'price' => $profile['price'] ?? 0,
                        'shared_users' => 1,
                    ]);

                    if ($result['success']) {
                        $created++;
                    } else {
                        $errors[] = $name . ': ' . implode(', ', $result['errors'] ?? ['Unknown error']);
                    }
                } catch (Exception $e) {
                    $errors[] = $name . ': ' . $e->getMessage();
                }
            }

            $service->disconnect();

            $message = "تم إنشاء {$created} باقة بنجاح";
            if ($replaced > 0) {
                $message .= " (تم استبدال {$replaced} باقة موجودة)";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'created' => $created,
                'replaced' => $replaced,
                'errors' => $errors,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إنشاء الباقات: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a package (Profile + Limitation)
     */
    public function deletePackage(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $validated = $request->validate([
                'profile_id' => 'nullable|string',
                'limitation_id' => 'nullable|string',
            ]);

            $service = new UserManagerService($router);
            $service->connect();

            $deleted = [];

            if (!empty($validated['profile_id'])) {
                $service->deleteProfile($validated['profile_id']);
                $deleted[] = 'Profile';
            }

            if (!empty($validated['limitation_id'])) {
                $service->deleteLimitation($validated['limitation_id']);
                $deleted[] = 'Limitation';
            }

            $service->disconnect();

            return response()->json([
                'success' => true,
                'message' => 'تم الحذف بنجاح: ' . implode(', ', $deleted),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل الحذف: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new User Group
     */
    public function createUserGroup(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $validated = $request->validate([
                'name' => 'required|string|max:50',
                'outer_auths' => 'nullable|array',
                'inner_auths' => 'nullable|array',
                'attributes' => 'nullable|string',
            ]);

            // Build outer-auths string
            $outerAuths = '';
            if (!empty($validated['outer_auths'])) {
                $outerAuths = implode(',', $validated['outer_auths']);
            }

            // Build inner-auths string
            $innerAuths = '';
            if (!empty($validated['inner_auths'])) {
                $innerAuths = implode(',', $validated['inner_auths']);
            }

            $service = new UserManagerService($router);
            $service->connect();

            $result = $service->createUserGroup([
                'name' => $validated['name'],
                'outer-auths' => $outerAuths,
                'inner-auths' => $innerAuths,
                'attributes' => $validated['attributes'] ?? '',
            ]);

            $service->disconnect();

            return response()->json([
                'success' => true,
                'message' => "تم إنشاء المجموعة '{$validated['name']}' بنجاح",
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إنشاء المجموعة: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a User Group
     */
    public function deleteUserGroup(Request $request, Router $router)
    {
        try {
            $this->authorize('view', $router);

            $validated = $request->validate([
                'group_id' => 'required|string',
            ]);

            $service = new UserManagerService($router);
            $service->connect();
            
            $service->deleteUserGroup($validated['group_id']);
            
            $service->disconnect();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المجموعة بنجاح',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل الحذف: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export UserManager users backup
     */
    public function exportBackup(Request $request)
    {
        try {
            $user = Auth::user();
            $routerId = $request->router_id;
            
            $router = Router::findOrFail($routerId);
            $this->authorize('view', $router);
            
            $service = new UserManagerService($router);
            $service->connect();
            
            // Get all users from router
            $users = $service->getUsers();
            $service->disconnect();
            
            $usersData = [];
            foreach ($users as $u) {
                if (!isset($u['name'])) continue;
                
                $usersData[] = [
                    'username' => $u['name'] ?? '',
                    'password' => $u['password'] ?? '',
                    'group' => $u['group'] ?? '',
                    'shared_users' => $u['shared-users'] ?? '1',
                    'disabled' => ($u['disabled'] ?? 'false') === 'true',
                    'comment' => $u['comment'] ?? '',
                    'attributes' => $u['attributes'] ?? '',
                ];
            }
            
            $backup = [
                'backup_date' => now()->format('Y-m-d H:i:s'),
                'router_name' => $router->name,
                'router_ip' => $router->host,
                'total_users' => count($usersData),
                'users' => $usersData,
            ];
            
            return response()->json([
                'success' => true,
                'router_name' => $router->name,
                'backup' => $backup
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل التصدير: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Import/Restore UserManager users from backup
     */
    public function importBackup(Request $request)
    {
        try {
            $user = Auth::user();
            $routerId = $request->router_id;
            $backup = $request->backup;
            
            if (!$backup || !isset($backup['users']) || !is_array($backup['users'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'ملف النسخة الاحتياطية غير صالح'
                ], 400);
            }
            
            $router = Router::findOrFail($routerId);
            $this->authorize('view', $router);
            
            $service = new UserManagerService($router);
            $service->connect();
            
            $restored = 0;
            $failed = 0;
            $skipped = 0;
            
            // Get existing users to avoid duplicates
            $existingUsers = $service->getUsers();
            $existingUsernames = array_column($existingUsers, 'name');
            
            foreach ($backup['users'] as $u) {
                if (!isset($u['username']) || !isset($u['password'])) {
                    $failed++;
                    continue;
                }
                
                // Skip if already exists
                if (in_array($u['username'], $existingUsernames)) {
                    $skipped++;
                    continue;
                }
                
                try {
                    // Add user to router
                    $service->createUser([
                        'name' => $u['username'],
                        'password' => $u['password'],
                        'group' => $u['group'] ?? 'default',
                        'shared-users' => $u['shared_users'] ?? '1',
                        'disabled' => !empty($u['disabled']) ? 'true' : 'false',
                        'comment' => $u['comment'] ?? '',
                    ]);
                    $restored++;
                    
                } catch (Exception $e) {
                    $failed++;
                }
            }
            
            $service->disconnect();
            
            // Also save to local database
            foreach ($backup['users'] as $u) {
                if (in_array($u['username'], $existingUsernames)) continue;
                
                Subscriber::updateOrCreate(
                    ['router_id' => $router->id, 'username' => $u['username']],
                    [
                        'password' => $u['password'],
                        'type' => 'usermanager',
                        'profile' => $u['group'] ?? 'default',
                        'status' => !empty($u['disabled']) ? 'disabled' : 'active',
                    ]
                );
            }
            
            $message = "تم استعادة {$restored} مستخدم بنجاح";
            if ($skipped > 0) $message .= " (تم تجاهل {$skipped} موجود مسبقاً)";
            if ($failed > 0) $message .= " (فشل {$failed})";
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'restored' => $restored,
                'skipped' => $skipped,
                'failed' => $failed
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل الاستعادة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check for updates - returns latest update timestamp for real-time sync
     */
    public function checkUpdates(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin()
            ? Router::pluck('id')->toArray()
            : $user->routers()->pluck('routers.id')->toArray();
        
        if (empty($routerIds)) {
            return response()->json(['last_update' => null, 'count' => 0]);
        }
        
        // Get the latest update timestamp
        $lastUpdate = Subscriber::whereIn('router_id', $routerIds)
            ->where('type', 'usermanager')
            ->max('updated_at');
        
        // Get counts for stats
        $stats = [
            'total' => Subscriber::whereIn('router_id', $routerIds)->where('type', 'usermanager')->count(),
            'active' => Subscriber::whereIn('router_id', $routerIds)->where('type', 'usermanager')->where('status', 'active')->count(),
            'expired' => Subscriber::whereIn('router_id', $routerIds)->where('type', 'usermanager')->where('status', 'expired')->count(),
            'unpaid' => Subscriber::whereIn('router_id', $routerIds)->where('type', 'usermanager')->where('is_paid', false)->count(),
        ];
        
        // Check if specific subscribers were updated since timestamp
        $since = $request->input('since');
        $updatedSubscribers = [];
        
        if ($since) {
            $updatedSubscribers = Subscriber::whereIn('router_id', $routerIds)
                ->where('type', 'usermanager')
                ->where('updated_at', '>', $since)
                ->select('id', 'username', 'profile', 'status', 'total_bytes', 'data_limit', 'is_paid', 'remaining_amount', 'subscription_price', 'updated_at')
                ->get()
                ->toArray();
        }
        
        return response()->json([
            'last_update' => $lastUpdate,
            'stats' => $stats,
            'updated_subscribers' => $updatedSubscribers,
            'has_updates' => $since ? count($updatedSubscribers) > 0 : false
        ]);
    }

    /**
     * تحديث أسعار جميع المشتركين حسب الباقة
     */
    public function bulkUpdatePrices(Request $request)
    {
        $request->validate([
            'router_id' => 'required|exists:routers,id',
            'profile' => 'required|string',
            'subscription_price' => 'required|numeric|min:0',
            'data_limit_gb' => 'nullable|numeric|min:0',
        ]);

        $router = Router::findOrFail($request->router_id);

        $query = Subscriber::where('router_id', $router->id)
            ->where('type', 'usermanager')
            ->where('profile', $request->profile);

        $updateData = ['subscription_price' => $request->subscription_price];
        
        if ($request->filled('data_limit_gb')) {
            $updateData['data_limit_gb'] = $request->data_limit_gb;
            $updateData['data_limit'] = (float)$request->data_limit_gb > 0 ? (int)((float)$request->data_limit_gb * 1073741824) : null;
        }

        $count = $query->update($updateData);

        return response()->json([
            'success' => true,
            'message' => "تم تحديث {$count} مشترك في باقة {$request->profile}",
            'count' => $count
        ]);
    }
}
