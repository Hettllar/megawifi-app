<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * الحصول على الإشعارات غير المقروءة (للـ header)
     */
    public function getUnread()
    {
        $user = Auth::user();
        
        // Super admin يرى جميع الإشعارات
        if ($user->isSuperAdmin()) {
            $notifications = AdminNotification::unread()
                ->latest()
                ->take(10)
                ->get();
            $unreadCount = AdminNotification::unread()->count();
        } else {
            // المدير يرى فقط إشعارات الراوترات المرتبطة به
            $routerIds = $user->routers()->pluck('routers.id')->toArray();
            
            $notifications = AdminNotification::unread()
                ->whereIn('router_id', $routerIds)
                ->latest()
                ->take(10)
                ->get();
            $unreadCount = AdminNotification::unread()
                ->whereIn('router_id', $routerIds)
                ->count();
        }

        return response()->json([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * صفحة جميع الإشعارات
     */
    public function index()
    {
        $user = Auth::user();
        
        if ($user->isSuperAdmin()) {
            $notifications = AdminNotification::latest()
                ->paginate(20);
            $unreadCount = AdminNotification::unread()->count();
        } else {
            $routerIds = $user->routers()->pluck('routers.id')->toArray();
            
            $notifications = AdminNotification::whereIn('router_id', $routerIds)
                ->latest()
                ->paginate(20);
            $unreadCount = AdminNotification::unread()
                ->whereIn('router_id', $routerIds)
                ->count();
        }

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    /**
     * تحديد إشعار كمقروء
     */
    public function markAsRead(AdminNotification $notification)
    {
        $notification->markAsRead();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * تحديد جميع الإشعارات كمقروءة
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        
        if ($user->isSuperAdmin()) {
            AdminNotification::markAllAsRead();
        } else {
            $routerIds = $user->routers()->pluck('routers.id')->toArray();
            AdminNotification::whereIn('router_id', $routerIds)
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تحديد جميع الإشعارات كمقروءة',
        ]);
    }

    /**
     * حذف إشعار
     */
    public function destroy(AdminNotification $notification)
    {
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الإشعار',
        ]);
    }

    /**
     * حذف جميع الإشعارات المقروءة
     */
    public function clearRead()
    {
        $user = Auth::user();
        
        if ($user->isSuperAdmin()) {
            AdminNotification::where('is_read', true)->delete();
        } else {
            $routerIds = $user->routers()->pluck('routers.id')->toArray();
            AdminNotification::whereIn('router_id', $routerIds)
                ->where('is_read', true)
                ->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الإشعارات المقروءة',
        ]);
    }
}
