<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$s = App\Models\Subscriber::find(6794);
$s->iptv_enabled = true;
$s->save();
echo "IPTV enabled for {$s->full_name}: " . ($s->iptv_enabled ? 'YES' : 'NO') . "\n";
