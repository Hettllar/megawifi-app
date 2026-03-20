<?php
require_once '/var/www/megawifi/vendor/autoload.php';
$app = require_once '/var/www/megawifi/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$r = \App\Models\Router::find(13);
echo "Router: {$r->name}\n";
echo "IP: {$r->ip_address}\n";
echo "Username: {$r->username}\n";
echo "Password: {$r->password}\n";
echo "API Port: {$r->api_port}\n";
