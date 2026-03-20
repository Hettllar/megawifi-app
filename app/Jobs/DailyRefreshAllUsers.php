<?php

namespace App\Jobs;

use App\Models\Router;
use App\Models\Subscriber;
use App\Services\UserManagerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Job لتحديث استهلاك جميع المشتركين يومياً
 * يعمل في الساعة 3:30 صباحاً
 * 
 * يقوم بـ:
 * 1. فصل كل مشترك وإعادة تفعيله (Toggle)
 * 2. جلب بيانات الاستهلاك من المستخدم مباشرة
 * 3. تحديث قاعدة البيانات
 * 
 * يستخدم ShouldBeUnique لمنع تشغيل نسختين في نفس الوقت
 */
class DailyRefreshAllUsers implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * عدد المحاولات في حالة الفشل
     * نضع 0 لتعطيل محاولات إعادة المحاولة (لأن المهمة طويلة)
     */
    public int $tries = 0;

    /**
     * المهلة الزمنية بالثواني (ساعة كاملة)
     */
    public int $timeout = 3600;

    /**
     * منع انتهاء المهلة أثناء التنفيذ
     */
    public bool $failOnTimeout = false;

    /**
     * مدة بقاء القفل (ساعة) لمنع التكرار
     */
    public int $uniqueFor = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('DailyRefreshAllUsers: ========== بدء التحديث اليومي لجميع المشتركين ==========');

        $routers = Router::where('is_active', true)->get();
        
        $stats = [
            'routers_processed' => 0,
            'routers_failed' => 0,
            'users_refreshed' => 0,
            'users_failed' => 0,
            'total_download' => 0,
            'total_upload' => 0,
        ];

        foreach ($routers as $router) {
            try {
                $result = $this->refreshRouterUsers($router);
                $stats['routers_processed']++;
                $stats['users_refreshed'] += $result['success'];
                $stats['users_failed'] += $result['failed'];
                $stats['total_download'] += $result['download'];
                $stats['total_upload'] += $result['upload'];
            } catch (Exception $e) {
                Log::error("DailyRefreshAllUsers: فشل في الراوتر {$router->name}: " . $e->getMessage());
                $stats['routers_failed']++;
            }
        }

        // الملخص النهائي
        $totalGB = number_format(($stats['total_download'] + $stats['total_upload']) / 1073741824, 2);
        
        Log::info("DailyRefreshAllUsers: ========== اكتمل التحديث اليومي ==========");
        Log::info("DailyRefreshAllUsers: الراوترات: {$stats['routers_processed']} ناجح، {$stats['routers_failed']} فاشل");
        Log::info("DailyRefreshAllUsers: المشتركين: {$stats['users_refreshed']} ناجح، {$stats['users_failed']} فاشل");
        Log::info("DailyRefreshAllUsers: إجمالي الاستهلاك المحدث: {$totalGB} GB");
    }

    /**
     * تحديث جميع مشتركي راوتر معين
     */
    protected function refreshRouterUsers(Router $router): array
    {
        Log::info("DailyRefreshAllUsers: جاري معالجة الراوتر {$router->name}...");

        $result = [
            'success' => 0,
            'failed' => 0,
            'download' => 0,
            'upload' => 0,
        ];

        // التحقق من إمكانية الاتصال
        if (!UserManagerService::isRouterReachable($router)) {
            Log::warning("DailyRefreshAllUsers: الراوتر {$router->name} غير متاح");
            throw new Exception("Router not reachable");
        }

        // جلب جميع مشتركي UserManager النشطين لهذا الراوتر
        $subscribers = Subscriber::where('router_id', $router->id)
            ->where('type', 'usermanager')
            ->whereIn('status', ['active', 'limited']) // نشط أو محدود
            ->whereNotNull('mikrotik_id')
            ->get();

        if ($subscribers->isEmpty()) {
            Log::info("DailyRefreshAllUsers: لا يوجد مشتركين نشطين في الراوتر {$router->name}");
            return $result;
        }

        Log::info("DailyRefreshAllUsers: وجد {$subscribers->count()} مشترك في {$router->name}");

        // الاتصال بالراوتر
        $service = new UserManagerService($router);
        $service->connect();

        foreach ($subscribers as $subscriber) {
            try {
                $refreshResult = $this->refreshSingleUser($service, $subscriber);
                if ($refreshResult) {
                    $result['success']++;
                    $result['download'] += $refreshResult['download'];
                    $result['upload'] += $refreshResult['upload'];
                } else {
                    $result['failed']++;
                }
            } catch (Exception $e) {
                Log::warning("DailyRefreshAllUsers: فشل تحديث {$subscriber->username}: " . $e->getMessage());
                $result['failed']++;
            }

            // تأخير بسيط بين كل مشترك لتجنب الضغط على الراوتر
            usleep(100000); // 100ms
        }

        $service->disconnect();

        Log::info("DailyRefreshAllUsers: انتهى الراوتر {$router->name} - {$result['success']} ناجح، {$result['failed']} فاشل");

        return $result;
    }

    /**
     * تحديث مشترك واحد (فصل وإعادة تفعيل)
     */
    protected function refreshSingleUser(UserManagerService $service, Subscriber $subscriber): ?array
    {
        try {
            // Toggle: فصل ثم إعادة تفعيل لإجبار تحديث البيانات
            $service->toggleUserStatus($subscriber->mikrotik_id, true);  // Disable
            usleep(500000); // انتظار 500ms لإغلاق الجلسة وتحديث العدادات
            $service->toggleUserStatus($subscriber->mikrotik_id, false); // Enable

            // انتظار قصير لتحديث بيانات المستخدم
            usleep(200000); // 200ms

            // جلب بيانات المستخدم مباشرة (تحتوي على actual-download و actual-upload)
            $userData = $service->getUserByUsername($subscriber->username);

            if (!$userData) {
                throw new Exception("User data not found");
            }

            // جلب الاستهلاك من بيانات المستخدم
            // UserManager 7 يستخدم actual-download/upload (الاستهلاك الحالي من الفترة)
            // و total-download/upload (الاستهلاك الكلي التاريخي)
            $totalDownload = $this->parseBytes($userData['actual-download'] ?? $userData['total-download'] ?? '0');
            $totalUpload = $this->parseBytes($userData['actual-upload'] ?? $userData['total-upload'] ?? '0');
            $totalUptime = $this->parseUptimeToSeconds($userData['actual-uptime'] ?? $userData['total-uptime'] ?? '0s');

            $totalBytes = $totalDownload + $totalUpload;

            // الحصول على الـ um_data الحالي لدمج البيانات
            $currentUmData = json_decode($subscriber->um_data ?? '{}', true) ?: [];
            
            // تحديث قاعدة البيانات
            $subscriber->update([
                'bytes_in' => $totalUpload,
                'bytes_out' => $totalDownload,
                'total_bytes' => $totalBytes,
                'um_data' => json_encode(array_merge($currentUmData, [
                    'download_used' => $totalDownload,
                    'upload_used' => $totalUpload,
                    'total_uptime' => $this->formatUptime($totalUptime),
                    'daily_refresh' => now()->toDateTimeString(),
                    'refresh_source' => 'user-manager-user',
                ])),
                'last_seen' => now(),
            ]);

            return [
                'download' => $totalDownload,
                'upload' => $totalUpload,
            ];

        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * تحويل صيغة الاستهلاك من MikroTik إلى bytes
     * مثال: "1.5G" => 1610612736, "100M" => 104857600
     */
    protected function parseBytes(string $value): int
    {
        $value = trim($value);
        if (empty($value) || $value === '0') {
            return 0;
        }

        // إذا كان رقم فقط
        if (is_numeric($value)) {
            return (int)$value;
        }

        // تحليل الوحدات
        $units = [
            'T' => 1099511627776,  // Terabyte
            'G' => 1073741824,     // Gigabyte
            'M' => 1048576,        // Megabyte
            'K' => 1024,           // Kilobyte
        ];

        foreach ($units as $unit => $multiplier) {
            if (preg_match('/^([\d.]+)\s*' . $unit . '/i', $value, $matches)) {
                return (int)((float)$matches[1] * $multiplier);
            }
        }

        return (int)$value;
    }

    /**
     * تحويل صيغة الوقت من MikroTik إلى ثواني
     * مثال: "1d2h30m45s" => 95445
     */
    protected function parseUptimeToSeconds(string $uptime): int
    {
        $seconds = 0;
        
        // أيام
        if (preg_match('/(\d+)d/', $uptime, $matches)) {
            $seconds += (int)$matches[1] * 86400;
        }
        
        // ساعات
        if (preg_match('/(\d+)h/', $uptime, $matches)) {
            $seconds += (int)$matches[1] * 3600;
        }
        
        // دقائق
        if (preg_match('/(\d+)m/', $uptime, $matches)) {
            $seconds += (int)$matches[1] * 60;
        }
        
        // ثواني
        if (preg_match('/(\d+)s/', $uptime, $matches)) {
            $seconds += (int)$matches[1];
        }
        
        return $seconds;
    }

    /**
     * تنسيق الثواني إلى صيغة مقروءة
     */
    protected function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        
        return empty($parts) ? '0m' : implode('', $parts);
    }
}
