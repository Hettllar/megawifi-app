<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRouterAccess
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission = 'view'): Response
    {
        $router = $request->route('router');
        
        if (!$router) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user) {
            abort(403, 'غير مصرح لك بالوصول');
        }

        // Super admins have full access
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // Check if user has access to this router
        if (!$user->hasAccessToRouter($router)) {
            abort(403, 'ليس لديك صلاحية للوصول إلى هذا الراوتر');
        }

        // Check specific permission
        if ($permission !== 'view' && !$user->canManageRouter($router)) {
            abort(403, 'ليس لديك صلاحية لإدارة هذا الراوتر');
        }

        return $next($request);
    }
}
