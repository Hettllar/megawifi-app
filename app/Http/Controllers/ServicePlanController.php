<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\ServicePlan;
use App\Models\ActivityLog;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class ServicePlanController extends Controller
{
    /**
     * Display a listing of service plans
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $query = ServicePlan::whereIn('router_id', $routerIds)->with('router');

        // Filter by router
        if ($request->filled('router_id') && $routerIds->contains($request->router_id)) {
            $query->where('router_id', $request->router_id);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $plans = $query->withCount('subscribers')
            ->orderBy('name')
            ->paginate(20);

        $routers = Router::whereIn('id', $routerIds)->get();

        return view('plans.index', compact('plans', 'routers'));
    }

    /**
     * Show the form for creating a new service plan
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $routers = Router::whereIn('id', $routerIds)->get();

        return view('plans.create', compact('routers'));
    }

    /**
     * Store a newly created service plan
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'router_id' => 'required|exists:routers,id',
            'name' => 'required|string|max:255',
            'type' => 'required|in:ppp,hotspot,usermanager',
            'download_speed' => 'required|string|max:50',
            'upload_speed' => 'required|string|max:50',
            'burst_download' => 'nullable|string|max:50',
            'burst_upload' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0',
            'validity_days' => 'nullable|integer|min:1',
            'data_limit' => 'nullable|integer|min:0',
            'session_timeout' => 'nullable|integer|min:0',
            'idle_timeout' => 'nullable|integer|min:0',
            'shared_users' => 'boolean',
            'shared_users_count' => 'nullable|integer|min:1',
            'local_address' => 'nullable|string|max:255',
            'remote_address' => 'nullable|string|max:255',
            'dns_server' => 'nullable|string|max:255',
            'address_pool' => 'nullable|string|max:255',
            'sync_to_router' => 'boolean',
        ]);

        $router = Router::findOrFail($validated['router_id']);
        $this->authorize('view', $router);

        // Build rate limit string
        $rateLimit = $validated['download_speed'] . '/' . $validated['upload_speed'];
        if (!empty($validated['burst_download']) && !empty($validated['burst_upload'])) {
            $rateLimit .= ' ' . $validated['burst_download'] . '/' . $validated['burst_upload'];
        }

        // Sync to MikroTik if requested
        $mikrotikProfileName = null;
        if ($request->boolean('sync_to_router')) {
            try {
                $service = new MikroTikService($router);
                $service->connect();

                if ($validated['type'] === 'ppp') {
                    $result = $service->addPPPProfile([
                        'name' => $validated['name'],
                        'rate-limit' => $rateLimit,
                        'local-address' => $validated['local_address'] ?? '',
                        'remote-address' => $validated['remote_address'] ?? '',
                        'dns-server' => $validated['dns_server'] ?? '',
                    ]);
                } else {
                    $result = $service->addHotspotProfile([
                        'name' => $validated['name'],
                        'rate-limit' => $rateLimit,
                        'session-timeout' => $validated['session_timeout'] ? $validated['session_timeout'] . 's' : '',
                        'idle-timeout' => $validated['idle_timeout'] ? $validated['idle_timeout'] . 's' : '',
                        'shared-users' => $validated['shared_users_count'] ?? 1,
                        'address-pool' => $validated['address_pool'] ?? '',
                    ]);
                }

                $service->disconnect();
                $mikrotikProfileName = $validated['name'];
            } catch (Exception $e) {
                return back()->withErrors(['mikrotik' => 'فشل الإضافة إلى الراوتر: ' . $e->getMessage()])->withInput();
            }
        }

        // Save to database
        $plan = ServicePlan::create([
            'router_id' => $router->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'mikrotik_profile_name' => $mikrotikProfileName ?? $validated['name'],
            'rate_limit' => $rateLimit,
            'download_speed' => $validated['download_speed'],
            'upload_speed' => $validated['upload_speed'],
            'burst_download' => $validated['burst_download'] ?? null,
            'burst_upload' => $validated['burst_upload'] ?? null,
            'price' => $validated['price'] ?? 0,
            'validity_days' => $validated['validity_days'] ?? null,
            'data_limit' => $validated['data_limit'] ?? null,
            'session_timeout' => $validated['session_timeout'] ?? null,
            'idle_timeout' => $validated['idle_timeout'] ?? null,
            'shared_users' => $validated['shared_users'] ?? false,
            'shared_users_count' => $validated['shared_users_count'] ?? 1,
            'local_address' => $validated['local_address'] ?? null,
            'remote_address' => $validated['remote_address'] ?? null,
            'dns_server' => $validated['dns_server'] ?? null,
            'address_pool' => $validated['address_pool'] ?? null,
        ]);

        ActivityLog::log('plan.created', "إضافة خطة جديدة: {$plan->name}", null, $plan->router_id, ServicePlan::class, $plan->id);

        return redirect()->route('plans.index')
            ->with('success', 'تم إضافة الخطة بنجاح');
    }

    /**
     * Display the specified service plan
     */
    public function show(ServicePlan $plan)
    {
        $this->authorize('view', $plan->router);

        $plan->load('router');
        $subscribers = $plan->subscribers()->paginate(10);

        return view('plans.show', compact('plan', 'subscribers'));
    }

    /**
     * Show the form for editing the service plan
     */
    public function edit(ServicePlan $plan)
    {
        $this->authorize('view', $plan->router);

        return view('plans.edit', compact('plan'));
    }

    /**
     * Update the specified service plan
     */
    public function update(Request $request, ServicePlan $plan)
    {
        $this->authorize('view', $plan->router);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'download_speed' => 'required|string|max:50',
            'upload_speed' => 'required|string|max:50',
            'burst_download' => 'nullable|string|max:50',
            'burst_upload' => 'nullable|string|max:50',
            'price' => 'nullable|numeric|min:0',
            'validity_days' => 'nullable|integer|min:1',
            'data_limit' => 'nullable|integer|min:0',
            'session_timeout' => 'nullable|integer|min:0',
            'idle_timeout' => 'nullable|integer|min:0',
            'shared_users' => 'boolean',
            'shared_users_count' => 'nullable|integer|min:1',
            'local_address' => 'nullable|string|max:255',
            'remote_address' => 'nullable|string|max:255',
            'dns_server' => 'nullable|string|max:255',
            'address_pool' => 'nullable|string|max:255',
        ]);

        // Build rate limit string
        $rateLimit = $validated['download_speed'] . '/' . $validated['upload_speed'];
        if (!empty($validated['burst_download']) && !empty($validated['burst_upload'])) {
            $rateLimit .= ' ' . $validated['burst_download'] . '/' . $validated['burst_upload'];
        }

        $plan->update([
            'name' => $validated['name'],
            'rate_limit' => $rateLimit,
            'download_speed' => $validated['download_speed'],
            'upload_speed' => $validated['upload_speed'],
            'burst_download' => $validated['burst_download'] ?? null,
            'burst_upload' => $validated['burst_upload'] ?? null,
            'price' => $validated['price'] ?? 0,
            'validity_days' => $validated['validity_days'] ?? null,
            'data_limit' => $validated['data_limit'] ?? null,
            'session_timeout' => $validated['session_timeout'] ?? null,
            'idle_timeout' => $validated['idle_timeout'] ?? null,
            'shared_users' => $validated['shared_users'] ?? false,
            'shared_users_count' => $validated['shared_users_count'] ?? 1,
            'local_address' => $validated['local_address'] ?? null,
            'remote_address' => $validated['remote_address'] ?? null,
            'dns_server' => $validated['dns_server'] ?? null,
            'address_pool' => $validated['address_pool'] ?? null,
        ]);

        ActivityLog::log('plan.updated', "تحديث الخطة: {$plan->name}", null, $plan->router_id, ServicePlan::class, $plan->id);

        return redirect()->route('plans.show', $plan)
            ->with('success', 'تم تحديث الخطة بنجاح');
    }

    /**
     * Remove the specified service plan
     */
    public function destroy(ServicePlan $plan)
    {
        $this->authorize('view', $plan->router);

        if ($plan->subscribers()->exists()) {
            return back()->withErrors(['error' => 'لا يمكن حذف خطة مرتبطة بمشتركين']);
        }

        $planName = $plan->name;
        $plan->delete();

        ActivityLog::log('plan.deleted', "حذف الخطة: {$planName}");

        return redirect()->route('plans.index')
            ->with('success', 'تم حذف الخطة بنجاح');
    }

    /**
     * Get plans for a specific router (AJAX)
     */
    public function byRouter(Router $router)
    {
        $this->authorize('view', $router);

        $plans = $router->servicePlans()->get(['id', 'name', 'type', 'rate_limit', 'price']);

        return response()->json($plans);
    }
}
