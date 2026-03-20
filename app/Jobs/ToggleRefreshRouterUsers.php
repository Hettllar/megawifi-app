<?php

namespace App\Jobs;

use App\Models\Router;
use App\Models\Subscriber;
use App\Services\UserManagerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class ToggleRefreshRouterUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes max
    public $tries = 1;
    
    protected int $routerId;

    public function __construct(int $routerId)
    {
        $this->routerId = $routerId;
    }

    /**
     * Execute the job - Toggle users on specific router to refresh their usage data
     */
    public function handle(): void
    {
        $router = Router::find($this->routerId);
        
        if (!$router || !$router->is_active) {
            Log::debug("ToggleRefreshRouterUsers: الراوتر {$this->routerId} غير موجود أو غير نشط");
            return;
        }

        Log::info("ToggleRefreshRouterUsers: بدء Toggle للراوتر {$router->name}");

        $stats = $this->toggleRouterUsers($router);
        
        // Update last toggle time for this router
        $router->update(['last_toggle_sync' => now()]);

        Log::info("ToggleRefreshRouterUsers: انتهى {$router->name} - Toggle: {$stats['toggled']}، فشل: {$stats['failed']}، تخطي: {$stats['skipped']}");
    }

    /**
     * Toggle all users on the router
     */
    protected function toggleRouterUsers(Router $router): array
    {
        $stats = ['toggled' => 0, 'failed' => 0, 'skipped' => 0];

        if (!UserManagerService::isRouterReachable($router)) {
            Log::debug("ToggleRefreshRouterUsers: الراوتر {$router->name} غير متاح");
            return $stats;
        }

        $service = new UserManagerService($router);
        
        if (!$service->connect()) {
            Log::warning("ToggleRefreshRouterUsers: فشل الاتصال بالراوتر {$router->name}");
            return $stats;
        }

        try {
            // Get all active subscribers for this router
            $subscribers = Subscriber::where('router_id', $router->id)
                ->whereNotNull('mikrotik_id')
                ->where('mikrotik_id', '!=', '')
                ->where('status', '!=', 'expired')
                ->get();

            Log::info("ToggleRefreshRouterUsers: معالجة " . $subscribers->count() . " مشترك في {$router->name}");

            foreach ($subscribers as $subscriber) {
                try {
                    // Toggle: Disable then Enable
                    $service->toggleUserStatus($subscriber->mikrotik_id, true);  // Disable
                    usleep(200000); // Wait 200ms
                    $service->toggleUserStatus($subscriber->mikrotik_id, false); // Enable

                    // Get updated usage from sessions
                    $sessions = $service->getUserSessions($subscriber->username);
                    
                    $totalDownload = 0;
                    $totalUpload = 0;
                    $totalUptime = 0;
                    
                    foreach ($sessions as $session) {
                        if (isset($session['download'])) $totalDownload += (int)$session['download'];
                        if (isset($session['upload'])) $totalUpload += (int)$session['upload'];
                        if (isset($session['uptime'])) $totalUptime += $this->parseUptimeToSeconds($session['uptime']);
                    }

                    $totalBytes = $totalDownload + $totalUpload;

                    // Update subscriber
                    $subscriber->update([
                        'bytes_in' => $totalUpload,
                        'bytes_out' => $totalDownload,
                        'total_bytes' => $totalBytes,
                        'um_data' => json_encode([
                            'download_used' => $totalDownload,
                            'upload_used' => $totalUpload,
                            'total_uptime' => $this->formatUptime($totalUptime),
                            'sessions_count' => count($sessions),
                            'last_toggle_sync' => now()->format('Y-m-d H:i:s'),
                        ]),
                        'last_seen' => now(),
                    ]);

                    $stats['toggled']++;

                    // Small delay between users to avoid overloading
                    usleep(100000); // 100ms delay

                } catch (Exception $e) {
                    Log::warning("ToggleRefreshRouterUsers: فشل toggle للمشترك {$subscriber->username}: " . $e->getMessage());
                    $stats['failed']++;
                }
            }

            // Sync sessions and update is_online status using PPP active connections
            try {
                // Use PPP active connections (not UserManager sessions) for online detection
                $pppActive = [];
                try {
                    $mikrotikService = new \App\Services\MikroTikService($router);
                    $mikrotikService->connect();
                    $pppActive = $mikrotikService->getPPPActive();
                    $mikrotikService->disconnect();
                } catch (\Exception $e2) {
                    Log::debug("ToggleRefreshRouterUsers: PPP active query failed: " . $e2->getMessage());
                }
                
                $onlineUsernames = [];
                foreach ($pppActive as $s) {
                    if (isset($s['name'])) {
                        $onlineUsernames[] = $s['name'];
                    }
                }

                // Mark active session users as online
                if (!empty($onlineUsernames)) {
                    Subscriber::where('router_id', $router->id)
                        ->whereIn('username', $onlineUsernames)
                        ->update(['is_online' => true]);
                }

                // Mark users without sessions as offline
                Subscriber::where('router_id', $router->id)
                    ->whereNotIn('username', $onlineUsernames ?: ['__none__'])
                    ->where('is_online', true)
                    ->update(['is_online' => false]);

                Log::info("ToggleRefreshRouterUsers: تحديث حالة الاتصال - متصل: " . count($onlineUsernames) . " في {$router->name}");
            } catch (Exception $e) {
                Log::warning("ToggleRefreshRouterUsers: فشل تحديث حالة الاتصال لـ {$router->name}: " . $e->getMessage());
            }

        } catch (Exception $e) {
            Log::error("ToggleRefreshRouterUsers: خطأ في {$router->name}: " . $e->getMessage());
        } finally {
            $service->disconnect();
        }

        return $stats;
    }

    /**
     * Parse uptime string to seconds
     */
    protected function parseUptimeToSeconds($uptime): int
    {
        if (empty($uptime) || $uptime === '0s') return 0;
        
        $seconds = 0;
        
        if (preg_match('/(\d+)w/', $uptime, $m)) $seconds += (int)$m[1] * 604800;
        if (preg_match('/(\d+)d/', $uptime, $m)) $seconds += (int)$m[1] * 86400;
        if (preg_match('/(\d+)h/', $uptime, $m)) $seconds += (int)$m[1] * 3600;
        if (preg_match('/(\d+)m/', $uptime, $m)) $seconds += (int)$m[1] * 60;
        if (preg_match('/(\d+)s/', $uptime, $m)) $seconds += (int)$m[1];
        
        return $seconds;
    }

    /**
     * Format seconds to human readable uptime
     */
    protected function formatUptime(int $seconds): string
    {
        if ($seconds < 60) return $seconds . 's';
        
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        $result = '';
        if ($days > 0) $result .= $days . 'd';
        if ($hours > 0) $result .= $hours . 'h';
        if ($minutes > 0) $result .= $minutes . 'm';
        
        return $result ?: '0s';
    }
}
