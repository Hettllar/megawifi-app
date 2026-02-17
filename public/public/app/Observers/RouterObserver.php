<?php

namespace App\Observers;

use App\Models\Router;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RouterObserver
{
    /**
     * Handle the Router "created" event.
     */
    public function created(Router $router): void
    {
        $this->updateWinboxPorts($router, 'created');
    }

    /**
     * Handle the Router "updated" event.
     */
    public function updated(Router $router): void
    {
        // Update if relevant fields changed (IP, port, or active status)
        if ($router->wasChanged(['wg_client_ip', 'ip_address', 'public_port', 'is_active'])) {
            $this->updateWinboxPorts($router, 'updated');
        }
    }

    /**
     * Handle the Router "deleted" event.
     */
    public function deleted(Router $router): void
    {
        $this->updateWinboxPorts($router, 'deleted');
    }

    /**
     * Update WinBox port forwarding rules
     */
    private function updateWinboxPorts(Router $router, string $action): void
    {
        try {
            if ($action === 'deleted' && $router->public_port && $router->wg_client_ip) {
                // Remove port forwarding rules for deleted router
                $port = (int) $router->public_port;
                $ip = $router->wg_client_ip;
                exec("sudo /usr/sbin/iptables -t nat -D PREROUTING -p tcp --dport {$port} -j DNAT --to-destination {$ip}:8291 2>/dev/null");
                exec("sudo /usr/sbin/iptables -t nat -D POSTROUTING -p tcp -d {$ip} --dport 8291 -j MASQUERADE 2>/dev/null");
                exec("sudo /usr/sbin/iptables -D FORWARD -p tcp -d {$ip} --dport 8291 -j ACCEPT 2>/dev/null");
                exec('sudo /usr/sbin/iptables-save | sudo tee /etc/iptables/rules.v4 > /dev/null 2>&1');
                Log::info("Port forwarding removed for deleted router {$router->name} (port {$port})");
            } elseif ($action === 'created' && $router->public_port && $router->wg_client_ip) {
                // Auto-add port forwarding for new router
                $port = (int) $router->public_port;
                $ip = $router->wg_client_ip;
                exec("sudo /usr/sbin/iptables -t nat -A PREROUTING -p tcp --dport {$port} -j DNAT --to-destination {$ip}:8291 2>/dev/null");
                exec("sudo /usr/sbin/iptables -t nat -A POSTROUTING -p tcp -d {$ip} --dport 8291 -j MASQUERADE 2>/dev/null");
                exec("sudo /usr/sbin/iptables -C FORWARD -p tcp -d {$ip} --dport 8291 -j ACCEPT 2>/dev/null || sudo /usr/sbin/iptables -A FORWARD -p tcp -d {$ip} --dport 8291 -j ACCEPT 2>/dev/null");
                exec('sudo /usr/sbin/iptables-save | sudo tee /etc/iptables/rules.v4 > /dev/null 2>&1');
                Log::info("Port forwarding auto-added for new router {$router->name} (port {$port} -> {$ip}:8291)");
            } elseif ($action === 'updated' && $router->public_port && $router->wg_client_ip) {
                // Re-sync: run artisan command in background
                Artisan::call('router:sync-ports', ['router_id' => $router->id]);
                Log::info("Port forwarding synced after router update: {$router->name}");
            }
        } catch (\Exception $e) {
            Log::error("Failed to update WinBox ports: " . $e->getMessage());
        }
    }
}
