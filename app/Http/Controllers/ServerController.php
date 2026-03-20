<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class ServerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    // ================================================================
    // قائمة السيرفرات
    // ================================================================
    public function index(Request $request)
    {
        $query = Server::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('hostname', 'like', "%{$request->search}%")
                  ->orWhere('location', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $servers = $query->orderBy('name')->paginate(15)->withQueryString();

        $stats = [
            'total'   => Server::count(),
            'online'  => Server::where('status', 'online')->count(),
            'offline' => Server::where('status', 'offline')->count(),
        ];

        return view('servers.index', compact('servers', 'stats'));
    }

    // ================================================================
    // نموذج الإضافة
    // ================================================================
    public function create()
    {
        $nextPort = $this->getNextAvailablePort();
        $defaultHost = config('app.ssh_host', 'syrianew.live');
        return view('servers.create', compact('nextPort', 'defaultHost'));
    }

    // ================================================================
    // حفظ السيرفر الجديد
    // ================================================================
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'hostname'     => 'required|string|max:255',
            'ssh_port'     => 'required|integer|min:1|max:65535',
            'ssh_username' => 'required|string|max:255',
            'ssh_password' => 'required|string|max:255',
            'location'     => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'public_host'  => 'nullable|string|max:255',
            'public_port'  => 'nullable|integer|min:1024|max:65535',
        ]);

        // تشفير كلمة المرور
        $validated['ssh_password'] = Crypt::encryptString($validated['ssh_password']);

        // تعيين البورت تلقائياً
        if (empty($validated['public_port'])) {
            $validated['public_port'] = $this->getNextAvailablePort();
        }

        if (empty($validated['public_host'])) {
            $validated['public_host'] = config('app.ssh_host', 'syrianew.live');
        }

        $server = Server::create($validated);

        return redirect()->route('servers.show', $server)
            ->with('success', 'تم إضافة السيرفر بنجاح');
    }

    // ================================================================
    // عرض تفاصيل السيرفر
    // ================================================================
    public function show(Server $server)
    {
        return view('servers.show', compact('server'));
    }

    // ================================================================
    // نموذج التعديل
    // ================================================================
    public function edit(Server $server)
    {
        return view('servers.edit', compact('server'));
    }

    // ================================================================
    // حفظ التعديلات
    // ================================================================
    public function update(Request $request, Server $server)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'hostname'     => 'required|string|max:255',
            'ssh_port'     => 'required|integer|min:1|max:65535',
            'ssh_username' => 'required|string|max:255',
            'ssh_password' => 'nullable|string|max:255',
            'location'     => 'nullable|string|max:255',
            'description'  => 'nullable|string',
            'public_host'  => 'nullable|string|max:255',
            'public_port'  => 'nullable|integer|min:1024|max:65535',
        ]);

        // تحديث كلمة المرور فقط إذا تم إدخالها
        if (!empty($validated['ssh_password'])) {
            $validated['ssh_password'] = Crypt::encryptString($validated['ssh_password']);
        } else {
            unset($validated['ssh_password']);
        }

        $server->update($validated);

        return redirect()->route('servers.show', $server)
            ->with('success', 'تم تحديث السيرفر بنجاح');
    }

    // ================================================================
    // حذف السيرفر
    // ================================================================
    public function destroy(Server $server)
    {
        // إزالة قاعدة iptables إذا وُجدت
        if ($server->public_port) {
            $this->removePortForwarding($server);
        }

        $server->delete();

        return redirect()->route('servers.index')
            ->with('success', 'تم حذف السيرفر');
    }

    // ================================================================
    // اختبار الاتصال
    // ================================================================
    public function testConnection(Server $server)
    {
        try {
            $password = $server->decrypted_password;
            $host = $server->hostname;
            $port = $server->ssh_port;
            $user = $server->ssh_username;

            // اختبار الاتصال عبر SSH باستخدام timeout
            $cmd = "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 -o PasswordAuthentication=yes "
                 . "-p {$port} {$user}@{$host} 'uname -a; uptime; free -h | head -2' 2>&1";

            // نستخدم sshpass إذا كانت متاحة
            if (!empty($password)) {
                $escaped = escapeshellarg($password);
                $cmd = "sshpass -p {$escaped} ssh -o StrictHostKeyChecking=no -o ConnectTimeout=5 "
                     . "-p {$port} {$user}@{$host} 'uname -a; uptime; free -h | head -2' 2>&1";
            }

            exec($cmd, $output, $returnCode);

            if ($returnCode === 0) {
                $server->update([
                    'status'          => 'online',
                    'last_seen'       => now(),
                    'connection_errors' => 0,
                    'last_error'      => null,
                    'last_checked_at' => now(),
                    'os_info'         => $output[0] ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'الاتصال ناجح',
                    'info'    => implode("\n", $output),
                ]);
            } else {
                throw new \Exception(implode("\n", $output));
            }
        } catch (\Exception $e) {
            $server->update([
                'status'            => 'offline',
                'connection_errors' => $server->connection_errors + 1,
                'last_error'        => $e->getMessage(),
                'last_error_at'     => now(),
                'last_checked_at'   => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'فشل الاتصال: ' . $e->getMessage(),
            ], 422);
        }
    }

    // ================================================================
    // فتح بورت SSH الخارجي (iptables DNAT)
    // ================================================================
    public function openPort(Server $server)
    {
        if (!$server->public_port) {
            return response()->json(['success' => false, 'message' => 'لا يوجد بورت معين'], 422);
        }

        try {
            $port = (int) $server->public_port;
            $targetHost = $server->hostname;
            $targetPort = (int) $server->ssh_port;

            // حذف القاعدة القديمة إن وجدت
            exec("sudo /usr/sbin/iptables -t nat -L PREROUTING -n 2>/dev/null | grep 'dpt:{$port}'",
                $checkOutput);

            if (!empty($checkOutput)) {
                exec("sudo /usr/sbin/iptables -t nat -D PREROUTING -p tcp --dport {$port} -j DNAT --to-destination {$targetHost}:{$targetPort} 2>/dev/null");
                exec("sudo /usr/sbin/iptables -t nat -D POSTROUTING -p tcp -d {$targetHost} --dport {$targetPort} -j MASQUERADE 2>/dev/null");
            }

            // إضافة قاعدة DNAT الجديدة
            $dnatCmd = "sudo /usr/sbin/iptables -t nat -A PREROUTING -p tcp --dport {$port} -j DNAT --to-destination {$targetHost}:{$targetPort} 2>&1";
            exec($dnatCmd, $dnatOut, $dnatReturn);

            if ($dnatReturn !== 0) {
                throw new \Exception('فشل إضافة قاعدة DNAT: ' . implode(' ', $dnatOut));
            }

            // MASQUERADE للـ forward
            $masqCmd = "sudo /usr/sbin/iptables -t nat -A POSTROUTING -p tcp -d {$targetHost} --dport {$targetPort} -j MASQUERADE 2>&1";
            exec($masqCmd, $masqOut, $masqReturn);

            // السماح بالـ FORWARD
            exec("sudo /usr/sbin/iptables -C FORWARD -p tcp -d {$targetHost} --dport {$targetPort} -j ACCEPT 2>/dev/null", $fwdCheck, $fwdCheckReturn);
            if ($fwdCheckReturn !== 0) {
                exec("sudo /usr/sbin/iptables -A FORWARD -p tcp -d {$targetHost} --dport {$targetPort} -j ACCEPT 2>/dev/null");
            }

            // حفظ القواعد
            exec('sudo /usr/sbin/iptables-save | sudo tee /etc/iptables/rules.v4 > /dev/null 2>&1');

            return response()->json([
                'success' => true,
                'message' => "تم فتح البورت {$port} بنجاح → {$targetHost}:{$targetPort}",
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ================================================================
    // فحص حالة البورت
    // ================================================================
    public function checkPort(Server $server)
    {
        if (!$server->public_port) {
            return response()->json(['open' => false, 'message' => 'لا يوجد بورت']);
        }

        $port = (int) $server->public_port;
        exec("sudo /usr/sbin/iptables -t nat -L PREROUTING -n 2>/dev/null | grep 'dpt:{$port}'", $output);

        return response()->json([
            'open'    => !empty($output),
            'message' => !empty($output) ? 'البورت مفتوح' : 'البورت مغلق',
            'port'    => $port,
        ]);
    }

    // ================================================================
    // Helpers
    // ================================================================
    private function getNextAvailablePort(): int
    {
        $basePort = 22100;
        $maxPort = Server::max('public_port');

        if (!$maxPort || $maxPort < $basePort) {
            return $basePort;
        }

        $usedPorts = Server::whereNotNull('public_port')
            ->where('public_port', '>=', $basePort)
            ->pluck('public_port')
            ->sort()
            ->values()
            ->toArray();

        for ($port = $basePort; $port <= $maxPort + 1; $port++) {
            if (!in_array($port, $usedPorts)) {
                return $port;
            }
        }

        return $maxPort + 1;
    }

    private function removePortForwarding(Server $server): void
    {
        $port = (int) $server->public_port;
        $targetHost = $server->hostname;
        $targetPort = (int) $server->ssh_port;

        exec("sudo /usr/sbin/iptables -t nat -D PREROUTING -p tcp --dport {$port} -j DNAT --to-destination {$targetHost}:{$targetPort} 2>/dev/null");
        exec("sudo /usr/sbin/iptables -t nat -D POSTROUTING -p tcp -d {$targetHost} --dport {$targetPort} -j MASQUERADE 2>/dev/null");
        exec('sudo /usr/sbin/iptables-save | sudo tee /etc/iptables/rules.v4 > /dev/null 2>&1');
    }
}
