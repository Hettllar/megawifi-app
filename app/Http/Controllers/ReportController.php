<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\Invoice;
use App\Models\ActiveSession;
use App\Models\TrafficHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Show reports dashboard
     */
    public function index()
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $routers = Router::whereIn('id', $routerIds)->get();

        return view('reports.index', compact('routers'));
    }

    /**
     * Subscribers report
     */
    public function subscribers(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $query = Subscriber::whereIn('router_id', $routerIds)
            ->with(['router', 'servicePlan']);

        // Date filter
        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        // Router filter
        if ($request->filled('router_id')) {
            $query->where('router_id', $request->router_id);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $subscribers = $query->orderBy('created_at', 'desc')->get();

        $stats = [
            'total' => $subscribers->count(),
            'active' => $subscribers->where('status', 'active')->count(),
            'disabled' => $subscribers->where('status', 'disabled')->count(),
            'expired' => $subscribers->where('status', 'expired')->count(),
            'ppp' => $subscribers->where('type', 'ppp')->count(),
            'hotspot' => $subscribers->where('type', 'hotspot')->count(),
        ];

        $routers = Router::whereIn('id', $routerIds)->get();

        return view('reports.subscribers', compact('subscribers', 'stats', 'routers'));
    }

    /**
     * Revenue report
     */
    public function revenue(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $fromDate = $request->filled('from_date') 
            ? Carbon::parse($request->from_date) 
            : Carbon::now()->startOfMonth();
        
        $toDate = $request->filled('to_date') 
            ? Carbon::parse($request->to_date) 
            : Carbon::now();

        $query = Invoice::whereIn('router_id', $routerIds)
            ->whereBetween('created_at', [$fromDate, $toDate]);

        // Router filter
        if ($request->filled('router_id')) {
            $query->where('router_id', $request->router_id);
        }

        $invoices = $query->with(['subscriber', 'router'])->orderBy('created_at', 'desc')->get();

        $stats = [
            'total_revenue' => $invoices->where('status', 'paid')->sum('amount'),
            'pending' => $invoices->where('status', 'pending')->sum('amount'),
            'invoices_count' => $invoices->count(),
            'paid_count' => $invoices->where('status', 'paid')->count(),
        ];

        // Revenue by router
        $revenueByRouter = $invoices->where('status', 'paid')
            ->groupBy('router_id')
            ->map(function ($items, $routerId) {
                $router = Router::find($routerId);
                return [
                    'router' => $router ? $router->name : 'غير معروف',
                    'amount' => $items->sum('amount'),
                    'count' => $items->count(),
                ];
            })->values();

        // Daily revenue
        $dailyRevenue = $invoices->where('status', 'paid')
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            })
            ->map(function ($items, $date) {
                return [
                    'date' => $date,
                    'amount' => $items->sum('amount'),
                ];
            })->values();

        $routers = Router::whereIn('id', $routerIds)->get();

        return view('reports.revenue', compact('invoices', 'stats', 'revenueByRouter', 'dailyRevenue', 'routers', 'fromDate', 'toDate'));
    }

    /**
     * Sessions/Usage report
     */
    public function sessions(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $fromDate = $request->filled('from_date') 
            ? Carbon::parse($request->from_date) 
            : Carbon::now()->subDays(7);
        
        $toDate = $request->filled('to_date') 
            ? Carbon::parse($request->to_date) 
            : Carbon::now();

        // Current active sessions
        $activeSessions = ActiveSession::whereIn('router_id', $routerIds)
            ->with(['subscriber', 'router'])
            ->get();

        // Traffic history
        $trafficQuery = TrafficHistory::whereIn('router_id', $routerIds)
            ->whereBetween('recorded_at', [$fromDate, $toDate]);

        if ($request->filled('router_id')) {
            $trafficQuery->where('router_id', $request->router_id);
        }

        $traffic = $trafficQuery->orderBy('recorded_at', 'desc')->get();

        $stats = [
            'active_now' => $activeSessions->count(),
            'total_download' => $traffic->sum('bytes_out'),
            'total_upload' => $traffic->sum('bytes_in'),
            'peak_users' => $traffic->max('active_users') ?? 0,
        ];

        // Traffic by date
        $trafficByDate = $traffic->groupBy(function ($item) {
            return $item->recorded_at->format('Y-m-d');
        })->map(function ($items, $date) {
            return [
                'date' => $date,
                'download' => $items->sum('bytes_out'),
                'upload' => $items->sum('bytes_in'),
                'users' => $items->avg('active_users'),
            ];
        })->values();

        $routers = Router::whereIn('id', $routerIds)->get();

        return view('reports.sessions', compact('activeSessions', 'stats', 'trafficByDate', 'routers', 'fromDate', 'toDate'));
    }

    /**
     * Expiring subscribers report
     */
    public function expiring(Request $request)
    {
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $days = $request->input('days', 7);

        $subscribers = Subscriber::whereIn('router_id', $routerIds)
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', Carbon::now()->addDays($days))
            ->where('expiration_date', '>=', Carbon::now())
            ->where('status', 'active')
            ->with(['router', 'servicePlan'])
            ->orderBy('expiration_date')
            ->get();

        $routers = Router::whereIn('id', $routerIds)->get();

        return view('reports.expiring', compact('subscribers', 'routers', 'days'));
    }

    /**
     * Export report to CSV
     */
    public function export(Request $request)
    {
        $type = $request->input('type', 'subscribers');
        $user = Auth::user();
        
        $routerIds = $user->isSuperAdmin() 
            ? Router::pluck('id') 
            : $user->routers()->pluck('routers.id');

        $filename = $type . '_' . date('Y-m-d_His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        if ($type === 'subscribers') {
            $subscribers = Subscriber::whereIn('router_id', $routerIds)
                ->with(['router', 'servicePlan'])
                ->get();

            $callback = function() use ($subscribers) {
                $file = fopen('php://output', 'w');
                // BOM for UTF-8
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                
                fputcsv($file, ['اسم المستخدم', 'الاسم الكامل', 'الهاتف', 'الراوتر', 'الباقة', 'الحالة', 'تاريخ الانتهاء']);
                
                foreach ($subscribers as $sub) {
                    fputcsv($file, [
                        $sub->username,
                        $sub->full_name,
                        $sub->phone,
                        $sub->router->name ?? '',
                        $sub->servicePlan->name ?? $sub->profile,
                        $sub->status,
                        $sub->expiration_date ? $sub->expiration_date->format('Y-m-d') : '',
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        return back()->with('error', 'نوع التقرير غير معروف');
    }
}
