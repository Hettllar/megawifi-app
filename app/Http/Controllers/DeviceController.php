<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class DeviceController extends Controller
{
    /**
     * Display connected devices from all routers
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $routers = Router::whereIn('id', $routerIds)->get();
        
        $selectedRouterId = $request->router_id;
        $devices = [];
        $error = null;

        // If router selected, fetch devices
        if ($selectedRouterId && $routerIds->contains($selectedRouterId)) {
            $router = Router::find($selectedRouterId);
            if ($router) {
                try {
                    $service = new MikroTikService($router);
                    $service->connect();
                    $devices = $this->getConnectedDevices($service, $request->type ?? 'all');
                    $service->disconnect();
                    
                    // Apply search filters
                    $devices = $this->filterDevices($devices, $request);
                } catch (Exception $e) {
                    $error = 'فشل الاتصال بالراوتر: ' . $e->getMessage();
                }
            }
        }

        return view('devices.index', compact('routers', 'devices', 'selectedRouterId', 'error'));
    }

    /**
     * Filter devices based on search criteria
     */
    private function filterDevices(array $devices, Request $request): array
    {
        $search = $request->search;
        $ip = $request->ip;
        $mac = $request->mac;
        $username = $request->username;
        
        if (!$search && !$ip && !$mac && !$username) {
            return $devices;
        }
        
        return array_filter($devices, function($device) use ($search, $ip, $mac, $username) {
            // General search
            if ($search) {
                $searchLower = strtolower($search);
                $matchSearch = false;
                
                if (isset($device['ip']) && stripos($device['ip'], $search) !== false) $matchSearch = true;
                if (isset($device['mac']) && stripos($device['mac'], $search) !== false) $matchSearch = true;
                if (isset($device['username']) && stripos($device['username'], $search) !== false) $matchSearch = true;
                if (isset($device['hostname']) && stripos($device['hostname'], $search) !== false) $matchSearch = true;
                if (isset($device['comment']) && stripos($device['comment'], $search) !== false) $matchSearch = true;
                
                if (!$matchSearch) return false;
            }
            
            // IP filter
            if ($ip && isset($device['ip'])) {
                if (stripos($device['ip'], $ip) === false) return false;
            }
            
            // MAC filter
            if ($mac && isset($device['mac'])) {
                $macClean = str_replace([':', '-'], '', strtolower($mac));
                $deviceMacClean = str_replace([':', '-'], '', strtolower($device['mac']));
                if (stripos($deviceMacClean, $macClean) === false) return false;
            }
            
            // Username filter
            if ($username && isset($device['username'])) {
                if (stripos($device['username'], $username) === false) return false;
            }
            
            return true;
        });
    }

    /**
     * Get connected devices from router
     */
    private function getConnectedDevices(MikroTikService $service, string $type = 'all'): array
    {
        $devices = [];
        
        // Get ARP entries
        if ($type === 'all' || $type === 'arp') {
            $arpList = $service->command(['/ip/arp/print']);
            foreach ($arpList as $arp) {
                if (isset($arp['.id'])) {
                    $devices[] = [
                        'type' => 'arp',
                        'ip' => $arp['address'] ?? '-',
                        'mac' => $arp['mac-address'] ?? '-',
                        'interface' => $arp['interface'] ?? '-',
                        'status' => isset($arp['complete']) && $arp['complete'] === 'true' ? 'complete' : 'incomplete',
                        'comment' => $arp['comment'] ?? '',
                    ];
                }
            }
        }
        
        // Get DHCP leases
        if ($type === 'all' || $type === 'dhcp') {
            $dhcpLeases = $service->command(['/ip/dhcp-server/lease/print']);
            foreach ($dhcpLeases as $lease) {
                if (isset($lease['.id'])) {
                    $devices[] = [
                        'type' => 'dhcp',
                        'ip' => $lease['address'] ?? '-',
                        'mac' => $lease['mac-address'] ?? '-',
                        'hostname' => $lease['host-name'] ?? '-',
                        'server' => $lease['server'] ?? '-',
                        'status' => $lease['status'] ?? 'unknown',
                        'expires' => $lease['expires-after'] ?? '-',
                        'comment' => $lease['comment'] ?? '',
                    ];
                }
            }
        }
        
        // Get PPPoE active connections
        if ($type === 'all' || $type === 'pppoe') {
            $pppoeActive = $service->command(['/ppp/active/print']);
            
            // Get all PPPoE server interfaces ONCE to map remote addresses
            $pppoeServerMap = [];
            try {
                $pppoeServers = $service->command(['/interface/pppoe-server/print']);
                foreach ($pppoeServers as $server) {
                    if (isset($server['name'])) {
                        $pppoeServerMap[$server['name']] = $server;
                    }
                }
            } catch (Exception $e) {}
            
            foreach ($pppoeActive as $ppp) {
                if (isset($ppp['.id'])) {
                    $remoteIp = '-';
                    $localIp = $ppp['address'] ?? '-';
                    
                    // Look up remote-address from cached map
                    $ifaceName = '<pppoe-' . ($ppp['name'] ?? '') . '>';
                    if (isset($pppoeServerMap[$ifaceName]['remote-address'])) {
                        $remoteIp = $pppoeServerMap[$ifaceName]['remote-address'];
                    }
                    
                    $devices[] = [
                        'type' => 'pppoe',
                        'username' => $ppp['name'] ?? '-',
                        'ip' => $localIp,
                        'remote_ip' => $remoteIp,
                        'mac' => $ppp['caller-id'] ?? '-',
                        'service' => $ppp['service'] ?? '-',
                        'uptime' => $ppp['uptime'] ?? '-',
                        'encoding' => $ppp['encoding'] ?? '-',
                    ];
                }
            }
        }
        
        // Get PPPoE server interfaces (Remote type)
        if ($type === 'all' || $type === 'remote') {
            try {
                $pppoeInterfaces = $service->command(['/interface/pppoe-server/print']);
                foreach ($pppoeInterfaces as $iface) {
                    if (isset($iface['.id'])) {
                        $devices[] = [
                            'type' => 'remote',
                            'username' => $iface['user'] ?? $iface['name'] ?? '-',
                            'ip' => $iface['local-address'] ?? '-',
                            'remote_ip' => $iface['remote-address'] ?? '-',
                            'mac' => $iface['caller-id'] ?? '-',
                            'interface' => $iface['name'] ?? '-',
                            'service' => $iface['service'] ?? '-',
                            'uptime' => $iface['uptime'] ?? '-',
                            'status' => isset($iface['running']) && $iface['running'] === 'true' ? 'running' : 'stopped',
                        ];
                    }
                }
            } catch (Exception $e) {
                // PPPoE server may not exist
            }
        }
        
        // Get Hotspot active
        if ($type === 'all' || $type === 'hotspot') {
            $hotspotActive = $service->command(['/ip/hotspot/active/print']);
            foreach ($hotspotActive as $hs) {
                if (isset($hs['.id'])) {
                    $devices[] = [
                        'type' => 'hotspot',
                        'username' => $hs['user'] ?? '-',
                        'ip' => $hs['address'] ?? '-',
                        'mac' => $hs['mac-address'] ?? '-',
                        'server' => $hs['server'] ?? '-',
                        'uptime' => $hs['uptime'] ?? '-',
                        'bytes_in' => $hs['bytes-in'] ?? 0,
                        'bytes_out' => $hs['bytes-out'] ?? 0,
                    ];
                }
            }
        }
        
        // Get Wireless clients
        if ($type === 'all' || $type === 'wireless') {
            try {
                $wirelessClients = $service->command(['/interface/wireless/registration-table/print']);
                foreach ($wirelessClients as $client) {
                    if (isset($client['.id'])) {
                        $devices[] = [
                            'type' => 'wireless',
                            'mac' => $client['mac-address'] ?? '-',
                            'interface' => $client['interface'] ?? '-',
                            'signal' => $client['signal-strength'] ?? '-',
                            'tx_rate' => $client['tx-rate'] ?? '-',
                            'rx_rate' => $client['rx-rate'] ?? '-',
                            'uptime' => $client['uptime'] ?? '-',
                        ];
                    }
                }
            } catch (Exception $e) {
                // Wireless may not be available
            }
        }
        
        return $devices;
    }

    /**
     * Refresh devices list via AJAX
     */
    public function refresh(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        if (!$request->router_id || !$routerIds->contains($request->router_id)) {
            return response()->json(['success' => false, 'message' => 'يرجى اختيار راوتر']);
        }

        $router = Router::find($request->router_id);
        
        try {
            $service = new MikroTikService($router);
            $service->connect();
            $devices = $this->getConnectedDevices($service, $request->type ?? 'all');
            $service->disconnect();
            
            return response()->json([
                'success' => true,
                'devices' => $devices,
                'count' => count($devices),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل الاتصال: ' . $e->getMessage(),
            ], 500);
        }
    }
}
