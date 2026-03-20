<?php

use App\Jobs\CheckExpiredSubscriptions;
use App\Jobs\CheckUsageLimit;
use App\Jobs\DailyRefreshAllUsers;
use App\Jobs\DisableExpiredUsers;
use App\Jobs\SendSmsRemindersJob;
use App\Jobs\ToggleRefreshRouterUsers;
use App\Models\Router;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

// ===================================================================
// Toggle Refresh لكل راوتر - حسب الفترة المحددة لكل راوتر
// ===================================================================

// Check routers for toggle refresh every 5 minutes
Schedule::call(function () {
    $routers = Router::where('is_active', true)
        ->whereNotNull('sync_interval')
        ->where('sync_interval', '>', 0)
        ->get();
    
    foreach ($routers as $router) {
        $interval = $router->sync_interval; // بالدقائق
        $lastSync = $router->last_toggle_sync;
        
        // Check if enough time has passed for this router
        if ($lastSync) {
            $lastSyncTime = \Carbon\Carbon::parse($lastSync);
            if (now()->diffInMinutes($lastSyncTime) < $interval) {
                continue; // Skip this router
            }
        }
        
        // Update last sync time BEFORE dispatching to prevent duplicate jobs
        $router->update(['last_toggle_sync' => now()]);
        
        // Dispatch toggle job for this router
        dispatch(new ToggleRefreshRouterUsers($router->id));
        
        Log::info("Scheduler: تم إرسال Toggle للراوتر {$router->name}");
    }
})->everyFiveMinutes()->name('toggle-refresh-routers');

// ===================================================================
// Check usage limits - التحقق من حدود الاستهلاك
// ===================================================================

// Check usage limits every 5 minutes
Schedule::job(new CheckUsageLimit)->everyFiveMinutes()->name('check-usage-limit');

// ===================================================================
// مهام عامة
// ===================================================================

// Check expired subscriptions every hour
Schedule::job(new CheckExpiredSubscriptions)->hourly()->name('check-expired');

// Send SMS reminders hourly
Schedule::job(new SendSmsRemindersJob)->hourly()->name('sms-reminders');

// ===================================================================
// تحديث استهلاك جميع المشتركين يومياً الساعة 3:30 صباحاً
// يقوم بفصل وإعادة تفعيل كل مشترك لتحديث بيانات الاستهلاك بدقة
// ===================================================================
Schedule::job(new DailyRefreshAllUsers)->dailyAt('03:30')->name('daily-refresh-all-users');

// ===================================================================
// تعطيل المستخدمين المنتهية صلاحيتهم - كل ساعة
// ===================================================================
Schedule::job(new DisableExpiredUsers)->hourly()->name('disable-expired-users');

// ===================================================================
// أرشفة جلسات UserManager من الراوترات إلى السيرفر - كل 30 دقيقة
// ===================================================================
Schedule::job(new \App\Jobs\OffloadUmSessions)->everyThirtyMinutes()->name('offload-um-sessions');

// ===================================================================
// تنظيف الجلسات القديمة من الراوتر (بعد التأكد من الأرشفة) - 4 مرات يومياً
// ===================================================================
// تشغيل 4 مرات يومياً لتسريع التنظيف (04:00, 10:00, 16:00, 22:00)
Schedule::job(new \App\Jobs\CleanupUmSessions)->cron('0 4,10,16,22 * * *')->name('cleanup-um-sessions');

// ===================================================================
// نسخ احتياطي يومي لجميع الراوترات - الساعة 3 صباحاً
// ===================================================================
Schedule::job(new \App\Jobs\BackupRouters)->dailyAt('03:00')->name('backup-routers');
// ===================================================================
// تسجيل جلسات المشتركين (IP + تاريخ) - كل 30 دقيقة
// خفيف: يقرأ من active_sessions فقط بدون اتصال بالراوترات
// ===================================================================
Schedule::job(new \App\Jobs\LogSessionIPs)->everyThirtyMinutes()->name('log-session-ips');
