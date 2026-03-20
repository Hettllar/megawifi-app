<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminNotification extends Model
{
    protected $fillable = [
        'type',
        'title',
        'message',
        'icon',
        'color',
        'user_id',
        'subscriber_id',
        'router_id',
        'data',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    // العلاقات
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    // أنواع الإشعارات
    public const TYPE_RENEWAL = 'renewal';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_NEW_SUBSCRIBER = 'new_subscriber';
    public const TYPE_EXPIRY = 'expiry';
    public const TYPE_USAGE_LIMIT = 'usage_limit';
    public const TYPE_SYSTEM = 'system';
    public const TYPE_HOTSPOT_CARD = 'hotspot_card';

    // إنشاء إشعار بطاقة هوتسبوت
    public static function notifyHotspotCard(User $reseller, Subscriber $subscriber, float $dataGB, float $cost): self
    {
        return self::create([
            'type' => self::TYPE_HOTSPOT_CARD,
            'title' => 'بطاقة هوتسبوت جديدة',
            'message' => "أنشأ الوكيل {$reseller->name} بطاقة هوتسبوت ({$subscriber->username}) - {$dataGB} GB" . ($cost > 0 ? " - تم خصم " . number_format($cost, 0) . " ل.س" : ''),
            'icon' => 'fa-wifi',
            'color' => 'orange',
            'user_id' => $reseller->id,
            'subscriber_id' => $subscriber->id,
            'router_id' => $subscriber->router_id,
            'data' => [
                'reseller_name' => $reseller->name,
                'subscriber_username' => $subscriber->username,
                'data_limit_gb' => $dataGB,
                'cost' => $cost,
                'profile' => $subscriber->profile,
            ],
        ]);
    }

    // إنشاء إشعار تجديد
    public static function notifyRenewal(User $reseller, Subscriber $subscriber, float $amount): self
    {
        return self::create([
            'type' => self::TYPE_RENEWAL,
            'title' => 'تجديد اشتراك من الوكيل',
            'message' => "قام الوكيل {$reseller->name} بتجديد اشتراك {$subscriber->username} - تم خصم " . number_format($amount, 0) . " ل.س",
            'icon' => 'fa-sync-alt',
            'color' => 'purple',
            'user_id' => $reseller->id,
            'subscriber_id' => $subscriber->id,
            'router_id' => $subscriber->router_id,
            'data' => [
                'reseller_name' => $reseller->name,
                'subscriber_username' => $subscriber->username,
                'subscriber_name' => $subscriber->full_name,
                'profile' => $subscriber->profile,
                'amount' => $amount,
            ],
        ]);
    }

    // إنشاء إشعار دفع
    public static function notifyPayment(Subscriber $subscriber, float $amount): self
    {
        return self::create([
            'type' => self::TYPE_PAYMENT,
            'title' => 'تسجيل دفعة',
            'message' => "تم تسجيل دفعة بقيمة " . number_format($amount, 0) . " IQD للمشترك {$subscriber->username}",
            'icon' => 'fa-money-bill-wave',
            'color' => 'green',
            'subscriber_id' => $subscriber->id,
            'router_id' => $subscriber->router_id,
            'data' => [
                'subscriber_username' => $subscriber->username,
                'amount' => $amount,
            ],
        ]);
    }

    // إنشاء إشعار جديد عام
    public static function notify(string $type, string $title, string $message, array $options = []): self
    {
        return self::create([
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'icon' => $options['icon'] ?? 'fa-bell',
            'color' => $options['color'] ?? 'blue',
            'user_id' => $options['user_id'] ?? null,
            'subscriber_id' => $options['subscriber_id'] ?? null,
            'router_id' => $options['router_id'] ?? null,
            'data' => $options['data'] ?? null,
        ]);
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // تحديد كمقروء
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    // تحديد الكل كمقروء
    public static function markAllAsRead(): void
    {
        self::where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }
}
