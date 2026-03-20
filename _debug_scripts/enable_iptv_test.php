<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Enable IPTV for subscriber 6804
$subscriber = \App\Models\Subscriber::find(6804);

if ($subscriber) {
    $subscriber->iptv_enabled = true;
    $subscriber->save();
    
    echo "✅ IPTV enabled for {$subscriber->username} (ID: {$subscriber->id})\n";
    echo "Phone: {$subscriber->phone}\n";
    
    if ($subscriber->iptvSubscription) {
        echo "IPTV Username: {$subscriber->iptvSubscription->username}\n";
        echo "IPTV Active: " . ($subscriber->iptvSubscription->is_active ? 'Yes' : 'No') . "\n";
    } else {
        echo "⚠️ No IPTV subscription found. It will be created on first toggle.\n";
    }
} else {
    echo "❌ Subscriber not found\n";
}
