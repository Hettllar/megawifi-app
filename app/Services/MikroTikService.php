<?php

namespace App\Services;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\ActiveSession;
use App\Models\ServicePlan;
use App\Models\SyncLog;
use App\Models\TrafficHistory;
use Exception;
use Illuminate\Support\Facades\Log;

class MikroTikService
{
    private MikroTikAPI $api;
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
        
        // Use WireGuard IP if enabled, otherwise use public IP
        $connectionIP = $router->wg_enabled && $router->wg_client_ip 
            ? $router->wg_client_ip 
            : $router->ip_address;
        
        $this->api = new MikroTikAPI(
            $connectionIP,
            $router->api_port,
            $router->api_username,
            $router->api_password
        );
    }

    /**
     * Execute raw API command
     */
    public function command(array $cmd): array
    {
        return $this->api->comm($cmd);
    }

    /**
     * Connect to the router with enhanced error handling
     */
    public function connect(): bool
    {
        try {
            $result = $this->api->connect();
            
            // Verify connection is working
            if ($result && $this->api->ping()) {
                $this->router->update([
                    'status' => 'online',
                    'last_seen' => now(),
                    'connection_errors' => 0,
                ]);
                return true;
            }
            
            throw new Exception("Connection verification failed");
            
        } catch (Exception $e) {
            $errorCount = ($this->router->connection_errors ?? 0) + 1;
            $this->router->update([
                'status' => 'offline',
                'connection_errors' => $errorCount,
                'last_error' => $e->getMessage(),
                'last_error_at' => now(),
            ]);
            
            Log::error("MikroTik connection failed for router {$this->router->id}: " . $e->getMessage(), [
                'router_id' => $this->router->id,
                'router_name' => $this->router->name,
                'ip' => $this->router->ip_address,
                'error' => $e->getMessage(),
                'attempt_count' => $errorCount,
            ]);
            throw $e;
        }
    }

    /**
     * Disconnect from the router
     */
    public function disconnect(): void
    {
        $this->api->disconnect();
    }

    /**
     * Set connection/read timeout
     */
    public function setTimeout(int $seconds): void
    {
        $this->api->setTimeout($seconds);
    }

    public function setMaxRetries(int $retries): void
    {
        $this->api->setMaxRetries($retries);
    }

    /**
     * Get router system information
     */
    public function getSystemInfo(): array
    {
        $identity = $this->api->comm(['/system/identity/print']);
        $resource = $this->api->comm(['/system/resource/print']);
        $routerboard = $this->api->comm(['/system/routerboard/print']);

        $info = [];
        
        if (isset($identity[0])) {
            $info['identity'] = $identity[0]['name'] ?? '';
        }
        
        if (isset($resource[0])) {
            $info['version'] = $resource[0]['version'] ?? '';
            $info['board_name'] = $resource[0]['board-name'] ?? '';
            $info['uptime'] = $this->parseUptime($resource[0]['uptime'] ?? '0');
            $info['total_memory'] = $resource[0]['total-memory'] ?? 0;
            $info['free_memory'] = $resource[0]['free-memory'] ?? 0;
            $info['total_hdd'] = $resource[0]['total-hdd-space'] ?? 0;
            $info['free_hdd'] = $resource[0]['free-hdd-space'] ?? 0;
            $info['cpu_load'] = $resource[0]['cpu-load'] ?? 0;
        }
        
        if (isset($routerboard[0])) {
            $info['serial_number'] = $routerboard[0]['serial-number'] ?? '';
        }

        return $info;
    }

    /**
     * Get router's public IP from IP Cloud or first public interface
     */
    public function getPublicIP(): ?array
    {
        try {
            // First try IP Cloud (DDNS)
            $cloud = $this->api->comm(['/ip/cloud/print']);
            if (!empty($cloud[0]['dns-name'])) {
                return [
                    'ip' => $cloud[0]['dns-name'],
                    'source' => 'cloud'
                ];
            }
            if (!empty($cloud[0]['public-address'])) {
                return [
                    'ip' => $cloud[0]['public-address'],
                    'source' => 'cloud'
                ];
            }
        } catch (Exception $e) {
            // IP Cloud may not be available
        }

        try {
            // Try to get from WAN interface (first interface with public IP)
            $addresses = $this->api->comm(['/ip/address/print']);
            foreach ($addresses as $addr) {
                $ip = explode('/', $addr['address'] ?? '')[0];
                // Check if it's a public IP (not private)
                if ($this->isPublicIP($ip)) {
                    return [
                        'ip' => $ip,
                        'source' => 'interface:' . ($addr['interface'] ?? 'unknown')
                    ];
                }
            }
        } catch (Exception $e) {
            Log::warning("Failed to get public IP from addresses: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Check if IP is public (not private/reserved)
     */
    private function isPublicIP(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $privateRanges = [
            ['10.0.0.0', '10.255.255.255'],
            ['172.16.0.0', '172.31.255.255'],
            ['192.168.0.0', '192.168.255.255'],
            ['127.0.0.0', '127.255.255.255'],
            ['169.254.0.0', '169.254.255.255'],
        ];

        $ipLong = ip2long($ip);
        foreach ($privateRanges as $range) {
            if ($ipLong >= ip2long($range[0]) && $ipLong <= ip2long($range[1])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get WinBox port from router services
     */
    public function getWinBoxPort(): ?int
    {
        try {
            $services = $this->api->comm(['/ip/service/print', '?name=winbox']);
            if (!empty($services[0]['port'])) {
                return (int) $services[0]['port'];
            }
        } catch (Exception $e) {
            Log::warning("Failed to get WinBox port: " . $e->getMessage());
        }
        return 8291; // Default WinBox port
    }

    /**
     * Update router info from system
     */
    public function updateRouterInfo(): Router
    {
        $info = $this->getSystemInfo();
        
        // Get public IP and WinBox port if not set
        $updateData = [
            'identity' => $info['identity'] ?? $this->router->identity,
            'router_os_version' => $info['version'] ?? $this->router->router_os_version,
            'board_name' => $info['board_name'] ?? $this->router->board_name,
            'serial_number' => $info['serial_number'] ?? $this->router->serial_number,
            'uptime' => $info['uptime'] ?? $this->router->uptime,
            'total_memory' => $info['total_memory'] ?? $this->router->total_memory,
            'free_memory' => $info['free_memory'] ?? $this->router->free_memory,
            'total_hdd' => $info['total_hdd'] ?? $this->router->total_hdd,
            'free_hdd' => $info['free_hdd'] ?? $this->router->free_hdd,
            'cpu_load' => $info['cpu_load'] ?? $this->router->cpu_load,
            'status' => 'online',
            'last_seen' => now(),
        ];

        // Auto-detect public IP if not set
        if (empty($this->router->public_ip)) {
            $publicIP = $this->getPublicIP();
            if ($publicIP) {
                $updateData['public_ip'] = $publicIP['ip'];
                Log::info("Auto-detected public IP for router {$this->router->name}: {$publicIP['ip']} (source: {$publicIP['source']})");
            }
        }

        // Auto-detect WinBox port if not set
        if (empty($this->router->public_port)) {
            $updateData['public_port'] = $this->getWinBoxPort();
        }

        $this->router->update($updateData);

        return $this->router->refresh();
    }

    // ==================== PPP SECRETS ====================

    /**
     * Get all PPP secrets
     */
    public function getPPPSecrets(): array
    {
        return $this->api->comm(['/ppp/secret/print']);
    }

    /**
     * Add PPP secret
     */
    public function addPPPSecret(array $data): array
    {
        $command = ['/ppp/secret/add'];
        
        if (isset($data['name'])) $command[] = '=name=' . $data['name'];
        if (isset($data['password'])) $command[] = '=password=' . $data['password'];
        if (isset($data['service'])) $command[] = '=service=' . $data['service'];
        if (isset($data['profile'])) $command[] = '=profile=' . $data['profile'];
        if (isset($data['local-address'])) $command[] = '=local-address=' . $data['local-address'];
        if (isset($data['remote-address'])) $command[] = '=remote-address=' . $data['remote-address'];
        if (isset($data['caller-id'])) $command[] = '=caller-id=' . $data['caller-id'];
        if (isset($data['comment'])) $command[] = '=comment=' . $data['comment'];
        if (isset($data['disabled'])) $command[] = '=disabled=' . ($data['disabled'] ? 'yes' : 'no');

        return $this->api->comm($command);
    }

    /**
     * Update PPP secret
     */
    public function updatePPPSecret(string $id, array $data): array
    {
        $command = ['/ppp/secret/set', '=.id=' . $id];
        
        if (isset($data['name'])) $command[] = '=name=' . $data['name'];
        if (isset($data['password'])) $command[] = '=password=' . $data['password'];
        if (isset($data['profile'])) $command[] = '=profile=' . $data['profile'];
        if (isset($data['local-address'])) $command[] = '=local-address=' . $data['local-address'];
        if (isset($data['remote-address'])) $command[] = '=remote-address=' . $data['remote-address'];
        if (isset($data['caller-id'])) $command[] = '=caller-id=' . $data['caller-id'];
        if (isset($data['comment'])) $command[] = '=comment=' . $data['comment'];
        if (isset($data['disabled'])) $command[] = '=disabled=' . ($data['disabled'] ? 'yes' : 'no');

        return $this->api->comm($command);
    }

    /**
     * Delete PPP secret
     */
    public function deletePPPSecret(string $id): array
    {
        return $this->api->comm(['/ppp/secret/remove', '=.id=' . $id]);
    }

    /**
     * Get PPP active connections
     */
    public function getPPPActive(): array
    {
        return $this->api->comm(['/ppp/active/print']);
    }

    /**
     * Get PPPoE interface traffic data
     */
    /**
     * Get PPPoE traffic from Queue (Simple Queue)
     */
    public function getPPPoEInterfaceTraffic(string $username): array
    {
        try {
            // Try to get from simple queue first
            $result = $this->api->comm(['/queue/simple/print', '?target=' . $username]);
            
            if (!empty($result) && isset($result[0])) {
                // Queue bytes format: "upload/download" e.g. "1234567/9876543"
                $bytes = $result[0]['bytes'] ?? '0/0';
                $parts = explode('/', $bytes);
                
                return [
                    'rx_bytes' => (int)($parts[1] ?? 0),  // Download (second value)
                    'tx_bytes' => (int)($parts[0] ?? 0),  // Upload (first value)
                ];
            }
            
            // Fallback: try by name containing username
            $result = $this->api->comm(['/queue/simple/print']);
            foreach ($result as $queue) {
                $target = $queue['target'] ?? '';
                $name = $queue['name'] ?? '';
                
                // Check if queue matches username
                if (strpos($target, $username) !== false || strpos($name, $username) !== false) {
                    $bytes = $queue['bytes'] ?? '0/0';
                    $parts = explode('/', $bytes);
                    
                    return [
                        'rx_bytes' => (int)($parts[1] ?? 0),
                        'tx_bytes' => (int)($parts[0] ?? 0),
                    ];
                }
            }
        } catch (Exception $e) {
            Log::debug("Failed to get queue traffic for {$username}: " . $e->getMessage());
        }
        
        return ['rx_bytes' => 0, 'tx_bytes' => 0];
    }

    /**
     * Disconnect PPP user
     */
    public function disconnectPPPUser(string $id): array
    {
        return $this->api->comm(['/ppp/active/remove', '=.id=' . $id]);
    }

    /**
     * Get PPP profiles
     */
    public function getPPPProfiles(): array
    {
        return $this->api->comm(['/ppp/profile/print']);
    }

    /**
     * Add PPP profile
     */
    public function addPPPProfile(array $data): array
    {
        $command = ['/ppp/profile/add'];
        
        if (isset($data['name'])) $command[] = '=name=' . $data['name'];
        if (isset($data['local-address'])) $command[] = '=local-address=' . $data['local-address'];
        if (isset($data['remote-address'])) $command[] = '=remote-address=' . $data['remote-address'];
        if (isset($data['rate-limit'])) $command[] = '=rate-limit=' . $data['rate-limit'];
        if (isset($data['session-timeout'])) $command[] = '=session-timeout=' . $data['session-timeout'];
        if (isset($data['idle-timeout'])) $command[] = '=idle-timeout=' . $data['idle-timeout'];
        if (isset($data['dns-server'])) $command[] = '=dns-server=' . $data['dns-server'];

        return $this->api->comm($command);
    }

    // ==================== HOTSPOT ====================

    /**
     * Get Hotspot users
     */
    public function getHotspotUsers(): array
    {
        return $this->api->comm(['/ip/hotspot/user/print']);
    }

    /**
     * Add Hotspot user
     */
    public function addHotspotUser(array $data): array
    {
        $command = ['/ip/hotspot/user/add'];
        
        if (isset($data['name'])) $command[] = '=name=' . $data['name'];
        if (isset($data['password'])) $command[] = '=password=' . $data['password'];
        if (isset($data['profile'])) $command[] = '=profile=' . $data['profile'];
        if (isset($data['mac-address'])) $command[] = '=mac-address=' . $data['mac-address'];
        if (isset($data['address'])) $command[] = '=address=' . $data['address'];
        if (isset($data['limit-uptime'])) $command[] = '=limit-uptime=' . $data['limit-uptime'];
        if (isset($data['limit-bytes-total'])) $command[] = '=limit-bytes-total=' . $data['limit-bytes-total'];
        if (isset($data['comment'])) $command[] = '=comment=' . $data['comment'];
        if (isset($data['disabled'])) $command[] = '=disabled=' . ($data['disabled'] ? 'yes' : 'no');

        $result = $this->api->comm($command);
        
        // If user was created and has usage data, update the counters
        if (!isset($result['!trap']) && isset($data['name'])) {
            $hasUsageData = (!empty($data['bytes-in']) && $data['bytes-in'] > 0) || 
                           (!empty($data['bytes-out']) && $data['bytes-out'] > 0);
            
            if ($hasUsageData) {
                // Find the user we just created
                $users = $this->api->comm([
                    '/ip/hotspot/user/print',
                    '?name=' . $data['name']
                ]);
                
                if (!empty($users) && isset($users[0]['.id'])) {
                    $userId = $users[0]['.id'];
                    $setCommand = ['/ip/hotspot/user/set', '=.id=' . $userId];
                    
                    if (!empty($data['bytes-in']) && $data['bytes-in'] > 0) {
                        $setCommand[] = '=bytes-in=' . $data['bytes-in'];
                    }
                    if (!empty($data['bytes-out']) && $data['bytes-out'] > 0) {
                        $setCommand[] = '=bytes-out=' . $data['bytes-out'];
                    }
                    
                    $this->api->comm($setCommand);
                }
            }
        }
        
        return $result;
    }

    /**
     * Add multiple Hotspot users in batch (faster)
     * Uses a single script execution for all users
     */
    public function addHotspotUsersBatch(array $users): array
    {
        if (empty($users)) {
            return ['added' => 0, 'failed' => 0, 'errors' => []];
        }

        $added = 0;
        $failed = 0;
        $errors = [];

        // Build batch script
        $scriptLines = [];
        foreach ($users as $index => $user) {
            $cmd = '/ip hotspot user add';
            $cmd .= ' name="' . addslashes($user['name']) . '"';
            $cmd .= ' password="' . addslashes($user['password']) . '"';
            
            if (!empty($user['profile'])) {
                $cmd .= ' profile="' . addslashes($user['profile']) . '"';
            }
            if (!empty($user['limit-bytes-total'])) {
                $cmd .= ' limit-bytes-total=' . $user['limit-bytes-total'];
            }
            if (!empty($user['comment'])) {
                $cmd .= ' comment="' . addslashes($user['comment']) . '"';
            }
            
            $scriptLines[] = $cmd;
        }

        // Execute all commands at once using script
        $scriptContent = implode("\n", $scriptLines);
        $scriptName = 'megawifi_batch_' . time();

        try {
            // Create temporary script
            $this->api->comm([
                '/system/script/add',
                '=name=' . $scriptName,
                '=source=' . $scriptContent
            ]);

            // Run the script
            $this->api->comm([
                '/system/script/run',
                '=number=' . $scriptName
            ]);

            // Remove the script
            $this->api->comm([
                '/system/script/remove',
                '=numbers=' . $scriptName
            ]);

            $added = count($users);

        } catch (Exception $e) {
            // Fallback: try individual commands if script fails
            Log::warning("Batch script failed, falling back to individual commands: " . $e->getMessage());
            
            foreach ($users as $user) {
                try {
                    $this->addHotspotUser($user);
                    $added++;
                } catch (Exception $ex) {
                    $failed++;
                    $errors[] = $user['name'] . ': ' . $ex->getMessage();
                }
            }

            // Try to clean up script if it exists
            try {
                $this->api->comm(['/system/script/remove', '=numbers=' . $scriptName]);
            } catch (Exception $ex) {
                // Ignore cleanup errors
            }
        }

        return [
            'added' => $added,
            'failed' => $failed,
            'errors' => $errors
        ];
    }

    /**
     * Update Hotspot user
     */
    public function updateHotspotUser(string $id, array $data): array
    {
        $command = ['/ip/hotspot/user/set', '=.id=' . $id];
        
        if (isset($data['name'])) $command[] = '=name=' . $data['name'];
        if (isset($data['password'])) $command[] = '=password=' . $data['password'];
        if (isset($data['profile'])) $command[] = '=profile=' . $data['profile'];
        if (isset($data['mac-address'])) $command[] = '=mac-address=' . $data['mac-address'];
        if (isset($data['address'])) $command[] = '=address=' . $data['address'];
        if (isset($data['limit-uptime'])) $command[] = '=limit-uptime=' . $data['limit-uptime'];
        if (isset($data['limit-bytes-total'])) $command[] = '=limit-bytes-total=' . $data['limit-bytes-total'];
        if (isset($data['comment'])) $command[] = '=comment=' . $data['comment'];
        if (isset($data['disabled'])) $command[] = '=disabled=' . ($data['disabled'] ? 'yes' : 'no');

        return $this->api->comm($command);
    }

    /**
     * Delete Hotspot user
     */
    public function deleteHotspotUser(string $id): array
    {
        return $this->api->comm(['/ip/hotspot/user/remove', '=.id=' . $id]);
    }

    /**
     * Get Hotspot active connections
     */
    public function getHotspotActive(): array
    {
        return $this->api->comm(['/ip/hotspot/active/print']);
    }

    /**
     * Get Hotspot hosts (includes traffic data)
     */
    public function getHotspotHosts(): array
    {
        return $this->api->comm(['/ip/hotspot/host/print']);
    }

    /**
     * Disconnect Hotspot user
     */
    public function disconnectHotspotUser(string $id): array
    {
        return $this->api->comm(['/ip/hotspot/active/remove', '=.id=' . $id]);
    }

    /**
     * Get Hotspot profiles
     */
    public function getHotspotProfiles(): array
    {
        return $this->api->comm(['/ip/hotspot/user/profile/print']);
    }

    /**
     * Toggle Hotspot user (enable/disable)
     */
    public function toggleHotspotUser(string $id, bool $disable): array
    {
        $command = ['/ip/hotspot/user/set', '=.id=' . $id];
        $command[] = '=disabled=' . ($disable ? 'yes' : 'no');
        return $this->api->comm($command);
    }

    /**
     * Remove Hotspot user
     */
    public function removeHotspotUser(string $id): array
    {
        return $this->api->comm(['/ip/hotspot/user/remove', '=.id=' . $id]);
    }

    /**
     * Remove multiple Hotspot users in batch (single API call)
     * MikroTik supports comma-separated IDs for batch operations
     */
    public function removeHotspotUsersBatch(array $ids): array
    {
        if (empty($ids)) {
            return ['success' => true, 'removed' => 0];
        }
        
        // MikroTik API: remove multiple items by joining IDs with comma
        $idList = implode(',', $ids);
        return $this->api->comm(['/ip/hotspot/user/remove', '=.id=' . $idList]);
    }

    /**
     * Sync Hotspot users from router to database
     */
    public function syncHotspotUsers(Router $router): array
    {
        $users = $this->getHotspotUsers();
        $synced = 0;
        $updated = 0;

        foreach ($users as $user) {
            if (!isset($user['name'])) continue;
            
            // Skip default-trial and system users
            if (in_array($user['name'], ['default-trial', 'default'])) continue;

            $existingSubscriber = Subscriber::where('router_id', $router->id)
                ->where('username', $user['name'])
                ->where('type', 'hotspot')
                ->first();

            $bytesIn = $this->parseBytes($user['bytes-in'] ?? '0');
            $bytesOut = $this->parseBytes($user['bytes-out'] ?? '0');
            $totalBytes = $bytesIn + $bytesOut;
            $limitBytesTotal = $this->parseBytes($user['limit-bytes-total'] ?? '0');

            $data = [
                'router_id' => $router->id,
                'username' => $user['name'],
                'password' => $user['password'] ?? '',
                'type' => 'hotspot',
                'status' => isset($user['disabled']) && $user['disabled'] === 'true' ? 'disabled' : 'active',
                'profile' => $user['profile'] ?? null,
                'mikrotik_id' => $user['.id'] ?? null,
                'bytes_in' => $bytesIn,
                'bytes_out' => $bytesOut,
                'total_bytes' => $totalBytes,
                'limit_bytes_total' => $limitBytesTotal > 0 ? $limitBytesTotal : null,
            ];

            if ($existingSubscriber) {
                $existingSubscriber->update($data);
                $updated++;
            } else {
                Subscriber::create($data);
                $synced++;
            }
        }

        return [
            'synced' => $synced,
            'updated' => $updated,
            'total' => count($users),
        ];
    }

    /**
     * Add Hotspot profile
     */
    public function addHotspotProfile(array $data): array
    {
        $command = ['/ip/hotspot/user/profile/add'];
        
        if (isset($data['name'])) $command[] = '=name=' . $data['name'];
        if (isset($data['rate-limit'])) $command[] = '=rate-limit=' . $data['rate-limit'];
        if (isset($data['session-timeout'])) $command[] = '=session-timeout=' . $data['session-timeout'];
        if (isset($data['idle-timeout'])) $command[] = '=idle-timeout=' . $data['idle-timeout'];
        if (isset($data['keepalive-timeout'])) $command[] = '=keepalive-timeout=' . $data['keepalive-timeout'];
        if (isset($data['shared-users'])) $command[] = '=shared-users=' . $data['shared-users'];
        if (isset($data['address-pool'])) $command[] = '=address-pool=' . $data['address-pool'];

        return $this->api->comm($command);
    }

    // ==================== USER MANAGER 7 ====================

    /**
     * Get UserManager users
     */
    public function getUserManagerUsers(): array
    {
        return $this->api->comm(['/user-manager/user/print']);
    }

    /**
     * Add UserManager user
     */
    public function addUserManagerUser(array $data): array
    {
        $command = ['/user-manager/user/add'];
        
        if (isset($data['name'])) $command[] = '=name=' . $data['name'];
        if (isset($data['password'])) $command[] = '=password=' . $data['password'];
        if (isset($data['group'])) $command[] = '=group=' . $data['group'];
        if (isset($data['shared-users'])) $command[] = '=shared-users=' . $data['shared-users'];
        if (isset($data['caller-id'])) $command[] = '=caller-id=' . $data['caller-id'];
        if (isset($data['disabled'])) $command[] = '=disabled=' . ($data['disabled'] ? 'yes' : 'no');
        if (isset($data['comment'])) $command[] = '=comment=' . $data['comment'];

        return $this->api->comm($command);
    }

    /**
     * Update UserManager user
     */
    public function updateUserManagerUser(string $id, array $data): array
    {
        $command = ['/user-manager/user/set', '=.id=' . $id];
        
        foreach ($data as $key => $value) {
            $command[] = '=' . $key . '=' . $value;
        }

        return $this->api->comm($command);
    }

    /**
     * Delete UserManager user
     */
    public function deleteUserManagerUser(string $id): array
    {
        return $this->api->comm(['/user-manager/user/remove', '=.id=' . $id]);
    }

    /**
     * Get UserManager profiles/groups
     */
    public function getUserManagerProfiles(): array
    {
        return $this->api->comm(['/user-manager/user-group/print']);
    }

    /**
     * Get UserManager sessions
     */
    public function getUserManagerSessions(): array
    {
        return $this->api->comm(['/user-manager/session/print']);
    }

    /**
     * Generate UserManager vouchers
     */
    public function generateVouchers(string $profile, int $count, string $prefix = ''): array
    {
        $command = [
            '/user-manager/user/generate',
            '=number=' . $count,
            '=group=' . $profile,
        ];
        
        if ($prefix) {
            $command[] = '=prefix=' . $prefix;
        }

        return $this->api->comm($command);
    }

    // ==================== SYNC METHODS ====================

    /**
     * Sync PPP secrets with database
     */
    public function syncPPPSecrets(): array
    {
        $startTime = microtime(true);
        $synced = 0;
        $failed = 0;
        $errors = [];

        try {
            $secrets = $this->getPPPSecrets();
            
            foreach ($secrets as $secret) {
                if (!isset($secret['name'])) continue;
                
                try {
                    Subscriber::updateOrCreate(
                        [
                            'router_id' => $this->router->id,
                            'username' => $secret['name'],
                        ],
                        [
                            'mikrotik_id' => $secret['.id'] ?? null,
                            'password' => $secret['password'] ?? '',
                            'profile' => $secret['profile'] ?? 'default',
                            'type' => 'ppp',
                            'status' => isset($secret['disabled']) && $secret['disabled'] === 'true' ? 'disabled' : 'active',
                            'caller_id' => $secret['caller-id'] ?? null,
                            'local_address' => $secret['local-address'] ?? null,
                            'remote_address' => $secret['remote-address'] ?? null,
                            'comment' => $secret['comment'] ?? null,
                            'is_synced' => true,
                            'last_synced_at' => now(),
                        ]
                    );
                    $synced++;
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = "User {$secret['name']}: " . $e->getMessage();
                }
            }

            $duration = (int)((microtime(true) - $startTime) * 1000);

            SyncLog::create([
                'router_id' => $this->router->id,
                'type' => 'users',
                'status' => $failed > 0 ? 'partial' : 'success',
                'records_synced' => $synced,
                'records_failed' => $failed,
                'error_message' => $failed > 0 ? implode("\n", $errors) : null,
                'duration' => $duration,
            ]);

            return [
                'success' => true,
                'synced' => $synced,
                'failed' => $failed,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            SyncLog::create([
                'router_id' => $this->router->id,
                'type' => 'users',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync active sessions (PPP only) with cumulative traffic tracking
     */
    public function syncActiveSessions(): array
    {
        $startTime = microtime(true);
        
        try {
            $synced = 0;

            // Sync PPP active
            try {
                $pppActive = $this->getPPPActive();
                $activeUsernames = [];
                
                foreach ($pppActive as $session) {
                    if (!isset($session['name'])) continue;
                    
                    $username = $session['name'];
                    $activeUsernames[] = $username;
                    
                    // Get traffic data from interface
                    $traffic = $this->getPPPoEInterfaceTraffic($username);
                    $currentBytesIn = $traffic['rx_bytes'];
                    $currentBytesOut = $traffic['tx_bytes'];
                    $currentUptime = $this->parseUptime($session['uptime'] ?? '0');
                    
                    $subscriber = Subscriber::where('router_id', $this->router->id)
                        ->where('username', $username)
                        ->first();

                    // Check existing active session
                    $existingSession = ActiveSession::where('router_id', $this->router->id)
                        ->where('username', $username)
                        ->where('type', 'ppp')
                        ->first();

                    if ($existingSession) {
                        // Check if this is a new session (reconnection)
                        // If current uptime < previous uptime, it means user reconnected
                        if ($currentUptime < $existingSession->uptime) {
                            // User reconnected - save old session traffic to history
                            if ($subscriber) {
                                // Add old session bytes to cumulative total
                                $subscriber->increment('bytes_in', $existingSession->bytes_in);
                                $subscriber->increment('bytes_out', $existingSession->bytes_out);
                                $subscriber->update([
                                    'total_bytes' => $subscriber->bytes_in + $subscriber->bytes_out
                                ]);
                                
                                // Save to traffic history
                                TrafficHistory::create([
                                    'subscriber_id' => $subscriber->id,
                                    'router_id' => $this->router->id,
                                    'bytes_in' => $existingSession->bytes_in,
                                    'bytes_out' => $existingSession->bytes_out,
                                    'session_start' => $existingSession->started_at,
                                    'session_end' => now(),
                                    'uptime' => $existingSession->uptime,
                                    'recorded_at' => now(),
                                ]);
                            }
                            
                            // Update session with new connection data
                            $existingSession->update([
                                'session_id' => $session['.id'] ?? null,
                                'mac_address' => $session['caller-id'] ?? null,
                                'ip_address' => $session['address'] ?? null,
                                'started_at' => now()->subSeconds($currentUptime),
                                'uptime' => $currentUptime,
                                'bytes_in' => $currentBytesIn,
                                'bytes_out' => $currentBytesOut,
                            ]);
                        } else {
                            // Same session - just update current values
                            $existingSession->update([
                                'uptime' => $currentUptime,
                                'bytes_in' => $currentBytesIn,
                                'bytes_out' => $currentBytesOut,
                                'ip_address' => $session['address'] ?? null,
                            ]);
                        }
                    } else {
                        // New session - create it
                        ActiveSession::create([
                            'router_id' => $this->router->id,
                            'subscriber_id' => $subscriber?->id,
                            'username' => $username,
                            'session_id' => $session['.id'] ?? null,
                            'type' => 'ppp',
                            'mac_address' => $session['caller-id'] ?? null,
                            'ip_address' => $session['address'] ?? null,
                            'started_at' => now()->subSeconds($currentUptime),
                            'uptime' => $currentUptime,
                            'bytes_in' => $currentBytesIn,
                            'bytes_out' => $currentBytesOut,
                        ]);
                    }
                    
                    $synced++;
                }
                
                // Handle disconnected users - save their traffic before removing
                $disconnectedSessions = ActiveSession::where('router_id', $this->router->id)
                    ->where('type', 'ppp')
                    ->whereNotIn('username', $activeUsernames)
                    ->get();
                
                foreach ($disconnectedSessions as $oldSession) {
                    if ($oldSession->subscriber_id) {
                        $subscriber = Subscriber::find($oldSession->subscriber_id);
                        if ($subscriber) {
                            // Add disconnected session bytes to cumulative total
                            $subscriber->increment('bytes_in', $oldSession->bytes_in);
                            $subscriber->increment('bytes_out', $oldSession->bytes_out);
                            $subscriber->update([
                                'total_bytes' => $subscriber->bytes_in + $subscriber->bytes_out
                            ]);
                            
                            // Save to traffic history
                            TrafficHistory::create([
                                'subscriber_id' => $subscriber->id,
                                'router_id' => $this->router->id,
                                'bytes_in' => $oldSession->bytes_in,
                                'bytes_out' => $oldSession->bytes_out,
                                'session_start' => $oldSession->started_at,
                                'session_end' => now(),
                                'uptime' => $oldSession->uptime,
                                'recorded_at' => now(),
                            ]);
                            
                            // Save to session history for user viewing
                            \App\Models\SessionHistory::saveFromActiveSession($oldSession);
                        }
                    }
                    $oldSession->delete();
                }

                // Update is_online status for subscribers on this router
                if (!empty($activeUsernames)) {
                    Subscriber::where("router_id", $this->router->id)
                        ->where("type", "ppp")
                        ->whereIn("username", $activeUsernames)
                        ->update(["is_online" => true]);
                }

                // Mark disconnected subscribers as offline
                Subscriber::where("router_id", $this->router->id)
                    ->where("type", "ppp")
                    ->whereNotIn("username", $activeUsernames)
                    ->where("is_online", true)
                    ->update(["is_online" => false]);

            } catch (Exception $e) {
                Log::warning("Failed to sync PPP sessions for router {$this->router->id}: " . $e->getMessage());
            }

            $duration = (int)((microtime(true) - $startTime) * 1000);

            SyncLog::create([
                'router_id' => $this->router->id,
                'type' => 'sessions',
                'status' => 'success',
                'records_synced' => $synced,
                'duration' => $duration,
            ]);

            return ['success' => true, 'synced' => $synced];
            
        } catch (Exception $e) {
            $duration = (int)((microtime(true) - $startTime) * 1000);
            
            SyncLog::create([
                'router_id' => $this->router->id,
                'type' => 'sessions',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration' => $duration,
            ]);
            
            throw $e;
        }
    }

    /**
     * Sync profiles
     */
    public function syncProfiles(): array
    {
        $synced = 0;

        // PPP Profiles
        $pppProfiles = $this->getPPPProfiles();
        foreach ($pppProfiles as $profile) {
            if (!isset($profile['name'])) continue;
            
            ServicePlan::updateOrCreate(
                [
                    'router_id' => $this->router->id,
                    'mikrotik_profile_name' => $profile['name'],
                    'type' => 'ppp',
                ],
                [
                    'name' => $profile['name'],
                    'rate_limit' => $profile['rate-limit'] ?? null,
                    'local_address' => $profile['local-address'] ?? null,
                    'remote_address' => $profile['remote-address'] ?? null,
                    'dns_server' => $profile['dns-server'] ?? null,
                ]
            );
            $synced++;
        }

        // Hotspot Profiles
        $hotspotProfiles = $this->getHotspotProfiles();
        foreach ($hotspotProfiles as $profile) {
            if (!isset($profile['name'])) continue;
            
            ServicePlan::updateOrCreate(
                [
                    'router_id' => $this->router->id,
                    'mikrotik_profile_name' => $profile['name'],
                    'type' => 'hotspot',
                ],
                [
                    'name' => $profile['name'],
                    'rate_limit' => $profile['rate-limit'] ?? null,
                    'session_timeout' => $this->parseUptime($profile['session-timeout'] ?? '0'),
                    'idle_timeout' => $this->parseUptime($profile['idle-timeout'] ?? '0'),
                    'shared_users' => isset($profile['shared-users']) ? (int)$profile['shared-users'] > 1 : false,
                    'shared_users_count' => isset($profile['shared-users']) ? (int)$profile['shared-users'] : 1,
                    'address_pool' => $profile['address-pool'] ?? null,
                ]
            );
            $synced++;
        }

        return ['success' => true, 'synced' => $synced];
    }

    // ==================== HELPERS ====================

    /**
     * Parse uptime string to seconds
     */
    private function parseUptime(string $uptime): int
    {
        $seconds = 0;
        
        if (preg_match('/(\d+)w/', $uptime, $m)) $seconds += $m[1] * 604800;
        if (preg_match('/(\d+)d/', $uptime, $m)) $seconds += $m[1] * 86400;
        if (preg_match('/(\d+)h/', $uptime, $m)) $seconds += $m[1] * 3600;
        if (preg_match('/(\d+)m/', $uptime, $m)) $seconds += $m[1] * 60;
        if (preg_match('/(\d+)s/', $uptime, $m)) $seconds += $m[1];
        
        return $seconds;
    }

    /**
     * Parse bytes string
     */
    private function parseBytes(string $bytes): int
    {
        return (int)$bytes;
    }

    /**
     * Fetch last N log entries from MikroTik
     */
    public function getRouterLog(int $limit = 100): array
    {
        $entries = $this->command(['/log/print']);
        $result = [];
        foreach ($entries as $entry) {
            if (isset($entry['!trap']) || isset($entry['!done'])) continue;
            $result[] = [
                'time'    => $entry['time'] ?? '',
                'topics'  => $entry['topics'] ?? '',
                'message' => $entry['message'] ?? '',
            ];
        }
        // Return last $limit entries
        return array_slice($result, -$limit);
    }
}
