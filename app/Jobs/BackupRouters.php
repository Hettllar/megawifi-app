<?php

namespace App\Jobs;

use App\Models\Router;
use App\Services\MikroTikService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupRouters implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    protected int $keepDays = 7;

    public function handle(): void
    {
        Log::info('BackupRouters: بدء النسخ الاحتياطي لجميع الراوترات...');

        $routers = Router::where('is_active', true)->get();
        $success = 0;
        $failed = 0;
        $date = now()->format('Y-m-d_His');

        foreach ($routers as $router) {
            try {
                $result = $this->backupRouter($router, $date);
                if ($result) {
                    $success++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
                Log::error("BackupRouters: فشل نسخ {$router->name}: " . $e->getMessage());
            }
        }

        // تنظيف النسخ القديمة
        $this->cleanupOldBackups();

        Log::info("BackupRouters: اكتمل - نجح: {$success}, فشل: {$failed}");
    }

    protected function backupRouter(Router $router, string $date): bool
    {
        $connectionIP = $router->wg_enabled && $router->wg_client_ip
            ? $router->wg_client_ip : $router->ip_address;
        $port = $router->api_port ?: 8728;

        $sock = @fsockopen($connectionIP, $port, $errno, $errstr, 5);
        if (!$sock) return false;
        @fclose($sock);

        $service = new MikroTikService($router);
        if (!$service->connect()) return false;

        try {
            $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $router->name);
            $backupName = "megawifi_{$safeName}_{$date}";

            // 1. إنشاء ملف backup على الراوتر
            $service->command([
                '/system/backup/save',
                '=name=' . $backupName,
                '=dont-encrypt=yes'
            ]);

            // انتظار إنشاء الملف
            sleep(5);

            // 2. التحقق من وجود الملف
            $fileContent = $service->command([
                '/file/print',
                '?name=' . $backupName . '.backup'
            ]);

            if (empty($fileContent) || !isset($fileContent[0])) {
                Log::warning("BackupRouters: لم يتم إنشاء ملف النسخة لـ {$router->name}");
                $service->disconnect();
                return false;
            }

            $fileSize = $fileContent[0]['size'] ?? '0';
            $dir = "backups/routers/{$router->id}";
            Storage::disk('local')->makeDirectory($dir);

            // 3. تحميل الملف عبر FTP
            $downloaded = $this->downloadViaFtp(
                $connectionIP,
                $router->api_username,
                $router->api_password,
                $backupName . '.backup',
                $dir . '/' . $backupName . '.backup'
            );

            // 4. حفظ ملف المعلومات
            $infoData = [
                'router_id' => $router->id,
                'router_name' => $router->name,
                'backup_date' => now()->toIso8601String(),
                'backup_file' => $backupName . '.backup',
                'file_size' => $fileSize,
                'status' => $downloaded ? 'downloaded' : 'on_router',
                'created_by' => 'system'
            ];
            Storage::disk('local')->put("{$dir}/{$backupName}.info", json_encode($infoData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            if ($downloaded) {
                $localSize = Storage::disk('local')->size("{$dir}/{$backupName}.backup");
                Log::info("BackupRouters: نسخة {$router->name} محفوظة على السيرفر ({$localSize} bytes)");
            } else {
                Log::info("BackupRouters: نسخة {$router->name} محفوظة على الراوتر فقط ({$fileSize})");
            }

            // 5. إنشاء export (نص عادي)
            $export = $service->command(['/export']);
            if (!empty($export) && isset($export[0]['ret'])) {
                Storage::disk('local')->put("{$dir}/{$backupName}.rsc", $export[0]['ret']);
            }

            // 6. حذف ملف النسخة من الراوتر (تنظيف)
            $files = $service->command(['/file/print', '?name=' . $backupName . '.backup']);
            if (!empty($files[0]['.id'])) {
                $service->command(['/file/remove', '=.id=' . $files[0]['.id']]);
            }

            $service->disconnect();
            return true;

        } catch (\Exception $e) {
            Log::error("BackupRouters: خطأ أثناء نسخ {$router->name}: " . $e->getMessage());
            try { $service->disconnect(); } catch (\Exception $ex) {}
            return false;
        }
    }

    protected function downloadViaFtp(string $ip, string $username, string $password, string $remoteFile, string $localPath): bool
    {
        $conn = @ftp_connect($ip, 21, 10);
        if (!$conn) {
            Log::warning("BackupRouters: FTP connect failed to {$ip}");
            return false;
        }

        $login = @ftp_login($conn, $username, $password);
        if (!$login) {
            Log::warning("BackupRouters: FTP login failed to {$ip}");
            @ftp_close($conn);
            return false;
        }

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

    protected function cleanupOldBackups(): void
    {
        $cutoff = now()->subDays($this->keepDays)->format('Y-m-d');

        $dirs = Storage::disk('local')->directories('backups/routers');
        foreach ($dirs as $dir) {
            $files = Storage::disk('local')->files($dir);
            foreach ($files as $file) {
                if (preg_match('/(\d{4}-\d{2}-\d{2})/', $file, $m)) {
                    if ($m[1] < $cutoff) {
                        Storage::disk('local')->delete($file);
                    }
                }
            }
        }
    }
}
