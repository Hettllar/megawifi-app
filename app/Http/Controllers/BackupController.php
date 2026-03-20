<?php

namespace App\Http\Controllers;

use App\Models\Router;
use App\Services\MikroTikService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BackupController extends Controller
{
    public function index()
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(403);
        }

        $routers = Router::where('is_active', true)->orderBy('name')->get();
        $backups = [];
        $totalSize = 0;
        $totalFiles = 0;

        foreach ($routers as $router) {
            $dir = "backups/routers/{$router->id}";
            $files = [];

            if (Storage::disk('local')->exists($dir)) {
                $allFiles = Storage::disk('local')->files($dir);
                foreach ($allFiles as $file) {
                    $ext = pathinfo($file, PATHINFO_EXTENSION);
                    if ($ext === 'info') {
                        $infoData = json_decode(Storage::disk('local')->get($file), true);
                        $backupFile = str_replace('.info', '.backup', $file);
                        $hasBackup = Storage::disk('local')->exists($backupFile);
                        $size = $hasBackup ? Storage::disk('local')->size($backupFile) : 0;
                        $totalSize += $size;
                        if ($hasBackup) $totalFiles++;

                        $files[] = [
                            'info' => $infoData,
                            'has_backup' => $hasBackup,
                            'size' => $size,
                            'filename' => basename($file, '.info'),
                            'date' => $infoData['backup_date'] ?? ($infoData['created_at'] ?? ''),
                            'status' => $infoData['status'] ?? 'unknown',
                        ];
                    }
                }
                // Sort by date descending
                usort($files, fn($a, $b) => strcmp($b['date'], $a['date']));
            }

            $backups[] = [
                'router' => $router,
                'files' => $files,
                'latest' => $files[0] ?? null,
                'count' => count($files),
            ];
        }

        return view('admin.backups', compact('backups', 'routers', 'totalSize', 'totalFiles'));
    }

    public function create(Request $request, $routerId)
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(403);
        }

        $router = Router::findOrFail($routerId);

        try {
            $connectionIP = $router->wg_enabled && $router->wg_client_ip
                ? $router->wg_client_ip : $router->ip_address;
            $port = $router->api_port ?: 8728;

            $sock = @fsockopen($connectionIP, $port, $errno, $errstr, 5);
            if (!$sock) {
                return back()->with('error', "لا يمكن الاتصال بـ {$router->name}");
            }
            @fclose($sock);

            $service = new MikroTikService($router);
            if (!$service->connect()) {
                return back()->with('error', "فشل الاتصال بـ {$router->name}");
            }

            $date = now()->format('Y-m-d_His');
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $router->name);
            $backupName = "megawifi_{$safeName}_{$date}";

            $service->command([
                '/system/backup/save',
                '=name=' . $backupName,
                '=dont-encrypt=yes'
            ]);
            sleep(5);

            $fileContent = $service->command([
                '/file/print',
                '?name=' . $backupName . '.backup'
            ]);

            if (empty($fileContent) || !isset($fileContent[0])) {
                $service->disconnect();
                return back()->with('error', "فشل إنشاء النسخة على {$router->name}");
            }

            $fileSize = $fileContent[0]['size'] ?? '0';
            $dir = "backups/routers/{$router->id}";
            Storage::disk('local')->makeDirectory($dir);

            // Download via FTP
            $downloaded = $this->downloadViaFtp(
                $connectionIP,
                $router->api_username,
                $router->api_password,
                $backupName . '.backup',
                $dir . '/' . $backupName . '.backup'
            );

            // Save info
            Storage::disk('local')->put("{$dir}/{$backupName}.info", json_encode([
                'router_id' => $router->id,
                'router_name' => $router->name,
                'backup_date' => now()->toIso8601String(),
                'backup_file' => $backupName . '.backup',
                'file_size' => $fileSize,
                'status' => $downloaded ? 'downloaded' : 'on_router',
                'created_by' => auth()->user()->name,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // Cleanup from router
            $files = $service->command(['/file/print', '?name=' . $backupName . '.backup']);
            if (!empty($files[0]['.id'])) {
                $service->command(['/file/remove', '=.id=' . $files[0]['.id']]);
            }

            $service->disconnect();

            $status = $downloaded ? 'تم إنشاء وتحميل النسخة بنجاح' : 'تم إنشاء النسخة على الراوتر (لم يتم التحميل)';
            return back()->with('success', "✅ {$status} لـ {$router->name}");

        } catch (\Exception $e) {
            Log::error("Backup create error: " . $e->getMessage());
            return back()->with('error', "خطأ: " . $e->getMessage());
        }
    }

    public function download($routerId, $filename)
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(403);
        }

        // Sanitize filename to prevent path traversal
        $filename = basename($filename);
        $path = "backups/routers/{$routerId}/{$filename}.backup";

        if (!Storage::disk('local')->exists($path)) {
            return back()->with('error', 'ملف النسخة غير موجود');
        }

        return Storage::disk('local')->download($path, $filename . '.backup');
    }

    public function delete(Request $request, $routerId, $filename)
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(403);
        }

        $filename = basename($filename);
        $dir = "backups/routers/{$routerId}";

        Storage::disk('local')->delete("{$dir}/{$filename}.backup");
        Storage::disk('local')->delete("{$dir}/{$filename}.info");
        Storage::disk('local')->delete("{$dir}/{$filename}.rsc");

        return back()->with('success', '✅ تم حذف النسخة');
    }

    public function restore(Request $request, $routerId, $filename)
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(403);
        }

        $router = Router::findOrFail($routerId);
        $filename = basename($filename);
        $path = "backups/routers/{$routerId}/{$filename}.backup";

        if (!Storage::disk('local')->exists($path)) {
            return back()->with('error', 'ملف النسخة غير موجود');
        }

        try {
            $connectionIP = $router->wg_enabled && $router->wg_client_ip
                ? $router->wg_client_ip : $router->ip_address;

            // Upload via FTP
            $content = Storage::disk('local')->get($path);
            $conn = @ftp_connect($connectionIP, 21, 10);
            if (!$conn) {
                return back()->with('error', 'فشل اتصال FTP');
            }

            $login = @ftp_login($conn, $router->api_username, $router->api_password);
            if (!$login) {
                @ftp_close($conn);
                return back()->with('error', 'فشل تسجيل دخول FTP');
            }

            @ftp_pasv($conn, true);
            $tempFile = tempnam(sys_get_temp_dir(), 'restore_');
            file_put_contents($tempFile, $content);
            $uploaded = @ftp_put($conn, '/' . $filename . '.backup', $tempFile, FTP_BINARY);
            @ftp_close($conn);
            @unlink($tempFile);

            if (!$uploaded) {
                return back()->with('error', 'فشل رفع الملف إلى الراوتر');
            }

            // Restore via API
            $service = new MikroTikService($router);
            if (!$service->connect()) {
                return back()->with('error', 'فشل اتصال API');
            }

            $service->command([
                '/system/backup/load',
                '=name=' . $filename . '.backup'
            ]);
            $service->disconnect();

            return back()->with('success', "✅ تمت استعادة النسخة على {$router->name}. سيتم إعادة تشغيل الراوتر.");

        } catch (\Exception $e) {
            return back()->with('error', "خطأ: " . $e->getMessage());
        }
    }

    public function backupAll()
    {
        if (auth()->user()->role !== 'super_admin') {
            abort(403);
        }

        \App\Jobs\BackupRouters::dispatch();
        return back()->with('success', '✅ تم بدء النسخ الاحتياطي لجميع الراوترات في الخلفية');
    }

    protected function downloadViaFtp(string $ip, string $username, string $password, string $remoteFile, string $localPath): bool
    {
        $conn = @ftp_connect($ip, 21, 10);
        if (!$conn) return false;
        $login = @ftp_login($conn, $username, $password);
        if (!$login) { @ftp_close($conn); return false; }
        @ftp_pasv($conn, true);
        $tempFile = tempnam(sys_get_temp_dir(), 'mikrotik_backup_');
        $ok = @ftp_get($conn, $tempFile, '/' . $remoteFile, FTP_BINARY);
        @ftp_close($conn);
        if ($ok && file_exists($tempFile) && filesize($tempFile) > 0) {
            Storage::disk('local')->put($localPath, file_get_contents($tempFile));
            @unlink($tempFile);
            return true;
        }
        @unlink($tempFile);
        return false;
    }

    protected function formatBytes($bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
