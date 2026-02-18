<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\ActivityLog;
use App\Services\MikroTikService;
use App\Services\WireGuardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Exception;

class RouterController extends Controller
{
    protected $wgService;
    
    public function __construct()
    {
        $this->wgService = new WireGuardService();
    }
    
    /**
     * Display a listing of routers
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = $user->isSuperAdmin() 
            ? Router::query() 
            : $user->routers();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('identity', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $routers = $query->withCount(['subscribers', 'activeSessions'])
            ->orderBy('name')
            ->paginate(15);

        // Stats for header
        $stats = [
            'total' => Router::count(),
            'online' => Router::where('status', 'online')->count(),
            'offline' => Router::where('status', 'offline')->count(),
        ];

        return view('routers.index', compact('routers', 'stats'));
    }

    /**
     * Get the next available WinBox port
     */
    private function getNextAvailablePort(): int
    {
        $basePort = 8310;
        $maxPort = Router::max('public_port');
        
        if (!$maxPort || $maxPort < $basePort) {
            return $basePort;
        }
        
        // Find first gap in used ports starting from 8310
        $usedPorts = Router::whereNotNull('public_port')
            ->where('public_port', '>=', $basePort)
            ->pluck('public_port')
            ->sort()
            ->values()
            ->toArray();
        
        for ($port = $basePort; $port <= $maxPort + 1; $port++) {
            if (!in_array($port, $usedPorts)) {
                return $port;
            }
        }
        
        return $maxPort + 1;
    }

    /**
     * Show the form for creating a new router
     */
    public function create()
    {
        $this->authorize('create', Router::class);
        
        // Get next available IP for WireGuard
        $nextWgIP = $this->wgService->getNextAvailableIP();
        
        // Get next available WinBox port
        $nextPort = $this->getNextAvailablePort();
        
        return view('routers.create', compact('nextWgIP', 'nextPort'));
    }

    /**
     * Store a newly created router
     */
    public function store(Request $request)
    {
        $this->authorize('create', Router::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'api_port' => 'required|integer|min:1|max:65535',
            'api_username' => 'required|string|max:255',
            'api_password' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'public_ip' => 'nullable|string|max:255',
            'public_port' => 'nullable|integer|min:1|max:65535',
            'wg_enabled' => 'nullable|boolean',
            'wg_private_key' => 'nullable|string',
            'wg_public_key' => 'nullable|string',
            'wg_client_ip' => 'nullable|ip',
        ]);

        $validated['wg_enabled'] = $request->boolean('wg_enabled');
        
        // تعيين البورت تلقائياً إذا لم يتم إدخاله
        if (empty($validated['public_port'])) {
            $validated['public_port'] = $this->getNextAvailablePort();
        }
        
        // إذا كان WireGuard مفعل، استخدم wg_client_ip كـ ip_address للاتصال
        if ($validated['wg_enabled'] && !empty($validated['wg_client_ip'])) {
            $validated['ip_address'] = $validated['wg_client_ip'];
        }
        
        // Remove wg_private_key and wg_public_key from creation - will be added later
        unset($validated['wg_private_key']);
        unset($validated['wg_public_key']);

        $router = Router::create($validated);

        // WireGuard peer will be added after user provides public key from router
        // (RouterOS generates its own keys, we can't pre-generate them)

        // Test connection (use WireGuard IP if enabled)
        $connectionIP = $router->wg_enabled && $router->wg_client_ip 
            ? $router->wg_client_ip 
            : $router->ip_address;
            
        try {
            $service = new MikroTikService($router);
            $service->connect($connectionIP);
            $service->updateRouterInfo();
            $service->disconnect();
            
            session()->flash('success', 'تم إضافة الراوتر وتم الاتصال بنجاح');
        } catch (Exception $e) {
            $router->update(['status' => 'offline']);
            session()->flash('warning', 'تم إضافة الراوتر لكن فشل الاتصال: ' . $e->getMessage());
        }

        // ربط المستخدم الحالي بالراوتر الجديد في router_admins
        $currentUser = Auth::user();
        if (!$currentUser->isSuperAdmin()) {
            $router->admins()->attach($currentUser->id, [
                'role' => $currentUser->role ?? 'admin',
                'can_add_users' => true,
                'can_delete_users' => true,
                'can_edit_users' => true,
                'can_view_reports' => true,
                'can_manage_hotspot' => true,
                'can_manage_ppp' => true,
            ]);
        }

        ActivityLog::log('router.created', "إضافة راوتر جديد: {$router->name}", null, $router->id);

        return redirect()->route('routers.show', $router);
    }

    /**
     * Display the specified router
     */
    public function show(Router $router)
    {
        $this->authorize('view', $router);

        $router->loadCount(['subscribers', 'activeSessions']);
        $router->load(['servicePlans', 'syncLogs' => function ($q) {
            $q->latest()->take(5);
        }]);

        $activeSessions = $router->activeSessions()
            ->with('subscriber')
            ->orderBy('started_at', 'desc')
            ->paginate(10);

        return view('routers.show', compact('router', 'activeSessions'));
    }

    /**
     * Show the form for editing the router
     */
    public function edit(Router $router)
    {
        $this->authorize('update', $router);
        
        return view('routers.edit', compact('router'));
    }

    /**
     * Update the specified router
     */
    public function update(Request $request, Router $router)
    {
        $this->authorize('update', $router);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'api_port' => 'required|integer|min:1|max:65535',
            'api_username' => 'required|string|max:255',
            'api_password' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'public_ip' => 'nullable|string|max:255',
            'public_port' => 'nullable|integer|min:1|max:65535',
            'price_per_gb' => 'nullable|numeric|min:0',
            'shamcash_qr' => 'nullable|image|max:2048',
            'brand_name' => 'nullable|string|max:100',
        ]);

        if (empty($validated['api_password'])) {
            unset($validated['api_password']);
        }

        // Handle ShamCash QR image upload
        if ($request->hasFile('shamcash_qr')) {
            // Delete old image if exists
            if ($router->shamcash_qr) {
                Storage::disk('public')->delete($router->shamcash_qr);
            }
            // Store new image
            $path = $request->file('shamcash_qr')->store('shamcash-qr', 'public');
            $validated['shamcash_qr'] = $path;
        } else {
            unset($validated['shamcash_qr']);
        }

        $router->update($validated);

        ActivityLog::log('router.updated', "تحديث الراوتر: {$router->name}", null, $router->id);

        return redirect()->route('routers.show', $router)
            ->with('success', 'تم تحديث بيانات الراوتر بنجاح');
    }

    /**
     * Delete ShamCash QR image
     */
    public function deleteShamcashQR(Router $router)
    {
        $this->authorize('update', $router);

        if ($router->shamcash_qr) {
            Storage::disk('public')->delete($router->shamcash_qr);
            $router->update(['shamcash_qr' => null]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Remove the specified router
     */
    public function destroy(Router $router)
    {
        $this->authorize('delete', $router);

        $routerName = $router->name;
        $router->delete();

        ActivityLog::log('router.deleted', "حذف الراوتر: {$routerName}");

        return redirect()->route('routers.index')
            ->with('success', 'تم حذف الراوتر بنجاح');
    }

    /**
     * Test connection to router
     */
    public function testConnection(Router $router)
    {
        $this->authorize('view', $router);

        try {
            $service = new MikroTikService($router);
            $service->connect();
            $info = $service->getSystemInfo();
            $service->disconnect();

            return response()->json([
                'success' => true,
                'message' => 'تم الاتصال بنجاح',
                'info' => $info,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل الاتصال: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Sync router data
     */
    public function sync(Router $router)
    {
        $this->authorize('update', $router);

        try {
            $service = new MikroTikService($router);
            $service->connect();
            
            $service->updateRouterInfo();
            $profilesResult = $service->syncProfiles();
            $usersResult = $service->syncPPPSecrets();
            $sessionsResult = $service->syncActiveSessions();
            
            $service->disconnect();

            ActivityLog::log('router.synced', "مزامنة الراوتر: {$router->name}", null, $router->id);

            return response()->json([
                'success' => true,
                'message' => 'تمت المزامنة بنجاح',
                'profiles' => $profilesResult,
                'users' => $usersResult,
                'sessions' => $sessionsResult,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشلت المزامنة: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update router sync settings (Toggle interval & WhatsApp type)
     */
    public function updateSyncSettings(Request $request, Router $router)
    {
        $this->authorize('update', $router);

        $request->validate([
            'sync_interval' => 'required|integer|min:60|max:1440',
            'whatsapp_type' => 'nullable|string|in:regular,business',
        ]);

        $router->update([
            'sync_interval' => $request->sync_interval,
            'whatsapp_type' => $request->whatsapp_type ?? 'regular',
        ]);

        $intervalText = match((int)$request->sync_interval) {
            60 => 'كل ساعة',
            120 => 'كل ساعتين',
            180 => 'كل 3 ساعات',
            240 => 'كل 4 ساعات',
            360 => 'كل 6 ساعات',
            480 => 'كل 8 ساعات',
            720 => 'كل 12 ساعة',
            1440 => 'كل 24 ساعة',
            default => "كل {$request->sync_interval} دقيقة"
        };

        return response()->json([
            'success' => true,
            'message' => "تم حفظ الإعدادات بنجاح",
        ]);
    }

    /**
     * Get router system resources (AJAX)
     */
    public function resources(Router $router)
    {
        $this->authorize('view', $router);

        try {
            $service = new MikroTikService($router);
            $service->connect();
            $info = $service->getSystemInfo();
            $service->disconnect();

            return response()->json([
                'success' => true,
                'data' => [
                    'cpu_load' => $info['cpu_load'] ?? 0,
                    'memory_used' => isset($info['total_memory'], $info['free_memory']) 
                        ? round((($info['total_memory'] - $info['free_memory']) / $info['total_memory']) * 100, 1) 
                        : 0,
                    'hdd_used' => isset($info['total_hdd'], $info['free_hdd']) && $info['total_hdd'] > 0
                        ? round((($info['total_hdd'] - $info['free_hdd']) / $info['total_hdd']) * 100, 1)
                        : 0,
                    'uptime' => $info['uptime'] ?? 0,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
    
    /**
     * Generate WireGuard script for router
     */
    public function generateWireGuardScript(Router $router)
    {
        $this->authorize('view', $router);
        
        if (!$router->wg_enabled) {
            return response()->json([
                'success' => false,
                'message' => 'WireGuard غير مفعل لهذا الراوتر',
            ], 422);
        }
        
        $fullScript = $this->wgService->generateRouterOSScript($router);
        $oneLiner = $this->wgService->generateOneLinerScript($router);
        
        return response()->json([
            'success' => true,
            'script' => $fullScript,
            'one_liner' => $oneLiner,
            'router_name' => $router->name,
            'wg_ip' => $router->wg_client_ip,
        ]);
    }
    
    /**
     * Test WireGuard connection
     */
    public function testWireGuard(Router $router)
    {
        $this->authorize('view', $router);
        
        $result = $this->wgService->testConnection($router);
        
        if ($result['success']) {
            $router->update(['wg_last_handshake' => now()]);
        }
        
        return response()->json($result);
    }
    
    /**
     * Setup WireGuard for existing router
     */
    public function setupWireGuard(Router $router)
    {
        $this->authorize('update', $router);
        
        try {
            // Generate new keys if not exist
            if (!$router->wg_private_key) {
                $keys = $this->wgService->generateKeyPair();
                $router->wg_private_key = $keys['private_key'];
                $router->wg_public_key = $keys['public_key'];
            }
            
            // Assign IP if not exist
            if (!$router->wg_client_ip) {
                $router->wg_client_ip = $this->wgService->getNextAvailableIP();
            }
            
            $router->wg_enabled = true;
            $router->save();
            
            // Add peer to server
            $this->wgService->addPeerToServer($router);
            
            ActivityLog::log('router.wireguard_setup', "إعداد WireGuard للراوتر: {$router->name}", null, $router->id);
            
            return response()->json([
                'success' => true,
                'message' => 'تم إعداد WireGuard بنجاح',
                'wg_ip' => $router->wg_client_ip,
                'full_script' => $this->wgService->generateRouterOSScript($router),
                'one_liner' => $this->wgService->generateOneLinerScript($router),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إعداد WireGuard: ' . $e->getMessage(),
            ], 422);
        }
    }
    
    /**
     * Regenerate WireGuard keys
     */
    public function regenerateWireGuardKeys(Router $router)
    {
        $this->authorize('update', $router);
        
        try {
            // Remove old peer
            if ($router->wg_public_key) {
                $this->wgService->removePeerFromServer($router);
            }
            
            // Generate new keys
            $keys = $this->wgService->generateKeyPair();
            $router->wg_private_key = $keys['private_key'];
            $router->wg_public_key = $keys['public_key'];
            $router->save();
            
            // Add new peer
            $this->wgService->addPeerToServer($router);
            
            return response()->json([
                'success' => true,
                'message' => 'تم إعادة توليد المفاتيح بنجاح',
                'full_script' => $this->wgService->generateRouterOSScript($router),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إعادة توليد المفاتيح: ' . $e->getMessage(),
            ], 422);
        }
    }
    
    /**
     * Open/sync WinBox port forwarding on the server
     */
    public function openPort(Router $router)
    {
        $this->authorize('update', $router);
        
        if (!$router->wg_client_ip || !$router->public_port) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تعيين WireGuard IP و بورت WinBox أولاً',
            ], 422);
        }
        
        try {
            $port = (int) $router->public_port;
            $ip = $router->wg_client_ip;
            
            // Check if rule already exists
            exec("sudo /usr/sbin/iptables -t nat -L PREROUTING -n 2>/dev/null | grep 'dpt:{$port}'", $checkOutput);
            $ruleExists = !empty($checkOutput);
            
            if ($ruleExists) {
                // Remove old rule first (in case target changed)
                exec("sudo /usr/sbin/iptables -t nat -D PREROUTING -p tcp --dport {$port} -j DNAT --to-destination {$ip}:8291 2>/dev/null");
                exec("sudo /usr/sbin/iptables -t nat -D POSTROUTING -p tcp -d {$ip} --dport 8291 -j MASQUERADE 2>/dev/null");
            }
            
            // Add DNAT rule: external_port -> router_wg_ip:8291
            $dnatCmd = "sudo /usr/sbin/iptables -t nat -A PREROUTING -p tcp --dport {$port} -j DNAT --to-destination {$ip}:8291 2>&1";
            exec($dnatCmd, $dnatOutput, $dnatReturn);
            
            if ($dnatReturn !== 0) {
                throw new Exception('فشل إضافة قاعدة DNAT: ' . implode(' ', $dnatOutput));
            }
            
            // Add MASQUERADE rule
            $masqCmd = "sudo /usr/sbin/iptables -t nat -A POSTROUTING -p tcp -d {$ip} --dport 8291 -j MASQUERADE 2>&1";
            exec($masqCmd, $masqOutput, $masqReturn);
            
            if ($masqReturn !== 0) {
                throw new Exception('فشل إضافة قاعدة MASQUERADE: ' . implode(' ', $masqOutput));
            }
            
            // Add FORWARD rule
            exec("sudo /usr/sbin/iptables -C FORWARD -p tcp -d {$ip} --dport 8291 -j ACCEPT 2>/dev/null", $fwdCheck, $fwdCheckReturn);
            if ($fwdCheckReturn !== 0) {
                exec("sudo /usr/sbin/iptables -A FORWARD -p tcp -d {$ip} --dport 8291 -j ACCEPT 2>/dev/null");
            }
            
            // Save rules for persistence
            exec('sudo /usr/sbin/iptables-save | sudo tee /etc/iptables/rules.v4 > /dev/null 2>&1');
            
            \Illuminate\Support\Facades\Log::info("Port forwarding opened for router {$router->id}: port {$port} -> {$ip}:8291");
            ActivityLog::log('router.port_opened', "فتح بورت WinBox {$port} للراوتر: {$router->name}", null, $router->id);
            
            return response()->json([
                'success' => true,
                'message' => "تم فتح البورت {$port} بنجاح! يمكنك الآن الدخول عبر WinBox على {$router->public_ip}:{$port}",
                'winbox_address' => "{$router->public_ip}:{$port}",
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل فتح البورت: ' . $e->getMessage(),
            ], 422);
        }
    }
    
    /**
     * Check if WinBox port is open on the server
     */
    public function checkPort(Router $router)
    {
        $this->authorize('view', $router);
        
        if (!$router->public_port) {
            return response()->json(['open' => false, 'message' => 'لم يتم تعيين بورت WinBox']);
        }
        
        $port = (int) $router->public_port;
        exec("sudo /usr/sbin/iptables -t nat -L PREROUTING -n 2>/dev/null | grep 'dpt:{$port}'", $output);
        
        return response()->json([
            'open' => !empty($output),
            'port' => $port,
            'address' => "{$router->public_ip}:{$port}",
            'message' => !empty($output) ? 'البورت مفتوح' : 'البورت مغلق',
        ]);
    }

    /**
     * Save WireGuard public key from router
     */
    public function saveWireGuardPublicKey(Request $request, Router $router)
    {
        $this->authorize('update', $router);
        
        $validated = $request->validate([
            'public_key' => 'required|string|size:44',
        ]);
        
        $publicKey = trim($validated['public_key']);
        
        // التحقق من أن المفتاح ليس مفتاح السيرفر
        $serverPublicKey = config('wireguard.server_public_key');
        if ($publicKey === $serverPublicKey) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ: هذا هو المفتاح العام للسيرفر وليس الراوتر! يجب نسخ المفتاح من الراوتر بعد تنفيذ السكريبت.',
            ], 422);
        }
        
        // التحقق من أن المفتاح غير مستخدم من راوتر آخر
        $existingRouter = Router::where('wg_public_key', $publicKey)
            ->where('id', '!=', $router->id)
            ->first();
        if ($existingRouter) {
            return response()->json([
                'success' => false,
                'message' => "خطأ: هذا المفتاح مستخدم بالفعل في راوتر آخر ({$existingRouter->name})",
            ], 422);
        }
        
        try {
            // Remove old peer if exists
            if ($router->wg_public_key && $router->wg_public_key !== $publicKey) {
                $this->wgService->removePeerFromServer($router);
            }
            
            // Update public key
            $router->wg_public_key = $publicKey;
            $router->save();
            
            // Add peer to server with new public key
            $result = $this->wgService->addPeerToServer($router);
            
            if (!$result) {
                throw new Exception('فشل إضافة الراوتر للسيرفر');
            }
            
            ActivityLog::log('router.wireguard_key_updated', "تحديث مفتاح WireGuard للراوتر: {$router->name}", null, $router->id, Router::class, $router->id);
            
            // محاولة الاتصال التلقائي وتفعيل الراوتر بعد حفظ المفتاح
            $autoActivated = false;
            try {
                // انتظار قصير حتى يتم تأسيس النفق
                sleep(2);
                
                $service = new MikroTikService($router);
                $service->connect();
                $service->updateRouterInfo();
                $service->disconnect();
                
                $router->update([
                    'status' => 'online',
                    'last_seen' => now(),
                    'connection_errors' => 0,
                ]);
                $autoActivated = true;
            } catch (Exception $e) {
                // لا مشكلة إذا فشل الاتصال التلقائي - يمكن المحاولة لاحقاً
                \Illuminate\Support\Facades\Log::info("Auto-connect after WG key save failed for router {$router->id}: " . $e->getMessage());
            }
            
            $message = $autoActivated 
                ? 'تم حفظ المفتاح وتفعيل الراوتر بنجاح! الراوتر متصل الآن.'
                : 'تم حفظ المفتاح العام وإضافة الراوتر للسيرفر بنجاح! جرب الآن اختبار الاتصال.';
            
            return response()->json([
                'success' => true,
                'message' => $message,
                'auto_activated' => $autoActivated,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل حفظ المفتاح: ' . $e->getMessage(),
            ], 422);
        }
    }
}
