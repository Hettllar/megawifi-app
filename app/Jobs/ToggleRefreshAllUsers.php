<?php

namespace App\Jobs;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\SyncSetting;
use App\Services\UserManagerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ToggleRefreshAllUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes max
    public $tries = 1;

    /**
     * Execute the job - Toggle all users to refresh their usage data
     * يقوم بتعطيل ثم تفعيل كل مشترك لتحديث بيانات الاستهلاك
     */
    public function handle(): void
    {
        Log::info('ToggleRefreshAllUsers: بدء تحديث الاستهلاك بـ Toggle لجميع المشتركين...');

        $routers = Router::where('is_active', true)->get();
        
        $totalStats = [
            'routers' => 0,
            'toggled' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($routers as $router) {
            try {
                $result = $this->toggleRouterUsers($router);
                $totalStats['routers']++;
                $totalStats['toggled'] += $result['toggled'];
                $totalStats['failed'] += $result['failed'];
                $totalStats['skipped'] += $result['skipped'];
            } catch (Exception $e) {
                Log::error("ToggleRefreshAllUsers: خطأ في الراوتر {$router->name}: " . $e->getMessage());
            }
        }

        // Note: last_toggle_refresh is updated in scheduler BEFORE dispatch to prevent duplicate jobs

        Log::info("ToggleRefreshAllUsers: انتهى - {$totalStats['routers']} راوتر، " .
            "Toggle: {$totalStats['toggled']}، فشل: {$totalStats['failed']}، تخطي: {$totalStats['skipped']}");
    }

    /**
     * Toggle all users on a specific router
     */
    protected function toggleRouterUsers(Router $router): array
    {
        $stats = ['toggled' => 0, 'failed' => 0, 'skipped' => 0];

        if (!UserManagerService::isRouterReachable($router)) {
            Log::debug("ToggleRefreshAllUsers: الراوتر {$router->name} غير متاح");
            return $stats;
        }

        $service = new UserManagerService($router);
        
        if (!$service->connect()) {
            Log::warning("ToggleRefreshAllUsers: فشل الاتصال بالراوتر {$router->name}");
            return $stats;
        }

        try {
            // Get all active subscribers for this router
            $subscribers = Subscriber::where('router_id', $router->id)
                ->whereNotNull('mikrotik_id')
                ->where('mikrotik_id', '!=', '')
                ->where('status', '!=', 'expired')
                ->get();

            Log::info("ToggleRefreshAllUsers: معالجة " . $subscribers->count() . " مشترك في {$router->name}");

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
                    Log::warning("ToggleRefreshAllUsers: فشل toggle للمشترك {$subscriber->username}: " . $e->getMessage());
                    $stats['failed']++;
                }
            }

        } catch (Exception $e) {
            Log::error("ToggleRefreshAllUsers: خطأ في {$router->name}: " . $e->getMessage());
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
