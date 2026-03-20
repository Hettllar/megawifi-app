<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Subscriber;
use App\Models\Router;

echo "=== نظام جمع الجيجات - تشخيص ===\n\n";

// 1. Check subscribers with data limits
$subscribers = Subscriber::where('type', 'usermanager')
    ->where('status', 'active')
    ->where('data_limit_gb', '>', 0)
    ->orderBy('total_bytes', 'desc')
    ->get(['id', 'username', 'router_id', 'total_bytes', 'bytes_in', 'bytes_out', 'data_limit_gb', 'data_limit', 'is_throttled', 'profile', 'original_profile', 'usage_reset_at', 'updated_at']);

echo "المشتركين مع حد بيانات: " . $subscribers->count() . "\n\n";

echo str_pad('المشترك', 25) . str_pad('الراوتر', 8) . str_pad('الاستهلاك GB', 15) . str_pad('الحد GB', 10) . str_pad('النسبة%', 10) . str_pad('مقيد', 8) . str_pad('البروفايل', 15) . str_pad('آخر تحديث', 22) . "\n";
echo str_repeat('-', 120) . "\n";

$problems = [];
foreach ($subscribers as $sub) {
    $totalGb = round($sub->total_bytes / 1073741824, 2);
    $pct = $sub->data_limit_gb > 0 ? round(($totalGb / $sub->data_limit_gb) * 100, 1) : 0;
    $throttled = $sub->is_throttled ? 'نعم' : 'لا';

    echo str_pad($sub->username, 25) . str_pad($sub->router_id, 8) . str_pad($totalGb, 15) . str_pad($sub->data_limit_gb, 10) . str_pad($pct . '%', 10) . str_pad($throttled, 8) . str_pad($sub->profile ?? '-', 15) . str_pad($sub->updated_at, 22) . "\n";

    // Check for problems
    if ($pct >= 100 && !$sub->is_throttled) {
        $problems[] = "⚠️ {$sub->username}: استهلك {$totalGb}GB من {$sub->data_limit_gb}GB ({$pct}%) لكن غير مقيد!";
    }
    if ($sub->is_throttled && $pct < 100) {
        $problems[] = "⚠️ {$sub->username}: مقيد رغم أن استهلاكه {$totalGb}GB أقل من الحد {$sub->data_limit_gb}GB!";
    }
    if ($sub->total_bytes == 0 && $sub->data_limit_gb > 0) {
        $problems[] = "⚠️ {$sub->username}: الاستهلاك = 0 (ربما لا يتم جمع البيانات)";
    }
}

echo "\n=== المشاكل المكتشفة ===\n";
if (empty($problems)) {
    echo "✅ لا توجد مشاكل\n";
} else {
    foreach ($problems as $p) {
        echo $p . "\n";
    }
}

// 2. Check routers connectivity for usage collection
echo "\n=== حالة الراوترات (الاتصال) ===\n";
$routers = Router::where('is_active', true)->get();
$failedRouters = [];
foreach ($routers as $router) {
    $ip = $router->wg_enabled && $router->wg_client_ip ? $router->wg_client_ip : $router->ip_address;
    $port = $router->api_port ?: 8728;
    $sock = @fsockopen($ip, $port, $errno, $errstr, 2);
    if ($sock) {
        @fclose($sock);
    } else {
        $failedRouters[] = "{$router->name} ({$ip}:{$port})";
    }
}
echo "راوترات لا تستجيب: " . count($failedRouters) . "/" . $routers->count() . "\n";
foreach ($failedRouters as $f) {
    echo "  ❌ $f\n";
}

// 3. Check recent usage updates
echo "\n=== آخر تحديثات الاستهلاك ===\n";
$recentUpdates = Subscriber::where('type', 'usermanager')
    ->where('data_limit_gb', '>', 0)
    ->where('total_bytes', '>', 0)
    ->orderBy('updated_at', 'desc')
    ->limit(5)
    ->get(['username', 'total_bytes', 'updated_at']);

foreach ($recentUpdates as $u) {
    $gb = round($u->total_bytes / 1073741824, 2);
    echo "  {$u->username}: {$gb} GB - آخر تحديث: {$u->updated_at}\n";
}

// 4. Check if there are subscribers with 0 bytes but have data_limit
$zeroUsage = Subscriber::where('type', 'usermanager')
    ->where('status', 'active')
    ->where('data_limit_gb', '>', 0)
    ->where('total_bytes', 0)
    ->count();
echo "\nمشتركين باستهلاك = 0 (محتمل مشكلة جمع): {$zeroUsage}\n";

// 5. Check subscribers total
$totalSubs = Subscriber::where('type', 'usermanager')->where('status', 'active')->count();
$subsWithLimit = Subscriber::where('type', 'usermanager')->where('status', 'active')->where('data_limit_gb', '>', 0)->count();
echo "إجمالي مشتركين UM نشطين: {$totalSubs}\n";
echo "منهم لديهم حد بيانات: {$subsWithLimit}\n";
