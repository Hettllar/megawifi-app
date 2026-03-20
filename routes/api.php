<?php

use App\Http\Controllers\Api\MobileApiController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [MobileApiController::class, 'login']);

Route::middleware('api.token')->group(function () {
    Route::post('/logout', [MobileApiController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard', [MobileApiController::class, 'dashboard']);

    // Subscribers (UserManager)
    Route::get('/subscribers', [MobileApiController::class, 'subscribers']);
    Route::get('/subscribers/{id}', [MobileApiController::class, 'subscriberDetail']);
    Route::post('/subscribers/batch-enable', [MobileApiController::class, 'batchEnable']);
    Route::post('/subscribers/batch-add-days', [MobileApiController::class, 'batchAddDays']);
    Route::post('/subscribers/batch-disable', [MobileApiController::class, 'batchDisable']);
    Route::post('/subscribers/{id}/toggle', [MobileApiController::class, 'toggleSubscriber']);
    Route::put('/subscribers/{id}', [MobileApiController::class, 'updateSubscriber']);
    Route::post('/subscribers/{id}/reset-usage', [MobileApiController::class, 'resetUsage']);
    Route::post('/subscribers/{id}/set-data-limit', [MobileApiController::class, 'setDataLimit']);
    Route::post('/subscribers/{id}/renew', [MobileApiController::class, 'renewSubscription']);
        Route::post('/subscribers/create', [MobileApiController::class, 'createSubscriber']);
    Route::delete('/subscribers/{id}', [MobileApiController::class, 'deleteSubscriber']);
    Route::post('/subscribers/{id}/transfer', [MobileApiController::class, 'transferSubscriber']);
    Route::post('/subscribers/{id}/toggle-iptv', [MobileApiController::class, 'toggleIptv']);
    Route::get('/subscribers/{id}/sessions', [MobileApiController::class, 'subscriberSessions']);
    Route::get('/subscribers/{id}/all-sessions', [MobileApiController::class, 'subscriberAllSessions']);
    Route::post('/subscribers/{id}/refresh-usage', [MobileApiController::class, 'refreshSubscriberUsage']);
    Route::get('/subscribers/{id}/live-usage', [MobileApiController::class, 'liveUsage']);


    // Hotspot
    Route::get('/hotspot', [MobileApiController::class, 'hotspotList']);
    Route::get('/hotspot/profiles/{routerId}', [MobileApiController::class, 'hotspotProfiles']);
    Route::post('/hotspot/generate', [MobileApiController::class, 'hotspotGenerateCards']);
    Route::post('/hotspot/add-card', [MobileApiController::class, 'hotspotAddCard']);
    Route::get('/hotspot/{id}', [MobileApiController::class, 'hotspotDetail']);
    Route::post('/hotspot/{id}/toggle', [MobileApiController::class, 'hotspotToggle']);
    Route::post('/hotspot/{id}/disconnect', [MobileApiController::class, 'hotspotDisconnect']);
    Route::post('/hotspot/{id}/edit', [MobileApiController::class, 'hotspotEdit']);
    Route::post('/hotspot/{id}/reset', [MobileApiController::class, 'hotspotReset']);
    Route::post('/hotspot/{id}/transfer', [MobileApiController::class, 'hotspotTransfer']);
    Route::delete('/hotspot/{id}', [MobileApiController::class, 'hotspotDelete']);
    Route::post('/hotspot/delete-used', [MobileApiController::class, 'hotspotDeleteUsed']);
    Route::post('/hotspot/sync/{routerId}', [MobileApiController::class, 'hotspotSync']);


    // Routers
    // Router Monitoring
    Route::get('/monitors', [MobileApiController::class, 'monitorRouters']);
    Route::get('/monitors/{id}/live', [MobileApiController::class, 'monitorRouterLive']);

    Route::post('/routers/{id}/reboot', [MobileApiController::class, 'rebootRouter']);
    Route::get('/routers/{id}/log', [MobileApiController::class, 'routerLog']);
    Route::post('/routers/{id}/sync', [MobileApiController::class, 'syncRouter']);
    Route::get('/routers/{id}/profiles', [MobileApiController::class, 'profiles']);
    Route::get('/routers/{id}/interfaces', [MobileApiController::class, 'routerInterfaces']);
    Route::get('/routers/{id}/interface-traffic', [MobileApiController::class, 'interfaceTraffic']);
    Route::get('/routers/{id}/neighbors', [MobileApiController::class, 'routerNeighbors']);

    // Sync Usage
    Route::post('/sync-usage', [MobileApiController::class, 'syncAllUsage']);


    // Notifications
    Route::get('/notifications', [MobileApiController::class, 'notifications']);
    Route::get('/notifications/unread', [MobileApiController::class, 'unreadNotifications']);
    Route::post('/notifications/{id}/read', [MobileApiController::class, 'markNotificationRead']);
    Route::post('/notifications/read-all', [MobileApiController::class, 'markAllNotificationsRead']);
    Route::delete('/notifications/delete-all', [MobileApiController::class, 'deleteAllNotifications']);
    Route::delete('/notifications/{id}', [MobileApiController::class, 'deleteNotification']);

    // Admin
    
    // Admin: Reseller Balance Recharge
    Route::get('/admin/resellers', [MobileApiController::class, 'listResellers']);
    Route::post('/admin/recharge-reseller', [MobileApiController::class, 'rechargeReseller']);
    Route::get('/admin/recharge-history', [MobileApiController::class, 'rechargeHistory']);

    Route::post('/admin/optimize', [MobileApiController::class, 'adminOptimize']);

    // Admin: User Management
    Route::get('/admin/users', [MobileApiController::class, 'listUsers']);
    Route::post('/admin/users', [MobileApiController::class, 'createUser']);
    Route::post('/admin/users/{id}/toggle', [MobileApiController::class, 'toggleUser']);
    Route::post('/admin/users/{id}/reset-device', [MobileApiController::class, 'resetDevice']);
    Route::delete('/admin/users/{id}', [MobileApiController::class, 'deleteUser']);
    Route::put('/admin/users/{id}', [MobileApiController::class, 'updateUser']);
    // WhatsApp Settings
    Route::get('/admin/whatsapp-settings/{routerId}', [MobileApiController::class, 'getWhatsAppSettings']);
    Route::put('/admin/whatsapp-settings/{routerId}', [MobileApiController::class, 'updateWhatsAppSettings']);
    Route::post('/admin/whatsapp-settings/{routerId}/shamcash-qr', [MobileApiController::class, 'uploadShamCashQr']);
    Route::delete('/admin/whatsapp-settings/{routerId}/shamcash-qr', [MobileApiController::class, 'deleteShamCashQr']);

    Route::get('/admin/routers', [MobileApiController::class, 'listAllRouters']);
    Route::post('/admin/routers/pricing', [MobileApiController::class, 'updateRouterPricing']);

    // Reseller
    Route::get('/reseller/dashboard', [MobileApiController::class, 'resellerDashboard']);
    Route::get('/reseller/subscribers', [MobileApiController::class, 'resellerSubscribers']);
    Route::post('/reseller/renew', [MobileApiController::class, 'resellerRenew']);
    Route::get('/reseller/search-subscriber', [MobileApiController::class, 'resellerSearchSubscriber']);
    Route::get('/reseller/operations', [MobileApiController::class, 'resellerOperations']);
    Route::get('/reseller/hotspot-profiles/{routerId}', [MobileApiController::class, 'resellerHotspotProfiles']);
    Route::post('/reseller/hotspot/create', [MobileApiController::class, 'resellerCreateHotspot']);
    Route::get('/reseller/iptv-search', [MobileApiController::class, 'resellerIptvSearch']);
    Route::post('/reseller/iptv-toggle', [MobileApiController::class, 'resellerIptvToggle']);

    // Admin: Reseller Linking
    Route::post('/admin/resellers/link', [MobileApiController::class, 'linkReseller']);
    Route::post('/admin/resellers/unlink', [MobileApiController::class, 'unlinkReseller']);
    Route::post('/admin/resellers/sync', [MobileApiController::class, 'syncResellers']);
    Route::get('/admin/reseller-admins', [MobileApiController::class, 'resellerAdmins']);
    Route::get('/admin/admin-resellers', [MobileApiController::class, 'adminResellers']);


    // ===== SMS Management API (for MegaSMS App) =====
    Route::get('/sms/dashboard', [\App\Http\Controllers\Api\SmsApiController::class, 'dashboard']);
    Route::get('/sms/logs', [\App\Http\Controllers\Api\SmsApiController::class, 'getAllLogs']);
    Route::get('/sms/{routerId}/settings', [\App\Http\Controllers\Api\SmsApiController::class, 'getSettings']);
    Route::put('/sms/{routerId}/settings', [\App\Http\Controllers\Api\SmsApiController::class, 'updateSettings']);
    Route::get('/sms/{routerId}/modem', [\App\Http\Controllers\Api\SmsApiController::class, 'checkModem']);
    Route::post('/sms/{routerId}/modem/configure', [\App\Http\Controllers\Api\SmsApiController::class, 'configureModem']);
    Route::post('/sms/{routerId}/test', [\App\Http\Controllers\Api\SmsApiController::class, 'sendTest']);
    Route::post('/sms/{routerId}/send/{subscriberId}', [\App\Http\Controllers\Api\SmsApiController::class, 'sendToSubscriber']);
    Route::post('/sms/{routerId}/bulk', [\App\Http\Controllers\Api\SmsApiController::class, 'sendBulk']);
    Route::post('/sms/{routerId}/reminders', [\App\Http\Controllers\Api\SmsApiController::class, 'sendReminders']);
    Route::get('/sms/{routerId}/logs', [\App\Http\Controllers\Api\SmsApiController::class, 'getLogs']);
    Route::delete('/sms/{routerId}/logs', [\App\Http\Controllers\Api\SmsApiController::class, 'clearLogs']);
    Route::get('/sms/{routerId}/subscribers', [\App\Http\Controllers\Api\SmsApiController::class, 'getSubscribers']);
    Route::put('/sms/subscriber/{subscriberId}/phone', [\App\Http\Controllers\Api\SmsApiController::class, 'updateSubscriberPhone']);
    Route::delete('/sms/log/{logId}', [\App\Http\Controllers\Api\SmsApiController::class, 'deleteLog']);
    Route::post('/sms/{routerId}/log-phone-sms', [\App\Http\Controllers\Api\SmsApiController::class, 'logPhoneSms']);
    Route::post('/sms/{routerId}/log-phone-sms-bulk', [\App\Http\Controllers\Api\SmsApiController::class, 'logPhoneSmsBlulk']);


    // VPN Configuration
    Route::get('/vpn/config', [MobileApiController::class, 'getVpnConfig']);
    Route::post('/admin/vpn/create', [MobileApiController::class, 'createVpnConfig']);
    Route::delete('/admin/vpn/{id}', [MobileApiController::class, 'deleteVpnConfig']);
    Route::get('/admin/vpn/list', [MobileApiController::class, 'listVpnConfigs']);

    // Router WireGuard management
    Route::get('/admin/router/{routerId}/wireguard/status', [MobileApiController::class, 'routerWireguardStatus']);
    Route::post('/admin/router/{routerId}/wireguard/setup', [MobileApiController::class, 'setupRouterWireguard']);
    Route::post('/admin/router/{routerId}/wireguard/add-peer', [MobileApiController::class, 'addRouterWireguardPeer']);
    // ZeroTier Management
    Route::post('/admin/router/{routerId}/zerotier/setup', [MobileApiController::class, 'setupRouterZerotier']);
    Route::get('/admin/router/{routerId}/zerotier/status', [MobileApiController::class, 'routerZerotierStatus']);
    Route::post('/admin/router/{routerId}/zerotier/disable', [MobileApiController::class, 'disableRouterZerotier']);
    Route::post('/admin/router/{routerId}/zerotier/heartbeat', [MobileApiController::class, 'updateZerotierHeartbeat']);

    // Mobile router listing (used by app VPN status)
    Route::get('/mobile/routers', [MobileApiController::class, 'listAllRouters']);


    // === Router Backup ===
    Route::post('/admin/router/{routerId}/backup', [MobileApiController::class, 'createRouterBackup']);
    Route::get('/admin/router/{routerId}/backup/export', [MobileApiController::class, 'downloadRouterExport']);
    Route::get('/admin/router/{routerId}/backup/list', [MobileApiController::class, 'listRouterBackups']);
    Route::get('/admin/router/{routerId}/backup/files', [MobileApiController::class, 'listRouterBackupFiles']);
    Route::post('/admin/router/{routerId}/backup/restore', [MobileApiController::class, 'restoreRouterBackup']);
});
