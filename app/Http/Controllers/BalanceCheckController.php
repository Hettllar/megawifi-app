<?php

namespace App\Http\Controllers;

use App\Models\Subscriber;
use App\Models\ActiveSession;
use App\Models\SessionHistory;
use App\Models\TrafficHistory;
use App\Models\IptvSubscription;
use App\Models\IptvChannel;
use Illuminate\Http\Request;

class BalanceCheckController extends Controller
{
    /**
     * Show balance check page
     */
    public function index()
    {
        return view('balance.index');
    }

    /**
     * Check balance by phone number
     */
    public function check(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:9|max:15',
        ]);

        $phone = $request->phone;
        
        // Clean phone number - remove spaces and dashes
        $phone = preg_replace('/[\s\-]/', '', $phone);
        
        // Try different phone formats
        $phoneVariants = [
            $phone,
            '0' . $phone,
            ltrim($phone, '0'),
            '963' . ltrim($phone, '0'),
            '+963' . ltrim($phone, '0'),
        ];
        
        $subscriber = Subscriber::where(function($q) use ($phoneVariants) {
            foreach ($phoneVariants as $variant) {
                $q->orWhere('phone', $variant);
                $q->orWhere('phone', 'LIKE', '%' . $variant);
            }
        })->first();

        if (!$subscriber) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على مشترك بهذا الرقم',
            ]);
        }

        // Calculate data usage
        $totalBytes = $subscriber->total_bytes ?? 0;
        $usedGb = $totalBytes / 1073741824;
        
        // Calculate data limit (convert bytes to GB if needed)
        $dataLimitGb = $subscriber->data_limit_gb ?? 0;
        if ($dataLimitGb == 0 && $subscriber->data_limit > 0) {
            // data_limit is stored in bytes, convert to GB
            $dataLimitGb = $subscriber->data_limit / 1073741824;
        }
        
        $remainingGb = $dataLimitGb > 0 ? max(0, $dataLimitGb - $usedGb) : null;
        $usagePercent = $dataLimitGb > 0 ? min(100, ($usedGb / $dataLimitGb) * 100) : 0;

        // Calculate remaining days
        $remainingDays = null;
        if ($subscriber->expiration_date) {
            $expDate = \Carbon\Carbon::parse($subscriber->expiration_date);
            $remainingDays = (int) max(0, now()->diffInDays($expDate, false));
        }

        // Get active sessions
        $activeSessions = ActiveSession::where('subscriber_id', $subscriber->id)
            ->orderBy('started_at', 'desc')
            ->take(10)
            ->get()
            ->map(function($session) {
                $duration = '';
                if ($session->started_at) {
                    $start = \Carbon\Carbon::parse($session->started_at);
                    $diff = $start->diffForHumans(null, true, true);
                    $duration = $diff;
                }
                
                return [
                    'ip' => $session->ip_address ?? '-',
                    'mac' => $session->mac_address ?? '-',
                    'started_at' => $session->started_at ? \Carbon\Carbon::parse($session->started_at)->format('Y-m-d H:i') : '-',
                    'duration' => $duration,
                    'upload' => round(($session->bytes_in ?? 0) / 1048576, 2), // MB
                    'download' => round(($session->bytes_out ?? 0) / 1048576, 2), // MB
                    'type' => $session->type ?? '-',
                    'is_active' => true,
                ];
            });

        // Get session history from traffic_history table (has more data)
        $sessionHistory = TrafficHistory::where('subscriber_id', $subscriber->id)
            ->orderBy('session_end', 'desc')
            ->take(20)
            ->get()
            ->map(function($session) {
                $durationText = '';
                $uptime = $session->uptime ?? 0;
                if ($uptime > 0) {
                    $hours = floor($uptime / 3600);
                    $minutes = floor(($uptime % 3600) / 60);
                    if ($hours > 0) {
                        $durationText = $hours . ' ساعة ' . $minutes . ' دقيقة';
                    } else {
                        $durationText = $minutes . ' دقيقة';
                    }
                }

                return [
                    'ip' => '-',
                    'mac' => '-',
                    'started_at' => $session->session_start ? \Carbon\Carbon::parse($session->session_start)->format('Y-m-d H:i') : '-',
                    'ended_at' => $session->session_end ? \Carbon\Carbon::parse($session->session_end)->format('Y-m-d H:i') : '-',
                    'duration' => $durationText,
                    'upload' => round(($session->bytes_in ?? 0) / 1048576, 2),
                    'download' => round(($session->bytes_out ?? 0) / 1048576, 2),
                    'total_mb' => round((($session->bytes_in ?? 0) + ($session->bytes_out ?? 0)) / 1048576, 2),
                    'type' => '-',
                    'is_active' => false,
                ];
            });

        // Check IPTV subscription
        $iptvData = ['has_subscription' => false];
        if ($subscriber->id) {
            $iptvSubscription = IptvSubscription::where('subscriber_id', $subscriber->id)
                ->where('is_active', true)
                ->first();

            if ($iptvSubscription) {
                // Count channels by category
                $channelCounts = IptvChannel::where('is_active', true)
                    ->selectRaw('
                        COUNT(*) as total,
                        SUM(CASE WHEN category = "sports" THEN 1 ELSE 0 END) as sports,
                        SUM(CASE WHEN category = "news" THEN 1 ELSE 0 END) as news,
                        SUM(CASE WHEN category = "religious" THEN 1 ELSE 0 END) as religious
                    ')
                    ->first();

                $iptvData = [
                    'has_subscription' => true,
                    'username' => $iptvSubscription->username,
                    'password' => $iptvSubscription->password,
                    'expires_at' => $iptvSubscription->expires_at ? \Carbon\Carbon::parse($iptvSubscription->expires_at)->format('Y-m-d') : null,
                    'channels' => [
                        'total' => $channelCounts->total ?? 0,
                        'sports' => $channelCounts->sports ?? 0,
                        'news' => $channelCounts->news ?? 0,
                        'religious' => $channelCounts->religious ?? 0,
                    ],
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'username' => $subscriber->username,
                'full_name' => $subscriber->full_name ?? $subscriber->username,
                'status' => $subscriber->status,
                'status_text' => $this->getStatusText($subscriber->status),
                'profile' => $subscriber->profile,
                'router' => $subscriber->router->name ?? '-',
                'expiration_date' => $subscriber->expiration_date ? \Carbon\Carbon::parse($subscriber->expiration_date)->format('Y-m-d') : null,
                'remaining_days' => $remainingDays,
                'used_gb' => round($usedGb, 2),
                'data_limit_gb' => round($dataLimitGb, 2),
                'remaining_gb' => $remainingGb !== null ? round($remainingGb, 2) : null,
                'usage_percent' => round($usagePercent, 1),
                'upload_gb' => round(($subscriber->bytes_in ?? 0) / 1073741824, 2),
                'download_gb' => round(($subscriber->bytes_out ?? 0) / 1073741824, 2),
                'usage_reset_at' => $subscriber->usage_reset_at ? \Carbon\Carbon::parse($subscriber->usage_reset_at)->format('Y-m-d H:i') : null,
                'last_login' => $subscriber->last_login ? \Carbon\Carbon::parse($subscriber->last_login)->format('Y-m-d H:i') : null,
                'is_paid' => $subscriber->is_paid ?? false,
                'remaining_amount' => $subscriber->remaining_amount ?? 0,
                'subscription_price' => $subscriber->subscription_price ?? 0,
                'payment_status' => $this->getPaymentStatus($subscriber),
                'sessions' => $activeSessions,
                'sessions_count' => $activeSessions->count(),
                'session_history' => $sessionHistory,
                'history_count' => $sessionHistory->count(),
                'iptv' => $iptvData,
            ],
        ]);
    }

    private function getStatusText($status)
    {
        return match($status) {
            'active' => 'نشط',
            'expired' => 'منتهي',
            'disabled' => 'معطل',
            'suspended' => 'موقوف',
            default => $status,
        };
    }

    private function getPaymentStatus($subscriber)
    {
        $isPaid = $subscriber->is_paid ?? false;
        $remaining = $subscriber->remaining_amount ?? 0;

        if ($isPaid && $remaining <= 0) {
            return ['status' => 'paid', 'text' => 'مدفوع', 'color' => 'green'];
        } elseif ($remaining > 0) {
            return ['status' => 'partial', 'text' => 'متبقي ' . number_format($remaining) . ' ل.س', 'color' => 'yellow'];
        } else {
            return ['status' => 'unpaid', 'text' => 'غير مدفوع', 'color' => 'red'];
        }
    }

    /**
     * Download M3U playlist
     */
    public function downloadM3U($phone)
    {
        // Find subscriber by phone first
        $phoneVariants = [$phone, '0' . $phone, ltrim($phone, '0'), '963' . ltrim($phone, '0'), '+963' . ltrim($phone, '0')];
        $subscriber = \App\Models\Subscriber::where(function($q) use ($phoneVariants) {
            foreach ($phoneVariants as $variant) {
                $q->orWhere('phone', $variant);
            }
        })->first();

        $iptvSub = null;
        if ($subscriber) {
            $iptvSub = IptvSubscription::where('subscriber_id', $subscriber->id)
                ->where('is_active', true)
                ->first();
        }

        if (!$iptvSub) {
            abort(404, 'No active IPTV subscription found');
        }

        $channels = IptvChannel::where('is_active', true)->orderBy('sort_order')->get();

        $m3u = "#EXTM3U\n";
        foreach ($channels as $ch) {
            $m3u .= "#EXTINF:-1 tvg-logo=\"{$ch->logo_url}\" group-title=\"{$ch->category}\",{$ch->name}\n";
            $m3u .= $ch->stream_url . "\n";
        }

        return response($m3u, 200, [
            'Content-Type' => 'audio/x-mpegurl',
            'Content-Disposition' => 'attachment; filename="megawifi-channels.m3u"',
        ]);
    }

    /**
     * IPTV Player page
     */
    public function iptvPlayer()
    {
        return view('iptv.player');
    }

    /**
     * Get channels for IPTV player
     */
    public function getChannels(Request $request)
    {
        $phone = $request->input('phone');
        
        \Log::info('IPTV getChannels debug', [
            'input_phone' => $request->input('phone'),
            'json_phone' => $request->json('phone'),
            'content' => $request->getContent(),
            'content_type' => $request->header('Content-Type'),
            'all_input' => $request->all(),
            'phone_final' => $phone,
        ]);
        
        if (!$phone) {
            return response()->json(['success' => false, 'message' => 'يرجى إدخال رقم الهاتف']);
        }

        // Clean phone number
        $phone = preg_replace('/[\s\-]/', '', $phone);

        // Try different phone formats
        $phoneVariants = [
            $phone,
            '0' . $phone,
            ltrim($phone, '0'),
            '963' . ltrim($phone, '0'),
            '+963' . ltrim($phone, '0'),
        ];

        $subscribers = \App\Models\Subscriber::where(function($q) use ($phoneVariants) {
            foreach ($phoneVariants as $variant) {
                $q->orWhere('phone', $variant);
                $q->orWhere('phone', 'LIKE', '%' . $variant);
            }
        })->get();

        if ($subscribers->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'رقم الهاتف غير مسجل']);
        }

        // Check IPTV subscription across ALL subscribers with this phone
        $iptvSub = null;
        $subscriber = null;
        foreach ($subscribers as $sub) {
            $iptvSub = IptvSubscription::where('subscriber_id', $sub->id)
                ->where('is_active', true)
                ->first();
            if ($iptvSub) {
                $subscriber = $sub;
                break;
            }
        }

        // Fallback: also check iptv_enabled flag on subscriber
        if (!$iptvSub) {
            foreach ($subscribers as $sub) {
                if ($sub->iptv_enabled) {
                    $subscriber = $sub;
                    break;
                }
            }
        }

        if (!$iptvSub && (!$subscriber || !$subscriber->iptv_enabled)) {
            return response()->json(['success' => false, 'message' => 'لا يوجد اشتراك IPTV فعّال لهذا الرقم']);
        }

        // Check expiry
        if ($iptvSub->expires_at && \Carbon\Carbon::parse($iptvSub->expires_at)->isPast()) {
            return response()->json(['success' => false, 'message' => 'اشتراك IPTV منتهي الصلاحية']);
        }

        $channels = IptvChannel::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Group channels by category
        $categoryNames = [
            'sports' => 'رياضة',
            'news' => 'أخبار',
            'religious' => 'دينية',
            'entertainment' => 'ترفيه',
            'movies' => 'أفلام',
            'kids' => 'أطفال',
            'drama' => 'دراما',
            'general' => 'عام',
        ];

        $grouped = [];
        foreach ($channels as $ch) {
            $cat = $ch->category ?? 'general';
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [
                    'name' => $categoryNames[$cat] ?? $cat,
                    'channels' => [],
                ];
            }
            $grouped[$cat]['channels'][] = [
                'id' => $ch->id,
                'name' => $ch->name,
                'logo' => $ch->logo,
                'stream_url' => (str_ends_with($ch->source_url, '.ts') ? route('iptv.stream-proxy', ['url' => $ch->source_url]) : route('iptv.hls-proxy', ['url' => $ch->source_url])),
                'is_hls' => $ch->stream_format === 'hls',
                'format' => $ch->stream_format ?? 'hls',
            ];
        }

        return response()->json([
            'success' => true,
            'subscriber' => $subscriber->username ?? $subscriber->name ?? $subscriber->phone,
            'channels' => $grouped,
        ]);
    }

    /**
     * Stream proxy for IPTV
     */
    public function streamProxy($channelId)
    {
        $channel = IptvChannel::findOrFail($channelId);

        return redirect($channel->source_url);
    }

    /**
     * HLS proxy - rewrites m3u8 URLs to go through proxy for HTTPS compatibility
     * Also handles .ts segments (non-streaming, finite chunks)
     */
    public function hlsProxy(Request $request)
    {
        // Handle CORS preflight
        if ($request->isMethod('OPTIONS')) {
            return response('', 200, [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, OPTIONS',
                'Access-Control-Allow-Headers' => '*',
                'Access-Control-Max-Age' => '86400',
            ]);
        }

        $url = $request->query('url');
        if (!$url) {
            abort(400, 'URL parameter required');
        }

        try {
            $urlPath = strtolower(parse_url($url, PHP_URL_PATH) ?? '');

            // For .ts segment files (finite chunks, NOT continuous streams)
            if (str_ends_with($urlPath, '.ts') || str_ends_with($urlPath, '.aac') || str_ends_with($urlPath, '.mp4')) {
                $curlCh = curl_init();
                curl_setopt($curlCh, CURLOPT_URL, $url);
                curl_setopt($curlCh, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curlCh, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($curlCh, CURLOPT_TIMEOUT, 15);
                curl_setopt($curlCh, CURLOPT_SSL_VERIFYPEER, false);
                $segBody = curl_exec($curlCh);
                $segCode = curl_getinfo($curlCh, CURLINFO_HTTP_CODE);
                $segCt = curl_getinfo($curlCh, CURLINFO_CONTENT_TYPE) ?: 'video/mp2t';
                curl_close($curlCh);

                return response($segBody ?: '', $segCode ?: 502, [
                    'Content-Type' => $segCt,
                    'Access-Control-Allow-Origin' => '*',
                    'Cache-Control' => 'public, max-age=10',
                ]);
            }

            // Use curl to track effective URL after redirects
            $curlCh = curl_init();
            curl_setopt($curlCh, CURLOPT_URL, $url);
            curl_setopt($curlCh, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curlCh, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curlCh, CURLOPT_TIMEOUT, 15);
            curl_setopt($curlCh, CURLOPT_SSL_VERIFYPEER, false);
            $body = curl_exec($curlCh);
            $httpCode = curl_getinfo($curlCh, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($curlCh, CURLINFO_CONTENT_TYPE) ?? '';
            $effectiveUrl = curl_getinfo($curlCh, CURLINFO_EFFECTIVE_URL);
            curl_close($curlCh);

            if ($body === false || $httpCode >= 500) {
                abort(502, 'Stream unavailable');
            }

            // Check if this is an m3u8 playlist
            $isM3u8 = str_contains($contentType, 'mpegurl')
                    || str_contains($contentType, 'x-mpegurl')
                    || str_ends_with($urlPath, '.m3u8');

            // Use EFFECTIVE URL (after redirects) as base for resolving paths
            if ($isM3u8 && !empty($body)) {
                $body = $this->rewriteM3u8Urls($body, $effectiveUrl);
                $contentType = 'application/vnd.apple.mpegurl';
            }

            return response($body, $httpCode ?: 200, [
                'Content-Type' => $contentType ?: 'application/octet-stream',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Headers' => '*',
                'Access-Control-Expose-Headers' => 'Content-Length, Content-Range',
                'Cache-Control' => $isM3u8 ? 'no-cache, no-store' : 'public, max-age=5',
            ]);
        } catch (\Exception $e) {
            \Log::error('HLS Proxy error', ['url' => $url, 'error' => $e->getMessage()]);
            abort(502, 'Stream unavailable');
        }
    }

    /**
     * Streaming proxy for continuous MPEG-TS streams
     * Unlike hlsProxy which buffers full response, this streams data in chunks
     */
    public function streamProxy2(Request $request)
    {
        $url = $request->query('url');
        if (!$url) {
            abort(400, 'URL parameter required');
        }

        return response()->stream(function () use ($url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 0); // No timeout for live stream
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_BUFFERSIZE, 8192);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) {
                echo $data;
                if (ob_get_level() > 0) ob_flush();
                flush();
                // Check if client disconnected
                if (connection_aborted()) {
                    return 0; // Stop transfer
                }
                return strlen($data);
            });
            curl_exec($ch);
            curl_close($ch);
        }, 200, [
            'Content-Type' => 'video/mp2t',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no', // Disable nginx buffering
            'Transfer-Encoding' => 'chunked',
        ]);
    }

    /**
     * Rewrite URLs inside m3u8 playlist to go through our proxy
     */
    private function rewriteM3u8Urls(string $content, string $originalUrl): string
    {
        // Get base URL for resolving relative paths
        $lastSlash = strrpos($originalUrl, '/');
        $baseUrl = $lastSlash !== false ? substr($originalUrl, 0, $lastSlash + 1) : $originalUrl . '/';
        $proxyRoute = route('iptv.hls-proxy');

        $lines = explode("\n", $content);
        $rewritten = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Empty lines - keep as-is
            if ($trimmed === '') {
                $rewritten[] = $line;
                continue;
            }

            // Handle URI= attributes in tags like #EXT-X-MAP, #EXT-X-KEY, etc.
            if (str_starts_with($trimmed, '#') && preg_match('/URI="([^"]+)"/', $trimmed, $matches)) {
                $uri = $matches[1];
                $absoluteUrl = $this->resolveHlsUrl($uri, $baseUrl);
                $proxyUrl = $proxyRoute . '?url=' . urlencode($absoluteUrl);
                $rewritten[] = str_replace('URI="' . $uri . '"', 'URI="' . $proxyUrl . '"', $trimmed);
                continue;
            }

            // Comment/tag lines without URI - keep as-is
            if (str_starts_with($trimmed, '#')) {
                $rewritten[] = $trimmed;
                continue;
            }

            // Non-comment lines are URLs (segment or playlist references)
            $absoluteUrl = $this->resolveHlsUrl($trimmed, $baseUrl);
            $proxyUrl = $proxyRoute . '?url=' . urlencode($absoluteUrl);
            $rewritten[] = $proxyUrl;
        }

        return implode("\n", $rewritten);
    }

    /**
     * Resolve a potentially relative URL against a base URL
     */
    private function resolveHlsUrl(string $url, string $baseUrl): string
    {
        // Already absolute
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // Absolute path (starts with /)
        if (str_starts_with($url, '/')) {
            $parsed = parse_url($baseUrl);
            $origin = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
            if (isset($parsed['port'])) {
                $origin .= ':' . $parsed['port'];
            }
            return $origin . $url;
        }

        // Relative path
        return $baseUrl . $url;
    }
}