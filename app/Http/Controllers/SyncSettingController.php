<?php

namespace App\Http\Controllers;

use App\Models\SyncSetting;
use Illuminate\Http\Request;

class SyncSettingController extends Controller
{
    /**
     * Show sync settings page
     */
    public function index()
    {
        $settings = [
            'auto_sync_enabled' => SyncSetting::get('auto_sync_enabled', 'true') === 'true',
            'sync_interval' => SyncSetting::get('sync_interval', 5),
            'full_sync_interval' => SyncSetting::get('full_sync_interval', 60),
            'toggle_refresh_enabled' => SyncSetting::get('toggle_refresh_enabled', 'false') === 'true',
            'toggle_refresh_interval' => SyncSetting::get('toggle_refresh_interval', 1440),
            'last_toggle_refresh' => SyncSetting::get('last_toggle_refresh'),
        ];

        return view('settings.sync', compact('settings'));
    }

    /**
     * Update sync settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'auto_sync_enabled' => 'required|in:true,false',
            'sync_interval' => 'required|integer|min:1|max:60',
            'full_sync_interval' => 'required|integer|min:5|max:1440',
            'toggle_refresh_enabled' => 'nullable|in:true,false',
            'toggle_refresh_interval' => 'nullable|integer|min:60|max:1440',
        ]);

        SyncSetting::set('auto_sync_enabled', $request->auto_sync_enabled);
        SyncSetting::set('sync_interval', $request->sync_interval);
        SyncSetting::set('full_sync_interval', $request->full_sync_interval);
        
        if ($request->has('toggle_refresh_enabled')) {
            SyncSetting::set('toggle_refresh_enabled', $request->toggle_refresh_enabled);
        }
        if ($request->has('toggle_refresh_interval')) {
            SyncSetting::set('toggle_refresh_interval', $request->toggle_refresh_interval);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حفظ إعدادات المزامنة بنجاح'
        ]);
    }

    /**
     * Get current sync status (for AJAX)
     */
    public function status()
    {
        return response()->json([
            'auto_sync_enabled' => SyncSetting::isAutoSyncEnabled(),
            'sync_interval' => SyncSetting::getSyncInterval(),
            'full_sync_interval' => SyncSetting::getFullSyncInterval(),
            'toggle_refresh_enabled' => SyncSetting::isToggleRefreshEnabled(),
            'toggle_refresh_interval' => SyncSetting::getToggleRefreshInterval(),
            'last_toggle_refresh' => SyncSetting::getLastToggleRefresh(),
        ]);
    }

    /**
     * Toggle auto sync
     */
    public function toggle(Request $request)
    {
        $currentStatus = SyncSetting::get('auto_sync_enabled', 'true');
        $newStatus = $currentStatus === 'true' ? 'false' : 'true';
        
        SyncSetting::set('auto_sync_enabled', $newStatus);

        return response()->json([
            'success' => true,
            'enabled' => $newStatus === 'true',
            'message' => $newStatus === 'true' ? 'تم تفعيل المزامنة التلقائية' : 'تم إيقاف المزامنة التلقائية'
        ]);
    }
}
