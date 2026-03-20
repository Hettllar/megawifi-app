
    public function deleteAllNotifications(Request $request)
    {
        $user = $request->user();
        
        $count = \App\Models\Notification::where('user_id', $user->id)->count();
        \App\Models\Notification::where('user_id', $user->id)->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'تم حذف جميع الإشعارات',
            'deleted_count' => $count
        ]);
    }

    // ==================== ZEROTIER MANAGEMENT ====================

    /**
     * Setup ZeroTier on a router
     */
    public function setupRouterZerotier(Request $request, $routerId)
    {
        $user = $request->user();
        $router = \App\Models\Router::where('id', $routerId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'network_id' => 'required|string|size:16',
            'member_id' => 'nullable|string',
        ]);

        $router->update([
            'connection_type' => 'zerotier',
            'zt_network_id' => $validated['network_id'],
            'zt_member_id' => $validated['member_id'] ?? null,
            'zt_enabled' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ZeroTier configured successfully',
            'router' => [
                'id' => $router->id,
                'name' => $router->name,
                'connection_type' => $router->connection_type,
                'zt_network_id' => $router->zt_network_id,
                'zt_enabled' => $router->zt_enabled,
                'connection_method' => $router->connection_method,
                'effective_ip' => $router->effective_ip,
            ]
        ]);
    }

    /**
     * Get ZeroTier status for a router
     */
    public function routerZerotierStatus(Request $request, $routerId)
    {
        $user = $request->user();
        $router = \App\Models\Router::where('id', $routerId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'zerotier' => [
                'enabled' => (bool) $router->zt_enabled,
                'network_id' => $router->zt_network_id,
                'member_id' => $router->zt_member_id,
                'ip' => $router->zt_ip,
                'connected' => $router->zt_connected,
                'last_seen' => $router->zt_last_seen?->toISOString(),
            ],
            'connection' => [
                'type' => $router->connection_type,
                'method' => $router->connection_method,
                'effective_ip' => $router->effective_ip,
            ]
        ]);
    }

    /**
     * Disable ZeroTier on a router
     */
    public function disableRouterZerotier(Request $request, $routerId)
    {
        $user = $request->user();
        $router = \App\Models\Router::where('id', $routerId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $router->update([
            'zt_enabled' => false,
            'connection_type' => $router->wg_enabled ? 'wireguard' : 'direct',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ZeroTier disabled',
            'connection_method' => $router->fresh()->connection_method,
            'effective_ip' => $router->fresh()->effective_ip,
        ]);
    }

    /**
     * Update ZeroTier heartbeat from router
     */
    public function updateZerotierHeartbeat(Request $request, $routerId)
    {
        $router = \App\Models\Router::findOrFail($routerId);

        $validated = $request->validate([
            'zt_ip' => 'nullable|ip',
            'member_id' => 'nullable|string',
        ]);

        $router->update([
            'zt_ip' => $validated['zt_ip'] ?? $router->zt_ip,
            'zt_member_id' => $validated['member_id'] ?? $router->zt_member_id,
            'zt_last_seen' => now(),
        ]);

        return response()->json(['success' => true]);
    }

}
