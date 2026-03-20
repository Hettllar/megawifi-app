<?php
require_once '/var/www/megawifi/vendor/autoload.php';
$app = require_once '/var/www/megawifi/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$r = \App\Models\Router::find(13);
echo "api_username: " . $r->api_username . "\n";
echo "api_password: " . $r->api_password . "\n";
echo "wg_enabled: " . $r->wg_enabled . "\n";
echo "wg_client_ip: " . $r->wg_client_ip . "\n";

// Check all columns
$cols = $r->getAttributes();
foreach ($cols as $k => $v) {
    if (strlen($v) < 200) {
        echo "{$k}: {$v}\n";
    }
}
