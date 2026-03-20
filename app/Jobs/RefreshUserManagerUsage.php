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
use Exception;

class RefreshUserManagerUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900; // 15 minutes
    public $tries = 1;

    /**
     * Execute the job - Refresh usage data for all UserManager users
     * يقوم بتحديث بيانات الاستهلاك من الـ Sessions لجميع المستخدمين
     */
    public function handle(): void
    {
        Log::info('RefreshUserManagerUsage: بدء تحديث بيانات الاستهلاك...');

        $routers = Router::where('is_active', true)->get();
        
        $totalStats = [
            'routers' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        foreach ($routers as $router) {
            try {
                $result = $this->refreshRouterUsers($router);
                $totalStats['routers']++;
                $totalStats['updated'] += $result['updated'];
                $totalStats['skipped'] += $result['skipped'];
            } catch (Exception $e) {
                Log::error("RefreshUserManagerUsage: خطأ في الراوتر {$router->name}: " . $e->getMessage());
            }
        }

        Log::info("RefreshUserManagerUsage: انتهى - {$totalStats['routers']} راوتر، " .
            "تم تحديث {$totalStats['updated']} مستخدم، تخطي {$totalStats['skipped']}");
    }

    /**
     * Refresh users on a specific router by getting usage from UserManager directly
     * يجلب بيانات الاستهلاك من /user-manager/user/print مباشرة
     */
    protected function refreshRouterUsers(Router $router): array
    {
        if (!UserManagerService::isRouterReachable($router)) {
            Log::debug("RefreshUserManagerUsage: الراوتر {$router->name} غير متاح");
            return ['updated' => 0, 'skipped' => 0];
        }

        $service = new UserManagerService($router);
        
        if (!$service->connect()) {
            Log::warning("RefreshUserManagerUsage: فشل الاتصال بالراوتر {$router->name}");
            return ['updated' => 0, 'skipped' => 0];
        }

        $stats = ['updated' => 0, 'skipped' => 0];

        try {
            // الطريقة الأولى: جلب بيانات الاستهلاك من /user-manager/user/print مباشرة
            // هذه الطريقة تُظهر الاستهلاك الإجمالي للمستخدم حتى لو غير متصل
            $usageByUser = $this->getUsersUsageFromUserManager($service);
            
            // الطريقة الثانية: تحديث من Sessions للمتصلين حالياً (إضافية للدقة)
            $this->updateUsageFromSessions($service, $usageByUser);
            
            if (empty($usageByUser)) {
                Log::info("RefreshUserManagerUsage: لا يوجد بيانات استهلاك في {$router->name}");
                $service->disconnect();
                return $stats;
            }

            Log::info("RefreshUserManagerUsage: معالجة " . count($usageByUser) . " مستخدم في {$router->name}");

            // Update local database for each user
            foreach ($usageByUser as $username => $usage) {
                try {
                    $subscriber = Subscriber::where('router_id', $router->id)
                        ->where('username', $username)
                        ->first();
                    
                    if (!$subscriber) {
                        $stats['skipped']++;
                        continue;
                    }

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
                        ]),
                        'last_seen' => now(),
                    ]);
                    
                    $stats['updated']++;
                    
                } catch (Exception $e) {
                    Log::warning("RefreshUserManagerUsage: فشل تحديث {$username}: " . $e->getMessage());
                    $stats['skipped']++;
                }
            }

        } catch (Exception $e) {
            Log::error("RefreshUserManagerUsage: خطأ في {$router->name}: " . $e->getMessage());
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

    /**
     * جلب بيانات الاستهلاك من /user-manager/user/print مباشرة
     * هذه الطريقة تُعيد الاستهلاك الكلي للمستخدم حتى لو غير متصل
     */
    protected function getUsersUsageFromUserManager(UserManagerService $service): array
    {
        $usageByUser = [];
        
        try {
            $users = $service->getUsers();
            
            foreach ($users as $user) {
                $username = $user['name'] ?? $user['username'] ?? null;
                if (!$username) continue;
                
                // جلب بيانات الاستهلاك من UserManager 7
                // يستخدم total-download و total-upload للاستهلاك الكلي
                // أو actual-download و actual-upload للجلسة الحالية
                $download = $this->parseBytes(
                    $user['total-download'] ?? 
                    $user['actual-download'] ?? 
                    $user['download'] ?? 
                    '0'
                );
                
                $upload = $this->parseBytes(
                    $user['total-upload'] ?? 
                    $user['actual-upload'] ?? 
                    $user['upload'] ?? 
                    '0'
                );
                
                $uptime = $this->parseUptimeToSeconds(
                    $user['total-uptime'] ?? 
                    $user['actual-uptime'] ?? 
                    $user['uptime-used'] ?? 
                    '0s'
                );
                
                // حفظ البيانات إذا كان هناك استهلاك
                if ($download > 0 || $upload > 0) {
                    $usageByUser[$username] = [
                        'download' => $download,
                        'upload' => $upload,
                        'uptime' => $uptime,
                        'sessions' => 1,
                        'source' => 'usermanager',
                    ];
                    
                    Log::debug("RefreshUserManagerUsage: استهلاك {$username} من UserManager: download={$download}, upload={$upload}");
                }
            }
            
        } catch (Exception $e) {
            Log::warning("RefreshUserManagerUsage: فشل جلب بيانات المستخدمين: " . $e->getMessage());
        }
        
        return $usageByUser;
    }

    /**
     * تحديث بيانات الاستهلاك من Sessions (للمتصلين حالياً)
     * يُضاف إلى البيانات الموجودة من UserManager
     */
    protected function updateUsageFromSessions(UserManagerService $service, array &$usageByUser): void
    {
        try {
            $sessions = $service->getAllSessions();
            
            foreach ($sessions as $session) {
                $username = $session['user'] ?? null;
                if (!$username) continue;
                
                $sessionDownload = (int)($session['download'] ?? 0);
                $sessionUpload = (int)($session['upload'] ?? 0);
                $sessionUptime = $this->parseUptimeToSeconds($session['uptime'] ?? '0s');
                
                if (!isset($usageByUser[$username])) {
                    // مستخدم جديد من Sessions فقط (لم يتوفر من UserManager)
                    $usageByUser[$username] = [
                        'download' => $sessionDownload,
                        'upload' => $sessionUpload,
                        'uptime' => $sessionUptime,
                        'sessions' => 1,
                        'source' => 'session',
                    ];
                }
                // لا نستبدل بيانات UserManager ببيانات Sessions لتجنب التضخم
            }
            
        } catch (Exception $e) {
            Log::warning("RefreshUserManagerUsage: فشل جلب Sessions: " . $e->getMessage());
        }
    }

    /**
     * تحويل قيمة bytes من صيغة RouterOS إلى رقم
     */
    protected function parseBytes($value): int
    {
        if (empty($value) || $value === '0') return 0;
        
        // إذا كان رقم صريح
        if (is_numeric($value)) {
            return (int)$value;
        }
        
        // معالجة صيغ مثل "1.5G" أو "500M" أو "100K"
        $value = strtoupper(trim($value));
        
        if (preg_match('/^([\d.]+)\s*([KMGT])?B?$/i', $value, $matches)) {
            $number = (float)$matches[1];
            $unit = $matches[2] ?? '';
            
            switch ($unit) {
                case 'K': return (int)($number * 1024);
                case 'M': return (int)($number * 1024 * 1024);
                case 'G': return (int)($number * 1024 * 1024 * 1024);
                case 'T': return (int)($number * 1024 * 1024 * 1024 * 1024);
                default: return (int)$number;
            }
        }
        
        return (int)$value;
    }
}
