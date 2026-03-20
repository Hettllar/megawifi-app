<?php

namespace App\Jobs;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\ActiveSession;
use App\Models\TrafficHistory;
use App\Services\MikroTikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class CollectTrafficData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 1;

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting traffic data collection...');

        $routers = Router::where('status', 'online')->get();
        
        foreach ($routers as $router) {
            try {
                $this->collectFromRouter($router);
            } catch (Exception $e) {
                Log::error("Failed to collect traffic from router {$router->name}: " . $e->getMessage());
            }
        }

        Log::info('Traffic data collection completed.');
    }

    /**
     * Collect traffic data from a specific router
     */
    private function collectFromRouter(Router $router): void
    {
        $service = new MikroTikService($router);
        
        try {
            $service->connect();
            
            // Get PPP active sessions
            $pppActive = $service->getPPPActive();
            
            foreach ($pppActive as $session) {
                if (!isset($session['name'])) continue;
                
                $username = $session['name'];
                
                // Get traffic from interface
                $traffic = $service->getPPPoEInterfaceTraffic($username);
                
                if ($traffic['rx_bytes'] == 0 && $traffic['tx_bytes'] == 0) continue;
                
                // Find subscriber
                $subscriber = Subscriber::where('router_id', $router->id)
                    ->where('username', $username)
                    ->first();
                
                if (!$subscriber) continue;
                
                // Get previous recorded traffic for this session
                $lastRecord = TrafficHistory::where('subscriber_id', $subscriber->id)
                    ->where('router_id', $router->id)
                    ->whereDate('recorded_at', today())
                    ->first();
                
                $currentBytesIn = $traffic['rx_bytes'];
                $currentBytesOut = $traffic['tx_bytes'];
                
                if ($lastRecord) {
                    // Calculate delta (new traffic since last record)
                    // Only add if current is greater (session hasn't reset)
                    if ($currentBytesIn >= $lastRecord->bytes_in && $currentBytesOut >= $lastRecord->bytes_out) {
                        // Update with current values (we store absolute values per day)
                        $lastRecord->update([
                            'bytes_in' => $currentBytesIn,
                            'bytes_out' => $currentBytesOut,
                            'recorded_at' => now(),
                        ]);
                    } else {
                        // Session was reset, add new record with current values
                        // First, save the old record's values to subscriber's total
                        $subscriber->increment('bytes_in', $lastRecord->bytes_in);
                        $subscriber->increment('bytes_out', $lastRecord->bytes_out);
                        $subscriber->increment('total_bytes', $lastRecord->bytes_in + $lastRecord->bytes_out);
                        
                        // Update today's record with new session values
                        $lastRecord->update([
                            'bytes_in' => $currentBytesIn,
                            'bytes_out' => $currentBytesOut,
                            'recorded_at' => now(),
                        ]);
                    }
                } else {
                    // First record of the day
                    TrafficHistory::create([
                        'router_id' => $router->id,
                        'subscriber_id' => $subscriber->id,
                        'bytes_in' => $currentBytesIn,
                        'bytes_out' => $currentBytesOut,
                        'recorded_at' => now(),
                    ]);
                }
                
                // Update active session
                ActiveSession::updateOrCreate(
                    [
                        'router_id' => $router->id,
                        'username' => $username,
                    ],
                    [
                        'subscriber_id' => $subscriber->id,
                        'type' => 'ppp',
                        'mac_address' => $session['caller-id'] ?? null,
                        'ip_address' => $session['address'] ?? null,
                        'bytes_in' => $currentBytesIn,
                        'bytes_out' => $currentBytesOut,
                    ]
                );
            }
            
            $service->disconnect();
            
        } catch (Exception $e) {
            $service->disconnect();
            throw $e;
        }
    }
}
