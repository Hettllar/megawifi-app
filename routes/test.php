<?php
use Illuminate\Support\Facades\Route;
Route::get('/test-um', function() {
    $user = auth()->user();
    if (!$user) return 'Not logged in';
    
    $routerIds = $user->isSuperAdmin()
        ? \App\Models\Router::pluck('id')->toArray()
        : $user->routers()->pluck('routers.id')->toArray();
    
    $routers = \App\Models\Router::whereIn('id', $routerIds)->get();
    $subscribers = \App\Models\Subscriber::whereIn('router_id', $routerIds)
        ->where('type', 'usermanager')
        ->with('router')
        ->take(10)
        ->get();
    
    return view('usermanager.test', compact('subscribers', 'routers'));
})->middleware(['web', 'auth']);
