<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'router_id',
        'name',
        'mikrotik_profile_name',
        'type',
        'rate_limit',
        'upload_speed',
        'download_speed',
        'session_timeout',
        'idle_timeout',
        'keepalive_timeout',
        'price',
        'validity_type',
        'validity_value',
        'data_limit',
        'shared_users',
        'shared_users_count',
        'address_pool',
        'local_address',
        'remote_address',
        'dns_server',
        'scripts',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'data_limit' => 'integer',
        'session_timeout' => 'integer',
        'idle_timeout' => 'integer',
        'keepalive_timeout' => 'integer',
        'validity_value' => 'integer',
        'shared_users' => 'boolean',
        'shared_users_count' => 'integer',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // Accessors
    public function getSpeedFormattedAttribute(): string
    {
        if ($this->rate_limit) {
            return $this->rate_limit;
        }
        
        $up = $this->upload_speed ?? '0';
        $down = $this->download_speed ?? '0';
        return "{$up}/{$down}";
    }

    public function getValidityFormattedAttribute(): string
    {
        if ($this->validity_type === 'unlimited') {
            return 'غير محدود';
        }
        
        $units = [
            'days' => 'يوم',
            'hours' => 'ساعة',
            'minutes' => 'دقيقة',
            'bytes' => 'بايت',
        ];
        
        return $this->validity_value . ' ' . ($units[$this->validity_type] ?? $this->validity_type);
    }

    public function getDataLimitFormattedAttribute(): string
    {
        if (!$this->data_limit) {
            return 'غير محدود';
        }
        
        return $this->formatBytes($this->data_limit);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeHotspot($query)
    {
        return $query->whereIn('type', ['hotspot', 'both']);
    }

    public function scopePpp($query)
    {
        return $query->whereIn('type', ['ppp', 'both']);
    }

    // Helpers
    protected function formatBytes($bytes, $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
