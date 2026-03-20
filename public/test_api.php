<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->boot();

header('Content-Type: application/json');

// Get user 1's routers (simulating API call)
$user = App\Models\User::first();
$routerIds = $user->routers()->pluck('routers.id')->toArray();
$routers = App\Models\Router::whereIn('id', $routerIds)->get();

$routersList = $routers->map(function ($r) {
    return [
        'id' => $r->id,
        'name' => $r->name,
        'identity' => $r->identity,
        'status' => $r->status,
        'ip_address' => $r->ip_address,
    ];
});

echo json_encode([
    'success' => true,
    'routers_count' => $routers->count(),
    'routers' => $routersList,
    'router_ids' => $routerIds,
    'user_id' => $user->id,
    'user_name' => $user->name,
], JSON_PRETTY_PRINT);
