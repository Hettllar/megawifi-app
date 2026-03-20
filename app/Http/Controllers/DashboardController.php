<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\ActiveSession;
use App\Models\Invoice;
use App\Models\TrafficHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Display the main dashboard
     */
    public function index()
    {
        $user = Auth::user();

        // Get user's routers based on role
        if ($user->isSuperAdmin()) {
            $routerIds = Router::pluck('id');
            $routers = Router::withCount(['subscribers', 'activeSessions'])->get();
        } else {
            $routerIds = $user->routers()->pluck('routers.id');
            $routers = $user->routers()->withCount(['subscribers', 'activeSessions'])->get();
        }

        // Statistics
        $stats = [
            'total_routers' => $routers->count(),
            'online_routers' => $routers->where('status', 'online')->count(),
            'offline_routers' => $routers->where('status', 'offline')->count(),
            'total_subscribers' => Subscriber::whereIn('router_id', $routerIds)->count(),
            'active_subscribers' => Subscriber::whereIn('router_id', $routerIds)->where('status', 'active')->count(),
            'expired_subscribers' => Subscriber::whereIn('router_id', $routerIds)->where('status', 'expired')->count(),
            'online_users' => ActiveSession::whereIn('router_id', $routerIds)->count(),
            'ppp_users' => ActiveSession::whereIn('router_id', $routerIds)->where('type', 'ppp')->count(),
            'hotspot_users' => ActiveSession::whereIn('router_id', $routerIds)->where('type', 'hotspot')->count(),
        ];

        // Recent activity
        $recentSessions = ActiveSession::whereIn('router_id', $routerIds)
            ->with(['router', 'subscriber'])
            ->orderBy('started_at', 'desc')
            ->take(10)
            ->get();

        // Subscribers expiring soon
        $expiringSubscribers = Subscriber::whereIn('router_id', $routerIds)
            ->where('status', 'active')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', Carbon::now()->addDays(7))
            ->where('expiration_date', '>=', Carbon::now())
            ->with('router')
            ->orderBy('expiration_date')
            ->take(10)
            ->get();

        // Monthly revenue (if invoicing is used)
        $monthlyRevenue = Invoice::whereIn('router_id', $routerIds)
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->where('status', 'paid')
            ->sum('amount');

        // Traffic summary - Calculate from subscribers total usage
        // bytes_in = Upload (from subscriber to network)
        // bytes_out = Download (from network to subscriber)
        $totalTraffic = Subscriber::whereIn('router_id', $routerIds)
            ->selectRaw('SUM(COALESCE(bytes_in, 0)) as total_upload, SUM(COALESCE(bytes_out, 0)) as total_download, SUM(COALESCE(total_bytes, 0)) as total_bytes')
            ->first();
        
        // Also get traffic from active sessions (current live traffic)
        $liveTraffic = ActiveSession::whereIn('router_id', $routerIds)
            ->selectRaw('SUM(COALESCE(bytes_in, 0)) as live_upload, SUM(COALESCE(bytes_out, 0)) as live_download')
            ->first();

        $todayTraffic = (object) [
            'total_in' => ($totalTraffic->total_upload ?? 0) + ($liveTraffic->live_upload ?? 0),
            'total_out' => ($totalTraffic->total_download ?? 0) + ($liveTraffic->live_download ?? 0),
            'total_bytes' => $totalTraffic->total_bytes ?? 0,
        ];

        return view('dashboard', compact(
            'routers',
            'stats',
            'recentSessions',
            'expiringSubscribers',
            'monthlyRevenue',
            'todayTraffic'
        ));
    }

    /**
     * API endpoint for real-time dashboard data
     */
    public function apiStats(Request $request)
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            $routerIds = Router::pluck('id');
        } else {
            $routerIds = $user->routers()->pluck('routers.id');
        }

        return response()->json([
            'online_users' => ActiveSession::whereIn('router_id', $routerIds)->count(),
            'ppp_users' => ActiveSession::whereIn('router_id', $routerIds)->where('type', 'ppp')->count(),
            'hotspot_users' => ActiveSession::whereIn('router_id', $routerIds)->where('type', 'hotspot')->count(),
            'online_routers' => Router::whereIn('id', $routerIds)->where('status', 'online')->count(),
            'offline_routers' => Router::whereIn('id', $routerIds)->where('status', 'offline')->count(),
        ]);
    }
}
