<?php
// تشخيص مشكلة حذف الجلسات وتأثيرها على جمع الجيجات
require_once '/var/www/megawifi/vendor/autoload.php';
$app = require_once '/var/www/megawifi/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Router;
use App\Models\Subscriber;
use App\Services\UserManagerService;

echo "=== تشخيص تأثير حذف الجلسات على الاستهلاك ===\n\n";

// 1. Check routers where cleanup ran
$routers = Router::where('is_active', true)->get();

foreach ($routers as $router) {
    $connectionIP = $router->wg_enabled && $router->wg_client_ip
        ? $router->wg_client_ip : $router->ip_address;
    $port = $router->api_port ?: 8728;
    $sock = @fsockopen($connectionIP, $port, $errno, $errstr, 3);
    if (!$sock) continue;
    @fclose($sock);

    echo "=== راوتر: {$router->name} (ID: {$router->id}) ===\n";

    $service = new UserManagerService($router);
    if (!$service->connect()) {
        echo "  فشل الاتصال\n\n";
        continue;
    }

    // Get UM users with their download/upload counters
    $umUsers = $service->getUsers();
    // Get UM sessions count
    $sessions = $service->getAllSessions();
    $service->disconnect();

    $sessionsByUser = [];
    foreach ($sessions as $s) {
        $u = $s['user'] ?? null;
        if (!$u) continue;
        if (!isset($sessionsByUser[$u])) $sessionsByUser[$u] = ['count' => 0, 'download' => 0, 'upload' => 0];
        $sessionsByUser[$u]['count']++;
        $sessionsByUser[$u]['download'] += (int)($s['download'] ?? 0);
        $sessionsByUser[$u]['upload'] += (int)($s['upload'] ?? 0);
    }

    // Get subscribers with data limits from DB
    $subs = Subscriber::where('router_id', $router->id)
        ->where('data_limit', '>', 0)
        ->get()
        ->keyBy('username');

    $problems = 0;
    foreach ($subs as $username => $sub) {
        // Find this user in UM
        $umUser = null;
        foreach ($umUsers as $u) {
            if (($u['name'] ?? $u['username'] ?? '') === $username) {
                $umUser = $u;
                break;
            }
        }

        if (!$umUser) continue;

        // Get UM counters
        $umDownload = (int)($umUser['total-download'] ?? $umUser['actual-download'] ?? $umUser['download'] ?? 0);
        $umUpload = (int)($umUser['total-upload'] ?? $umUser['actual-upload'] ?? $umUser['upload'] ?? 0);
        $umTotal = $umDownload + $umUpload;

        // Get session totals
        $sessionData = $sessionsByUser[$username] ?? ['count' => 0, 'download' => 0, 'upload' => 0];
        $sessionTotal = $sessionData['download'] + $sessionData['upload'];

        $dbTotal = $sub->total_bytes;

        // Check for mismatch: DB total much higher than UM total
        $umTotalGB = round($umTotal / 1073741824, 2);
        $sessionTotalGB = round($sessionTotal / 1073741824, 2);
        $dbTotalGB = round($dbTotal / 1073741824, 2);

        // Flag if DB is significantly higher than BOTH UM user counter and session sum
        if ($dbTotal > 0 && $umTotal > 0 && $dbTotal > $umTotal * 1.1) {
            echo "  ⚠️ {$username}: DB={$dbTotalGB}GB, UM_user={$umTotalGB}GB, Sessions={$sessionTotalGB}GB (sessions: {$sessionData['count']})\n";
            echo "     → DB أعلى من UM بـ " . round(($dbTotal - $umTotal) / 1073741824, 2) . "GB - الاستهلاك متجمد!\n";
            $problems++;
        } elseif ($dbTotal > 0 && $umTotal == 0) {
            echo "  ❌ {$username}: DB={$dbTotalGB}GB, UM_user=0, Sessions={$sessionTotalGB}GB - UM counters مفقودة!\n";
            $problems++;
        }
    }

    // Also check: sessions on router vs sessions archived
    $archivedCount = \DB::table('session_history')
        ->where('router_id', $router->id)
        ->where('source', 'offload')
        ->count();

    echo "  جلسات على الراوتر: " . count($sessions) . ", مؤرشفة: {$archivedCount}\n";

    if ($problems == 0) {
        echo "  ✅ لا مشاكل\n";
    } else {
        echo "  ❗ {$problems} مشاكل\n";
    }
    echo "\n";
}

// 2. Show subscribers whose usage hasn't updated recently
echo "\n=== مشتركين لم يتحدث استهلاكهم منذ أكثر من ساعة ===\n";
$stale = Subscriber::where('data_limit', '>', 0)
    ->where('total_bytes', '>', 0)
    ->where('updated_at', '<', now()->subHour())
    ->whereHas('router', fn($q) => $q->where('is_active', true))
    ->get();

foreach ($stale as $s) {
    $gb = round($s->total_bytes / 1073741824, 2);
    echo "  {$s->username} (router {$s->router_id}): {$gb}GB, آخر تحديث: {$s->updated_at}\n";
}

echo "\n=== فحص failed_jobs ===\n";
$failed = \DB::table('failed_jobs')->where('payload', 'like', '%OffloadUmSessions%')->count();
echo "OffloadUmSessions failed: {$failed}\n";
$failed2 = \DB::table('failed_jobs')->where('payload', 'like', '%CleanupUmSessions%')->count();
echo "CleanupUmSessions failed: {$failed2}\n";
$failed3 = \DB::table('failed_jobs')->where('payload', 'like', '%CheckUsageLimit%')->count();
echo "CheckUsageLimit failed: {$failed3}\n";

$pending = \DB::table('jobs')->count();
echo "\nJobs في الانتظار: {$pending}\n";
