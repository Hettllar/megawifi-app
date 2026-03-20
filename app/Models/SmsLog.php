<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'router_id',
        'subscriber_id',
        'phone_number',
        'message',
        'type',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    /**
     * SMS types
     */
    const TYPE_REMINDER = 'reminder';
    const TYPE_EXPIRY = 'expiry';
    const TYPE_MANUAL = 'manual';
    const TYPE_WELCOME = 'welcome';
    const TYPE_RENEWAL = 'renewal';

    /**
     * SMS statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_DELIVERED = 'delivered';

    /**
     * Get the router that sent this SMS
     */
    public function router()
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * Get the subscriber this SMS was sent to
     */
    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Scope for pending messages
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for sent messages
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope for failed messages
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for reminder messages
     */
    public function scopeReminders($query)
    {
        return $query->where('type', self::TYPE_REMINDER);
    }

    /**
     * Scope for today's messages
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for messages by router
     */
    public function scopeByRouter($query, $routerId)
    {
        return $query->where('router_id', $routerId);
    }

    /**
     * Mark as sent
     */
    public function markAsSent()
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $error = null)
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $error,
        ]);
    }

    /**
     * Get status badge class for UI
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'bg-yellow-100 text-yellow-800',
            self::STATUS_SENT => 'bg-green-100 text-green-800',
            self::STATUS_FAILED => 'bg-red-100 text-red-800',
            self::STATUS_DELIVERED => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get status text in Arabic
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PENDING => 'قيد الانتظار',
            self::STATUS_SENT => 'تم الإرسال',
            self::STATUS_FAILED => 'فشل الإرسال',
            self::STATUS_DELIVERED => 'تم التسليم',
            default => 'غير معروف',
        };
    }

    /**
     * Get type text in Arabic
     */
    public function getTypeTextAttribute(): string
    {
        return match($this->type) {
            self::TYPE_REMINDER => 'تذكير',
            self::TYPE_EXPIRY => 'انتهاء الاشتراك',
            self::TYPE_MANUAL => 'يدوي',
            self::TYPE_WELCOME => 'ترحيب',
            self::TYPE_RENEWAL => 'تجديد',
            default => 'غير معروف',
        };
    }
}
