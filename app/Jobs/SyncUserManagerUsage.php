<?php

namespace App\Jobs;

use App\Models\Router;
use App\Models\SyncSetting;
use App\Services\UserManagerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class SyncUserManagerUsage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 2;
    
    protected ?int $routerId;

    /**
     * Create a new job instance.
     * @param int|null $routerId If provided, sync only this router. Otherwise sync all routers.
     */
    public function __construct(?int $routerId = null)
    {
        $this->routerId = $routerId;
    }

    /**
     * Execute the job - Sync usage for UserManager users
     */
    public function handle(): void
    {
        // If specific router is provided, sync only that router
        if ($this->routerId) {
            $router = Router::find($this->routerId);
            if ($router && $router->is_active) {
                $this->syncRouterUsage($router);
            }
            return;
        }

        // Otherwise sync all routers (legacy behavior)
        Log::info('SyncUserManagerUsage: بدء مزامنة استهلاك UserManager...');

        $routers = Router::where('is_active', true)->get();

        $totalStats = [
            'routers' => 0,
            'synced' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($routers as $router) {
            try {
                $result = $this->syncRouterUsage($router);

                if ($result['success']) {
                    $totalStats['routers']++;
                    $totalStats['synced'] += $result['synced'];
                    $totalStats['failed'] += $result['failed'];
                }

            } catch (Exception $e) {
                Log::error("SyncUserManagerUsage: خطأ في الراوتر {$router->name}: " . $e->getMessage());
                $totalStats['errors'][] = $router->name;
            }
        }

        Log::info("SyncUserManagerUsage: الملخص - {$totalStats['routers']} راوتر، تم مزامنة {$totalStats['synced']} مستخدم، فشل {$totalStats['failed']}");
    }

    /**
     * Sync usage for a specific router
     */
    protected function syncRouterUsage(Router $router): array
    {
        // Check if router is reachable first
        if (!UserManagerService::isRouterReachable($router)) {
            Log::debug("SyncUserManagerUsage: الراوتر {$router->name} غير متاح");
            return ['success' => false, 'synced' => 0, 'failed' => 0];
        }

        $service = new UserManagerService($router);

        if (!$service->connect()) {
            Log::warning("SyncUserManagerUsage: فشل الاتصال بالراوتر {$router->name}");
            return ['success' => false, 'synced' => 0, 'failed' => 0];
        }

        try {
            $result = $service->syncUsage();
            Log::info("SyncUserManagerUsage: {$router->name} - تم مزامنة {$result['synced']} مستخدم (interval: {$router->sync_interval}م)");

            return [
                'success' => true,
                'synced' => $result['synced'] ?? 0,
                'failed' => $result['failed'] ?? 0,
            ];

        } catch (Exception $e) {
            Log::error("SyncUserManagerUsage: خطأ في مزامنة {$router->name}: " . $e->getMessage());
            return ['success' => false, 'synced' => 0, 'failed' => 0];

        } finally {
            $service->disconnect();
        }
    }
}