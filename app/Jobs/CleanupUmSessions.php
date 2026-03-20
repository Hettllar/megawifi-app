<?php

namespace App\Jobs;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\SessionHistory;
use App\Services\UserManagerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CleanupUmSessions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    // حذف الجلسات الأقدم من هذا العدد من الأيام فقط
    protected int $olderThanDays = 3;

    // حد أقصى للحذف في كل دورة لكل راوتر
    protected int $batchLimit = 1500;

    // إعادة الاتصال بعد هذا العدد من عمليات الحذف (الراوتر يقطع بعد ~2000)
    protected int $reconnectEvery = 500;

    public function handle(): void
    {
        Log::info('CleanupUmSessions: بدء تنظيف جلسات UM القديمة...');

        $routers = Router::where('is_active', true)->get();
        $totalCleaned = 0;

        foreach ($routers as $router) {
            try {
                $cbKey = 'cleanup_cb_' . $router->id;
                if (Cache::get($cbKey, 0) >= 2) continue;

                $connectionIP = $router->wg_enabled && $router->wg_client_ip
                    ? $router->wg_client_ip : $router->ip_address;
                $port = $router->api_port ?: 8728;
                $sock = @fsockopen($connectionIP, $port, $errno, $errstr, 3);
                if (!$sock) {
                    try { Cache::put($cbKey, Cache::get($cbKey, 0) + 1, now()->addMinutes(30)); } catch (\Exception $e) {}
                    continue;
                }
                @fclose($sock);

                $cleaned = $this->cleanupRouter($router);
                $totalCleaned += $cleaned;
                try { Cache::forget($cbKey); } catch (\Exception $e) {}
            } catch (\Exception $e) {
                Log::error("CleanupUmSessions: خطأ {$router->name}: " . $e->getMessage());
                try { Cache::put('cleanup_cb_' . $router->id, 2, now()->addMinutes(30)); } catch (\Exception $e2) {}
            }
        }

        Log::info("CleanupUmSessions: تم حذف {$totalCleaned} جلسة قديمة من الراوترات");
    }

    protected function cleanupRouter(Router $router): int
    {
        $service = new UserManagerService($router);
        if (!$service->connect()) {
            return 0;
        }

        $totalDeleted = 0;

        // تحميل المشتركين لتحديث archived_bytes
        $subscribers = Subscriber::where('router_id', $router->id)
            ->get()->keyBy('username');

        try {
            $sessions = $service->getAllSessions();
            if (empty($sessions)) {
                $service->disconnect();
                return 0;
            }

            $cutoff = now()->subDays($this->olderThanDays);
            $batchDeleted = 0;

            // تجميع البايتات المحذوفة لكل مشترك لتحديثها دفعة واحدة
            $bytesToArchive = [];

            foreach ($sessions as $session) {
                if ($totalDeleted >= $this->batchLimit) break;

                $umId = $session['.id'] ?? null;
                if (!$umId) continue;

                // لا تحذف الجلسات النشطة أبدًا
                if (isset($session['active']) && $session['active'] === 'true') continue;

                // تحقق من وجود الجلسة مؤرشفة في السيرفر
                $isArchived = SessionHistory::where('um_session_id', $umId)
                    ->where('router_id', $router->id)
                    ->exists();
                if (!$isArchived) continue;

                // تحقق أن الجلسة أقدم من الحد الزمني
                $endedAt = $session['ended'] ?? $session['last-seen'] ?? null;
                if ($endedAt) {
                    try {
                        $endDate = \Carbon\Carbon::parse($endedAt);
                        if ($endDate->isAfter($cutoff)) continue;
                    } catch (\Exception $e) {
                        continue;
                    }
                }

                // ===== تسجيل البايتات قبل الحذف =====
                $username = $session['user'] ?? null;
                $sessionDownload = (int)($session['download'] ?? 0);
                $sessionUpload = (int)($session['upload'] ?? 0);
                $sessionBytes = $sessionDownload + $sessionUpload;

                if ($username && $sessionBytes > 0) {
                    if (!isset($bytesToArchive[$username])) {
                        $bytesToArchive[$username] = 0;
                    }
                    $bytesToArchive[$username] += $sessionBytes;
                }

                // إعادة الاتصال كل reconnectEvery عملية لتفادي قطع الراوتر
                if ($batchDeleted > 0 && $batchDeleted % $this->reconnectEvery === 0) {
                    try { $service->disconnect(); } catch (\Exception $e) {}
                    usleep(500000);
                    $service = new UserManagerService($router);
                    if (!$service->connect()) {
                        Log::warning("CleanupUmSessions: فشل إعادة الاتصال بـ {$router->name} بعد {$batchDeleted} عملية");
                        break;
                    }
                }

                // حذف آمن
                try {
                    $result = $service->disconnectSession($umId);
                    if (!isset($result['!trap'])) {
                        $totalDeleted++;
                        $batchDeleted++;
                    }
                } catch (\Exception $e) {
                    Log::warning("CleanupUmSessions: قطع اتصال {$router->name} بعد {$batchDeleted} حذف، إعادة اتصال...");
                    usleep(1000000);
                    $service = new UserManagerService($router);
                    if (!$service->connect()) break;
                }
            }

            // ===== تحديث archived_bytes لكل مشترك =====
            foreach ($bytesToArchive as $username => $bytes) {
                $subscriber = $subscribers->get($username);
                if ($subscriber) {
                    DB::table('subscribers')
                        ->where('id', $subscriber->id)
                        ->increment('archived_bytes', $bytes);
                }
            }

        } catch (\Exception $e) {
            Log::error("CleanupUmSessions: خطأ مع {$router->name}: " . $e->getMessage());
        } finally {
            try { $service->disconnect(); } catch (\Exception $e) {}
        }

        if ($totalDeleted > 0) {
            Log::info("CleanupUmSessions: حذف {$totalDeleted} جلسة من {$router->name}");
        }

        return $totalDeleted;
    }
}
