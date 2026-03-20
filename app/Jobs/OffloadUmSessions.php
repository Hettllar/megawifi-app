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

class OffloadUmSessions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    public function handle(): void
    {
        Log::info('OffloadUmSessions: بدء أرشفة جلسات UserManager...');

        $routers = Router::where('is_active', true)->get();
        $totalArchived = 0;
        $totalErrors = 0;

        foreach ($routers as $router) {
            try {
                // Circuit breaker
                $cbKey = 'offload_cb_' . $router->id;
                if (Cache::get($cbKey, 0) >= 1) continue;

                $connectionIP = $router->wg_enabled && $router->wg_client_ip
                    ? $router->wg_client_ip : $router->ip_address;
                $port = $router->api_port ?: 8728;
                $sock = @fsockopen($connectionIP, $port, $errno, $errstr, 3);
                if (!$sock) {
                    Cache::put($cbKey, 1, now()->addMinutes(30));
                    continue;
                }
                @fclose($sock);

                $archived = $this->offloadRouter($router);
                $totalArchived += $archived;
                try { Cache::forget($cbKey); } catch (\Exception $e) {}
            } catch (\Exception $e) {
                Log::error("OffloadUmSessions: خطأ {$router->name}: " . $e->getMessage());
                Cache::put('offload_cb_' . $router->id, 1, now()->addMinutes(30));
                $totalErrors++;
            }
        }

        Log::info("OffloadUmSessions: تم أرشفة {$totalArchived} جلسة، أخطاء: {$totalErrors}");
    }

    protected function offloadRouter(Router $router): int
    {
        $service = new UserManagerService($router);
        if (!$service->connect()) {
            Log::warning("OffloadUmSessions: فشل الاتصال بالراوتر {$router->name}");
            return 0;
        }

        $archived = 0;

        try {
            $sessions = $service->getAllSessions();
            if (empty($sessions)) {
                $service->disconnect();
                return 0;
            }

            // Load subscribers for this router
            $subscribers = Subscriber::where('router_id', $router->id)
                ->get()->keyBy('username');

            foreach ($sessions as $session) {
                $umId = $session['.id'] ?? null;
                $username = $session['user'] ?? null;
                if (!$umId || !$username) continue;

                // Skip if already archived
                if (SessionHistory::where('um_session_id', $umId)
                    ->where('router_id', $router->id)->exists()) {
                    continue;
                }

                // Only archive ended sessions (have ended field or not active)
                $isActive = isset($session['active']) && $session['active'] === 'true';
                if ($isActive) continue;

                $subscriber = $subscribers->get($username);

                $download = (int)($session['download'] ?? 0);
                $upload = (int)($session['upload'] ?? 0);

                SessionHistory::create([
                    'router_id' => $router->id,
                    'subscriber_id' => $subscriber ? $subscriber->id : null,
                    'username' => $username,
                    'session_id' => $session['session-id'] ?? null,
                    'um_session_id' => $umId,
                    'type' => 'ppp',
                    'mac_address' => $session['caller-id'] ?? null,
                    'ip_address' => $session['ip-address'] ?? null,
                    'started_at' => $this->parseDate($session['started'] ?? null),
                    'ended_at' => $this->parseDate($session['ended'] ?? null) ?? now(),
                    'duration' => $this->parseDuration($session['uptime'] ?? '0'),
                    // uptime comes as '31s' or '2h3m' from UM
                    'bytes_in' => $download,
                    'bytes_out' => $upload,
                    'total_bytes' => $download + $upload,
                    'source' => 'offload',
                ]);

                $archived++;
            }
        } catch (\Exception $e) {
            Log::error("OffloadUmSessions: خطأ أثناء الأرشفة {$router->name}: " . $e->getMessage());
        } finally {
            $service->disconnect();
        }

        if ($archived > 0) {
            Log::info("OffloadUmSessions: تم أرشفة {$archived} جلسة من {$router->name}");
        }

        return $archived;
    }

    private function parseBytes(string $val): int
    {
        if (is_numeric($val)) return (int)$val;
        $val = strtoupper(trim($val));
        $multipliers = ['K' => 1024, 'M' => 1048576, 'G' => 1073741824, 'T' => 1099511627776];
        foreach ($multipliers as $suffix => $mult) {
            if (str_ends_with($val, $suffix . 'IB') || str_ends_with($val, $suffix . 'B') || str_ends_with($val, $suffix)) {
                return (int)(floatval($val) * $mult);
            }
        }
        return (int)$val;
    }

    private function parseDuration(string $val): int
    {
        // MikroTik format: 1d2h3m4s or 2h3m4s or 3m4s
        $seconds = 0;
        if (preg_match('/(\d+)w/', $val, $m)) $seconds += $m[1] * 604800;
        if (preg_match('/(\d+)d/', $val, $m)) $seconds += $m[1] * 86400;
        if (preg_match('/(\d+)h/', $val, $m)) $seconds += $m[1] * 3600;
        if (preg_match('/(\d+)m/', $val, $m)) $seconds += $m[1] * 60;
        if (preg_match('/(\d+)s/', $val, $m)) $seconds += $m[1];
        return $seconds ?: (int)$val;
    }

    private function parseDate(?string $val): ?\Carbon\Carbon
    {
        if (!$val) return null;
        try {
            return \Carbon\Carbon::parse($val);
        } catch (\Exception $e) {
            return null;
        }
    }
}