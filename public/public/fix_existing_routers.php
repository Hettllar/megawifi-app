<?php
/**
 * Fix existing routers:
 * 1. Set public_ip = 'megawifi.site' for routers missing it (required for WinBox)
 * 2. Create router_admins entries for admin users who don't have them
 * 3. Re-attempt WireGuard connection for routers with wg_public_key but status offline
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

use App\Models\Router;
use App\Models\User;
use App\Services\MikroTikService;
use App\Services\WireGuardService;

echo "═══════════════════════════════════════════════════\n";
echo "  Fix Existing Routers - WinBox & WireGuard\n";
echo "═══════════════════════════════════════════════════\n\n";

$routers = Router::all();
echo "📊 Total routers: " . $routers->count() . "\n\n";

// ─── 1. Fix missing public_ip (WinBox access) ───
echo "━━━ Step 1: Fix WinBox access (public_ip) ━━━\n";
$fixedPublicIp = 0;
foreach ($routers as $router) {
    if (empty($router->public_ip)) {
        $router->update(['public_ip' => 'megawifi.site']);
        echo "  ✅ Router #{$router->id} ({$router->name}): Set public_ip = megawifi.site\n";
        $fixedPublicIp++;
    } else {
        echo "  ✓ Router #{$router->id} ({$router->name}): public_ip = {$router->public_ip}\n";
    }
    
    if (empty($router->public_port)) {
        echo "  ⚠️  Router #{$router->id} ({$router->name}): Missing public_port (WinBox port)!\n";
        echo "     → يجب تعيين بورت WinBox يدوياً من صفحة التعديل\n";
    }
}
echo "  Fixed: {$fixedPublicIp} routers\n\n";

// ─── 2. Fix missing router_admins entries ───
echo "━━━ Step 2: Fix router_admins (user access) ━━━\n";
$admins = User::whereIn('role', ['admin', 'super_admin'])->get();
$fixedAdmins = 0;

foreach ($routers as $router) {
    $existingAdminIds = $router->admins()->pluck('users.id')->toArray();
    
    if (empty($existingAdminIds)) {
        // Router has no admins - assign all admin/super_admin users
        foreach ($admins as $admin) {
            if (!$admin->isSuperAdmin()) { // super_admin sees all anyway
                $router->admins()->attach($admin->id, [
                    'role' => $admin->role,
                    'can_add_users' => true,
                    'can_delete_users' => true,
                    'can_edit_users' => true,
                    'can_view_reports' => true,
                    'can_manage_hotspot' => true,
                    'can_manage_ppp' => true,
                ]);
                echo "  ✅ Router #{$router->id} ({$router->name}): Linked to user {$admin->name} ({$admin->role})\n";
                $fixedAdmins++;
            }
        }
    } else {
        echo "  ✓ Router #{$router->id} ({$router->name}): Has " . count($existingAdminIds) . " admin(s)\n";
    }
}
echo "  Fixed: {$fixedAdmins} admin links\n\n";

// ─── 3. Fix WireGuard peers on server ───
echo "━━━ Step 3: Re-sync WireGuard peers ━━━\n";
$wgService = new WireGuardService();
$fixedWg = 0;

foreach ($routers as $router) {
    if (!$router->wg_enabled || !$router->wg_client_ip) {
        echo "  ⏭️  Router #{$router->id} ({$router->name}): WireGuard not enabled, skipping\n";
        continue;
    }
    
    if (empty($router->wg_public_key)) {
        echo "  ⚠️  Router #{$router->id} ({$router->name}): Missing WG public key - needs manual setup\n";
        echo "     → افتح صفحة الراوتر ونفذ السكريبت وألصق المفتاح العام\n";
        continue;
    }
    
    // Re-add peer to server (idempotent - doesn't hurt if already exists)
    $result = $wgService->addPeerToServer($router);
    if ($result) {
        echo "  ✅ Router #{$router->id} ({$router->name}): WG peer synced (IP: {$router->wg_client_ip})\n";
        $fixedWg++;
    } else {
        echo "  ❌ Router #{$router->id} ({$router->name}): Failed to sync WG peer\n";
    }
}
echo "  Synced: {$fixedWg} WG peers\n\n";

// ─── 4. Re-test connections ───
echo "━━━ Step 4: Test connections ━━━\n";
$connected = 0;
$failed = 0;

foreach ($routers as $router) {
    if (!$router->wg_enabled || !$router->wg_public_key || !$router->wg_client_ip) {
        continue;
    }
    
    try {
        $service = new MikroTikService($router);
        $service->connect();
        $service->updateRouterInfo();
        $service->disconnect();
        
        $router->update([
            'status' => 'online',
            'last_seen' => now(),
            'connection_errors' => 0,
        ]);
        echo "  ✅ Router #{$router->id} ({$router->name}): ONLINE ✓\n";
        $connected++;
    } catch (Exception $e) {
        $router->update(['status' => 'offline']);
        echo "  ❌ Router #{$router->id} ({$router->name}): OFFLINE - {$e->getMessage()}\n";
        $failed++;
    }
}

echo "\n═══════════════════════════════════════════════════\n";
echo "  Summary\n";
echo "═══════════════════════════════════════════════════\n";
echo "  Total routers:     " . $routers->count() . "\n";
echo "  Fixed public_ip:   {$fixedPublicIp}\n";
echo "  Fixed admin links: {$fixedAdmins}\n";
echo "  WG peers synced:   {$fixedWg}\n";
echo "  Connected:         {$connected}\n";
echo "  Failed:            {$failed}\n";
echo "═══════════════════════════════════════════════════\n";

// Show routers missing public_port
$missingPort = Router::whereNull('public_port')->orWhere('public_port', 0)->get();
if ($missingPort->count() > 0) {
    echo "\n⚠️  الراوترات التالية تنقصها بورت WinBox:\n";
    foreach ($missingPort as $r) {
        echo "  - #{$r->id} {$r->name} → عدّل من: /routers/{$r->id}/edit\n";
    }
    echo "  💡 يجب تعيين بورت WinBox (Port Forwarding) لكل راوتر يدوياً\n";
}

// Clear caches
echo "\n🔄 Clearing caches...\n";
exec('cd /var/www/megawifi && php artisan optimize:clear 2>&1', $output);
echo implode("\n", $output) . "\n";
exec('systemctl restart php8.4-fpm 2>&1');
echo "✅ Done!\n";
