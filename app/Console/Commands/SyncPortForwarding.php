<?php

namespace App\Console\Commands;

use App\Models\Router;
use App\Services\IptablesHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPortForwarding extends Command
{
    protected $signature = 'router:sync-ports {router_id?}';
    protected $description = 'Sync port forwarding rules for routers';

    public function handle(): int
    {
        $routerId = $this->argument('router_id');

        if ($routerId) {
            $routers = Router::where('id', $routerId)->get();
            if ($routers->isEmpty()) {
                $this->error("Router not found with ID: {$routerId}");
                return 1;
            }
        } else {
            $routers = Router::where('is_active', true)
                ->where('wg_enabled', true)
                ->whereNotNull('wg_client_ip')
                ->whereNotNull('public_port')
                ->get();
        }

        if ($routers->isEmpty()) {
            $this->warn('No routers found to sync');
            return 0;
        }

        $this->info("Found {$routers->count()} router(s) to sync");
        $this->newLine();

        foreach ($routers as $router) {
            $this->syncRouter($router);
        }

        $this->newLine();
        $this->info('Port forwarding sync completed');

        return 0;
    }

    private function syncRouter(Router $router): void
    {
        $this->line("Router: {$router->name} (ID: {$router->id})");
        $this->line("  WG IP: {$router->wg_client_ip}");
        $this->line("  Public Port: {$router->public_port}");

        try {
            $iptables = new IptablesHelper();

            // Check if rule exists
            $checkResult = $iptables->checkRule($router->public_port);

            if ($checkResult === 'FOUND') {
                $this->line("  Rule already exists");
                return;
            }

            $this->line("  Rule missing, adding...");

            // Add full rule (DNAT + MASQUERADE + FORWARD + save)
            $result = $iptables->addRule($router->public_port, $router->wg_client_ip);

            $this->line("  Rule added: {$result}");

            Log::info("Port forwarding added for router {$router->id}", [
                'router_name' => $router->name,
                'wg_ip' => $router->wg_client_ip,
                'public_port' => $router->public_port,
            ]);

        } catch (\Exception $e) {
            $this->error("  Error: {$e->getMessage()}");
            Log::error("Failed to sync port forwarding for router {$router->id}: {$e->getMessage()}");
        }
    }
}
