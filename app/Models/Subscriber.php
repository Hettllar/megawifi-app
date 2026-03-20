<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'router_id',
        'service_plan_id',
        'username',
        'password',
        'full_name',
        'phone',
        'email',
        'address',
        'national_id',
        'type',
        'status',
        'stopped_at',
        'stop_reason',
        'mikrotik_id',
        'profile',
        'original_profile',
        'caller_id',
        'remote_address',
        'local_address',
        'expiration_date',
        'first_login',
        'last_login',
        'last_login_ip',
        'bytes_in',
        'bytes_out',
        'total_bytes',
        'archived_bytes',
        'um_usage_offset',
        'limit_bytes_total',
        'data_limit',
        'data_limit_gb',
        'uptime_used',
        'uptime_limit',
        'balance',
        'total_paid',
        'comment',
        'um_data',
        'is_synced',
        'is_throttled',
        'throttled_at',
        'usage_reset_at',
        'last_synced_at',
        'created_by',
        'reseller_id',
        // Payment fields
        'subscription_price',
        'remaining_amount',
        'is_paid',
        'is_online',
        'whatsapp_number',
        'iptv_enabled',
        'iptv_allowed_ips',
    ];

    protected $casts = [
        'expiration_date' => 'datetime',
        'first_login' => 'datetime',
        'last_login' => 'datetime',
        'last_synced_at' => 'datetime',
        'stopped_at' => 'datetime',
        'throttled_at' => 'datetime',
        'usage_reset_at' => 'datetime',
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
        'total_bytes' => 'integer',
        'data_limit' => 'integer',
        'data_limit_gb' => 'decimal:2',
        'uptime_used' => 'integer',
        'uptime_limit' => 'integer',
        'balance' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'subscription_price' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'is_synced' => 'boolean',
        'is_throttled' => 'boolean',
        'is_paid' => 'boolean',
        'is_online' => 'boolean',
        'um_data' => 'array',
    ];

    protected $hidden = [
        'password',
    ];

    // Relationships
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function servicePlan(): BelongsTo
    {
        return $this->belongsTo(ServicePlan::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id');
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(ActiveSession::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function iptvSubscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(IptvSubscription::class);
    }

    public function trafficHistory(): HasMany
    {
        return $this->hasMany(TrafficHistory::class);
    }

    // Accessors
    public function getIsOnlineAttribute(): bool
    {
        return $this->activeSessions()->exists();
    }

    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expiration_date) {
            return false;
        }
        return $this->expiration_date->isPast();
    }

    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->expiration_date) {
            return null;
        }
        return max(0, now()->diffInDays($this->expiration_date, false));
    }

    public function getTotalBytesFormattedAttribute(): string
    {
        return $this->formatBytes($this->total_bytes);
    }

    public function getBytesInFormattedAttribute(): string
    {
        return $this->formatBytes($this->bytes_in);
    }

    public function getBytesOutFormattedAttribute(): string
    {
        return $this->formatBytes($this->bytes_out);
    }

    public function getDataUsagePercentAttribute(): float
    {
        // استخدام data_limit_gb إذا كان موجود
        $limitBytes = $this->data_limit;
        if ($this->data_limit_gb > 0) {
            $limitBytes = $this->data_limit_gb * 1073741824; // تحويل GB إلى bytes
        }
        
        if (!$limitBytes || $limitBytes == 0) {
            return 0;
        }
        return min(100, round(($this->total_bytes / $limitBytes) * 100, 2));
    }

    /**
     * تحقق إذا تجاوز المشترك حد الاستهلاك
     */
    public function hasExceededDataLimit(): bool
    {
        if (!$this->data_limit_gb || $this->data_limit_gb <= 0) {
            return false;
        }
        
        $limitBytes = $this->data_limit_gb * 1073741824;
        return $this->total_bytes >= $limitBytes;
    }

    /**
     * الحصول على حد البيانات بالبايت
     */
    public function getDataLimitBytesAttribute(): ?int
    {
        if ($this->data_limit_gb > 0) {
            return (int) ($this->data_limit_gb * 1073741824);
        }
        return $this->data_limit;
    }

    /**
     * الحصول على البيانات المتبقية
     */
    public function getRemainingBytesAttribute(): ?int
    {
        $limit = $this->data_limit_bytes;
        if (!$limit) {
            return null;
        }
        return max(0, $limit - $this->total_bytes);
    }

    /**
     * تنسيق البيانات المتبقية
     */
    public function getRemainingFormattedAttribute(): string
    {
        $remaining = $this->remaining_bytes;
        if ($remaining === null) {
            return 'غير محدد';
        }
        return $this->formatBytes($remaining);
    }

    public function getUptimeFormattedAttribute(): string
    {
        $seconds = $this->uptime_used;
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        return "{$days}d {$hours}h {$minutes}m";
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired')
            ->orWhere(function ($q) {
                $q->whereNotNull('expiration_date')
                    ->where('expiration_date', '<', now());
            });
    }

    public function scopeHotspot($query)
    {
        return $query->whereIn('type', ['hotspot', 'both']);
    }

    public function scopePpp($query)
    {
        return $query->whereIn('type', ['ppp', 'both']);
    }

    public function scopeOnline($query)
    {
        return $query->whereHas('activeSessions');
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('username', 'like', "%{$search}%")
                ->orWhere('full_name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%");
        });
    }

    // Helpers
    protected function formatBytes($bytes, $precision = 2): string
    {
        if ($bytes == 0) return '0 B';
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
