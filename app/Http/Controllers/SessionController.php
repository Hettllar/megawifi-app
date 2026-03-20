<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\ActiveSession;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class SessionController extends Controller
{
    /**
     * Display a listing of active sessions
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $query = ActiveSession::whereIn('router_id', $routerIds)
            ->with(['router', 'subscriber']);

        // Filter by router
        if ($request->filled('router_id') && $routerIds->contains($request->router_id)) {
            $query->where('router_id', $request->router_id);
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('mac_address', 'like', "%{$search}%");
            });
        }

        $sessions = $query->orderBy('started_at', 'desc')->paginate(20);

        $routers = Router::whereIn('id', $routerIds)->get();

        return view('sessions.index', compact('sessions', 'routers'));
    }

    /**
     * Refresh sessions from all accessible routers
     */
    public function refresh(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        if ($request->filled('router_id') && $routerIds->contains($request->router_id)) {
            $routers = Router::where('id', $request->router_id)->get();
        } else {
            $routers = Router::whereIn('id', $routerIds)->where('status', 'online')->get();
        }

        $totalSynced = 0;
        $errors = [];

        foreach ($routers as $router) {
            try {
                $service = new MikroTikService($router);
                $service->connect();
                $result = $service->syncActiveSessions();
                $service->disconnect();
                
                $totalSynced += $result['synced'];
            } catch (Exception $e) {
                $errors[] = "{$router->name}: " . $e->getMessage();
            }
        }

        if (count($errors) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'تمت المزامنة مع بعض الأخطاء',
                'synced' => $totalSynced,
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'تمت مزامنة الجلسات بنجاح',
            'synced' => $totalSynced,
        ]);
    }

    /**
     * Disconnect a specific session
     */
    public function disconnect(ActiveSession $session)
    {
        $this->authorize('view', $session->router);

        try {
            $service = new MikroTikService($session->router);
            $service->connect();

            if ($session->type === 'ppp') {
                $service->disconnectPPPUser($session->session_id);
            } else if ($session->type === 'hotspot') {
                $service->disconnectHotspotUser($session->session_id);
            }

            $service->disconnect();

            $session->delete();

            return response()->json([
                'success' => true,
                'message' => 'تم قطع الاتصال بنجاح',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل قطع الاتصال: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get sessions statistics
     */
    public function stats(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $query = ActiveSession::whereIn('router_id', $routerIds);

        if ($request->filled('router_id') && $routerIds->contains($request->router_id)) {
            $query->where('router_id', $request->router_id);
        }

        $stats = [
            'total' => $query->count(),
            'ppp' => (clone $query)->where('type', 'ppp')->count(),
            'hotspot' => (clone $query)->where('type', 'hotspot')->count(),
            'usermanager' => (clone $query)->where('type', 'usermanager')->count(),
            'total_bytes_in' => (clone $query)->sum('bytes_in'),
            'total_bytes_out' => (clone $query)->sum('bytes_out'),
        ];

        return response()->json($stats);
    }
}
