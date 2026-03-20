<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\ActiveSession;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class HotspotController extends Controller
{
    /**
     * Export hotspot cards backup
     */
    public function exportBackup(Request $request)
    {
        try {
            $user = Auth::user();
            $routerId = $request->router_id;
            
            $router = Router::findOrFail($routerId);
            $this->authorize('view', $router);
            
            $service = new MikroTikService($router);
            $service->connect();
            
            // Get all hotspot profiles from router
            $profiles = $service->getHotspotProfiles();
            $profilesData = [];
            foreach ($profiles as $p) {
                if (!isset($p['name'])) continue;
                if ($p['name'] === 'default') continue; // Skip default profile
                
                $profilesData[] = [
                    'name' => $p['name'] ?? '',
                    'rate_limit' => $p['rate-limit'] ?? '',
                    'shared_users' => $p['shared-users'] ?? '1',
                    'session_timeout' => $p['session-timeout'] ?? '',
                    'idle_timeout' => $p['idle-timeout'] ?? '',
                    'keepalive_timeout' => $p['keepalive-timeout'] ?? '',
                    'address_pool' => $p['address-pool'] ?? '',
                    'mac_cookie_timeout' => $p['mac-cookie-timeout'] ?? '',
                    'on_login' => $p['on-login'] ?? '',
                    'on_logout' => $p['on-logout'] ?? '',
                ];
            }
            
            // Get all hotspot users from router
            $users = $service->getHotspotUsers();
            
            // Get active sessions to get current usage data
            $activeSessions = $service->getHotspotActive();
            $activeUsage = [];
            foreach ($activeSessions as $session) {
                if (isset($session['user'])) {
                    $activeUsage[$session['user']] = [
                        'bytes_in' => intval($session['bytes-in'] ?? 0),
                        'bytes_out' => intval($session['bytes-out'] ?? 0),
                        'uptime' => $session['uptime'] ?? '0s',
                    ];
                }
            }
            
            $service->disconnect();
            
            $cards = [];
            foreach ($users as $u) {
                if (!isset($u['name'])) continue;
                if (in_array($u['name'], ['default-trial', 'default'])) continue;
                
                $username = $u['name'];
                
                // Get bytes from user record (for users with limits)
                $bytesIn = intval($u['bytes-in'] ?? 0);
                $bytesOut = intval($u['bytes-out'] ?? 0);
                $uptime = $u['uptime'] ?? '0s';
                
                // If user is active, add active session usage
                if (isset($activeUsage[$username])) {
                    $bytesIn += $activeUsage[$username]['bytes_in'];
                    $bytesOut += $activeUsage[$username]['bytes_out'];
                    $uptime = $activeUsage[$username]['uptime'];
                }
                
                $cards[] = [
                    'username' => $username,
                    'password' => $u['password'] ?? '',
                    'profile' => $u['profile'] ?? '',
                    'bytes_in' => $bytesIn,
                    'bytes_out' => $bytesOut,
                    'limit_bytes_total' => intval($u['limit-bytes-total'] ?? 0),
                    'uptime' => $uptime,
                    'disabled' => ($u['disabled'] ?? 'false') === 'true',
                    'comment' => $u['comment'] ?? '',
                ];
            }
            
            $backup = [
                'backup_date' => now()->format('Y-m-d H:i:s'),
                'router_name' => $router->name,
                'router_ip' => $router->host,
                'total_profiles' => count($profilesData),
                'total_cards' => count($cards),
                'profiles' => $profilesData,
                'cards' => $cards,
            ];
            
            return response()->json([
                'success' => true,
                'router_name' => $router->name,
                'backup' => $backup
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل التصدير: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Import/Restore hotspot cards from backup
     */
    public function importBackup(Request $request)
    {
        try {
            $user = Auth::user();
            $routerId = $request->router_id;
            $backup = $request->backup;
            
            if (!$backup || !isset($backup['cards']) || !is_array($backup['cards'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'ملف النسخة الاحتياطية غير صالح'
                ], 400);
            }
            
            $router = Router::findOrFail($routerId);
            $this->authorize('view', $router);
            
            $service = new MikroTikService($router);
            $service->connect();
            
            $profilesRestored = 0;
            $profilesSkipped = 0;
            $restored = 0;
            $failed = 0;
            $skipped = 0;
            
            // First: Restore profiles if exists in backup
            \Log::info('Backup profiles count: ' . (isset($backup['profiles']) ? count($backup['profiles']) : 'not set'));
            
            // Also collect unique profiles from cards
            $profilesFromCards = [];
            foreach ($backup['cards'] as $card) {
                if (!empty($card['profile']) && $card['profile'] !== 'default') {
                    $profilesFromCards[$card['profile']] = true;
                }
            }
            \Log::info('Unique profiles used by cards: ' . json_encode(array_keys($profilesFromCards)));
            
            if (isset($backup['profiles']) && is_array($backup['profiles'])) {
                // Get existing profiles
                $existingProfiles = $service->getHotspotProfiles();
                $existingProfileNames = array_column($existingProfiles, 'name');
                \Log::info('Existing profile names on target router: ' . json_encode($existingProfileNames));
                
                // Check if any profile from cards is missing
                $missingProfiles = array_diff(array_keys($profilesFromCards), $existingProfileNames);
                \Log::info('Missing profiles that need to be created: ' . json_encode(array_values($missingProfiles)));
                
                // Log backup profiles
                $backupProfileNames = array_column($backup['profiles'], 'name');
                \Log::info('Profiles in backup file: ' . json_encode($backupProfileNames));
                
                foreach ($backup['profiles'] as $profile) {
                    if (!isset($profile['name']) || empty($profile['name'])) {
                        \Log::warning('Profile skipped - no name');
                        continue;
                    }
                    
                    // Skip if already exists
                    if (in_array($profile['name'], $existingProfileNames)) {
                        $profilesSkipped++;
                        \Log::info('Profile skipped (exists): ' . $profile['name']);
                        continue;
                    }
                    
                    try {
                        $profileData = ['name' => $profile['name']];
                        
                        if (!empty($profile['rate_limit'])) {
                            $profileData['rate-limit'] = $profile['rate_limit'];
                        }
                        if (!empty($profile['shared_users'])) {
                            $profileData['shared-users'] = $profile['shared_users'];
                        }
                        if (!empty($profile['session_timeout'])) {
                            $profileData['session-timeout'] = $profile['session_timeout'];
                        }
                        if (!empty($profile['idle_timeout'])) {
                            $profileData['idle-timeout'] = $profile['idle_timeout'];
                        }
                        if (!empty($profile['keepalive_timeout'])) {
                            $profileData['keepalive-timeout'] = $profile['keepalive_timeout'];
                        }
                        if (!empty($profile['address_pool'])) {
                            $profileData['address-pool'] = $profile['address_pool'];
                        }
                        if (!empty($profile['mac_cookie_timeout'])) {
                            $profileData['mac-cookie-timeout'] = $profile['mac_cookie_timeout'];
                        }
                        if (!empty($profile['on_login'])) {
                            $profileData['on-login'] = $profile['on_login'];
                        }
                        if (!empty($profile['on_logout'])) {
                            $profileData['on-logout'] = $profile['on_logout'];
                        }
                        
                        $result = $service->addHotspotProfile($profileData);
                        // Check if profile was added successfully
                        if (!isset($result['!trap'])) {
                            $profilesRestored++;
                        } else {
                            \Log::warning('Failed to add profile: ' . $profile['name'] . ' - ' . json_encode($result));
                        }
                    } catch (Exception $e) {
                        \Log::warning('Exception adding profile: ' . $profile['name'] . ' - ' . $e->getMessage());
                    }
                }
                
                // Create missing profiles that are used by cards but not in backup
                foreach ($missingProfiles as $missingProfile) {
                    // Check if this profile was already restored from backup
                    $foundInBackup = false;
                    foreach ($backup['profiles'] as $bp) {
                        if (($bp['name'] ?? '') === $missingProfile) {
                            $foundInBackup = true;
                            break;
                        }
                    }
                    
                    if (!$foundInBackup) {
                        try {
                            \Log::info('Creating missing profile from cards: ' . $missingProfile);
                            $result = $service->addHotspotProfile(['name' => $missingProfile]);
                            if (!isset($result['!trap'])) {
                                $profilesRestored++;
                                \Log::info('Successfully created missing profile: ' . $missingProfile);
                            } else {
                                \Log::warning('Failed to create missing profile: ' . $missingProfile . ' - ' . json_encode($result));
                            }
                        } catch (Exception $e) {
                            \Log::warning('Exception creating missing profile: ' . $missingProfile . ' - ' . $e->getMessage());
                        }
                    }
                }
            } else {
                // No profiles in backup, but create profiles from cards
                $existingProfiles = $service->getHotspotProfiles();
                $existingProfileNames = array_column($existingProfiles, 'name');
                
                foreach ($profilesFromCards as $profileName => $val) {
                    if (!in_array($profileName, $existingProfileNames)) {
                        try {
                            \Log::info('Creating profile from cards (no backup profiles): ' . $profileName);
                            $result = $service->addHotspotProfile(['name' => $profileName]);
                            if (!isset($result['!trap'])) {
                                $profilesRestored++;
                                \Log::info('Successfully created profile: ' . $profileName);
                            }
                        } catch (Exception $e) {
                            \Log::warning('Exception creating profile: ' . $profileName . ' - ' . $e->getMessage());
                        }
                    }
                }
            }
            
            // Reconnect to ensure fresh connection
            try {
                $service->disconnect();
            } catch (Exception $e) {
                // Ignore disconnect errors
            }
            $service = new MikroTikService($router);
            $service->connect();
            
            // Second: Get existing users to avoid duplicates
            $existingUsers = $service->getHotspotUsers();
            $existingUsernames = array_column($existingUsers, 'name');
            
            // Get available profiles on router (refresh after adding new profiles)
            $availableProfiles = $service->getHotspotProfiles();
            $availableProfileNames = array_column($availableProfiles, 'name');
            
            \Log::info('Available profiles after reconnect: ' . json_encode($availableProfileNames));
            
            $errors = [];
            \Log::info('Starting to add users. Total cards in backup: ' . count($backup['cards']));
            \Log::info('Existing users count: ' . count($existingUsernames));
            
            foreach ($backup['cards'] as $card) {
                if (!isset($card['username']) || !isset($card['password'])) {
                    $failed++;
                    \Log::warning('Card skipped - missing username or password');
                    continue;
                }
                
                // Skip if already exists
                if (in_array($card['username'], $existingUsernames)) {
                    $skipped++;
                    continue;
                }
                
                try {
                    // Check if profile exists, if not use 'default'
                    $profile = $card['profile'] ?? 'default';
                    if (!in_array($profile, $availableProfileNames)) {
                        \Log::warning("Profile not found: {$profile}, using default");
                        $profile = 'default';
                    }
                    
                    // Add user to router with all original data
                    $userData = [
                        'name' => $card['username'],
                        'password' => $card['password'],
                        'profile' => $profile,
                    ];
                    
                    // Add bytes used if any
                    if (!empty($card['bytes_in'])) {
                        $userData['bytes-in'] = $card['bytes_in'];
                    }
                    if (!empty($card['bytes_out'])) {
                        $userData['bytes-out'] = $card['bytes_out'];
                    }
                    
                    // Add limit if set
                    if (!empty($card['limit_bytes_total'])) {
                        $userData['limit-bytes-total'] = $card['limit_bytes_total'];
                    }
                    
                    // Add disabled status
                    if (!empty($card['disabled'])) {
                        $userData['disabled'] = 'true';
                    }
                    
                    // Add comment if exists
                    if (!empty($card['comment'])) {
                        $userData['comment'] = $card['comment'];
                    }
                    
                    $result = $service->addHotspotUser($userData);
                    if (isset($result['!trap'])) {
                        \Log::error("Failed to add user {$card['username']}: " . json_encode($result));
                        $failed++;
                        $errors[] = $card['username'] . ': ' . ($result[0]['message'] ?? 'Unknown error');
                    } else {
                        $restored++;
                    }
                    
                } catch (Exception $e) {
                    $failed++;
                    $errors[] = $card['username'] . ': ' . $e->getMessage();
                    \Log::error("Exception adding user {$card['username']}: " . $e->getMessage());
                }
            }
            
            \Log::info("Import complete: restored=$restored, skipped=$skipped, failed=$failed");
            
            $service->disconnect();
            
            // Also save to local database
            foreach ($backup['cards'] as $card) {
                if (in_array($card['username'], $existingUsernames)) continue;
                
                Subscriber::updateOrCreate(
                    ['router_id' => $router->id, 'username' => $card['username']],
                    [
                        'password' => $card['password'],
                        'type' => 'hotspot',
                        'profile' => $card['profile'] ?? 'default',
                        'bytes_in' => $card['bytes_in'] ?? 0,
                        'bytes_out' => $card['bytes_out'] ?? 0,
                        'limit_bytes_total' => $card['limit_bytes_total'] ?? 0,
                        'status' => !empty($card['disabled']) ? 'disabled' : 'active',
                    ]
                );
            }
            
            $message = "";
            if ($profilesRestored > 0) {
                $message .= "تم استعادة {$profilesRestored} بروفايل";
                if ($profilesSkipped > 0) $message .= " (تم تجاهل {$profilesSkipped} موجود)";
                $message .= " | ";
            }
            $message .= "تم استعادة {$restored} بطاقة بنجاح";
            if ($skipped > 0) $message .= " (تم تجاهل {$skipped} بطاقة موجودة مسبقاً)";
            if ($failed > 0) $message .= " (فشل {$failed})";
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'restored' => $restored,
                'skipped' => $skipped,
                'failed' => $failed,
                'profiles_restored' => $profilesRestored,
                'errors' => array_slice($errors, 0, 10) // Show first 10 errors
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل الاستعادة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display Hotspot users
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id')->toArray()
            : $user->routers()->pluck('routers.id')->toArray();

        $routers = Router::whereIn('id', $routerIds)->get();
        
        if (empty($routerIds) || $routers->isEmpty()) {
            return view('hotspot.index', [
                'routers' => collect(),
                'subscribers' => Subscriber::whereRaw('0 = 1')->paginate(20),
                'stats' => ['total' => 0, 'active' => 0, 'disabled' => 0, 'online' => 0]
            ]);
        }

        $query = Subscriber::whereIn('router_id', $routerIds)
            ->where('type', 'hotspot')
            ->with(['router', 'activeSessions']);

        // Filter by router
        if ($request->filled('router_id')) {
            $query->where('router_id', $request->router_id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Get stats before pagination (using efficient count queries)
        $statsQuery = Subscriber::whereIn('router_id', $routerIds)->where('type', 'hotspot');
        
        if ($request->filled('router_id')) {
            $statsQuery->where('router_id', $request->router_id);
        }
        
        // البطاقات غير المستخدمة (bytes_in = 0 AND bytes_out = 0)
        $unusedCount = (clone $statsQuery)
            ->where(function($q) {
                $q->whereNull('bytes_in')->orWhere('bytes_in', 0);
            })
            ->where(function($q) {
                $q->whereNull('bytes_out')->orWhere('bytes_out', 0);
            })
            ->count();
        
        // البطاقات المستهلكة بالكامل (وصلت للحد الأقصى)
        $fullyConsumedCount = (clone $statsQuery)
            ->whereNotNull('limit_bytes_total')
            ->where('limit_bytes_total', '>', 0)
            ->whereRaw('(COALESCE(bytes_in, 0) + COALESCE(bytes_out, 0)) >= limit_bytes_total')
            ->count();
        
        // البطاقات قيد الاستخدام (استخدمت جزء ولم تكتمل)
        $inUseCount = (clone $statsQuery)
            ->where(function($q) {
                $q->where('bytes_in', '>', 0)->orWhere('bytes_out', '>', 0);
            })
            ->where(function($q) {
                $q->whereNull('limit_bytes_total')
                  ->orWhere('limit_bytes_total', 0)
                  ->orWhereRaw('(COALESCE(bytes_in, 0) + COALESCE(bytes_out, 0)) < limit_bytes_total');
            })
            ->count();
        
        $stats = [
            'total' => (clone $statsQuery)->count(),
            'active' => (clone $statsQuery)->where('status', 'active')->count(),
            'disabled' => (clone $statsQuery)->where('status', 'disabled')->count(),
            'online' => ActiveSession::whereIn('subscriber_id', 
                (clone $statsQuery)->pluck('id')
            )->distinct('subscriber_id')->count(),
            'unused' => $unusedCount,
            'consumed' => $fullyConsumedCount,
            'inUse' => $inUseCount,
        ];

        // Paginate subscribers to prevent memory issues - ordered by usage (highest first)
        $subscribers = $query->orderByRaw('(COALESCE(bytes_in, 0) + COALESCE(bytes_out, 0)) DESC')->paginate(100)->withQueryString();

        return view('hotspot.index', compact('subscribers', 'routers', 'stats'));
    }

    /**
     * Show create hotspot user form
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $routers = Router::whereIn('id', $routerIds)->get();
        
        $selectedRouter = $request->filled('router_id') 
            ? Router::findOrFail($request->router_id) 
            : $routers->first();

        return view('hotspot.create', compact('routers', 'selectedRouter'));
    }

    /**
     * Show card after creation
     */
    public function card(Subscriber $hotspot)
    {
        $this->authorize('view', $hotspot->router);
        
        // Convert bytes to GB
        $dataLimitGb = $hotspot->limit_bytes_total > 0 
            ? round($hotspot->limit_bytes_total / (1024 * 1024 * 1024), 2)
            : null;
        
        $card = [
            'username' => $hotspot->username,
            'password' => $hotspot->password,
            'profile' => $hotspot->profile,
            'data_limit' => $dataLimitGb,
        ];
        
        return view('hotspot.card', compact('card'));
    }

    /**
     * Store new hotspot user
     */
    public function store(Request $request)
    {
        $request->validate([
            'router_id' => 'required|exists:routers,id',
            'username' => 'required|string|min:3|max:255',
            'password' => 'required|string|min:3',
            'profile' => 'nullable|string',
            'data_limit_gb' => 'required|numeric|min:0.1|max:1000',
        ]);

        $router = Router::findOrFail($request->router_id);
        $this->authorize('view', $router);

        $dataLimitGb = floatval($request->data_limit_gb);
        $dataLimitBytes = intval($dataLimitGb * 1024 * 1024 * 1024);

        try {
            $service = new MikroTikService($router);
            $service->connect();

            // Add hotspot user to router
            $params = [
                'name' => $request->username,
                'password' => $request->password,
            ];

            if ($request->filled('profile')) {
                $params['profile'] = $request->profile;
            }
            if ($dataLimitBytes > 0) {
                $params['limit-bytes-total'] = $dataLimitBytes;
            }

            $service->addHotspotUser($params);
            $service->disconnect();

            // Save to database
            $subscriber = Subscriber::create([
                'router_id' => $router->id,
                'username' => $request->username,
                'password' => $request->password,
                'type' => 'hotspot',
                'status' => 'active',
                'profile' => $request->profile,
                'limit_bytes_total' => $dataLimitBytes,
            ]);

            // Redirect to card page
            return redirect()->route('hotspot.card', $subscriber->id);

        } catch (Exception $e) {
            return back()->withInput()
                ->with('error', 'فشل إضافة المستخدم: ' . $e->getMessage());
        }
    }

    /**
     * Show hotspot user details
     */
    public function show(Subscriber $hotspot)
    {
        $this->authorize('view', $hotspot->router);

        $hotspot->load(['router', 'activeSessions']);

        return view('hotspot.show', compact('hotspot'));
    }

    /**
     * Show edit form
     */
    public function edit(Subscriber $hotspot)
    {
        $this->authorize('view', $hotspot->router);

        return view('hotspot.edit', compact('hotspot'));
    }

    /**
     * Update hotspot user
     */
    public function update(Request $request, Subscriber $hotspot)
    {
        $this->authorize('view', $hotspot->router);

        $request->validate([
            'password' => 'nullable|string|min:3',
            'profile' => 'nullable|string',
            'full_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        try {
            $service = new MikroTikService($hotspot->router);
            $service->connect();

            $params = [];
            if ($request->filled('password')) {
                $params['password'] = $request->password;
            }
            if ($request->filled('profile')) {
                $params['profile'] = $request->profile;
            }

            if (!empty($params)) {
                $service->updateHotspotUser($hotspot->mikrotik_id, $params);
            }

            $service->disconnect();

            // Update database
            $hotspot->update([
                'password' => $request->password ?? $hotspot->password,
                'profile' => $request->profile ?? $hotspot->profile,
                'full_name' => $request->full_name,
                'phone' => $request->phone,
            ]);

            return redirect()->route('hotspot.index')
                ->with('success', 'تم تحديث المستخدم بنجاح');

        } catch (Exception $e) {
            return back()->withInput()
                ->with('error', 'فشل التحديث: ' . $e->getMessage());
        }
    }

    /**
     * Delete hotspot user
     */
    public function destroy(Subscriber $hotspot)
    {
        $this->authorize('view', $hotspot->router);

        try {
            $service = new MikroTikService($hotspot->router);
            $service->connect();
            $service->removeHotspotUser($hotspot->mikrotik_id);
            $service->disconnect();

            $hotspot->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم حذف المستخدم بنجاح'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل الحذف: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disconnect active hotspot session
     */
    public function disconnect(Subscriber $hotspot)
    {
        $this->authorize('view', $hotspot->router);

        try {
            $service = new MikroTikService($hotspot->router);
            $service->connect();
            $service->disconnectHotspotUser($hotspot->username);
            $service->disconnect();

            // Remove active session from database
            ActiveSession::where('subscriber_id', $hotspot->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم قطع الاتصال بنجاح'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل قطع الاتصال: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle hotspot user status (enable/disable)
     */
    public function toggle(Subscriber $hotspot)
    {
        $this->authorize('view', $hotspot->router);

        try {
            $service = new MikroTikService($hotspot->router);
            $service->connect();

            $newStatus = $hotspot->status === 'active' ? 'disabled' : 'active';
            $service->toggleHotspotUser($hotspot->mikrotik_id, $newStatus === 'disabled');
            
            $service->disconnect();

            $hotspot->update(['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => $newStatus === 'active' ? 'تم تفعيل المستخدم' : 'تم تعطيل المستخدم',
                'status' => $newStatus
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت العملية: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync hotspot users from router
     */
    public function sync(Router $router)
    {
        $this->authorize('view', $router);

        try {
            $service = new MikroTikService($router);
            $service->connect();
            
            $result = $service->syncHotspotUsers($router);
            
            $service->disconnect();

            return response()->json([
                'success' => true,
                'message' => "تمت المزامنة! تم مزامنة {$result['synced']} مستخدم جديد، تحديث {$result['updated']} مستخدم",
                'data' => $result
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت المزامنة: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get hotspot profiles from router
     */
    public function profiles(Router $router)
    {
        $this->authorize('view', $router);

        try {
            $service = new MikroTikService($router);
            $service->connect();
            $profilesData = $service->getHotspotProfiles();
            $service->disconnect();

            // Extract profile names only
            $profileNames = array_map(function($p) {
                return $p['name'] ?? null;
            }, $profilesData);
            
            // Remove null values and default-trial
            $profileNames = array_filter($profileNames, function($name) {
                return $name && $name !== 'default-trial';
            });

            return response()->json([
                'success' => true,
                'profiles' => array_values($profileNames)
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'profiles' => []
            ], 500);
        }
    }

    /**
     * Get active hotspot sessions
     */
    public function sessions(Router $router)
    {
        $this->authorize('view', $router);

        try {
            $service = new MikroTikService($router);
            $service->connect();
            $sessions = $service->getHotspotActive();
            $service->disconnect();

            return response()->json([
                'success' => true,
                'sessions' => $sessions
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete fully consumed hotspot cards (reached their data limit)
     * Cards still in use (partially consumed) are NOT deleted
     */
    public function deleteUsed(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id')->toArray()
            : $user->routers()->pluck('routers.id')->toArray();

        $deletedFromRouter = 0;
        $deletedFromDb = 0;
        $failed = 0;
        $skipped = 0;
        $routers = Router::whereIn('id', $routerIds)->get();

        foreach ($routers as $router) {
            try {
                $service = new MikroTikService($router);
                $service->connect();
                
                // Get hotspot users directly from router
                $users = $service->getHotspotUsers();
                $idsToDelete = [];
                $usernamesToDelete = [];
                
                foreach ($users as $u) {
                    if (!isset($u['name']) || !isset($u['.id'])) continue;
                    
                    // Skip system users
                    if (in_array($u['name'], ['default-trial', 'default'])) continue;
                    
                    $bytesIn = intval($u['bytes-in'] ?? 0);
                    $bytesOut = intval($u['bytes-out'] ?? 0);
                    $totalUsed = $bytesIn + $bytesOut;
                    
                    // Get the limit (if set)
                    $limitBytesTotal = intval($u['limit-bytes-total'] ?? 0);
                    
                    // Skip unused cards (bytes = 0)
                    if ($totalUsed == 0) continue;
                    
                    // FULLY CONSUMED = reached or exceeded the limit
                    if ($limitBytesTotal > 0) {
                        if ($totalUsed >= $limitBytesTotal) {
                            $idsToDelete[] = $u['.id'];
                            $usernamesToDelete[] = $u['name'];
                        } else {
                            $skipped++;
                        }
                    } else {
                        $disabled = isset($u['disabled']) && $u['disabled'] === 'true';
                        if ($disabled) {
                            $idsToDelete[] = $u['.id'];
                            $usernamesToDelete[] = $u['name'];
                        } else {
                            $skipped++;
                        }
                    }
                }
                
                // Delete from router
                if (!empty($idsToDelete)) {
                    $service->removeHotspotUsersBatch($idsToDelete);
                    $deletedFromRouter += count($idsToDelete);
                }
                
                $service->disconnect();
                
                // Delete from database: fully consumed cards
                // Cards where bytes used >= limit OR status = disabled with usage
                $dbDeleted = Subscriber::where('router_id', $router->id)
                    ->where('type', 'hotspot')
                    ->where(function($q) {
                        // Has usage (not zero)
                        $q->where(function($inner) {
                            $inner->where('bytes_in', '>', 0)
                                  ->orWhere('bytes_out', '>', 0);
                        });
                    })
                    ->where(function($q) {
                        // Reached limit OR disabled
                        $q->whereRaw('(bytes_in + bytes_out) >= limit_bytes_total AND limit_bytes_total > 0')
                          ->orWhere('status', 'disabled');
                    })
                    ->delete();
                
                $deletedFromDb += $dbDeleted;
                
            } catch (Exception $e) {
                $failed++;
            }
        }

        $total = max($deletedFromRouter, $deletedFromDb);
        $message = "تم حذف {$total} بطاقة مستهلكة بالكامل";
        if ($skipped > 0) $message .= " (تم تجاهل {$skipped} بطاقة جاري استخدامها)";
        if ($failed > 0) $message .= " (فشل الاتصال بـ {$failed} راوتر)";

        return response()->json([
            'success' => true,
            'message' => $message,
            'deleted' => $total,
            'skipped' => $skipped,
            'failed' => $failed
        ]);
    }

    /**
     * Delete all unused hotspot cards (bytes = 0, never used)
     * Deletes from BOTH router AND database
     */
    public function deleteUnused(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id')->toArray()
            : $user->routers()->pluck('routers.id')->toArray();

        $deletedFromRouter = 0;
        $deletedFromDb = 0;
        $failed = 0;
        $routers = Router::whereIn('id', $routerIds)->get();

        foreach ($routers as $router) {
            try {
                $service = new MikroTikService($router);
                $service->connect();
                
                // Get hotspot users directly from router
                $users = $service->getHotspotUsers();
                $idsToDelete = [];
                $routerUsernames = []; // All usernames on router
                $unusedUsernames = []; // Unused usernames on router
                
                foreach ($users as $u) {
                    if (!isset($u['name']) || !isset($u['.id'])) continue;
                    
                    // Skip system users
                    if (in_array($u['name'], ['default-trial', 'default'])) continue;
                    
                    $routerUsernames[] = $u['name'];
                    
                    $bytesIn = intval($u['bytes-in'] ?? 0);
                    $bytesOut = intval($u['bytes-out'] ?? 0);
                    
                    // Unused = NEVER used (zero bytes consumed)
                    if ($bytesIn == 0 && $bytesOut == 0) {
                        $idsToDelete[] = $u['.id'];
                        $unusedUsernames[] = $u['name'];
                    }
                }
                
                // Delete unused from router
                if (!empty($idsToDelete)) {
                    $service->removeHotspotUsersBatch($idsToDelete);
                    $deletedFromRouter += count($idsToDelete);
                }
                
                $service->disconnect();
                
                // Delete from database: unused cards (bytes = 0 or NULL)
                $dbDeleted = Subscriber::where('router_id', $router->id)
                    ->where('type', 'hotspot')
                    ->where(function($q) {
                        $q->whereNull('bytes_in')->orWhere('bytes_in', 0);
                    })
                    ->where(function($q) {
                        $q->whereNull('bytes_out')->orWhere('bytes_out', 0);
                    })
                    ->delete();
                
                $deletedFromDb += $dbDeleted;
                
            } catch (Exception $e) {
                $failed++;
                
                // Even if router fails, still clean database
                $dbDeleted = Subscriber::where('router_id', $router->id)
                    ->where('type', 'hotspot')
                    ->where(function($q) {
                        $q->whereNull('bytes_in')->orWhere('bytes_in', 0);
                    })
                    ->where(function($q) {
                        $q->whereNull('bytes_out')->orWhere('bytes_out', 0);
                    })
                    ->delete();
                
                $deletedFromDb += $dbDeleted;
            }
        }

        $total = max($deletedFromRouter, $deletedFromDb);
        return response()->json([
            'success' => true,
            'message' => "تم حذف {$total} بطاقة غير مستخدمة" . ($failed > 0 ? " (فشل الاتصال بـ {$failed} راوتر)" : ""),
            'deleted' => $total,
            'deleted_router' => $deletedFromRouter,
            'deleted_db' => $deletedFromDb,
            'failed' => $failed
        ]);
    }

    /**
     * Show card generator form
     */
    public function showCardGenerator(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $routers = Router::whereIn('id', $routerIds)->get();
        
        $selectedRouter = $request->filled('router_id') 
            ? Router::findOrFail($request->router_id) 
            : $routers->first();

        return view('hotspot.cards', compact('routers', 'selectedRouter'));
    }

    /**
     * Generate and print hotspot cards
     */
    public function generateCards(Request $request)
    {
        $request->validate([
            'router_id' => 'required|exists:routers,id',
            'count' => 'required|integer|min:1|max:500',
            'profile' => 'nullable|string',
            'username_length' => 'required|integer|min:3|max:12',
            'password_length' => 'required|integer|min:3|max:12',
            'prefix' => 'nullable|string|max:10',
            'card_color' => 'required|string',
            'text_color' => 'required|string',
            'add_to_router' => 'nullable|boolean',
            'data_limit_gb' => 'required|numeric|min:0.1|max:1000',
            // New card customization fields
            'network_name' => 'nullable|string|max:30',
            'phone_number' => 'nullable|string|max:20',
            'show_data_limit' => 'nullable|boolean',
            'font_network' => 'nullable|integer|min:6|max:24',
            'font_data_limit' => 'nullable|integer|min:6|max:28',
            'font_username' => 'nullable|integer|min:6|max:24',
            'font_password' => 'nullable|integer|min:6|max:24',
            'font_phone' => 'nullable|integer|min:4|max:18',
            'font_labels' => 'nullable|integer|min:4|max:14',
            // Position fields
            'pos_network' => 'nullable|string',
            'pos_data_limit' => 'nullable|string',
            'pos_username' => 'nullable|string',
            'pos_password' => 'nullable|string',
            'pos_phone' => 'nullable|string',
        ]);

        $router = Router::findOrFail($request->router_id);
        $this->authorize('view', $router);

        $count = $request->count;
        $usernameLength = $request->username_length;
        $passwordLength = $request->password_length;
        $prefix = $request->prefix ?? '';
        $profile = $request->profile;
        $addToRouter = $request->boolean('add_to_router', false);
        $dataLimitGb = $request->filled('data_limit_gb') ? floatval($request->data_limit_gb) : null;
        $dataLimitBytes = $dataLimitGb ? intval($dataLimitGb * 1024 * 1024 * 1024) : null;

        $cards = [];
        $existingUsernames = [];

        // Get existing usernames if adding to router
        if ($addToRouter) {
            try {
                $service = new MikroTikService($router);
                $service->connect();
                $existingUsers = $service->getHotspotUsers();
                $existingUsernames = array_column($existingUsers, 'name');
            } catch (Exception $e) {
                return redirect()->back()->withErrors(['error' => 'فشل الاتصال بالراوتر: ' . $e->getMessage()]);
            }
        }

        // Generate unique cards
        for ($i = 0; $i < $count; $i++) {
            $attempts = 0;
            do {
                $username = $prefix . $this->generateNumericString($usernameLength);
                $attempts++;
            } while (in_array($username, $existingUsernames) && $attempts < 100);

            if ($attempts >= 100) {
                continue; // Skip if couldn't generate unique username
            }

            $password = $this->generateNumericString($passwordLength);
            
            $cards[] = [
                'username' => $username,
                'password' => $password,
                'profile' => $profile,
                'data_limit_bytes' => $dataLimitBytes,
                'data_limit_gb' => $dataLimitGb,
            ];
            
            $existingUsernames[] = $username;
        }

        // Add cards to router if requested
        $addedCount = 0;
        $failedCount = 0;
        
        if ($addToRouter && !empty($cards)) {
            try {
                if (!isset($service)) {
                    $service = new MikroTikService($router);
                    $service->connect();
                }

                // Prepare batch data for router
                $batchUsers = [];
                foreach ($cards as $card) {
                    $params = [
                        'name' => $card['username'],
                        'password' => $card['password'],
                    ];

                    if (!empty($card['profile'])) {
                        $params['profile'] = $card['profile'];
                    }

                    if (!empty($card['data_limit_bytes'])) {
                        $params['limit-bytes-total'] = $card['data_limit_bytes'];
                    }

                    $batchUsers[] = $params;
                }

                // Add all users in one batch operation
                $result = $service->addHotspotUsersBatch($batchUsers);
                $addedCount = $result['added'];
                $failedCount = $result['failed'];

                // Save all cards to database in batch
                $subscribersData = [];
                $now = now();
                foreach ($cards as $card) {
                    $subscribersData[] = [
                        'router_id' => $router->id,
                        'username' => $card['username'],
                        'password' => $card['password'],
                        'type' => 'hotspot',
                        'status' => 'active',
                        'profile' => $card['profile'],
                        'limit_bytes_total' => $card['data_limit_bytes'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                
                // Insert all at once
                Subscriber::insert($subscribersData);

                $service->disconnect();
            } catch (Exception $e) {
                return redirect()->back()->withErrors(['error' => 'فشل إضافة البطاقات: ' . $e->getMessage()]);
            }
        }

        return view('hotspot.print', [
            'cards' => $cards,
            'router' => $router,
            'cardColor' => $request->card_color,
            'textColor' => $request->text_color,
            'profile' => $profile,
            'addedToRouter' => $addToRouter,
            'addedCount' => $addedCount,
            'failedCount' => $failedCount,
            // New card customization options
            'networkName' => $request->network_name ?? '',
            'networkColor' => $request->network_color ?? '#FFFFFF',
            'phoneNumber' => $request->phone_number ?? '',
            'showDataLimit' => $request->boolean('show_data_limit', true),
            // Font sizes
            'fontNetwork' => $request->font_network ?? 8,
            'fontDataLimit' => $request->font_data_limit ?? 10,
            'fontUsername' => $request->font_username ?? 10,
            'fontPassword' => $request->font_password ?? 10,
            'fontPhone' => $request->font_phone ?? 6,
            'fontLabels' => $request->font_labels ?? 5,
            // Element positions
            'posNetwork' => $request->pos_network ?? '50,5',
            'posDataLimit' => $request->pos_data_limit ?? '50,22',
            'posUsername' => $request->pos_username ?? '30,42',
            'posPassword' => $request->pos_password ?? '70,42',
            'posPhone' => $request->pos_phone ?? '50,80',
        ]);
    }

    /**
     * Generate random numeric string
     */
    private function generateNumericString(int $length): string
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= random_int(0, 9);
        }
        return $result;
    }
}
