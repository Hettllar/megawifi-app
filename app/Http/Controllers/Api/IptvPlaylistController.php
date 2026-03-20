<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IptvChannel;
use App\Models\IptvSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class IptvPlaylistController extends Controller
{
    /**
     * Generate M3U8 playlist for IPTV Smarters
     */
    public function getPlaylist(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');

        // Verify subscription
        $subscription = IptvSubscription::where('username', $username)
            ->where('password', $password)
            ->first();

        if (!$subscription || !$subscription->isActive()) {
            return response()->json(['error' => 'Invalid credentials or expired subscription'], 401);
        }

        // Get active channels
        $channels = IptvChannel::active()
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
            
            // Generate authenticated stream URL
            $streamUrl = route('iptv.stream', ['slug' => $channel->slug]) . 
                '?token=' . base64_encode($username . ':' . $password);
            
            $m3u .= $streamUrl . "\n";
        }

        return Response::make($m3u, 200, [
            'Content-Type' => 'application/x-mpegURL',
            'Content-Disposition' => 'attachment; filename="megawifi_iptv.m3u8"',
        ]);
    }

    /**
     * Stream channel (proxy through FFmpeg)
     */
    public function streamChannel(Request $request, $slug)
    {
        $token = $request->input('token');
        
        // Verify token
        if (!$token) {
            abort(401, 'Token required');
        }

        $credentials = base64_decode($token);
        [$username, $password] = explode(':', $credentials);

        $subscription = IptvSubscription::where('username', $username)
            ->where('password', $password)
            ->first();

        if (!$subscription || !$subscription->isActive()) {
            abort(401, 'Invalid or expired subscription');
        }

        // Get channel
        $channel = IptvChannel::where('slug', $slug)->active()->firstOrFail();

        // Generate HLS stream path
        $hlsPath = "/var/www/iptv/hls/{$channel->slug}/playlist.m3u8";

        if (!file_exists($hlsPath)) {
            // Start streaming process in background
            $this->startStream($channel);
            sleep(2); // Wait for stream to initialize
        }

        // Serve HLS playlist
        if (file_exists($hlsPath)) {
            return Response::file($hlsPath, [
                'Content-Type' => 'application/x-mpegURL',
            ]);
        }

        abort(503, 'Stream not available');
    }

    /**
     * Start FFmpeg streaming process
     */
    private function startStream($channel)
    {
        $script = "/var/www/iptv/scripts/stream_channel.sh";
        $command = sprintf(
            "nohup %s %s %s > /dev/null 2>&1 &",
            escapeshellarg($script),
            escapeshellarg($channel->source_url),
            escapeshellarg($channel->slug)
        );

        exec($command);
    }

    /**
     * Get channel list (for API)
     */
    public function getChannels(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');

        $subscription = IptvSubscription::where('username', $username)
            ->where('password', $password)
            ->first();

        if (!$subscription || !$subscription->isActive()) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $channels = IptvChannel::active()
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');

        return response()->json([
            'channels' => $channels,
            'subscription' => [
                'expires_at' => $subscription->expires_at,
                'max_connections' => $subscription->max_connections
            ]
        ]);
    }
}
