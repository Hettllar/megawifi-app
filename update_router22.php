<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

// First find the router
$routers = DB::table('routers')->select('id','name','ip_address','wg_client_ip','wg_public_key')->get();
echo "All routers:\n";
foreach ($routers as $r) {
    echo "  ID={$r->id} Name={$r->name} IP={$r->ip_address} WG_IP={$r->wg_client_ip} PubKey=" . substr($r->wg_public_key ?? '', 0, 20) . "\n";
}

// Find by old IP or old key
$router = DB::table('routers')->where('wg_client_ip', '10.10.0.18')
    ->orWhere('wg_public_key', 'FnZoPSCOnH0QuzbIeGeKRvPUKWztwLfQJGhX1ityu1U=')
    ->first();

if ($router) {
    echo "\nFound router to update: ID={$router->id} Name={$router->name}\n";
    $updated = DB::table('routers')->where('id', $router->id)->update([
        'wg_public_key' => '0JIo1RLTWgZWVzAH5yPp6Iv3tcoC1owJFKxd5g4L2hY=',
        'wg_private_key' => 'SJ0mzum88xkxxY+nbULQCOr1nBg+GARYvY6xjPzy9mg=',
        'wg_client_ip' => '10.10.0.34',
        'ip_address' => '10.10.0.34',
    ]);
    echo "Updated rows: $updated\n";
    
    $verify = DB::table('routers')->where('id', $router->id)->first();
    echo "Verified - IP: {$verify->ip_address}, WG_IP: {$verify->wg_client_ip}, PubKey: {$verify->wg_public_key}\n";
} else {
    echo "\nRouter not found by old IP/key. Trying by name...\n";
    $router = DB::table('routers')->where('name', 'like', '%درويش%')->first();
    if ($router) {
        echo "Found: ID={$router->id} Name={$router->name}\n";
    } else {
        echo "Not found!\n";
    }
}
