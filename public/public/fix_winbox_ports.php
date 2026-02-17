<?php
/**
 * Diagnose and fix WinBox port forwarding on the server
 * Run: php fix_winbox_ports.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

use App\Models\Router;

echo "═══════════════════════════════════════════════════\n";
echo "  WinBox Port Forwarding Diagnostic & Fix\n";
echo "═══════════════════════════════════════════════════\n\n";

// Step 1: Check system requirements
echo "━━━ Step 1: System Check ━━━\n";

// Check if iptables is available
$iptablesPath = trim(shell_exec('which iptables 2>/dev/null') ?? '');
if (empty($iptablesPath)) {
    $iptablesPath = '/usr/sbin/iptables';
}
echo "  iptables path: {$iptablesPath}\n";

// Check if running as root or has sudo
$whoami = trim(shell_exec('whoami'));
echo "  Running as: {$whoami}\n";

// Check IP forwarding
$ipForward = trim(shell_exec('cat /proc/sys/net/ipv4/ip_forward 2>/dev/null') ?? '0');
echo "  IP forwarding: " . ($ipForward === '1' ? '✅ Enabled' : '❌ Disabled') . "\n";

if ($ipForward !== '1') {
    echo "  → Enabling IP forwarding...\n";
    exec('sudo sysctl -w net.ipv4.ip_forward=1 2>&1', $sysOut);
    echo "    " . implode("\n    ", $sysOut) . "\n";
    
    // Make persistent
    exec('grep -q "net.ipv4.ip_forward" /etc/sysctl.conf && sudo sed -i "s/#*net.ipv4.ip_forward.*/net.ipv4.ip_forward=1/" /etc/sysctl.conf || echo "net.ipv4.ip_forward=1" | sudo tee -a /etc/sysctl.conf', $persistOut);
    echo "  → Made persistent in /etc/sysctl.conf\n";
}

// Check WireGuard interface
echo "\n━━━ Step 2: WireGuard Check ━━━\n";
$wgStatus = shell_exec('sudo wg show wg0 2>&1') ?? '';
if (strpos($wgStatus, 'Unable') !== false || empty(trim($wgStatus))) {
    echo "  ❌ WireGuard interface wg0 is DOWN!\n";
    echo "  → Starting WireGuard...\n";
    exec('sudo wg-quick up wg0 2>&1', $wgOut);
    echo "    " . implode("\n    ", $wgOut) . "\n";
} else {
    echo "  ✅ WireGuard wg0 is UP\n";
    
    // Count peers
    preg_match_all('/peer:/', $wgStatus, $peerMatches);
    echo "  Peers: " . count($peerMatches[0]) . "\n";
}

// Step 3: Check existing iptables rules
echo "\n━━━ Step 3: Current iptables NAT rules ━━━\n";
$natRules = shell_exec('sudo iptables -t nat -L PREROUTING -n --line-numbers 2>&1') ?? '';
echo $natRules . "\n";

$forwardRules = shell_exec('sudo iptables -L FORWARD -n --line-numbers 2>&1') ?? '';
echo "FORWARD rules:\n{$forwardRules}\n";

// Step 4: Get all routers and configure ports
echo "\n━━━ Step 4: Configure Port Forwarding for All Routers ━━━\n";
$routers = Router::where('is_active', true)
    ->whereNotNull('wg_client_ip')
    ->whereNotNull('public_port')
    ->where('public_port', '>', 0)
    ->get();

if ($routers->isEmpty()) {
    echo "  ⚠️ No routers with WG IP and public_port found!\n";
    
    // Show all routers for debugging
    echo "\n  All routers in DB:\n";
    $allRouters = Router::all();
    foreach ($allRouters as $r) {
        echo "    #{$r->id} {$r->name}: wg_ip={$r->wg_client_ip}, public_port={$r->public_port}, public_ip={$r->public_ip}, wg_enabled={$r->wg_enabled}\n";
    }
} else {
    echo "  Found {$routers->count()} router(s) to configure\n\n";
}

foreach ($routers as $router) {
    $port = (int) $router->public_port;
    $ip = $router->wg_client_ip;
    
    echo "  ┌─ Router: {$router->name} (ID: {$router->id})\n";
    echo "  │  WG IP: {$ip}\n";
    echo "  │  WinBox Port: {$port}\n";
    echo "  │  Public: {$router->public_ip}:{$port}\n";
    
    // Check if DNAT rule exists
    $checkDnat = shell_exec("sudo iptables -t nat -L PREROUTING -n 2>/dev/null | grep 'dpt:{$port}'") ?? '';
    
    if (!empty(trim($checkDnat))) {
        echo "  │  DNAT: ✅ exists\n";
    } else {
        echo "  │  DNAT: ❌ missing → Adding...\n";
        exec("sudo iptables -t nat -A PREROUTING -p tcp --dport {$port} -j DNAT --to-destination {$ip}:8291 2>&1", $dnatOut, $dnatRet);
        if ($dnatRet === 0) {
            echo "  │  DNAT: ✅ Added successfully\n";
        } else {
            echo "  │  DNAT: ❌ Failed: " . implode(' ', $dnatOut) . "\n";
        }
    }
    
    // Check MASQUERADE rule
    $checkMasq = shell_exec("sudo iptables -t nat -L POSTROUTING -n 2>/dev/null | grep '{$ip}.*dpt:8291'") ?? '';
    
    if (!empty(trim($checkMasq))) {
        echo "  │  MASQUERADE: ✅ exists\n";
    } else {
        echo "  │  MASQUERADE: ❌ missing → Adding...\n";
        exec("sudo iptables -t nat -A POSTROUTING -p tcp -d {$ip} --dport 8291 -j MASQUERADE 2>&1", $masqOut, $masqRet);
        if ($masqRet === 0) {
            echo "  │  MASQUERADE: ✅ Added\n";
        } else {
            echo "  │  MASQUERADE: ❌ Failed: " . implode(' ', $masqOut) . "\n";
        }
    }
    
    // Check FORWARD rule
    exec("sudo iptables -C FORWARD -p tcp -d {$ip} --dport 8291 -j ACCEPT 2>/dev/null", $fwdOut, $fwdRet);
    if ($fwdRet === 0) {
        echo "  │  FORWARD: ✅ exists\n";
    } else {
        echo "  │  FORWARD: ❌ missing → Adding...\n";
        exec("sudo iptables -A FORWARD -p tcp -d {$ip} --dport 8291 -j ACCEPT 2>&1", $fwdAddOut, $fwdAddRet);
        if ($fwdAddRet === 0) {
            echo "  │  FORWARD: ✅ Added\n";
        } else {
            echo "  │  FORWARD: ❌ Failed: " . implode(' ', $fwdAddOut) . "\n";
        }
    }
    
    // Ping test to WG IP
    exec("ping -c 1 -W 2 {$ip} 2>&1", $pingOut, $pingRet);
    if ($pingRet === 0) {
        echo "  │  Ping {$ip}: ✅ reachable\n";
    } else {
        echo "  │  Ping {$ip}: ❌ NOT reachable (WireGuard tunnel may be down)\n";
    }
    
    echo "  └─ WinBox: {$router->public_ip}:{$port}\n\n";
}

// Step 5: Save iptables rules
echo "━━━ Step 5: Save iptables rules ━━━\n";
exec('sudo iptables-save | sudo tee /etc/iptables/rules.v4 > /dev/null 2>&1', $saveOut, $saveRet);
if ($saveRet === 0) {
    echo "  ✅ Rules saved to /etc/iptables/rules.v4\n";
} else {
    // Try alternative save method
    exec('sudo netfilter-persistent save 2>&1', $altSaveOut, $altSaveRet);
    if ($altSaveRet === 0) {
        echo "  ✅ Rules saved via netfilter-persistent\n";
    } else {
        echo "  ⚠️ Could not save rules persistently. Try: sudo apt install iptables-persistent\n";
    }
}

// Step 6: Final verification
echo "\n━━━ Step 6: Final Verification ━━━\n";
$finalNat = shell_exec('sudo iptables -t nat -L PREROUTING -n 2>&1') ?? '';
echo "NAT PREROUTING rules:\n{$finalNat}\n";

$finalPostrouting = shell_exec('sudo iptables -t nat -L POSTROUTING -n 2>&1') ?? '';
echo "NAT POSTROUTING rules:\n{$finalPostrouting}\n";

echo "═══════════════════════════════════════════════════\n";
echo "  Done! Try connecting via WinBox now.\n";
echo "═══════════════════════════════════════════════════\n";

// Summary
echo "\nWinBox Connection Info:\n";
foreach ($routers as $router) {
    echo "  {$router->name}: {$router->public_ip}:{$router->public_port}\n";
}
echo "\n";
