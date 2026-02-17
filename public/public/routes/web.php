<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RouterController;
use App\Http\Controllers\SubscriberController;
use App\Http\Controllers\ServicePlanController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserManagerController;
use App\Http\Controllers\HotspotController;
use App\Http\Controllers\SyncSettingController;
use App\Http\Controllers\BalanceCheckController;
use App\Http\Controllers\WireGuardController;
use App\Http\Controllers\ResellerController;
use App\Http\Controllers\ResellerPanelController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\SmsController;
use Illuminate\Support\Facades\Route;

// Public routes (no auth required)
Route::get('/check-balance', [BalanceCheckController::class, 'index'])->name('balance.index');
Route::post('/check-balance', [BalanceCheckController::class, 'check'])->name('balance.check');
Route::get('/balance/download-m3u/{phone}', [BalanceCheckController::class, 'downloadM3U'])->name('balance.download-m3u');

// IPTV Player routes
Route::get('/iptv', [BalanceCheckController::class, 'iptvPlayer'])->name('iptv.player');
Route::post('/iptv/channels', [BalanceCheckController::class, 'getChannels'])->name('iptv.channels');
Route::get('/iptv/stream/{channelId}', [BalanceCheckController::class, 'streamProxy'])->name('iptv.stream')
    ->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class, \Illuminate\View\Middleware\ShareErrorsFromSession::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);
Route::get('/iptv/hls-proxy', [BalanceCheckController::class, 'hlsProxy'])->name('iptv.hls-proxy')
    ->withoutMiddleware([\Illuminate\Session\Middleware\StartSession::class, \Illuminate\View\Middleware\ShareErrorsFromSession::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

// Guest routes
Route::middleware('guest')->group(function () {
    Route::get('/', function () {
        return redirect()->route('login');
    });
    
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/stats', [DashboardController::class, 'apiStats'])->name('api.stats');
    
    // Profile
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');
    
    // Routers
    Route::resource('routers', RouterController::class);
    Route::post('/routers/{router}/test', [RouterController::class, 'testConnection'])->name('routers.test');
    Route::post('/routers/{router}/sync', [RouterController::class, 'sync'])->name('routers.sync');
    Route::post('/routers/{router}/sync-settings', [RouterController::class, 'updateSyncSettings'])->name('routers.sync-settings');
    Route::get('/routers/{router}/resources', [RouterController::class, 'resources'])->name('routers.resources');
    Route::delete('/routers/{router}/shamcash-qr', [RouterController::class, 'deleteShamcashQR'])->name('routers.delete-shamcash-qr');
    
    // WireGuard VPN routes
    Route::get('/routers/{router}/wireguard/script', [RouterController::class, 'generateWireGuardScript'])->name('routers.wireguard.script');
    Route::post('/routers/{router}/wireguard/test', [RouterController::class, 'testWireGuard'])->name('routers.wireguard.test');
    Route::post('/routers/{router}/wireguard/setup', [RouterController::class, 'setupWireGuard'])->name('routers.wireguard.setup');
    Route::post('/routers/{router}/wireguard/regenerate-keys', [RouterController::class, 'regenerateWireGuardKeys'])->name('routers.wireguard.regenerate');
    Route::post('/routers/{router}/wireguard/save-public-key', [RouterController::class, 'saveWireGuardPublicKey'])->name('routers.wireguard.save-public-key');
    Route::post('/routers/{router}/open-port', [RouterController::class, 'openPort'])->name('routers.open-port');
    Route::get('/routers/{router}/check-port', [RouterController::class, 'checkPort'])->name('routers.check-port');
    
    // Subscribers
    Route::resource('subscribers', SubscriberController::class);
    Route::get('/subscribers/{subscriber}/contract', [SubscriberController::class, 'contract'])->name('subscribers.contract');
    Route::post('/subscribers/{subscriber}/disconnect', [SubscriberController::class, 'disconnect'])->name('subscribers.disconnect');
    Route::post('/subscribers/{subscriber}/toggle', [SubscriberController::class, 'toggle'])->name('subscribers.toggle');
    Route::post('/subscribers/{subscriber}/toggle-iptv', [SubscriberController::class, 'toggleIptv'])->name('subscribers.toggle-iptv');
    Route::post('/subscribers/{subscriber}/renew', [SubscriberController::class, 'renew'])->name('subscribers.renew');
    Route::post('/subscribers/sync-sessions', [SubscriberController::class, 'syncSessions'])->name('subscribers.sync-sessions');
    Route::get('/subscribers/backup/export', [SubscriberController::class, 'exportBackup'])->name('subscribers.backup.export');
    Route::post('/subscribers/backup/import', [SubscriberController::class, 'importBackup'])->name('subscribers.backup.import');
    
    // Service Plans
    Route::resource('plans', ServicePlanController::class);
    Route::get('/routers/{router}/plans', [ServicePlanController::class, 'byRouter'])->name('plans.by-router');
    Route::post('/plans/{plan}/toggle', [ServicePlanController::class, 'toggle'])->name('plans.toggle');
    
    // Active Sessions
    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::post('/sessions/refresh', [SessionController::class, 'refresh'])->name('sessions.refresh');
    Route::post('/sessions/{session}/disconnect', [SessionController::class, 'disconnect'])->name('sessions.disconnect');
    Route::get('/sessions/stats', [SessionController::class, 'stats'])->name('sessions.stats');
    
    // Connected Devices
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/refresh', [DeviceController::class, 'refresh'])->name('devices.refresh');
    
    // Downloads page (Mobile Apps)
    Route::get('/downloads', function () {
        return view('downloads');
    })->name('downloads');
    
    // WireGuard VPN (for Devices page)
    Route::get('/wireguard/status', [WireGuardController::class, 'status'])->name('wireguard.status');
    Route::post('/wireguard/setup', [WireGuardController::class, 'setup'])->name('wireguard.setup');
    Route::post('/wireguard/add-peer', [WireGuardController::class, 'addPeer'])->name('wireguard.add-peer');
    Route::post('/wireguard/remove-peer', [WireGuardController::class, 'removePeer'])->name('wireguard.remove-peer');
    
    // Users (Admin only)
    Route::resource('users', UserController::class);
    Route::get('/users/{user}/routers', [UserController::class, 'manageRouters'])->name('users.routers');
    Route::post('/users/{user}/routers', [UserController::class, 'updateRouters'])->name('users.routers.update');
    Route::patch('/users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

    // Notifications (Admin only)
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread', [NotificationController::class, 'getUnread'])->name('unread');
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::delete('/{notification}', [NotificationController::class, 'destroy'])->name('destroy');
        Route::post('/clear-read', [NotificationController::class, 'clearRead'])->name('clear-read');
    });

    // Resellers Management
    Route::get('/resellers', [ResellerController::class, 'index'])->name('resellers.index');
    Route::get('/resellers/create', [ResellerController::class, 'create'])->name('resellers.create');
    Route::post('/resellers', [ResellerController::class, 'store'])->name('resellers.store');
    Route::get('/resellers/{reseller}', [ResellerController::class, 'show'])->name('resellers.show');
    Route::get('/resellers/{reseller}/edit', [ResellerController::class, 'edit'])->name('resellers.edit');
    Route::put('/resellers/{reseller}', [ResellerController::class, 'update'])->name('resellers.update');
    Route::delete('/resellers/{reseller}', [ResellerController::class, 'destroy'])->name('resellers.destroy');
    Route::get('/resellers/{reseller}/permissions', [ResellerController::class, 'permissions'])->name('resellers.permissions');
    Route::put('/resellers/{reseller}/permissions', [ResellerController::class, 'updatePermissions'])->name('resellers.permissions.update');
    Route::get('/resellers/{reseller}/deposit', [ResellerController::class, 'deposit'])->name('resellers.deposit');
    Route::post('/resellers/{reseller}/deposit', [ResellerController::class, 'processDeposit'])->name('resellers.deposit.process');
    Route::get('/resellers/{reseller}/transactions', [ResellerController::class, 'transactions'])->name('resellers.transactions');
    Route::get('/routers/{router}/reseller-pricing', [ResellerController::class, 'pricing'])->name('resellers.pricing');
    Route::put('/routers/{router}/reseller-pricing', [ResellerController::class, 'updatePricing'])->name('resellers.pricing.update');
    Route::post('/resellers/calculate-price', [ResellerController::class, 'calculatePrice'])->name('resellers.calculate-price');

    // Reseller Panel (for reseller users)
    Route::prefix('reseller-panel')->name('reseller.')->group(function () {
        Route::get('/', [ResellerPanelController::class, 'dashboard'])->name('dashboard');
        Route::get('/hotspot', [ResellerPanelController::class, 'hotspot'])->name('hotspot');
        Route::post('/hotspot/create', [ResellerPanelController::class, 'createHotspot'])->name('hotspot.create');
        Route::get('/hotspot/{router}/profiles', [ResellerPanelController::class, 'getHotspotProfiles'])->name('hotspot.profiles');
        Route::get('/usermanager', [ResellerPanelController::class, 'usermanager'])->name('usermanager');
        Route::post('/usermanager/renew', [ResellerPanelController::class, 'renewUsermanager'])->name('usermanager.renew');
        Route::post('/usermanager/search', [ResellerPanelController::class, 'searchSubscriber'])->name('usermanager.search');
    });

    // Reports
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/subscribers', [ReportController::class, 'subscribers'])->name('reports.subscribers');
    Route::get('/reports/revenue', [ReportController::class, 'revenue'])->name('reports.revenue');
    Route::get('/reports/sessions', [ReportController::class, 'sessions'])->name('reports.sessions');
    Route::get('/reports/expiring', [ReportController::class, 'expiring'])->name('reports.expiring');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');
    
    // UserManager 7
    Route::get('/usermanager', [UserManagerController::class, 'index'])->name('usermanager.index');
    Route::post('/usermanager/sync-all', [UserManagerController::class, 'syncAllRouters'])->name('usermanager.sync-all');
    Route::post('/usermanager/refresh-all-usage', [UserManagerController::class, 'refreshAllUsage'])->name('usermanager.refresh-all-usage');
    Route::post('/usermanager/bulk-expiry', [UserManagerController::class, 'bulkExpiry'])->name('usermanager.bulk-expiry');
    Route::post('/usermanager/bulk-update-prices', [UserManagerController::class, 'bulkUpdatePrices'])->name('usermanager.bulk-update-prices');
    Route::get('/usermanager/backup/export', [UserManagerController::class, 'exportBackup'])->name('usermanager.backup.export');
    Route::post('/usermanager/backup/import', [UserManagerController::class, 'importBackup'])->name('usermanager.backup.import');
    Route::post('/usermanager/{router}/sync', [UserManagerController::class, 'sync'])->name('usermanager.sync');
    Route::post('/usermanager/{router}/sync-usage', [UserManagerController::class, 'syncUsage'])->name('usermanager.sync-usage');
    Route::post('/usermanager/{router}/refresh-user-usage', [UserManagerController::class, 'refreshUserUsage'])->name('usermanager.refresh-user-usage');
    Route::get('/usermanager/{router}/subscriber/{subscriber}/data', [UserManagerController::class, 'getSubscriberData'])->name('usermanager.subscriber-data');
    Route::post('/usermanager/{router}/renew-user', [UserManagerController::class, 'renewUser'])->name('usermanager.renew-user');
    Route::post('/usermanager/{router}/throttle-user', [UserManagerController::class, 'throttleUser'])->name('usermanager.throttle-user');
    Route::post('/usermanager/{router}/enable-user', [UserManagerController::class, 'enableUser'])->name('usermanager.enable-user');
    Route::get('/usermanager/migrate', [UserManagerController::class, 'showMigrationPage'])->name('usermanager.migrate.index');
    Route::get('/usermanager/{router}/migrate', [UserManagerController::class, 'showMigrationForm'])->name('usermanager.migrate');
    Route::get('/usermanager/{router}/ppp-users', [UserManagerController::class, 'getPPPUsers'])->name('usermanager.ppp-users');
    Route::post('/usermanager/{router}/migrate-ppp', [UserManagerController::class, 'migratePPPUsers'])->name('usermanager.migrate-ppp');
    Route::get('/usermanager/{router}/profiles', [UserManagerController::class, 'getProfiles'])->name('usermanager.profiles');
    Route::post('/usermanager/{router}/assign-profile', [UserManagerController::class, 'assignProfileToAll'])->name('usermanager.assign-profile');
    Route::post('/usermanager/{router}/change-user-profile', [UserManagerController::class, 'changeUserProfile'])->name('usermanager.change-user-profile');
    Route::get('/usermanager/packages', [UserManagerController::class, 'packagesIndex'])->name('usermanager.packages.index');
    Route::get('/usermanager/{router}/packages', [UserManagerController::class, 'packages'])->name('usermanager.packages');
    Route::get('/usermanager/{router}/packages/data', [UserManagerController::class, 'getPackages'])->name('usermanager.packages.data');
    Route::get('/usermanager/{router}/packages-json', [UserManagerController::class, 'getPackages'])->name('usermanager.packages-json');
    Route::post('/usermanager/{router}/add-user', [UserManagerController::class, 'addUser'])->name('usermanager.add-user');
    Route::post('/usermanager/{router}/packages', [UserManagerController::class, 'createPackage'])->name('usermanager.packages.create');
    Route::post('/usermanager/{router}/quick-profiles', [UserManagerController::class, 'createQuickProfiles'])->name('usermanager.quick-profiles');
    Route::delete('/usermanager/{router}/packages', [UserManagerController::class, 'deletePackage'])->name('usermanager.packages.delete');
    Route::post('/usermanager/{router}/user-groups', [UserManagerController::class, 'createUserGroup'])->name('usermanager.user-groups.create');
    Route::delete('/usermanager/{router}/user-groups', [UserManagerController::class, 'deleteUserGroup'])->name('usermanager.user-groups.delete');
    Route::get('/usermanager/{router}/groups', [UserManagerController::class, 'groups'])->name('usermanager.groups');
    Route::get('/usermanager/{router}/sessions', [UserManagerController::class, 'sessions'])->name('usermanager.sessions');
    Route::post('/usermanager/{router}/sessions/{sessionId}/disconnect', [UserManagerController::class, 'disconnectSession'])->name('usermanager.sessions.disconnect');
    Route::get('/usermanager/{router}/vouchers', [UserManagerController::class, 'showVoucherGenerator'])->name('usermanager.vouchers');
    Route::post('/usermanager/{router}/vouchers', [UserManagerController::class, 'generateVouchers'])->name('usermanager.vouchers.generate');
    Route::post('/usermanager/{router}/create-throttled-profile', [UserManagerController::class, 'createThrottledProfile'])->name('usermanager.create-throttled-profile');
    Route::get('/usermanager/{subscriber}', [UserManagerController::class, 'show'])->name('usermanager.show');
    Route::put('/usermanager/{subscriber}', [UserManagerController::class, 'update'])->name('usermanager.update');
    Route::post('/usermanager/{subscriber}/reset-usage', [UserManagerController::class, 'resetUsage'])->name('usermanager.reset-usage');
    Route::post('/usermanager/{subscriber}/set-data-limit', [UserManagerController::class, 'setDataLimit'])->name('usermanager.set-data-limit');
    Route::post('/usermanager/{subscriber}/renew', [UserManagerController::class, 'renewSubscription'])->name('usermanager.renew');
    Route::post('/subscribers/{subscriber}/update-info', [UserManagerController::class, 'updateSubscriberInfo'])->name('subscribers.update-info');
    Route::post('/usermanager/{subscriber}/transfer', [UserManagerController::class, 'transferSubscriber'])->name('usermanager.transfer');
    Route::delete('/usermanager/{subscriber}', [UserManagerController::class, 'destroy'])->name('usermanager.destroy');

    // Hotspot
    Route::get('/hotspot', [HotspotController::class, 'index'])->name('hotspot.index');
    Route::get('/hotspot/create', [HotspotController::class, 'create'])->name('hotspot.create');
    Route::get('/hotspot/cards', [HotspotController::class, 'showCardGenerator'])->name('hotspot.cards');
    Route::post('/hotspot/cards/generate', [HotspotController::class, 'generateCards'])->name('hotspot.cards.generate');
    Route::get('/hotspot/backup/export', [HotspotController::class, 'exportBackup'])->name('hotspot.backup.export');
    Route::post('/hotspot/backup/import', [HotspotController::class, 'importBackup'])->name('hotspot.backup.import');
    Route::post('/hotspot', [HotspotController::class, 'store'])->name('hotspot.store');
    Route::delete('/hotspot/delete-used', [HotspotController::class, 'deleteUsed'])->name('hotspot.delete-used');
    Route::delete('/hotspot/delete-unused', [HotspotController::class, 'deleteUnused'])->name('hotspot.delete-unused');
    Route::get('/hotspot/{hotspot}/card', [HotspotController::class, 'card'])->name('hotspot.card');
    Route::get('/hotspot/{hotspot}', [HotspotController::class, 'show'])->name('hotspot.show');
    Route::get('/hotspot/{hotspot}/edit', [HotspotController::class, 'edit'])->name('hotspot.edit');
    Route::put('/hotspot/{hotspot}', [HotspotController::class, 'update'])->name('hotspot.update');
    Route::delete('/hotspot/{hotspot}', [HotspotController::class, 'destroy'])->name('hotspot.destroy');
    Route::post('/hotspot/{hotspot}/disconnect', [HotspotController::class, 'disconnect'])->name('hotspot.disconnect');
    Route::post('/hotspot/{hotspot}/toggle', [HotspotController::class, 'toggle'])->name('hotspot.toggle');
    Route::post('/hotspot/{router}/sync', [HotspotController::class, 'sync'])->name('hotspot.sync');
    Route::get('/hotspot/{router}/profiles', [HotspotController::class, 'profiles'])->name('hotspot.profiles');
    Route::get('/hotspot/{router}/sessions', [HotspotController::class, 'sessions'])->name('hotspot.sessions');

    // Sync Settings
    Route::get('/settings/sync', [SyncSettingController::class, 'index'])->name('settings.sync');
    Route::post('/settings/sync', [SyncSettingController::class, 'update'])->name('settings.sync.update');
    Route::get('/settings/sync/status', [SyncSettingController::class, 'status'])->name('settings.sync.status');
    Route::post('/settings/sync/toggle', [SyncSettingController::class, 'toggle'])->name('settings.sync.toggle');

    // SMS Settings & Reminders
    Route::get('/routers/{router}/sms', [SmsController::class, 'index'])->name('sms.index');
    Route::put('/routers/{router}/sms/settings', [SmsController::class, 'updateSettings'])->name('sms.settings.update');
    Route::post('/routers/{router}/sms/test', [SmsController::class, 'sendTest'])->name('sms.test');
    Route::post('/routers/{router}/sms/reminders', [SmsController::class, 'sendReminders'])->name('sms.reminders');
    Route::post('/routers/{router}/sms/send-all', [SmsController::class, 'sendToAll'])->name('sms.send-all');
    Route::get('/routers/{router}/sms/logs', [SmsController::class, 'logs'])->name('sms.logs');
    Route::delete('/routers/{router}/sms/logs/{log}', [SmsController::class, 'deleteLog'])->name('sms.logs.delete');
    Route::post('/routers/{router}/sms/configure-modem', [SmsController::class, 'configureModem'])->name('sms.configure-modem');
    Route::get('/routers/{router}/sms/check-modem', [SmsController::class, 'checkModem'])->name('sms.check-modem');
    Route::post('/routers/{router}/sms/subscriber/{subscriber}', [SmsController::class, 'sendToSubscriber'])->name('sms.send-to-subscriber');
    Route::post('/routers/{router}/sms/bulk', [SmsController::class, 'sendBulk'])->name('sms.send-bulk');
    Route::post('/subscribers/{subscriber}/phone', [SmsController::class, 'updateSubscriberPhone'])->name('subscribers.update-phone');

    // WinBox Remote Access
    Route::get('/admin/winbox-remote', function () {
        return view('admin.winbox-remote', [
            'routers' => App\Models\Router::where('is_active', true)->get()
        ]);
    })->name('admin.winbox-remote');
});
