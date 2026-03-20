<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerRouterPermission extends Model
{
    protected $fillable = [
        'reseller_id',
        'router_id',
        'can_create_hotspot',
        'can_edit_hotspot',
        'can_delete_hotspot',
        'can_enable_disable_hotspot',
        'can_create_ppp',
        'can_edit_ppp',
        'can_delete_ppp',
        'can_enable_disable_ppp',
        'can_create_usermanager',
        'can_edit_usermanager',
        'can_delete_usermanager',
        'can_renew_usermanager',
        'can_enable_disable_usermanager',
        'can_view_reports',
        'can_generate_vouchers',
    ];

    protected $casts = [
        'can_create_hotspot' => 'boolean',
        'can_edit_hotspot' => 'boolean',
        'can_delete_hotspot' => 'boolean',
        'can_enable_disable_hotspot' => 'boolean',
        'can_create_ppp' => 'boolean',
        'can_edit_ppp' => 'boolean',
        'can_delete_ppp' => 'boolean',
        'can_enable_disable_ppp' => 'boolean',
        'can_create_usermanager' => 'boolean',
        'can_edit_usermanager' => 'boolean',
        'can_delete_usermanager' => 'boolean',
        'can_renew_usermanager' => 'boolean',
        'can_enable_disable_usermanager' => 'boolean',
        'can_view_reports' => 'boolean',
        'can_generate_vouchers' => 'boolean',
    ];

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    // Helper methods
    public function hasHotspotAccess(): bool
    {
        return $this->can_create_hotspot || $this->can_edit_hotspot || 
               $this->can_delete_hotspot || $this->can_enable_disable_hotspot;
    }

    public function hasPppAccess(): bool
    {
        return $this->can_create_ppp || $this->can_edit_ppp || 
               $this->can_delete_ppp || $this->can_enable_disable_ppp;
    }

    public function hasUserManagerAccess(): bool
    {
        return $this->can_create_usermanager || $this->can_edit_usermanager || 
               $this->can_delete_usermanager || $this->can_renew_usermanager ||
               $this->can_enable_disable_usermanager;
    }

    public static function getPermissionLabels(): array
    {
        return [
            'can_create_hotspot' => 'إنشاء هوت سبوت',
            'can_edit_hotspot' => 'تعديل هوت سبوت',
            'can_delete_hotspot' => 'حذف هوت سبوت',
            'can_enable_disable_hotspot' => 'تفعيل/تعطيل هوت سبوت',
            'can_create_ppp' => 'إنشاء PPP',
            'can_edit_ppp' => 'تعديل PPP',
            'can_delete_ppp' => 'حذف PPP',
            'can_enable_disable_ppp' => 'تفعيل/تعطيل PPP',
            'can_create_usermanager' => 'إنشاء UserManager',
            'can_edit_usermanager' => 'تعديل UserManager',
            'can_delete_usermanager' => 'حذف UserManager',
            'can_renew_usermanager' => 'تجديد UserManager',
            'can_enable_disable_usermanager' => 'تفعيل/تعطيل UserManager',
            'can_view_reports' => 'عرض التقارير',
            'can_generate_vouchers' => 'إنشاء كروت',
        ];
    }
}
