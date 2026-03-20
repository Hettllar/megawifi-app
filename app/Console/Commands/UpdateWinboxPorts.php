<?php

namespace App\Console\Commands;

use App\Services\IptablesHelper;
use Illuminate\Console\Command;
use App\Models\Router;

class UpdateWinboxPorts extends Command
{
    protected $signature = 'winbox:update-ports';
    protected $description = 'Update WinBox port forwarding rules for all active routers';

    public function handle()
    {
        $this->info('=== Updating WinBox Port Forwarding ===');

        // Get active routers with WireGuard IP
        $routers = Router::where('is_active', true)
            ->whereNotNull('wg_client_ip')
            ->orderBy('id')
            ->get();

        if ($routers->isEmpty()) {
            $this->warn('No active routers with WireGuard IP found.');
            return 1;
        }

        $iptables = new IptablesHelper();
        $basePort = 8291;
        $port = $basePort;

        foreach ($routers as $router) {
            $ip = $router->wg_client_ip;
            $this->line("Router: {$router->name} -> Port {$port} -> {$ip}:8291");
            $iptables->addRule($port, $ip);
            $port++;
        }

        $this->newLine();
        $this->info("=== Done! Configured {$routers->count()} routers ===");
        $this->newLine();

        // Show connection info
        $this->table(
            ['Router', 'WinBox Address'],
            $routers->map(function ($router, $index) use ($basePort) {
                return [
                    $router->name,
                    "152.53.131.211:" . ($basePort + $index)
                ];
            })
        );

        return 0;
    }
}
