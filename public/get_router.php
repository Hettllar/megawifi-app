<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$r = \App\Models\Router::find(13);
if ($r) {
    echo json_encode($r->only(['id','name','host','username','password','api_port','wg_client_ip']), JSON_UNESCAPED_UNICODE);
} else {
    echo 'NOT FOUND';
}
