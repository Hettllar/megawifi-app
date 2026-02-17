<?php
/**
 * Open WinBox ports 8302 and 8304 on the server
 * Run: sudo php open_ports.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle($request = Illuminate\Http\Request::capture());

use App\Models\Router;

echo "═══════════════════════════════════════\n";
echo "  Opening WinBox Ports 8302 & 8304\n";
echo "═══════════════════════════════════════\n\n";

// Enable IP forwarding
exec('sysctl -w net.ipv4.ip_forward=1 2>&1');
echo "✅ IP forwarding enabled\n\n";

// Find routers with these ports
$ports = [8302, 8304];

foreach ($ports as $port) {
    $router = Router::where('public_port', $port)->first();
    
    if (!$router) {
        echo "⚠️  Port {$port}: No router found with this port in DB\n";
        echo "   Listing all routers:\n";
        Router::all()->each(function ($r) {
            echo "   - #{$r->id} {$r->name}: port={$r->public_port}, wg_ip={$r->wg_client_ip}\n";
        });
        continue;
    }
    
    $ip = $router->wg_client_ip;
    echo "━━━ Port {$port} → Router: {$router->name} (WG: {$ip}) ━━━\n";
    
    // Remove old rules if exist
    exec("iptables -t nat -D PREROUTING -p tcp --dport {$port} -j DNAT --to-destination {$ip}:8291 2>/dev/null");
    exec("iptables -t nat -D POSTROUTING -p tcp -d {$ip} --dport 8291 -j MASQUERADE 2>/dev/null");
    
    // Add DNAT
    exec("iptables -t nat -A PREROUTING -p tcp --dport {$port} -j DNAT --to-destination {$ip}:8291 2>&1", $out1, $ret1);
    echo "  DNAT: " . ($ret1 === 0 ? "✅" : "❌ " . implode(' ', $out1)) . "\n";
    
    // Add MASQUERADE
    exec("iptables -t nat -A POSTROUTING -p tcp -d {$ip} --dport 8291 -j MASQUERADE 2>&1", $out2, $ret2);
    echo "  MASQUERADE: " . ($ret2 === 0 ? "✅" : "❌ " . implode(' ', $out2)) . "\n";
    
    // Add FORWARD
    exec("iptables -C FORWARD -p tcp -d {$ip} --dport 8291 -j ACCEPT 2>/dev/null", $fwdCheck, $fwdRet);
    if ($fwdRet !== 0) {
        exec("iptables -A FORWARD -p tcp -d {$ip} --dport 8291 -j ACCEPT 2>&1", $out3, $ret3);
        echo "  FORWARD: " . ($ret3 === 0 ? "✅" : "❌ " . implode(' ', $out3)) . "\n";
    } else {
        echo "  FORWARD: ✅ (exists)\n";
    }
    
    // Ping test
    exec("ping -c 1 -W 2 {$ip} 2>&1", $pingOut, $pingRet);
    echo "  Ping {$ip}: " . ($pingRet === 0 ? "✅ reachable" : "❌ NOT reachable") . "\n";
    
    echo "  → WinBox: megawifi.site:{$port}\n\n";
    
    // Clear output arrays
    $out1 = $out2 = $out3 = $pingOut = [];
}

// Save rules
exec('iptables-save > /etc/iptables/rules.v4 2>/dev/null');
exec('netfilter-persistent save 2>/dev/null');
echo "✅ Rules saved\n\n";

// Verify
echo "━━━ Verification ━━━\n";
$rules = shell_exec('iptables -t nat -L PREROUTING -n 2>&1');
echo $rules . "\n";

echo "═══════════════════════════════════════\n";
echo "  Done! Try WinBox now:\n";
echo "  megawifi.site:8302\n";
echo "  megawifi.site:8304\n";
echo "═══════════════════════════════════════\n";
