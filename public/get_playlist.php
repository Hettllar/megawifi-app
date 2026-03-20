<?php
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get parameters
$username = $_GET['username'] ?? '';
$password = $_GET['password'] ?? '';

// Verify subscription
$subscription = App\Models\IptvSubscription::where('username', $username)
    ->where('password', $password)
    ->first();

if (!$subscription || !$subscription->isActive()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials or expired subscription']);
    exit;
}

// Get active channels
$channels = App\Models\IptvChannel::active()
    ->orderBy('sort_order')
    ->orderBy('category')
    ->orderBy('name')
    ->get();

// Generate M3U8 playlist
$m3u = "#EXTM3U\n";

foreach ($channels as $channel) {
    $m3u .= sprintf(
        "#EXTINF:-1 tvg-id=\"%s\" tvg-name=\"%s\" tvg-logo=\"%s\" group-title=\"%s\",%s\n",
        $channel->slug,
        $channel->name,
        $channel->logo ?: '',
        $channel->category,
        $channel->name
    );
    
    // Use direct stream URL
    $m3u .= $channel->source_url . "\n";
}

header('Content-Type: application/x-mpegURL');
header('Content-Disposition: attachment; filename="megawifi_iptv.m3u8"');
echo $m3u;
