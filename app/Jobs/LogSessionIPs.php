<?php

namespace App\Jobs;

use App\Models\ActiveSession;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogSessionIPs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 60;

    public function handle(): void
    {
        $sessions = ActiveSession::whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->select('subscriber_id', 'router_id', 'username', 'ip_address', 'mac_address', 'started_at')
            ->get();

        if ($sessions->isEmpty()) return;

        $now = now();
        $logged = 0;

        foreach ($sessions->chunk(500) as $chunk) {
            $rows = $chunk->map(fn($s) => [
                'subscriber_id' => $s->subscriber_id,
                'router_id' => $s->router_id,
                'username' => $s->username,
                'ip_address' => $s->ip_address,
                'mac_address' => $s->mac_address,
                'started_at' => $s->started_at,
                'created_at' => $now,
            ])->toArray();

            $logged += DB::table('session_logs')->insertOrIgnore($rows);
        }

        // تنظيف تلقائي: حذف السجلات الأقدم من 90 يوم
        $deleted = DB::table('session_logs')
            ->where('created_at', '<', now()->subDays(90))
            ->delete();

        if ($logged > 0 || $deleted > 0) {
            Log::info("LogSessionIPs: تم تسجيل {$logged} جلسة، حذف {$deleted} قديمة");
        }
    }
}
