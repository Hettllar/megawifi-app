<?php

namespace App\Jobs;

use App\Models\Router;
use App\Models\SmsSettings;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class SendSmsRemindersJob implements ShouldQueue, ShouldBeUnique
{
    public $uniqueFor = 3600; // 1 hour uniqueness

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Starting SMS reminders job');

        // Get all routers with enabled SMS settings
        $smsSettings = SmsSettings::where('is_enabled', true)->get();

        if ($smsSettings->isEmpty()) {
            Log::info('No routers with SMS enabled');
            return;
        }

        $totalResults = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        foreach ($smsSettings as $settings) {
            $router = Router::find($settings->router_id);

            if (!$router) {
                Log::warning("Router not found for SMS settings ID: {$settings->id}");
                continue;
            }

            // Check if it's the right time to send (within 30 minutes of send_time)
            if (!$this->isTimeToSend($settings->send_time)) {
                Log::info("Skipping router {$router->name} - not time to send yet");
                continue;
            }

            try {
                $smsService = new SmsService($router);
                
                if (!$smsService->connect()) {
                    Log::error("Could not connect to router {$router->name} for SMS");
                    continue;
                }

                $results = $smsService->sendExpiryReminders();
                
                $smsService->disconnect();

                $totalResults['sent'] += $results['sent'];
                $totalResults['failed'] += $results['failed'];
                $totalResults['skipped'] += $results['skipped'];

                Log::info("SMS reminders for router {$router->name}", $results);

            } catch (Exception $e) {
                Log::error("SMS job failed for router {$router->name}: " . $e->getMessage());
            }
        }

        Log::info('SMS reminders job completed', $totalResults);
    }

    /**
     * Check if current time is within send window
     */
    private function isTimeToSend(string $sendTime): bool
    {
        $now = now();
        $targetTime = today()->setTimeFromTimeString($sendTime);
        
        // Send if within 30 minutes of target time
        return $now->diffInMinutes($targetTime, false) >= -30 && 
               $now->diffInMinutes($targetTime, false) <= 30;
    }

    /**
     * Handle job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error('SMS reminders job failed completely: ' . $exception->getMessage());
    }
}
