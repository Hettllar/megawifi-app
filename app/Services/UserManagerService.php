<?php

namespace App\Services;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\ServicePlan;
use App\Models\ActiveSession;
use App\Models\SyncLog;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class UserManagerService
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
     * Convert UTF-8 text to Windows-1256 for proper Arabic display in Winbox
     */
    private function encodeForMikrotik(string $text): string
    {
        if (empty($text) || mb_check_encoding($text, 'ASCII')) {
            return $text;
        }
        
        try {
            $converted = @iconv('UTF-8', 'WINDOWS-1256//TRANSLIT', $text);
            if ($converted !== false) {
                return $converted;
            }
        } catch (\Exception $e) {
            // Fall through to return original
        }
        
        return $text;
    }

    /**
     * Convert Windows-1256 text from MikroTik to UTF-8
     */
    private function decodeFromMikrotik(string $text): string
    {
        if (empty($text) || mb_check_encoding($text, 'ASCII')) {
            return $text;
        }
        
        // If already valid UTF-8 with multibyte chars, check if it looks like real UTF-8 Arabic
        if (mb_check_encoding($text, 'UTF-8') && preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return $text;
        }
        
        // Try converting from Windows-1256 to UTF-8
        try {
            $converted = @iconv('WINDOWS-1256', 'UTF-8//IGNORE', $text);
            if ($converted !== false && !empty($converted)) {
                return $converted;
            }
        } catch (\Exception $e) {
            // Fall through
        }
        
        return $text;
    }

    /**
     * Quick ping check to see if router is reachable
     */
    public static function isRouterReachable(Router $router, int $timeout = 2): bool
    {
        $socket = @fsockopen($router->ip_address, $router->api_port ?? 8728, $errno, $errstr, $timeout);
        if ($socket) {
            fclose($socket);
            return true;
        }
        return false;
    }

    /**
     * Connect to router
     */
    public function connect(): bool
    {
        return $this->api->connect();
    }

    /**
     * Disconnect from router
     */
    public function disconnect(): void
    {
        $this->api->disconnect();
    }

    /**
     * Get all UserManager users with full details including usage statistics
     */
    public function getUsers(): array
    {
        try {
            // Request all relevant fields including total usage stats
            $result = $this->api->comm([
                '/user-manager/user/print',
                '=.proplist=.id,name,password,group,shared-users,attributes,disabled,comment,caller-id,total-download,total-upload,total-uptime,actual-download,actual-upload,actual-uptime,download-limit,upload-limit,uptime-limit,validity'
            ]);
            
            // Filter out empty responses
            $users = [];
            foreach ($result as $item) {
                if (isset($item['name'])) {
                    $users[] = $item;
                }
            }
            
            return $users;
        } catch (Exception $e) {
            Log::error("Failed to get UserManager users: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get UserManager user by ID
     */
    
    /**
     * Find a UserManager user by username
     */
    public function findUserByName(string $username): ?array
    {
        try {
            $result = $this->api->comm(['/user-manager/user/print', '?name=' . $username]);
            foreach ($result as $item) {
                if (is_array($item) && isset($item['.id']) && isset($item['name']) && $item['name'] === $username) {
                    return $item;
                }
            }
            return null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function getUserById(string $id): ?array
    {
        try {
            $result = $this->api->comm(['/user-manager/user/print', '=.id=' . $id, '=detail=']);
            return $result[0] ?? null;
        } catch (Exception $e) {
            Log::error("Failed to get UserManager user: " . $e->getMessage());
            return null;
        }
    }

    
    /**
     * Get user usage counters from UM7 monitor (Total Download/Upload)
     * This returns the actual cumulative totals shown in Winbox Users tab
     * Uses /user-manager/user/monitor which gives accurate totals
     */
    public function getUserMonitorCounters(): array
    {
        try {
            // First get all users with their IDs
            $users = $this->getUsers();
            if (empty($users)) return [];

            // Build ordered ID→name mapping
            $ids = [];
            $names = [];
            foreach ($users as $user) {
                $name = $user["name"] ?? null;
                $id = $user[".id"] ?? null;
                if (!$name || !$id) continue;
                $ids[] = $id;
                $names[] = $name;
            }

            if (empty($ids)) return [];

            // Single bulk monitor call using =numbers= (56x faster than individual calls)
            $result = $this->api->comm([
                "/user-manager/user/monitor",
                "=numbers=" . implode(',', $ids),
                "=once="
            ]);

            // Filter only data results (skip !done marker)
            $counters = [];
            $idx = 0;
            foreach ($result as $row) {
                if (!isset($row["total-download"])) continue;
                if ($idx >= count($names)) break;

                $counters[$names[$idx]] = [
                    "total-download" => (int)($row["total-download"] ?? 0),
                    "total-upload" => (int)($row["total-upload"] ?? 0),
                    "total-uptime" => $row["total-uptime"] ?? "",
                    "active-sessions" => (int)($row["active-sessions"] ?? 0),
                    "actual-profile" => $row["actual-profile"] ?? "",
                ];
                $idx++;
            }

            return $counters;
        } catch (\Exception $e) {
            Log::warning("getUserMonitorCounters failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get monitor data for a single user by their .id
     */
    public function getSingleUserMonitor(string $id): ?array
    {
        $result = $this->api->comm([
            "/user-manager/user/monitor",
            "=numbers=" . $id,
            "=once="
        ]);

        foreach ($result as $row) {
            if (isset($row["total-download"])) {
                return [
                    "total-download" => (int)($row["total-download"] ?? 0),
                    "total-upload" => (int)($row["total-upload"] ?? 0),
                    "total-uptime" => $row["total-uptime"] ?? "",
                    "active-sessions" => (int)($row["active-sessions"] ?? 0),
                    "actual-profile" => $row["actual-profile"] ?? "",
                ];
            }
        }
        return null;
    }


    /**
     * Get user by username
     */
    public function getUserByUsername(string $username): ?array
    {
        try {
            $result = $this->api->comm(['/user-manager/user/print', '?name=' . $username]);
            return $result[0] ?? null;
        } catch (Exception $e) {
            Log::error('Failed to get UserManager user by username: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Add UserManager user with profile assignment
     */
    public function addUser(array $data): array
    {
        $result = [
            'success' => false,
            'user_id' => null,
            'message' => '',
        ];

        try {
            // Step 1: Create user
            $command = ['/user-manager/user/add'];
            
            // UserManager 7 uses 'name' not 'username'
            $username = $data['name'] ?? $data['username'] ?? null;
            if ($username) $command[] = '=name=' . $username;
            if (isset($data['password'])) $command[] = '=password=' . $data['password'];
            if (isset($data['comment'])) $command[] = '=comment=' . $this->encodeForMikrotik($data['comment']);
            if (isset($data['shared-users'])) $command[] = '=shared-users=' . $data['shared-users'];
            if (isset($data['disabled'])) $command[] = '=disabled=' . ($data['disabled'] ? 'yes' : 'no');
            
            // Add attributes for data limit if specified
            if (isset($data['download-limit'])) {
                $command[] = '=attributes=' . 'Mikrotik-Total-Limit:' . $data['download-limit'];
            }

            Log::info("Adding user with command: " . json_encode($command, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
            $addResult = $this->api->comm($command);
            Log::info("Add user result: " . json_encode($addResult, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));

            // Check for errors
            if (isset($addResult['!trap'])) {
                $errorMsg = $addResult['!trap'][0]['message'] ?? 'Unknown error';
                Log::error("Failed to add user: " . $errorMsg);
                $result['message'] = $errorMsg;
                return $result;
            }

            // Get user ID
            $userId = $addResult['ret'] ?? $addResult['.id'] ?? null;
            if (!$userId) {
                // Try to find the user we just created
                $users = $this->api->comm(['/user-manager/user/print', '?name=' . $username]);
                if (!empty($users) && isset($users[0]['.id'])) {
                    $userId = $users[0]['.id'];
                }
            }

            if (!$userId) {
                $result['message'] = 'تم إنشاء المستخدم لكن لم يتم العثور على المعرف';
                $result['success'] = true;
                return $result;
            }

            $result['user_id'] = $userId;
            $result['.id'] = $userId;
            $result['ret'] = $userId;

            // Step 2: Assign profile to user if specified
            $profile = $data['profile'] ?? $data['group'] ?? null;
            if ($profile) {
                $assignResult = $this->api->comm([
                    '/user-manager/user-profile/add',
                    '=user=' . $username,
                    '=profile=' . $profile,
                ]);
                
                Log::info("Assign profile result: " . json_encode($assignResult, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
                
                if (isset($assignResult['!trap'])) {
                    Log::warning("Failed to assign profile: " . json_encode($assignResult, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
                }
            }

            $result['success'] = true;
            $result['message'] = 'تم إضافة المستخدم بنجاح';
            
        } catch (Exception $e) {
            Log::error("Exception adding user: " . $e->getMessage());
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Update UserManager user
     */
    public function updateUser(string $id, array $data): array
    {
        $command = ['/user-manager/user/set', '=.id=' . $id];
        
        if (isset($data['password'])) $command[] = '=password=' . $data['password'];
        if (isset($data['group'])) $command[] = '=group=' . $data['group'];
        if (isset($data['shared-users'])) $command[] = '=shared-users=' . $data['shared-users'];
        if (isset($data['download-limit'])) $command[] = '=download-limit=' . $data['download-limit'];
        if (isset($data['upload-limit'])) $command[] = '=upload-limit=' . $data['upload-limit'];
        if (isset($data['uptime-limit'])) $command[] = '=uptime-limit=' . $data['uptime-limit'];
        if (isset($data['time-limit'])) $command[] = '=time-limit=' . $data['time-limit'];
        if (isset($data['validity'])) $command[] = '=validity=' . $data['validity'];
        if (isset($data['price'])) $command[] = '=price=' . $data['price'];
        if (isset($data['comment'])) $command[] = '=comment=' . $this->encodeForMikrotik($data['comment']);
        if (isset($data['disabled'])) $command[] = '=disabled=' . ($data['disabled'] ? 'yes' : 'no');

        return $this->api->comm($command);
    }

    /**
     * Toggle user status (enable/disable)
     * @param string $id User ID (.id)
     * @param bool $disable True to disable, false to enable
     */
    public function toggleUserStatus(string $id, bool $disable = true): array
    {
        return $this->api->comm([
            '/user-manager/user/set',
            '=.id=' . $id,
            '=disabled=' . ($disable ? 'yes' : 'no'),
        ]);
    }

    /**
     * Get all sessions for a specific user (contains actual usage data)
     * @param string $username The username to get sessions for
     * @return array Array of session records with download/upload/uptime
     */
    public function getUserSessions(string $username): array
    {
        try {
            $result = $this->api->comm([
                '/user-manager/session/print',
                '?user=' . $username,
            ]);
            
            $sessions = [];
            foreach ($result as $item) {
                if (isset($item['user']) && $item['user'] === $username) {
                    $sessions[] = $item;
                }
            }
            
            return $sessions;
        } catch (Exception $e) {
            Log::error("Failed to get sessions for user {$username}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all sessions for all users (for bulk usage sync)
     * @return array Array of all session records
     */
    public function getAllSessions(): array
    {
        try {
            $result = $this->api->comm(['/user-manager/session/print']);
            
            $sessions = [];
            foreach ($result as $item) {
                if (isset($item['user'])) {
                    $sessions[] = $item;
                }
            }
            
            return $sessions;
        } catch (Exception $e) {
            Log::error("Failed to get all sessions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete UserManager user
     */
    public function deleteUser(string $id): array
    {
        return $this->api->comm(['/user-manager/user/remove', '=.id=' . $id]);
    }

    /**
     * Get UserManager limitations (groups in UM7)
     */
    public function getGroups(): array
    {
        try {
            // UserManager 7 uses 'limitation' instead of 'user-group'
            return $this->api->comm(['/user-manager/limitation/print']);
        } catch (Exception $e) {
            Log::error("Failed to get UserManager limitations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get UserManager Profiles
     */
    public function getProfiles(): array
    {
        try {
            return $this->api->comm(['/user-manager/profile/print']);
        } catch (Exception $e) {
            Log::error("Failed to get UserManager profiles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get UserManager User-Profiles (assignments)
     */
    public function getUserProfiles(): array
    {
        try {
            return $this->api->comm(['/user-manager/user-profile/print']);
        } catch (Exception $e) {
            Log::error("Failed to get user profiles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete all user-profile assignments for a specific user
     */
    public function deleteUserProfiles(string $username): int
    {
        try {
            // Get all user-profile assignments for this user
            $userProfiles = $this->api->comm([
                '/user-manager/user-profile/print',
                '?user=' . $username
            ]);
            
            $deleted = 0;
            foreach ($userProfiles as $up) {
                if (isset($up['.id'])) {
                    $this->api->comm([
                        '/user-manager/user-profile/remove',
                        '=.id=' . $up['.id']
                    ]);
                    $deleted++;
                }
            }
            
            Log::info("Deleted {$deleted} user-profile assignments for user {$username}");
            return $deleted;
        } catch (Exception $e) {
            Log::warning("Could not delete user-profiles for {$username}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Assign profile to a user
     */
    public function assignProfileToUser(string $userId, string $profileName): array
    {
        return $this->api->comm([
            '/user-manager/user-profile/add',
            '=user=' . $userId,
            '=profile=' . $profileName,
        ]);
    }

    /**
     * Change profile for a single user
     */
    public function changeUserProfile(string $mikrotikId, string $profileName): bool
    {
        try {
            // Get username from mikrotik_id
            $users = $this->api->comm([
                '/user-manager/user/print',
                '?.id=' . $mikrotikId
            ]);
            
            if (empty($users) || !isset($users[0]['name'])) {
                throw new Exception('User not found on router');
            }
            
            $username = $users[0]['name'];
            
            Log::info("Changing profile for user {$username} (ID: {$mikrotikId}) to {$profileName}");
            
            // In UM7, user-profile uses the username, not the ID
            // First, try to find existing profile assignments
            $existingAssignments = $this->api->comm([
                '/user-manager/user-profile/print',
                '?user=' . $username
            ]);

            Log::info("Found " . count($existingAssignments) . " existing assignments for user {$username}");

            // If no assignments found, try with ID
            if (empty($existingAssignments)) {
                $existingAssignments = $this->api->comm([
                    '/user-manager/user-profile/print',
                    '?user=' . $mikrotikId
                ]);
                Log::info("Found " . count($existingAssignments) . " existing assignments for user ID {$mikrotikId}");
            }

            // Update existing assignment instead of delete and add
            if (!empty($existingAssignments) && isset($existingAssignments[0]['.id'])) {
                // Update the first assignment with new profile
                $assignmentId = $existingAssignments[0]['.id'];
                $this->api->comm([
                    '/user-manager/user-profile/set',
                    '=.id=' . $assignmentId,
                    '=profile=' . $profileName,
                ]);
                Log::info("Updated assignment {$assignmentId} to profile {$profileName}");
            } else {
                // No existing assignment, add new one
                $result = $this->api->comm([
                    '/user-manager/user-profile/add',
                    '=user=' . $username,
                    '=profile=' . $profileName,
                ]);
                Log::info("Added new profile assignment for user {$username} to {$profileName}, result: " . json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
            }

            return true;
        } catch (Exception $e) {
            Log::error("Failed to change user profile: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Change profile for a user by username (not mikrotik_id)
     */
    public function changeUserProfileByName(string $username, string $profileName): bool
    {
        try {
            Log::info("Changing profile for user {$username} to {$profileName}");
            
            // Delete all existing user-profile assignments
            $this->deleteUserProfiles($username);
            
            // Add new profile assignment
            $result = $this->api->comm([
                '/user-manager/user-profile/add',
                '=user=' . $username,
                '=profile=' . $profileName,
            ]);
            
            Log::info("Changed profile for user {$username} to {$profileName}");
            return true;
        } catch (Exception $e) {
            Log::error("Failed to change profile for {$username}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Assign profile to all users who don't have it
     */
    public function assignProfileToAllUsers(string $profileName): array
    {
        $assigned = 0;
        $failed = 0;
        $errors = [];

        try {
            // Get all users
            $users = $this->getUsers();
            
            // Get existing user-profile assignments
            $existingAssignments = $this->getUserProfiles();
            $assignedUserIds = [];
            
            foreach ($existingAssignments as $assignment) {
                $assignedUserIds[] = $assignment['user'] ?? '';
            }

            foreach ($users as $user) {
                $userId = $user['.id'] ?? null;
                $username = $user['name'] ?? $user['username'] ?? 'unknown';
                
                if (!$userId) continue;
                
                // Skip if already has a profile assignment
                if (in_array($userId, $assignedUserIds)) {
                    continue;
                }

                try {
                    $this->assignProfileToUser($userId, $profileName);
                    $assigned++;
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = "User {$username}: " . $e->getMessage();
                }
            }

            return [
                'success' => true,
                'assigned' => $assigned,
                'failed' => $failed,
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            Log::error("Failed to assign profile to all users: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Renew user subscription - Add new profile assignment to extend validity
     * This is how UserManager 7 handles renewals
     */
    public function renewUser(string $username, string $profileName, ?float $dataLimitGb = null, ?int $expiryDays = null): array
    {
        try {
            Log::info("renewUser: بدء تجديد {$username} بباقة {$profileName}");

            // Step 1: Find user on router to get .id
            $users = $this->api->comm([
                '/user-manager/user/print',
                '?name=' . $username
            ]);

            if (empty($users) || !isset($users[0]['name'])) {
                Log::error("renewUser: المستخدم {$username} غير موجود على الراوتر");
                throw new Exception('المستخدم غير موجود على الراوتر');
            }

            $userId = $users[0]['.id'];
            Log::info("renewUser: وجد المستخدم {$username} بمعرف {$userId}");

            // Step 2: Disable user (disconnect from network)
            $this->api->comm([
                '/user-manager/user/set',
                '=.id=' . $userId,
                '=disabled=yes',
            ]);
            Log::info("renewUser: تم تعطيل المستخدم {$username}");
            sleep(3);

            // Step 3: Delete all old user-profile assignments
            $this->deleteUserProfiles($username);
            Log::info("renewUser: تم حذف البروفايلات القديمة لـ {$username}");
            sleep(3);

            // Step 4: Add new profile assignment
            $result = $this->api->comm([
                '/user-manager/user-profile/add',
                '=user=' . $username,
                '=profile=' . $profileName,
            ]);
            Log::info("renewUser: نتيجة إضافة البروفايل: " . json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));

            // Check for errors
            $hasError = false;
            $errorMsg = '';
            if (is_array($result)) {
                foreach ($result as $item) {
                    if (is_array($item) && (isset($item['!trap']) || (isset($item[0]) && $item[0] === '!trap'))) {
                        $hasError = true;
                        $errorMsg = $item['message'] ?? 'Unknown error';
                        break;
                    }
                }
            }

            if ($hasError) {
                // Re-enable user even if profile add failed
                $this->api->comm([
                    '/user-manager/user/set',
                    '=.id=' . $userId,
                    '=disabled=no',
                ]);
                Log::error("renewUser: فشل إضافة البروفايل: {$errorMsg}");
                throw new Exception('فشل في إضافة الباقة: ' . $errorMsg);
            }

            // Step 5: Set data limit if specified
            if ($dataLimitGb !== null && $dataLimitGb > 0) {
                $this->setUserDataLimit($username, $dataLimitGb);
                Log::info("renewUser: تم تعيين حد البيانات {$dataLimitGb}GB لـ {$username}");
            }

            sleep(3);

            // Step 6: Re-enable user (reconnects with new profile)
            $this->api->comm([
                '/user-manager/user/set',
                '=.id=' . $userId,
                '=disabled=no',
            ]);
            Log::info("renewUser: تم تفعيل المستخدم {$username} بالبروفايل الجديد {$profileName}");

            return [
                'success' => true,
                'message' => 'تم تجديد الاشتراك بنجاح',
                'result' => $result,
                'user_id' => $userId,
            ];
        } catch (Exception $e) {
            Log::error("renewUser: فشل تجديد {$username}: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Set custom data limit for a user (in GB)
     */
    public function setUserDataLimit(string $userId, float $dataLimitGb): void
    {
        try {
            // Convert GB to bytes
            $dataLimitBytes = $dataLimitGb > 0 ? (int) ($dataLimitGb * 1073741824) : 0;
            
            // Get user to find their profile
            $userProfiles = $this->api->comm(['/user-manager/user-profile/print', '?user=' . $userId]);
            
            if (!empty($userProfiles)) {
                // Get the most recent user-profile entry
                $lastProfile = end($userProfiles);
                $userProfileId = $lastProfile['.id'] ?? null;
                
                if ($userProfileId) {
                    // Try to set the transfer limit directly on user-profile
                    try {
                        $this->api->comm([
                            '/user-manager/user-profile/set',
                            '=.id=' . $userProfileId,
                            '=transfer-limit=' . $dataLimitBytes,
                        ]);
                        $msg = $dataLimitBytes > 0 ? "Set transfer limit {$dataLimitBytes} bytes for user-profile {$userProfileId}" : "Removed transfer limit for user-profile {$userProfileId}"; Log::info($msg);
                    } catch (Exception $e) {
                        Log::warning("Could not set transfer limit on user-profile: " . $e->getMessage());
                    }
                }
            }
        } catch (Exception $e) {
            Log::warning("Could not set data limit for user {$userId}: " . $e->getMessage());
        }
    }


    /**
     * Reset user usage counters by deleting and recreating the user
     * This zeros the Total Download/Upload/Uptime in UserManager
     */
    public function resetUserUsage(string $username, ?string $originalProfile = null): array
    {
        try {
            // Get the user by username
            $users = $this->api->comm(['/user-manager/user/print', '?name=' . $username]);

            if (empty($users) || !isset($users[0]['name'])) {
                throw new Exception('المستخدم غير موجود على الراوتر');
            }

            $userId = $users[0]['.id'];
            $password = $users[0]['password'] ?? $username;
            $group = $users[0]['group'] ?? 'default';
            $sharedUsers = $users[0]['shared-users'] ?? '1';
            $comment = $users[0]['comment'] ?? '';

            // Get current profile if not provided
            if (!$originalProfile) {
                $userProfile = $this->api->comm(['/user-manager/user-profile/print', '?user=' . $username]);
                $originalProfile = $userProfile[0]['profile'] ?? 'default';
            }

            Log::warning("Resetting usage for user {$username} (ID: {$userId}) - delete+recreate");

            // Step 1: Delete the user
            $this->api->comm(['/user-manager/user/remove', '=.id=' . $userId]);

            // Step 2: Recreate the user with same credentials
            $createCmd = [
                '/user-manager/user/add',
                '=name=' . $username,
                '=password=' . $password,
                '=group=' . $group,
                '=shared-users=' . $sharedUsers,
            ];
            if (!empty($comment)) {
                $createCmd[] = '=comment=' . $this->encodeForMikrotik($comment);
            }

            $createResult = $this->api->comm($createCmd);
            $newUserId = $createResult[0]['ret'] ?? null;

            // Step 3: Restore the profile
            if ($originalProfile) {
                $this->api->comm([
                    '/user-manager/user-profile/add',
                    '=user=' . $username,
                    '=profile=' . $originalProfile,
                ]);
            }

            Log::warning("Successfully reset usage for user {$username}, new ID: {$newUserId}");

            return [
                'success' => true,
                'message' => 'تم تصفير الاستهلاك بنجاح',
                'new_user_id' => $newUserId,
            ];
        } catch (Exception $e) {
            Log::error("Failed to reset usage for {$username}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get UserManager Limitations
     */
    public function getLimitations(): array
    {
        try {
            return $this->api->comm(['/user-manager/limitation/print']);
        } catch (Exception $e) {
            Log::error("Failed to get UserManager limitations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * تجديد الاشتراك مع إعادة تعيين الاستهلاك ورفع التقييد
     * يحذف المستخدم ويعيد إنشاءه مع البروفايل الأصلي
     */
    public function renewAndResetUsage(string $userId, ?string $profileName = null): array
    {
        try {
            // Get the user by ID
            $users = $this->api->comm(['/user-manager/user/print', '?.id=' . $userId]);
            
            if (empty($users) || !isset($users[0]['name'])) {
                throw new Exception('User not found on router');
            }
            
            $username = $users[0]['name'];
            $password = $users[0]['password'] ?? $username;
            $group = $users[0]['group'] ?? 'default';
            $sharedUsers = $users[0]['shared-users'] ?? '1';
            $comment = $users[0]['comment'] ?? '';
            
            // Get current profile if not specified
            if (!$profileName) {
                $userProfile = $this->api->comm(['/user-manager/user-profile/print', '?user=' . $username]);
                $profileName = isset($userProfile[0]['profile']) ? $userProfile[0]['profile'] : 'default';
            }
            
            Log::info("Renewing and resetting user {$username} with profile {$profileName}");
            
            // Step 1: Delete the user
            $this->api->comm(['/user-manager/user/remove', '=.id=' . $userId]);
            
            // Step 2: Recreate the user
            $createCmd = [
                '/user-manager/user/add',
                '=name=' . $username,
                '=password=' . $password,
                '=group=' . $group,
                '=shared-users=' . $sharedUsers,
            ];
            if (!empty($comment)) {
                $createCmd[] = '=comment=' . $this->encodeForMikrotik($comment);
            }
            
            $createResult = $this->api->comm($createCmd);
            $newUserId = $createResult[0]['ret'] ?? null;
            
            // Step 3: Add the profile
            $this->api->comm([
                '/user-manager/user-profile/add',
                '=user=' . $username,
                '=profile=' . $profileName,
            ]);
            
            Log::info("Successfully renewed user {$username}, new ID: {$newUserId}");
            
            return [
                'success' => true,
                'message' => 'تم تجديد الاشتراك وتصفير الاستهلاك بنجاح',
                'new_user_id' => $newUserId,
            ];
        } catch (Exception $e) {
            Log::error("Failed to renew user {$userId}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a Limitation (speed/data limits)
     */
    public function createLimitation(array $data): array
    {
        $command = ['/user-manager/limitation/add'];
        
        if (isset($data['name'])) $command[] = '=name=' . $data['name'];
        if (isset($data['rate-limit'])) $command[] = '=rate-limit=' . $data['rate-limit'];
        if (isset($data['rate-limit-rx'])) $command[] = '=rate-limit-rx=' . $data['rate-limit-rx'];
        if (isset($data['rate-limit-tx'])) $command[] = '=rate-limit-tx=' . $data['rate-limit-tx'];
        if (isset($data['download-limit'])) $command[] = '=download-limit=' . $data['download-limit'];
        if (isset($data['upload-limit'])) $command[] = '=upload-limit=' . $data['upload-limit'];
        if (isset($data['transfer-limit'])) $command[] = '=transfer-limit=' . $data['transfer-limit'];
        if (isset($data['uptime-limit'])) $command[] = '=uptime-limit=' . $data['uptime-limit'];
        
        Log::info("Creating limitation with command: " . json_encode($command, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
        $result = $this->api->comm($command);
        Log::info("Limitation creation result: " . json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
        
        return $result;
    }

    /**
     * إنشاء بروفايل التقييد (throttled) على الراوتر
     * يستخدم لتقييد سرعة المشتركين الذين تجاوزوا حد الاستهلاك
     */
    public function createThrottledProfile(string $profileName = 'throttled', string $speed = '1k'): array
    {
        try {
            $limitationName = $profileName . '-limit';
            
            // التحقق من وجود البروفايل
            $existingProfile = $this->api->comm(['/user-manager/profile/print', '?name=' . $profileName]);
            if (!empty($existingProfile) && isset($existingProfile[0]['.id'])) {
                Log::info("Throttled profile '{$profileName}' already exists");
                return [
                    'success' => true,
                    'message' => "البروفايل '{$profileName}' موجود مسبقاً",
                    'exists' => true,
                ];
            }
            
            // التحقق من وجود Limitation
            $existingLimitation = $this->api->comm(['/user-manager/limitation/print', '?name=' . $limitationName]);
            
            if (empty($existingLimitation) || !isset($existingLimitation[0]['.id'])) {
                // إنشاء Limitation جديد بسرعة بطيئة
                $limitResult = $this->api->comm([
                    '/user-manager/limitation/add',
                    '=name=' . $limitationName,
                    '=rate-limit-rx=' . $speed,
                    '=rate-limit-tx=' . $speed,
                ]);
                
                if (isset($limitResult[0]['message'])) {
                    throw new Exception('فشل إنشاء Limitation: ' . $limitResult[0]['message']);
                }
                Log::info("Created throttled limitation: {$limitationName} with speed {$speed}");
            }
            
            // إنشاء Profile
            $profileResult = $this->api->comm([
                '/user-manager/profile/add',
                '=name=' . $profileName,
                '=validity=unlimited',
                '=starts-when=first-auth',
            ]);
            
            if (isset($profileResult[0]['message'])) {
                throw new Exception('فشل إنشاء Profile: ' . $profileResult[0]['message']);
            }
            Log::info("Created throttled profile: {$profileName}");
            
            // ربط Profile بـ Limitation
            $linkResult = $this->api->comm([
                '/user-manager/profile-limitation/add',
                '=profile=' . $profileName,
                '=limitation=' . $limitationName,
            ]);
            
            if (isset($linkResult[0]['message'])) {
                Log::warning("Failed to link profile to limitation: " . $linkResult[0]['message']);
            }
            
            Log::info("Successfully created throttled profile '{$profileName}' with speed {$speed}");
            
            return [
                'success' => true,
                'message' => "تم إنشاء بروفايل التقييد '{$profileName}' بسرعة {$speed} بنجاح",
                'profile' => $profileName,
                'limitation' => $limitationName,
                'speed' => $speed,
            ];
            
        } catch (Exception $e) {
            Log::error("Failed to create throttled profile: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'فشل إنشاء بروفايل التقييد: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Create a Profile
     */
    public function createProfile(array $data): array
    {
        $command = ['/user-manager/profile/add'];
        
        if (isset($data['name'])) $command[] = '=name=' . $data['name'];
        if (isset($data['name-for-users'])) $command[] = '=name-for-users=' . $data['name-for-users'];
        if (isset($data['validity'])) $command[] = '=validity=' . $data['validity'];
        if (isset($data['starts-when'])) $command[] = '=starts-when=' . $data['starts-when'];
        if (isset($data['price'])) $command[] = '=price=' . $data['price'];
        if (isset($data['override-shared-users'])) $command[] = '=override-shared-users=' . $data['override-shared-users'];
        
        Log::info("Creating profile with command: " . json_encode($command, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
        $result = $this->api->comm($command);
        Log::info("Profile creation result: " . json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
        
        return $result;
    }

    /**
     * Link Limitation to Profile (Profile-Limitation)
     */
    public function linkLimitationToProfile(string $profile, string $limitation): array
    {
        $command = [
            '/user-manager/profile-limitation/add',
            '=profile=' . $profile,
            '=limitation=' . $limitation,
        ];
        
        Log::info("Linking profile to limitation with command: " . json_encode($command, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
        $result = $this->api->comm($command);
        Log::info("Profile-Limitation link result: " . json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
        
        return $result;
    }

    /**
     * Create complete package (Limitation + Profile + Link)
     */
    public function createPackage(array $data): array
    {
        $result = [
            'success' => false,
            'limitation' => null,
            'profile' => null,
            'errors' => [],
        ];

        $name = $data['name'];
        $rateLimit = $data['rate_limit'] ?? null; // e.g., "10M/10M"
        $dataLimit = $data['data_limit'] ?? null; // in bytes
        $validity = $data['validity'] ?? null; // e.g., "30d" or "1h"
        $price = $data['price'] ?? 0;
        $sharedUsers = $data['shared_users'] ?? 1;

        Log::info("Creating package: {$name}, rate_limit: {$rateLimit}, validity: {$validity}");

        try {
            // Step 1: Create Limitation
            $limitationData = ['name' => $name];
            
            if ($rateLimit) {
                // تحويل صيغة السرعة إلى الصيغة الصحيحة لـ UserManager 7
                // rate-limit-rx = download, rate-limit-tx = upload
                $speeds = explode('/', $rateLimit);
                if (count($speeds) == 2) {
                    $limitationData['rate-limit-tx'] = $speeds[0]; // upload
                    $limitationData['rate-limit-rx'] = $speeds[1]; // download
                } else {
                    $limitationData['rate-limit'] = $rateLimit;
                }
            }
            if ($dataLimit) {
                $limitationData['transfer-limit'] = $dataLimit;
            }
            
            $limitResult = $this->createLimitation($limitationData);
            $result['limitation'] = $limitResult;
            
            // Check for limitation errors
            if (isset($limitResult['!trap']) || (isset($limitResult[0]['message']) && strpos($limitResult[0]['message'], 'failure') !== false)) {
                $errorMsg = $limitResult['!trap'][0]['message'] ?? $limitResult[0]['message'] ?? 'Unknown limitation error';
                throw new Exception("Limitation error: {$errorMsg}");
            }

            // Step 2: Create Profile
            $profileData = [
                'name' => $name,
                'name-for-users' => $name,
                'starts-when' => 'first-auth',
            ];
            
            if ($validity) {
                $profileData['validity'] = $validity;
            }
            if ($price > 0) {
                $profileData['price'] = (string)$price;
            }
            if ($sharedUsers > 1) {
                $profileData['override-shared-users'] = (string)$sharedUsers;
            }
            
            $profileResult = $this->createProfile($profileData);
            $result['profile'] = $profileResult;
            
            // Check for profile errors
            if (isset($profileResult['!trap']) || (isset($profileResult[0]['message']) && strpos($profileResult[0]['message'], 'failure') !== false)) {
                $errorMsg = $profileResult['!trap'][0]['message'] ?? $profileResult[0]['message'] ?? 'Unknown profile error';
                throw new Exception("Profile error: {$errorMsg}");
            }

            // Step 3: Link Limitation to Profile
            $linkResult = $this->linkLimitationToProfile($name, $name);
            $result['link'] = $linkResult;
            
            // Check for link errors (not critical)
            if (isset($linkResult['!trap'])) {
                Log::warning("Profile-Limitation link warning: " . json_encode($linkResult, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
            }

            $result['success'] = true;
            Log::info("Package '{$name}' created successfully");
            
        } catch (Exception $e) {
            $result['errors'][] = $e->getMessage();
            Log::error("Failed to create package '{$name}': " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Delete Limitation
     */
    public function deleteLimitation(string $id): array
    {
        return $this->api->comm(['/user-manager/limitation/remove', '=.id=' . $id]);
    }

    /**
     * Delete Profile
     */
    public function deleteProfile(string $id): array
    {
        return $this->api->comm(['/user-manager/profile/remove', '=.id=' . $id]);
    }

    /**
     * Get Profile-Limitations (links)
     */
    public function getProfileLimitations(): array
    {
        try {
            return $this->api->comm(['/user-manager/profile-limitation/print']);
        } catch (Exception $e) {
            Log::error("Failed to get profile-limitations: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Delete Profile-Limitation link
     */
    public function deleteProfileLimitation(string $id): array
    {
        return $this->api->comm(['/user-manager/profile-limitation/remove', '=.id=' . $id]);
    }

    /**
     * Get User Groups (Authentication Groups)
     */
    public function getUserGroups(): array
    {
        try {
            return $this->api->comm(['/user-manager/user-group/print']);
        } catch (Exception $e) {
            Log::error("Failed to get user groups: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Create User Group
     */
    public function createUserGroup(array $data): array
    {
        $command = ['/user-manager/user-group/add'];
        
        if (isset($data['name'])) $command[] = '=name=' . $data['name'];
        
        // Outer Auths
        if (isset($data['outer-auths'])) $command[] = '=outer-auths=' . $data['outer-auths'];
        
        // Inner Auths
        if (isset($data['inner-auths'])) $command[] = '=inner-auths=' . $data['inner-auths'];
        
        // Attributes
        if (isset($data['attributes']) && !empty($data['attributes'])) {
            $command[] = '=attributes=' . $data['attributes'];
        }
        
        return $this->api->comm($command);
    }

    /**
     * Update User Group
     */
    public function updateUserGroup(string $id, array $data): array
    {
        $command = ['/user-manager/user-group/set', '=.id=' . $id];
        
        if (isset($data['name'])) $command[] = '=name=' . $data['name'];
        if (isset($data['outer-auths'])) $command[] = '=outer-auths=' . $data['outer-auths'];
        if (isset($data['inner-auths'])) $command[] = '=inner-auths=' . $data['inner-auths'];
        if (isset($data['attributes'])) $command[] = '=attributes=' . $data['attributes'];
        
        return $this->api->comm($command);
    }

    /**
     * Delete User Group
     */
    public function deleteUserGroup(string $id): array
    {
        return $this->api->comm(['/user-manager/user-group/remove', '=.id=' . $id]);
    }

    /**
     * Get UserManager Attributes
     */
    public function getAttributes(): array
    {
        try {
            return $this->api->comm(['/user-manager/attribute/print']);
        } catch (Exception $e) {
            Log::error("Failed to get attributes: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get UserManager active sessions
     */
    public function getSessions(): array
    {
        try {
            return $this->api->comm(['/user-manager/session/print']);
        } catch (Exception $e) {
            Log::error("Failed to get UserManager sessions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Disconnect UserManager session
     */
    public function disconnectSession(string $id): array
    {
        return $this->api->comm(['/user-manager/session/remove', '=.id=' . $id]);
    }

    /**
     * Get UserManager logs
     */
    public function getLogs(int $limit = 100): array
    {
        try {
            return $this->api->comm(['/user-manager/log/print', '=count=' . $limit]);
        } catch (Exception $e) {
            Log::error("Failed to get UserManager logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate vouchers
     */
    public function generateVouchers(string $group, int $count, array $options = []): array
    {
        $command = [
            '/user-manager/user/generate',
            '=numbers=' . $count,
            '=group=' . $group,
        ];

        if (isset($options['prefix'])) {
            $command[] = '=prefix=' . $options['prefix'];
        }
        if (isset($options['length'])) {
            $command[] = '=length=' . $options['length'];
        }
        if (isset($options['validity'])) {
            $command[] = '=validity=' . $options['validity'];
        }
        if (isset($options['uptime-limit'])) {
            $command[] = '=uptime-limit=' . $options['uptime-limit'];
        }

        return $this->api->comm($command);
    }

    /**
     * Sync UserManager users with database
     */
    public function syncUsers(): array
    {
        $startTime = microtime(true);
        $synced = 0;
        $failed = 0;
        $errors = [];

        try {
            $users = $this->getUsers();
            
            // Get user-profile assignments to find actual profile for each user
            $userProfiles = $this->getUserProfiles();
            $userProfileMap = [];
            foreach ($userProfiles as $up) {
                $username = $up['user'] ?? null;
                if ($username) {
                    // Keep the latest profile assignment for each user
                    $userProfileMap[$username] = $up['profile'] ?? 'default';
                }
            }
            
            // Get limitations to find speed for each profile
            $limitations = $this->getLimitations();
            $limitationMap = [];
            foreach ($limitations as $lim) {
                $name = $lim['name'] ?? null;
                if ($name) {
                    // rate-limit-rx = download, rate-limit-tx = upload (in bits per second)
                    $downloadRaw = $lim['rate-limit-rx'] ?? $lim['rate-limit'] ?? '0';
                    $uploadRaw = $lim['rate-limit-tx'] ?? '0';
                    
                    // Convert to human readable format
                    $downloadSpeed = $this->formatSpeed($downloadRaw);
                    $uploadSpeed = $this->formatSpeed($uploadRaw);
                    
                    $limitationMap[$name] = [
                        'download' => $downloadSpeed,
                        'upload' => $uploadSpeed,
                        'rate_limit' => $downloadSpeed ? "{$uploadSpeed}/{$downloadSpeed}" : '',
                    ];
                }
            }
            
            foreach ($users as $user) {
                // UserManager 7 uses 'name' not 'username'
                $username = $user['name'] ?? $user['username'] ?? null;
                if (!$username) continue;
                
                try {
                    // Parse validity/expiration
                    $expirationDate = null;
                    if (isset($user['validity']) && $user['validity'] !== '') {
                        // Validity format: 1d 2h 3m or timestamp
                        $expirationDate = $this->parseValidity($user['validity']);
                    }

                    // Parse limits
                    $downloadLimit = $this->parseBytes($user['download-limit'] ?? '0');
                    $uploadLimit = $this->parseBytes($user['upload-limit'] ?? '0');
                    $uptimeLimit = $this->parseTime($user['uptime-limit'] ?? '0');

                    // Get total used values from UserManager 7
                    // UserManager 7 uses 'total-download', 'total-upload', 'total-uptime'
                    $downloadUsed = $this->parseBytes($user['total-download'] ?? $user['actual-download'] ?? '0');
                    $uploadUsed = $this->parseBytes($user['total-upload'] ?? $user['actual-upload'] ?? '0');
                    $uptimeUsed = $this->parseTime($user['total-uptime'] ?? $user['actual-uptime'] ?? '0');

                    // Determine status
                    $disabled = isset($user['disabled']) && $user['disabled'] === 'true';
                    $status = 'active';
                    
                    if ($disabled) {
                        $status = 'disabled';
                    } elseif ($expirationDate && $expirationDate->isPast()) {
                        $status = 'expired';
                    }

                    // Get actual profile from user-profile assignment (not group)
                    $actualProfile = $userProfileMap[$username] ?? $user['group'] ?? 'default';
                    
                    // Get speed from limitation (same name as profile)
                    $speedInfo = $limitationMap[$actualProfile] ?? ['download' => '', 'upload' => '', 'rate_limit' => ''];

                    // Search by router_id and username only (not type)
                    // This allows updating existing PPP users that were migrated to UserManager
                    // Check if subscriber exists - don't overwrite expiration_date from renewals
                    $existingSub = Subscriber::where('router_id', $this->router->id)
                        ->where('username', $username)->first();

                    $subscriber = Subscriber::updateOrCreate(
                        [
                            'router_id' => $this->router->id,
                            'username' => $username,
                        ],
                        [
                            'type' => 'usermanager',
                            'mikrotik_id' => $user['.id'] ?? null,
                            'password' => $user['password'] ?? '',
                            'profile' => $actualProfile,
                            'status' => $status,
                            'expiration_date' => ($existingSub && $existingSub->expiration_date) ? $existingSub->expiration_date : $expirationDate,
                            'comment' => isset($user['comment']) ? $this->decodeFromMikrotik($user['comment']) : null,
                            'full_name' => ($existingSub && $existingSub->full_name) ? $existingSub->full_name : (isset($user['comment']) ? $this->decodeFromMikrotik($user['comment']) : null),
                            'is_synced' => true,
                            'last_synced_at' => now(),
                            'uptime_used' => $uptimeUsed,
                            
                            // UserManager specific data (stored in JSON)
                            // NOTE: We don't update bytes_in/bytes_out/total_bytes here
                            // because UserManager 7 doesn't return usage data reliably
                            // Use RefreshUserManagerUsage job or CheckUsageLimit to get usage from sessions
                            'um_data' => json_encode([
                                'shared_users' => $user['shared-users'] ?? 1,
                                'download_limit' => $downloadLimit,
                                'upload_limit' => $uploadLimit,
                                'uptime_limit' => $uptimeLimit,
                                'time_limit' => $this->parseTime($user['time-limit'] ?? '0'),
                                'download_used' => $downloadUsed,
                                'upload_used' => $uploadUsed,
                                'uptime_used' => $uptimeUsed,
                                'price' => $user['price'] ?? null,
                                'validity' => $user['validity'] ?? null,
                                'rate_limit' => $speedInfo['rate_limit'],
                                'download_speed' => $speedInfo['download'],
                                'upload_speed' => $speedInfo['upload'],
                            ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
                        ]
                    );

                    $synced++;
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = "User {$username}: " . $e->getMessage();
                    Log::error("UserManager sync error for {$username}: " . $e->getMessage());
                }
            }
            $duration = (int)((microtime(true) - $startTime) * 1000);

            SyncLog::create([
                'router_id' => $this->router->id,
                'type' => 'usermanager_users',
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
                'duration' => $duration,
            ];
        } catch (Exception $e) {
            $duration = (int)((microtime(true) - $startTime) * 1000);
            
            SyncLog::create([
                'router_id' => $this->router->id,
                'type' => 'usermanager_users',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration' => $duration,
            ]);

            throw $e;
        }
    }

    /**
     * Sync only usage/consumption data for UserManager users
     * Gets usage from /user-manager/session - same as manual refresh
     */
    public function syncUsage(): array
    {
        $startTime = microtime(true);
        $synced = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        try {
            // Get all sessions from UserManager - this contains actual usage data
            $allSessions = $this->api->comm(['/user-manager/session/print']);
            
            // Group sessions by username
            $sessionsByUser = [];
            foreach ($allSessions as $session) {
                $username = $session['user'] ?? null;
                if ($username) {
                    if (!isset($sessionsByUser[$username])) {
                        $sessionsByUser[$username] = [];
                    }
                    $sessionsByUser[$username][] = $session;
                }
            }

            // Also get user list for status check
            $users = $this->api->comm(['/user-manager/user/print']);
            $userStatus = [];
            foreach ($users as $user) {
                $username = $user['name'] ?? $user['username'] ?? null;
                if ($username) {
                    $userStatus[$username] = [
                        'disabled' => isset($user['disabled']) && $user['disabled'] === 'true',
                    ];
                }
            }

            Log::info("Found " . count($sessionsByUser) . " users with sessions for router {$this->router->id}");

            // Get all subscribers for this router - تحميل مرة واحدة فقط
            $subscribers = Subscriber::where('router_id', $this->router->id)
                ->get()
                ->keyBy('username');
            
            // Process in batches for better performance
            $updateData = [];
            
            foreach ($subscribers as $username => $subscriber) {
                
                try {
                    // Get sessions for this user
                    $sessions = $sessionsByUser[$username] ?? [];
                    
                    // Sum up all session usage
                    $totalDownload = 0;
                    $totalUpload = 0;
                    $totalUptime = 0;

                    foreach ($sessions as $session) {
                        if (isset($session['download'])) {
                            $totalDownload += $this->parseBytes($session['download']);
                        }
                        if (isset($session['upload'])) {
                            $totalUpload += $this->parseBytes($session['upload']);
                        }
                        if (isset($session['uptime'])) {
                            $totalUptime += $this->parseUptimeSeconds($session['uptime']);
                        }
                    }

                    $totalBytes = $totalDownload + $totalUpload;

                    // Get existing um_data or create new
                    $umData = $subscriber->um_data ?? [];
                    if (is_string($umData)) {
                        $umData = json_decode($umData, true) ?? [];
                    }

                    // Update usage values
                    $umData['download_used'] = $totalDownload;
                    $umData['upload_used'] = $totalUpload;
                    $umData['sessions_count'] = count($sessions);

                    // Check user status
                    $disabled = $userStatus[$username]['disabled'] ?? false;
                    $currentStatus = $subscriber->status;

                    if ($disabled && !in_array($currentStatus, ['disabled', 'expired'])) {
                        $currentStatus = 'disabled';
                    } elseif (!$disabled && $currentStatus === 'disabled') {
                        $currentStatus = 'active';
                    }

                    // Prepare update data
                    $updateData = [
                        'uptime_used' => $totalUptime,
                        'um_data' => json_encode($umData, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE),
                        'status' => $currentStatus,
                        'last_synced_at' => now(),
                    ];

                    // Update usage - always update if we have data from sessions
                    if ($totalBytes > 0) {
                        $updateData['bytes_in'] = $totalUpload;
                        $updateData['bytes_out'] = $totalDownload;
                        $updateData['total_bytes'] = $totalBytes;
                    }

                    // Update subscriber
                    $subscriber->update($updateData);

                    $synced++;

                } catch (Exception $e) {
                    $failed++;
                    $errors[] = "User {$username}: " . $e->getMessage();
                    Log::error("UserManager usage sync error for {$username}: " . $e->getMessage());
                }
            }

            $duration = (int)((microtime(true) - $startTime) * 1000);

            Log::info("Usage sync completed for router {$this->router->id}: synced={$synced}, failed={$failed}, skipped={$skipped}");

            SyncLog::create([
                'router_id' => $this->router->id,
                'type' => 'usermanager_usage',
                'status' => $failed > 0 ? 'partial' : 'success',
                'records_synced' => $synced,
                'records_failed' => $failed,
                'error_message' => $failed > 0 ? implode("\n", array_slice($errors, 0, 10)) : null,
                'duration' => $duration,
            ]);

            return [
                'success' => true,
                'synced' => $synced,
                'failed' => $failed,
                'skipped' => $skipped,
                'errors' => $errors,
                'duration' => $duration,
            ];
        } catch (Exception $e) {
            $duration = (int)((microtime(true) - $startTime) * 1000);
            
            Log::error("Usage sync failed for router {$this->router->id}: " . $e->getMessage());
            
            SyncLog::create([
                'router_id' => $this->router->id,
                'type' => 'usermanager_usage',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'duration' => $duration,
            ]);

            throw $e;
        }
    }

    /**
     * Parse uptime string to seconds (e.g., "2d6h10m4s" => seconds)
     */
    private function parseUptimeSeconds($uptime)
    {
        if (empty($uptime) || $uptime === '0s') return 0;
        
        $seconds = 0;
        if (preg_match('/(\d+)w/', $uptime, $m)) $seconds += (int)$m[1] * 604800;
        if (preg_match('/(\d+)d/', $uptime, $m)) $seconds += (int)$m[1] * 86400;
        if (preg_match('/(\d+)h/', $uptime, $m)) $seconds += (int)$m[1] * 3600;
        if (preg_match('/(\d+)m/', $uptime, $m)) $seconds += (int)$m[1] * 60;
        if (preg_match('/(\d+)s/', $uptime, $m)) $seconds += (int)$m[1];
        
        return $seconds;
    }

    /**
     * Sync UserManager sessions
     */
    public function syncSessions(): array
    {
        $startTime = microtime(true);
        $synced = 0;

        try {
            // Clear old UserManager sessions for this router
            ActiveSession::where('router_id', $this->router->id)
                ->where('type', 'usermanager')
                ->delete();

            $sessions = $this->getSessions();

            foreach ($sessions as $session) {
                if (!isset($session['username'])) continue;

                $subscriber = Subscriber::where('router_id', $this->router->id)
                    ->where('username', $session['username'])
                    ->where('type', 'usermanager')
                    ->first();

                ActiveSession::create([
                    'router_id' => $this->router->id,
                    'subscriber_id' => $subscriber?->id,
                    'username' => $session['username'],
                    'session_id' => $session['.id'] ?? null,
                    'type' => 'usermanager',
                    'ip_address' => $session['calling-station-id'] ?? $session['ip-address'] ?? null,
                    'mac_address' => $session['mac-address'] ?? null,
                    'uptime' => $this->parseTime($session['uptime'] ?? '0'),
                    'bytes_in' => $this->parseBytes($session['upload'] ?? '0'),
                    'bytes_out' => $this->parseBytes($session['download'] ?? '0'),
                    'nas_ip' => $session['nas-ip-address'] ?? null,
                ]);

                $synced++;
            }

            // Collect active usernames from sessions
            $activeUMUsernames = [];
            foreach ($sessions as $s) {
                if (isset($s['username'])) $activeUMUsernames[] = $s['username'];
            }

            // Update is_online for active UserManager subscribers
            if (!empty($activeUMUsernames)) {
                Subscriber::where('router_id', $this->router->id)
                    ->where('type', 'usermanager')
                    ->whereIn('username', $activeUMUsernames)
                    ->update(['is_online' => true]);
            }

            // Mark disconnected UserManager subscribers as offline
            Subscriber::where('router_id', $this->router->id)
                ->where('type', 'usermanager')
                ->whereNotIn('username', $activeUMUsernames ?: ['__none__'])
                ->where('is_online', true)
                ->update(['is_online' => false]);

            $duration = (int)((microtime(true) - $startTime) * 1000);

            return [
                'success' => true,
                'synced' => $synced,
                'duration' => $duration,
            ];
        } catch (Exception $e) {
            Log::error("UserManager sessions sync failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Parse validity to Carbon date
     */
    private function parseValidity(string $validity): ?Carbon
    {
        if (empty($validity) || $validity === '0' || $validity === 'unlimited') {
            return null;
        }

        // Check if it's a timestamp
        if (is_numeric($validity)) {
            return Carbon::createFromTimestamp($validity);
        }

        // Parse format like "1d 2h 3m" or "30d"
        preg_match_all('/(\d+)([dhms])/', $validity, $matches);
        
        if (empty($matches[0])) {
            return null;
        }

        $date = now();
        
        for ($i = 0; $i < count($matches[1]); $i++) {
            $value = (int)$matches[1][$i];
            $unit = $matches[2][$i];
            
            switch ($unit) {
                case 'd':
                    $date->addDays($value);
                    break;
                case 'h':
                    $date->addHours($value);
                    break;
                case 'm':
                    $date->addMinutes($value);
                    break;
                case 's':
                    $date->addSeconds($value);
                    break;
            }
        }

        return $date;
    }

    /**
     * Parse bytes (e.g., "1G", "500M", "1024K")
     */
    private function parseBytes(string $value): int
    {
        if (empty($value) || $value === '0' || $value === 'unlimited') {
            return 0;
        }

        $value = trim($value);
        $number = (float)$value;
        $unit = strtoupper(substr($value, -1));

        switch ($unit) {
            case 'G':
                return (int)($number * 1073741824);
            case 'M':
                return (int)($number * 1048576);
            case 'K':
                return (int)($number * 1024);
            default:
                return (int)$number;
        }
    }

    /**
     * Format speed from bytes/bits per second to human readable (e.g., 4M, 10M)
     */
    private function formatSpeed($value): string
    {
        if (empty($value) || $value === '0' || $value === 0) {
            return '0';
        }
        
        // If already formatted (contains M, K, G)
        if (is_string($value) && preg_match('/[MKGmkg]/', $value)) {
            return strtoupper($value);
        }
        
        $bytes = (int)$value;
        
        if ($bytes >= 1000000000) {
            return round($bytes / 1000000000) . 'G';
        } elseif ($bytes >= 1000000) {
            return round($bytes / 1000000) . 'M';
        } elseif ($bytes >= 1000) {
            return round($bytes / 1000) . 'K';
        } else {
            return $bytes . '';
        }
    }

    /**
     * Parse time (e.g., "1d 2h 3m")
     */
    private function parseTime(string $value): int
    {
        if (empty($value) || $value === '0' || $value === 'unlimited') {
            return 0;
        }

        // If it's already seconds
        if (is_numeric($value)) {
            return (int)$value;
        }

        // Parse format like "1d 2h 3m"
        preg_match_all('/(\d+)([dhms])/', $value, $matches);
        
        if (empty($matches[0])) {
            return 0;
        }

        $seconds = 0;
        
        for ($i = 0; $i < count($matches[1]); $i++) {
            $num = (int)$matches[1][$i];
            $unit = $matches[2][$i];
            
            switch ($unit) {
                case 'd':
                    $seconds += $num * 86400;
                    break;
                case 'h':
                    $seconds += $num * 3600;
                    break;
                case 'm':
                    $seconds += $num * 60;
                    break;
                case 's':
                    $seconds += $num;
                    break;
            }
        }

        return $seconds;
    }

    /**
     * Format bytes to human readable
     */
    public function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' B';
        }
    }

    /**
     * Format time to human readable
     */
    public function formatTime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";

        return empty($parts) ? '0m' : implode(' ', $parts);
    }

    /**
     * Migrate PPP users to UserManager
     * This creates users in UserManager and optionally removes from PPP Secrets
     */
    public function migratePPPUsers(string $defaultGroup, bool $deletePPP = false, array $filterUsernames = []): array
    {
        $migrated = 0;
        $failed = 0;
        $errors = [];

        try {
            // Get all PPP secrets from router
            $pppSecrets = $this->api->comm(['/ppp/secret/print']);

            foreach ($pppSecrets as $secret) {
                if (!isset($secret['name'])) continue;

                // Only migrate selected users if filter provided
                if (!empty($filterUsernames) && !in_array($secret['name'], $filterUsernames)) {
                    continue;
                }

                try {
                    $username = $secret['name'];
                    $password = $secret['password'] ?? '';
                    $profile = $secret['profile'] ?? 'default';
                    $comment = $secret['comment'] ?? '';

                    // Check if user already exists in UserManager
                    // Filter response to only count actual user entries (have .id and name fields)
                    $existingUsers = $this->api->comm([
                        '/user-manager/user/print',
                        '?name=' . $username
                    ]);
                    $realUsers = array_filter($existingUsers, function($item) {
                        return is_array($item) && isset($item['.id']) && isset($item['name']);
                    });

                    if (!empty($realUsers)) {
                        // User already exists in UserManager, skip
                        continue;
                    }

                    // Create user in UserManager (without group - assigned via user-profile)
                    $addResult = $this->api->comm([
                        '/user-manager/user/add',
                        '=name=' . $username,
                        '=password=' . $password,
                        '=comment=' . $comment . ' [Migrated from PPP: ' . $profile . ']',
                    ]);

                    // Check for errors in response
                    foreach ($addResult as $item) {
                        if (is_array($item) && isset($item['message']) && strpos($item['message'], 'failure') !== false) {
                            throw new Exception($item['message']);
                        }
                    }

                    // Assign profile/group to user via user-profile
                    $this->api->comm([
                        '/user-manager/user-profile/add',
                        '=user=' . $username,
                        '=profile=' . $defaultGroup,
                    ]);

                    // Delete from PPP Secrets if requested
                    if ($deletePPP && isset($secret['.id'])) {
                        $this->api->comm([
                            '/ppp/secret/remove',
                            '=.id=' . $secret['.id']
                        ]);
                    }

                    // Update database
                    $subscriber = Subscriber::where('router_id', $this->router->id)
                        ->where('username', $username)
                        ->where('type', 'ppp')
                        ->first();

                    if ($subscriber) {
                        $subscriber->update([
                            'type' => 'usermanager',
                            'profile' => $defaultGroup,
                            'comment' => $comment . ' [Migrated from PPP]',
                        ]);
                    }

                    $migrated++;

                } catch (Exception $e) {
                    $failed++;
                    $errors[] = ($secret['name'] ?? 'Unknown') . ': ' . $e->getMessage();
                    Log::error("PPP to UM migration error: " . $e->getMessage());
                }
            }

            return [
                'success' => true,
                'migrated' => $migrated,
                'failed' => $failed,
                'errors' => $errors,
                'total_ppp' => count($pppSecrets),
            ];

        } catch (Exception $e) {
            Log::error("PPP to UM migration failed: " . $e->getMessage());
            return [
                'success' => false,
                'migrated' => $migrated,
                'failed' => $failed,
                'errors' => array_merge($errors, [$e->getMessage()]),
            ];
        }
    }

    /**
     * Get PPP secrets count for migration preview
     */
    public function getPPPSecretsCount(): int
    {
        try {
            $secrets = $this->api->comm(['/ppp/secret/print', '=count-only=']);
            return (int)($secrets['ret'] ?? count($this->api->comm(['/ppp/secret/print'])));
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get UserManager groups for selection
     */
    public function getGroupNames(): array
    {
        try {
            $groups = $this->getGroups();
            return array_column($groups, 'name');
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get user-profile assignments for a user
     */
    public function getUserProfileAssignments(string $username): array
    {
        $result = $this->api->comm([
            '/user-manager/user-profile/print',
            '?user=' . $username
        ]);
        return is_array($result) ? $result : [];
    }

    /**
     * Set end-time on a user-profile assignment
     */
    public function setUserProfileEndTime(string $profileAssignmentId, string $endTime): void
    {
        try {
            $this->api->comm([
                '/user-manager/user-profile/set',
                '=.id=' . $profileAssignmentId,
                '=end-time=' . $endTime,
            ]);
            Log::info("Set end-time {$endTime} for user-profile {$profileAssignmentId}");
        } catch (Exception $e) {
            Log::warning("Failed to set end-time for user-profile {$profileAssignmentId}: " . $e->getMessage());
            throw $e;
        }
    }
}
