<?php

namespace App\Jobs;

use App\Models\Router;
use App\Services\MikroTikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class SyncRouterData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    
    protected Router $router;
    protected bool $fullSync;

    /**
     * Create a new job instance.
     */
    public function __construct(Router $router, bool $fullSync = false)
    {
        $this->router = $router;
        $this->fullSync = $fullSync;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $service = new MikroTikService($this->router);
            $service->connect();

            // Always update router info and sessions
            $service->updateRouterInfo();
            $service->syncActiveSessions();

            // Full sync includes users and profiles
            if ($this->fullSync) {
                $service->syncProfiles();
                $service->syncPPPSecrets();
            }

            $service->disconnect();

            Log::info("Router sync completed: {$this->router->name}");
        } catch (Exception $e) {
            Log::error("Router sync failed for {$this->router->name}: " . $e->getMessage());
            
            $this->router->update(['status' => 'offline']);
            
            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error("SyncRouterData job failed for router {$this->router->id}: " . $exception->getMessage());
    }
}
