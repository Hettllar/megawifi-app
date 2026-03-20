<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class VPNConfigController extends Controller
{
    private $serverPublicKey = 'grZHPw4NCDEFciiKoNbjZLGeldZpltru+5CUOF66l2I=';
    private $serverEndpoint = '152.53.128.114:51820';
    private $serverNetwork = '10.10.0.0/24';
    
    /**
     * Get or create VPN config for the current user
     */
    public function getConfig(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            
            $vpnConfig = $this->getUserVPNConfig($user);
            
            if (!$vpnConfig) {
                $vpnConfig = $this->createUserVPNConfig($user);
            }
            
            $configContent = $this->generateConfigFile($vpnConfig);
            
            return response()->json([
                'success' => true,
                'config' => $configContent,
                'client_ip' => $vpnConfig['client_ip'],
                'private_key' => $vpnConfig['private_key'],
                'public_key' => $vpnConfig['public_key'],
                'server_public_key' => $this->serverPublicKey,
                'server_endpoint' => $this->serverEndpoint,
                'allowed_ips' => $this->serverNetwork,
                'qr_data' => $configContent
            ]);
            
        } catch (\Exception $e) {
            Log::error('VPN Config error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Get user's existing VPN config from cache
     */
    private function getUserVPNConfig($user)
    {
        $cacheKey = 'vpn_config_user_' . $user->id;
        return cache($cacheKey);
    }
    
    /**
     * Create new VPN config for user
     */
    private function createUserVPNConfig($user)
    {
        // Generate WireGuard keys
        $privateKey = trim(shell_exec('wg genkey 2>/dev/null'));
        $publicKey = '';
        
        if ($privateKey) {
            $publicKey = trim(shell_exec('echo "' . $privateKey . '" | wg pubkey 2>/dev/null'));
        }
        
        // Fallback to default test keys if wg command not available
        if (empty($publicKey)) {
            $privateKey = 'YGFU+NNoq4G2zn1Bm9ybew7Ki+E9tF4O56rkL0PzmH0=';
            $publicKey = 'f6agsRWFnClIUisBIAjx+/HUnYPaEVr/W6MXkwLSRGs=';
        }
        
        // Assign unique IP based on user ID (10.10.0.100 - 10.10.0.250)
        $clientIP = '10.10.0.' . (100 + ($user->id % 150));
        
        $config = [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
            'client_ip' => $clientIP . '/24',
            'user_id' => $user->id
        ];
        
        // Add peer to WireGuard server
        $this->addPeerToServer($publicKey, $clientIP);
        
        // Cache for 30 days
        $cacheKey = 'vpn_config_user_' . $user->id;
        cache([$cacheKey => $config], now()->addDays(30));
        
        return $config;
    }
    
    /**
     * Add peer to WireGuard server
     */
    private function addPeerToServer($publicKey, $clientIP)
    {
        $cmd = sprintf(
            'wg set wg0 peer %s allowed-ips %s/32 2>&1',
            escapeshellarg($publicKey),
            $clientIP
        );
        shell_exec($cmd);
        shell_exec('wg-quick save wg0 2>&1');
        Log::info('VPN: Added peer ' . $clientIP);
    }
    
    /**
     * Generate WireGuard config file content
     */
    private function generateConfigFile($config)
    {
        $privateKey = $config['private_key'];
        $clientIP = $config['client_ip'];
        
        return "[Interface]
PrivateKey = {$privateKey}
Address = {$clientIP}
DNS = 8.8.8.8

[Peer]
PublicKey = {$this->serverPublicKey}
AllowedIPs = {$this->serverNetwork}
Endpoint = {$this->serverEndpoint}
PersistentKeepalive = 25";
    }
    
    /**
     * Download config as file
     */
    public function downloadConfig(Request $request)
    {
        $response = $this->getConfig($request);
        $data = json_decode($response->getContent(), true);
        
        if (isset($data['error'])) {
            return response($data['error'], 400);
        }
        
        return response($data['config'])
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="megawifi-vpn.conf"');
    }
}
