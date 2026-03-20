<?php
require '/var/www/megawifi/vendor/autoload.php';
$app = require_once '/var/www/megawifi/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
$routers = App\Models\Router::where('is_active', true)->get();
foreach($routers as $r) {
    echo $r->id . ' | ' . $r->name . ' | ' . $r->ip_address . PHP_EOL;
}
