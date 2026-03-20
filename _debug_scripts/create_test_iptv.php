<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Find active subscriber with phone
$sub = \App\Models\Subscriber::where('status', 'active')
    ->whereNotNull('phone')
    ->where('phone', '!=', '')
    ->doesntHave('iptvSubscription')
    ->first();

if (!$sub) {
    echo "No active subscriber with phone found!\n";
    exit(1);
}

echo "Found subscriber:\n";
echo "ID: {$sub->id}\n";
echo "Username: {$sub->username}\n";
echo "Name: {$sub->full_name}\n";
echo "Phone: {$sub->phone}\n";
echo "Status: {$sub->status}\n\n";

// Create IPTV subscription
$iptv = \App\Models\IptvSubscription::create([
    'subscriber_id' => $sub->id,
    'user_id' => null,
    'username' => 'iptv_sub_' . $sub->id,
    'password' => \Illuminate\Support\Str::random(12),
    'expires_at' => now()->addYear(),
    'is_active' => 1,
    'max_connections' => 2,
    'notes' => 'Test IPTV subscription'
]);

echo "Created IPTV subscription:\n";
echo "Username: {$iptv->username}\n";
echo "Password: {$iptv->password}\n";
echo "\nTest this phone: {$sub->phone}\n";
