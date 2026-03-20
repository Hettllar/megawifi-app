<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Services\MikroTikAPI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WireGuardController extends Controller
{
    /**
     * Check WireGuard status on router
     */
    public function status(Request $request)
    {
        try {
            $routerId = $request->get('router_id');
            $router = Router::findOrFail($routerId);
            
            $api = $this->connectToRouter($router);
            if (!$api) {
                return response()->json(['active' => false, 'message' => 'لا يمكن الاتصال بالراوتر']);
            }
            
            // Check if WireGuard interface exists
            $interfaces = $api->comm(['/interface/wireguard/print']);
            
            if (empty($interfaces) || (isset($interfaces[0][0]) && $interfaces[0][0] === '!trap')) {
                $api->disconnect();
                return response()->json([
                    'active' => false,
                    'message' => 'WireGuard غير مفعل'
                ]);
            }
            
            $wgInterface = $interfaces[0] ?? [];
            
            // Get public key
            $publicKey = $wgInterface['public-key'] ?? '';
            
            // Get listen port
            $listenPort = $wgInterface['listen-port'] ?? 51820;
            
            // Get router's public IP
            $publicIp = $this->getRouterPublicIp($api, $router);
            
            // Get peers
            $wgName = $wgInterface['name'] ?? 'wireguard1';
            $peers = $api->comm(['/interface/wireguard/peers/print', '?interface=' . $wgName]);
            
            if (!is_array($peers) || (isset($peers[0][0]) && $peers[0][0] === '!trap')) {
                $peers = [];
            }
            
            $api->disconnect();
            
            return response()->json([
                'active' => true,
                'interface' => $wgName,
                'public_key' => $publicKey,
                'listen_port' => $listenPort,
                'endpoint' => $publicIp . ':' . $listenPort,
                'peers' => $peers
            ]);
            
        } catch (\Exception $e) {
            Log::error('WireGuard status error: ' . $e->getMessage());
            return response()->json([
                'active' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Setup WireGuard on router
     */
    public function setup(Request $request)
    {
        try {
            $routerId = $request->get('router_id');
            $address = $request->get('address', '10.10.10.1/24');
            $port = $request->get('port', 51820);
            
            $router = Router::findOrFail($routerId);
            
            $api = $this->connectToRouter($router);
            if (!$api) {
                return response()->json(['success' => false, 'message' => 'لا يمكن الاتصال بالراوتر']);
            }
            
            // Check if WireGuard interface already exists
            $existing = $api->comm(['/interface/wireguard/print']);
            
            if (!empty($existing) && !(isset($existing[0][0]) && $existing[0][0] === '!trap')) {
                $api->disconnect();
                return response()->json([
                    'success' => false,
                    'message' => 'WireGuard موجود مسبقاً'
                ]);
            }
            
            // Create WireGuard interface
            $result = $api->comm([
                '/interface/wireguard/add',
                '=name=wireguard1',
                '=listen-port=' . $port,
                '=mtu=1420'
            ]);
            
            // Add IP address to WireGuard interface
            $api->comm([
                '/ip/address/add',
                '=address=' . $address,
                '=interface=wireguard1'
            ]);
            
            // Add firewall rule to allow WireGuard
            $api->comm([
                '/ip/firewall/filter/add',
                '=chain=input',
                '=protocol=udp',
                '=dst-port=' . $port,
                '=action=accept',
                '=comment=WireGuard VPN',
                '=place-before=0'
            ]);
            
            // Enable masquerade for WireGuard network
            $network = explode('/', $address)[0];
            $networkPrefix = implode('.', array_slice(explode('.', $network), 0, 3)) . '.0/24';
            
            $api->comm([
                '/ip/firewall/nat/add',
                '=chain=srcnat',
                '=src-address=' . $networkPrefix,
                '=action=masquerade',
                '=comment=WireGuard NAT'
            ]);
            
            $api->disconnect();
            
            return response()->json([
                'success' => true,
                'message' => 'تم إعداد WireGuard بنجاح'
            ]);
            
        } catch (\Exception $e) {
            Log::error('WireGuard setup error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Add peer to WireGuard
     */
    public function addPeer(Request $request)
    {
        try {
            $routerId = $request->get('router_id');
            $peerName = $request->get('name');
            $peerIp = $request->get('ip', '10.10.10.2/32');
            
            $router = Router::findOrFail($routerId);
            
            $api = $this->connectToRouter($router);
            if (!$api) {
                return response()->json(['success' => false, 'message' => 'لا يمكن الاتصال بالراوتر']);
            }
            
            // Get WireGuard interface info
            $interfaces = $api->comm(['/interface/wireguard/print']);
            
            if (empty($interfaces) || (isset($interfaces[0][0]) && $interfaces[0][0] === '!trap')) {
                $api->disconnect();
                return response()->json([
                    'success' => false,
                    'message' => 'WireGuard غير مفعل'
                ]);
            }
            
            $wgInterface = $interfaces[0] ?? [];
            $serverPublicKey = $wgInterface['public-key'] ?? '';
            $listenPort = $wgInterface['listen-port'] ?? 51820;
            
            // Generate keys for the peer using RouterOS
            $tempName = 'wg_temp_' . time();
            $api->comm(['/interface/wireguard/add', '=name=' . $tempName]);
            
            // Get the temp interface to retrieve its keys
            $tempInterfaces = $api->comm(['/interface/wireguard/print', '?name=' . $tempName]);
            
            $peerPrivateKey = '';
            $peerPublicKey = '';
            
            if (!empty($tempInterfaces) && isset($tempInterfaces[0])) {
                $peerPrivateKey = $tempInterfaces[0]['private-key'] ?? '';
                $peerPublicKey = $tempInterfaces[0]['public-key'] ?? '';
                
                // Remove temp interface
                if (isset($tempInterfaces[0]['.id'])) {
                    $api->comm(['/interface/wireguard/remove', '=.id=' . $tempInterfaces[0]['.id']]);
                }
            }
            
            // If no keys generated, use PHP sodium
            if (empty($peerPrivateKey) || empty($peerPublicKey)) {
                $peerPrivateKey = $this->generatePrivateKey();
                $peerPublicKey = $this->generatePublicKey($peerPrivateKey);
            }
            
            // Add peer to WireGuard
            $wgName = $wgInterface['name'] ?? 'wireguard1';
            $api->comm([
                '/interface/wireguard/peers/add',
                '=interface=' . $wgName,
                '=public-key=' . $peerPublicKey,
                '=allowed-address=' . $peerIp,
                '=comment=' . $peerName
            ]);
            
            // Get router public IP
            $publicIp = $this->getRouterPublicIp($api, $router);
            
            // Get WireGuard address
            $addresses = $api->comm(['/ip/address/print', '?interface=' . $wgName]);
            
            $serverAddress = '';
            $dnsServer = '';
            if (!empty($addresses) && isset($addresses[0])) {
                $serverAddress = explode('/', $addresses[0]['address'] ?? '')[0];
                $dnsServer = $serverAddress;
            }
            
            $api->disconnect();
            
            // Generate client config
            $clientIp = str_replace('/32', '/32', $peerIp);
            $config = "[Interface]\n";
            $config .= "PrivateKey = {$peerPrivateKey}\n";
            $config .= "Address = {$clientIp}\n";
            $config .= "DNS = {$dnsServer}\n\n";
            $config .= "[Peer]\n";
            $config .= "PublicKey = {$serverPublicKey}\n";
            $config .= "AllowedIPs = 0.0.0.0/0\n";
            $config .= "Endpoint = {$publicIp}:{$listenPort}\n";
            $config .= "PersistentKeepalive = 25\n";
            
            return response()->json([
                'success' => true,
                'config' => $config,
                'public_key' => $peerPublicKey
            ]);
            
        } catch (\Exception $e) {
            Log::error('WireGuard add peer error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Remove peer from WireGuard
     */
    public function removePeer(Request $request)
    {
        try {
            $routerId = $request->get('router_id');
            $peerId = $request->get('peer_id');
            
            $router = Router::findOrFail($routerId);
            
            $api = $this->connectToRouter($router);
            if (!$api) {
                return response()->json(['success' => false, 'message' => 'لا يمكن الاتصال بالراوتر']);
            }
            
            $api->comm(['/interface/wireguard/peers/remove', '=.id=' . $peerId]);
            
            $api->disconnect();
            
            return response()->json([
                'success' => true,
                'message' => 'تم حذف الجهاز'
            ]);
            
        } catch (\Exception $e) {
            Log::error('WireGuard remove peer error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Connect to MikroTik router
     */
    private function connectToRouter($router): ?MikroTikAPI
    {
        try {
            $connectionIP = $router->wg_enabled && $router->wg_client_ip 
                ? $router->wg_client_ip 
                : $router->ip_address;
            $api = new MikroTikAPI(
                $connectionIP,
                $router->api_port ?? 8728,
                $router->api_username ?? 'admin',
                $router->api_password ?? ''
            );
            $api->connect();
            return $api;
        } catch (\Exception $e) {
            Log::error('Router connection failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get router's public IP
     */
    private function getRouterPublicIp($api, $router): string
    {
        try {
            // Try to get public IP from cloud
            $cloud = $api->comm(['/ip/cloud/print']);
            
            if (!empty($cloud) && isset($cloud[0]['public-address'])) {
                return $cloud[0]['public-address'];
            }
            
            // Fallback: use router's IP from database
            return $router->ip_address;
        } catch (\Exception $e) {
            return $router->ip_address;
        }
    }
    
    /**
     * Generate WireGuard private key (base64)
     */
    private function generatePrivateKey(): string
    {
        $key = random_bytes(32);
        $key[0] = chr(ord($key[0]) & 248);
        $key[31] = chr((ord($key[31]) & 127) | 64);
        return base64_encode($key);
    }
    
    /**
     * Generate WireGuard public key from private key
     */
    private function generatePublicKey(string $privateKey): string
    {
        // This requires sodium extension
        if (function_exists('sodium_crypto_scalarmult_base')) {
            $privateKeyBin = base64_decode($privateKey);
            $publicKeyBin = sodium_crypto_scalarmult_base($privateKeyBin);
            return base64_encode($publicKeyBin);
        }
        
        // Fallback: return empty (will use RouterOS generated keys)
        return '';
    }
}
