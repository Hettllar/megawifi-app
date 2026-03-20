<?php
require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Get parameters
$username = $_GET['username'] ?? '';
$password = $_GET['password'] ?? '';

// Get client IP
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$clientIp = explode(',', $clientIp)[0]; // Get first IP if multiple

// Verify subscription
$subscription = App\Models\IptvSubscription::where('username', $username)
    ->where('password', $password)
    ->with('subscriber')
    ->first();

if (!$subscription || !$subscription->isActive()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials or expired subscription']);
    exit;
}

// Check if subscriber exists and IPTV is enabled
if ($subscription->subscriber) {
    if (!$subscription->subscriber->iptv_enabled) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['error' => 'IPTV service is not enabled for this subscriber']);
        exit;
    }

    // Check IP restrictions if configured
    if ($subscription->subscriber->iptv_allowed_ips) {
        $allowedIps = array_map('trim', explode(',', $subscription->subscriber->iptv_allowed_ips));
        $ipAllowed = false;
        
        foreach ($allowedIps as $allowedIp) {
            // Check for exact match
            if ($clientIp === $allowedIp) {
                $ipAllowed = true;
                break;
            }
            
            // Check for CIDR range (e.g., 192.168.1.0/24)
            if (strpos($allowedIp, '/') !== false) {
                list($subnet, $mask) = explode('/', $allowedIp);
                $subnet = ip2long($subnet);
                $ip = ip2long($clientIp);
                $mask = ~((1 << (32 - $mask)) - 1);
                
                if (($ip & $mask) == ($subnet & $mask)) {
                    $ipAllowed = true;
                    break;
                }
            }
            
            // Check for wildcard (e.g., 192.168.1.*)
            if (strpos($allowedIp, '*') !== false) {
                $pattern = '/^' . str_replace(['.', '*'], ['\.', '.*'], $allowedIp) . '$/';
                if (preg_match($pattern, $clientIp)) {
                    $ipAllowed = true;
                    break;
                }
            }
        }
        
        if (!$ipAllowed) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'error' => 'Access denied from this IP address',
                'client_ip' => $clientIp,
            ]);
            exit;
        }
    }
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
