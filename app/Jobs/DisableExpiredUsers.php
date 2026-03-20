<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DisableExpiredUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $expiredUsers = User::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now())
            ->get();

        foreach ($expiredUsers as $user) {
            $user->update(['is_active' => false]);

            ActivityLog::log(
                'user.auto_disabled',
                "تم تعطيل المستخدم تلقائياً (انتهاء الصلاحية): {$user->name}",
                null,
                null,
                User::class,
                $user->id
            );

            Log::info("Auto-disabled expired user: {$user->name} (ID: {$user->id}), expired at: {$user->expires_at}");
        }

        if ($expiredUsers->count() > 0) {
            Log::info("DisableExpiredUsers: تم تعطيل {$expiredUsers->count()} مستخدم منتهي الصلاحية");
        }
    }
}
