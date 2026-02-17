<?php

namespace App\Services;

use App\Models\Router;
use Illuminate\Support\Facades\Log;

class WireGuardService
{
    protected $serverPublicKey;
    protected $serverEndpoint;
    protected $wgInterface = 'wg0';
    protected $wgConfigPath = '/etc/wireguard/wg0.conf';
    protected $serverPrivateKeyPath = '/etc/wireguard/server_private.key';
    protected $serverPublicKeyPath = '/etc/wireguard/server_public.key';
    
    public function __construct()
    {
        $this->serverEndpoint = config('wireguard.endpoint', '104.207.66.159:51820');
        $this->serverPublicKey = config('wireguard.server_public_key', $this->getServerPublicKey());
    }
    
    /**
     * Get server's public key
     */
    protected function getServerPublicKey(): string
    {
        if (file_exists($this->serverPublicKeyPath)) {
            return trim(file_get_contents($this->serverPublicKeyPath));
        }
        
        // Try to read from shell
        $output = shell_exec("cat {$this->serverPublicKeyPath} 2>/dev/null");
        return trim($output ?? '');
    }
    
    /**
     * Generate WireGuard key pair for a router
     */
    public function generateKeyPair(): array
    {
        // Check if wg command is available
        if (!$this->isWgCommandAvailable()) {
            // Fallback: generate keys using PHP (not as secure but works everywhere)
            return $this->generateKeyPairPHP();
        }
        
        try {
            // Generate private key
            $privateKey = trim(shell_exec('wg genkey 2>&1'));
            
            if (empty($privateKey) || strlen($privateKey) < 40) {
                throw new \Exception('Failed to generate private key');
            }
            
            // Generate public key from private key
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows
                $publicKey = trim(shell_exec("echo {$privateKey} | wg pubkey 2>&1"));
            } else {
                // Linux/Unix
                $publicKey = trim(shell_exec("echo '{$privateKey}' | wg pubkey 2>&1"));
            }
            
            if (empty($publicKey) || strlen($publicKey) < 40) {
                throw new \Exception('Failed to generate public key');
            }
            
            return [
                'private_key' => $privateKey,
                'public_key' => $publicKey,
            ];
        } catch (\Exception $e) {
            Log::warning('WireGuard key generation failed, using PHP fallback: ' . $e->getMessage());
            return $this->generateKeyPairPHP();
        }
    }
    
    /**
     * Check if wg command is available
     */
    protected function isWgCommandAvailable(): bool
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $check = shell_exec('where wg 2>&1');
        } else {
            $check = shell_exec('which wg 2>&1');
        }
        
        return !empty($check) && stripos($check, 'not found') === false;
    }
    
    /**
     * Generate keys using PHP (fallback method)
     */
    protected function generateKeyPairPHP(): array
    {
        // Generate random 32 bytes for private key
        $privateKeyBytes = random_bytes(32);
        $privateKey = base64_encode($privateKeyBytes);
        
        // For public key, we need Curve25519 which requires sodium extension
        if (function_exists('sodium_crypto_box_publickey_from_secretkey')) {
            $publicKeyBytes = sodium_crypto_box_publickey_from_secretkey($privateKeyBytes);
            $publicKey = base64_encode($publicKeyBytes);
        } else {
            // If sodium not available, generate another random key (not cryptographically correct but works for testing)
            $publicKey = base64_encode(random_bytes(32));
            Log::warning('Sodium extension not available, using fallback key generation');
        }
        
        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }
    
    /**
     * Get next available IP for WireGuard tunnel
     */
    public function getNextAvailableIP(): string
    {
        // Get all used IPs from routers database
        $usedIPs = Router::whereNotNull('wg_client_ip')
            ->pluck('wg_client_ip')
            ->map(function ($ip) {
                // Extract last octet
                preg_match('/10\.0\.0\.(\d+)/', $ip, $matches);
                return (int)($matches[1] ?? 0);
            })
            ->toArray();
        
        // Also check WireGuard peers (in case there are orphan peers)
        $wgOutput = shell_exec('sudo /usr/bin/wg show wg0 allowed-ips 2>/dev/null') ?? '';
        preg_match_all('/10\.0\.0\.(\d+)/', $wgOutput, $wgMatches);
        if (!empty($wgMatches[1])) {
            $usedIPs = array_merge($usedIPs, array_map('intval', $wgMatches[1]));
        }
        
        $usedIPs = array_unique($usedIPs);
        
        // Start from .10 (leaving .1 for server, .2-9 for manual clients)
        for ($i = 10; $i <= 254; $i++) {
            if (!in_array($i, $usedIPs)) {
                return "10.0.0.{$i}";
            }
        }
        
        throw new \Exception('No available IP addresses in WireGuard subnet');
    }
    
    /**
     * Add peer to WireGuard server configuration
     */
    public function addPeerToServer(Router $router): bool
    {
        if (!$router->wg_public_key || !$router->wg_client_ip) {
            Log::warning("Cannot add WireGuard peer: missing public_key or client_ip for router {$router->id}");
            return false;
        }
        
        // التحقق من أن المفتاح ليس مفتاح السيرفر
        if ($router->wg_public_key === $this->serverPublicKey) {
            Log::error("Cannot add WireGuard peer: public key is server's own key for router {$router->id}");
            return false;
        }
        
        // Add peer using wg command (live update) - needs sudo for www-data
        $cmd = sprintf(
            'sudo /usr/bin/wg set %s peer %s allowed-ips %s/32 2>&1',
            $this->wgInterface,
            escapeshellarg($router->wg_public_key),
            $router->wg_client_ip
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            Log::error("Failed to add WireGuard peer for router {$router->id}: " . implode("\n", $output));
            return false;
        }
        
        // Save config for persistence
        exec("sudo /usr/bin/wg-quick save {$this->wgInterface} 2>&1", $saveOutput, $saveReturn);
        
        if ($saveReturn !== 0) {
            Log::warning("WireGuard peer added but config save failed: " . implode("\n", $saveOutput));
        }
        
        Log::info("WireGuard peer added successfully for router {$router->id} ({$router->wg_client_ip})");
        
        return true;
    }
    
    /**
     * Remove peer from WireGuard server
     */
    public function removePeerFromServer(Router $router): bool
    {
        if (!$router->wg_public_key) {
            return false;
        }
        
        // Remove peer using wg command with sudo
        $cmd = sprintf(
            'sudo /usr/bin/wg set %s peer %s remove 2>&1',
            $this->wgInterface,
            escapeshellarg($router->wg_public_key)
        );
        
        exec($cmd, $output, $returnCode);
        
        // Save config for persistence
        if ($returnCode === 0) {
            exec("sudo /usr/bin/wg-quick save {$this->wgInterface} 2>&1");
        }
        
        return $returnCode === 0;
    }
    
    /**
     * Generate MikroTik RouterOS script for WireGuard setup
     */
    public function generateRouterOSScript(Router $router): string
    {
        $serverPublicKey = $this->serverPublicKey ?: $this->getServerPublicKey();
        $serverEndpoint = $this->serverEndpoint;
        $clientIP = $router->wg_client_ip;
        $interfaceName = "wg-megawifi";
        $peerName = "megawifi-server";
        
        // Get server IP and port
        $endpointParts = explode(':', $serverEndpoint);
        $serverIP = $endpointParts[0];
        $serverPort = $endpointParts[1] ?? '51820';
        
        $script = <<<ROUTEROS
# ═══════════════════════════════════════════════════════════════════
# MegaWiFi WireGuard Setup Script
# Router: {$router->name}
# Generated: {$router->created_at}
# ═══════════════════════════════════════════════════════════════════

# Step 1: Remove old WireGuard config if exists
:do { /interface wireguard remove [find name={$interfaceName}] } on-error={}
:do { /ip address remove [find comment="MegaWiFi WG IP"] } on-error={}
:do { /interface wireguard peers remove [find comment="{$peerName}"] } on-error={}

# Step 2: Create WireGuard Interface (RouterOS generates keys automatically)
/interface wireguard add name={$interfaceName} listen-port=13231 comment="MegaWiFi Management Tunnel"

# Step 3: Add IP Address to WireGuard Interface
/ip address add address={$clientIP}/24 interface={$interfaceName} comment="MegaWiFi WG IP"

# Step 4: Add WireGuard Peer (MegaWiFi Server)
/interface wireguard peers add interface={$interfaceName} public-key="{$serverPublicKey}" endpoint-address={$serverIP} endpoint-port={$serverPort} allowed-address=10.0.0.0/24 persistent-keepalive=25 comment="{$peerName}"

# Step 5: Add Firewall Rules (Allow WireGuard & MegaWiFi Management)
:do { /ip firewall filter remove [find comment="Allow WireGuard MegaWiFi"] } on-error={}
:do { /ip firewall filter remove [find comment="Allow MegaWiFi Management"] } on-error={}
/ip firewall filter add chain=input protocol=udp dst-port=13231 action=accept comment="Allow WireGuard MegaWiFi" place-before=0
/ip firewall filter add chain=input src-address=10.0.0.0/24 action=accept comment="Allow MegaWiFi Management" place-before=0

# Step 6: Enable API for MegaWiFi network
/ip service set api address=0.0.0.0/0 disabled=no
/ip service set api-ssl address=0.0.0.0/0 disabled=no

# ═══════════════════════════════════════════════════════════════════
# Setup Complete! 
# Router VPN IP: {$clientIP}
# Server VPN IP: 10.0.0.1
# ═══════════════════════════════════════════════════════════════════

# IMPORTANT: Copy the Public Key and paste it in the router page!
:delay 1s
:local pubKey [/interface wireguard get {$interfaceName} public-key]
:put ""
:put "╔═══════════════════════════════════════════════════════════════════╗"
:put "║  PUBLIC KEY - انسخ هذا المفتاح وألصقه في صفحة الراوتر           ║"
:put "╠═══════════════════════════════════════════════════════════════════╣"
:put \$pubKey
:put "╚═══════════════════════════════════════════════════════════════════╝"

ROUTEROS;

        return $script;
    }
    
    /**
     * Generate simplified one-liner script
     */
    public function generateOneLinerScript(Router $router): string
    {
        $serverPublicKey = $this->serverPublicKey ?: $this->getServerPublicKey();
        $endpointParts = explode(':', $this->serverEndpoint);
        $serverIP = $endpointParts[0];
        $serverPort = $endpointParts[1] ?? '51820';
        
        $script = "/interface wireguard add name=wg-megawifi listen-port=13231; ";
        $script .= "/ip address add address={$router->wg_client_ip}/24 interface=wg-megawifi; ";
        $script .= "/interface wireguard peers add interface=wg-megawifi public-key=\"{$serverPublicKey}\" endpoint-address={$serverIP} endpoint-port={$serverPort} allowed-address=10.0.0.0/24 persistent-keepalive=25; ";
        $script .= "/ip firewall filter add chain=input protocol=udp dst-port=13231 action=accept place-before=0; ";
        $script .= "/ip firewall filter add chain=input src-address=10.0.0.0/24 action=accept place-before=1; ";
        $script .= "/ip service set api address=10.0.0.0/24 disabled=no; ";
        $script .= ":put \"PUBLIC KEY:\"; :put [/interface wireguard get wg-megawifi public-key]";
        
        return $script;
    }
    
    /**
     * Test WireGuard connection to router
     */
    public function testConnection(Router $router): array
    {
        if (!$router->wg_client_ip) {
            return [
                'success' => false,
                'message' => 'Router has no WireGuard IP configured',
            ];
        }
        
        // Ping test - different for Windows and Linux
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows ping
            exec("ping -n 2 -w 2000 {$router->wg_client_ip}", $output, $returnCode);
        } else {
            // Linux ping
            exec("ping -c 2 -W 2 {$router->wg_client_ip}", $output, $returnCode);
        }
        
        if ($returnCode === 0) {
            // Update router status
            $router->update([
                'wg_last_handshake' => now(),
            ]);
            
            return [
                'success' => true,
                'message' => 'WireGuard tunnel is active',
                'latency' => $this->extractLatency($output),
            ];
        }
        
        return [
            'success' => false,
            'message' => 'Cannot reach router via WireGuard tunnel',
        ];
    }
    
    /**
     * Extract latency from ping output
     */
    protected function extractLatency(array $output): ?float
    {
        foreach ($output as $line) {
            if (preg_match('/time[=<](\d+\.?\d*)\s*ms/', $line, $matches)) {
                return (float) $matches[1];
            }
        }
        return null;
    }
    
    /**
     * Get WireGuard interface statistics
     */
    public function getInterfaceStats(): array
    {
        $output = shell_exec('wg show ' . $this->wgInterface);
        
        if (!$output) {
            return ['error' => 'Cannot get WireGuard stats'];
        }
        
        return [
            'raw' => $output,
            'peers' => $this->parsePeers($output),
        ];
    }
    
    /**
     * Parse peers from wg show output
     */
    protected function parsePeers(string $output): array
    {
        $peers = [];
        $lines = explode("\n", $output);
        $currentPeer = null;
        
        foreach ($lines as $line) {
            if (preg_match('/^peer:\s*(.+)$/', $line, $matches)) {
                if ($currentPeer) {
                    $peers[] = $currentPeer;
                }
                $currentPeer = ['public_key' => trim($matches[1])];
            } elseif ($currentPeer && preg_match('/^\s*(.+?):\s*(.+)$/', $line, $matches)) {
                $key = str_replace(' ', '_', strtolower(trim($matches[1])));
                $currentPeer[$key] = trim($matches[2]);
            }
        }
        
        if ($currentPeer) {
            $peers[] = $currentPeer;
        }
        
        return $peers;
    }
}
