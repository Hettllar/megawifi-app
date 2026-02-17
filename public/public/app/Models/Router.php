<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Router extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'identity',
        'ip_address',
        'public_ip',
        'public_port',
        'api_port',
        'api_username',
        'api_password',
        'location',
        'description',
        'status',
        'last_seen',
        'router_os_version',
        'board_name',
        'serial_number',
        'uptime',
        'total_memory',
        'free_memory',
        'total_hdd',
        'free_hdd',
        'cpu_load',
        'is_active',
        'sync_enabled',
        'sync_interval',
        'last_toggle_sync',
        'whatsapp_type',
        'price_per_gb',
        'shamcash_qr',
        'brand_name',
        // WireGuard VPN fields
        'wg_enabled',
        'wg_private_key',
        'wg_public_key',
        'wg_client_ip',
        'wg_last_handshake',
        // Error tracking fields
        'connection_errors',
        'last_error',
        'last_error_at',
        'last_sync_at',
    ];

    protected $casts = [
        'last_seen' => 'datetime',
        'is_active' => 'boolean',
        'sync_enabled' => 'boolean',
        'wg_enabled' => 'boolean',
        'wg_last_handshake' => 'datetime',
        'last_toggle_sync' => 'datetime',
        'last_error_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'connection_errors' => 'integer',
        'api_port' => 'integer',
        'sync_interval' => 'integer',
        'uptime' => 'integer',
        'total_memory' => 'integer',
        'free_memory' => 'integer',
        'total_hdd' => 'integer',
        'free_hdd' => 'integer',
    ];

    protected $hidden = [
        'api_password',
        'wg_private_key',
    ];

    // Relationships
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'router_admins')
            ->withPivot(['role', 'can_add_users', 'can_delete_users', 'can_edit_users', 'can_view_reports', 'can_manage_hotspot', 'can_manage_ppp'])
            ->withTimestamps();
    }

    public function servicePlans(): HasMany
    {
        return $this->hasMany(ServicePlan::class);
    }

    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function activeSessions(): HasMany
    {
        return $this->hasMany(ActiveSession::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function trafficHistory(): HasMany
    {
        return $this->hasMany(TrafficHistory::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    // Accessors
    public function getMemoryUsagePercentAttribute(): float
    {
        if ($this->total_memory && $this->total_memory > 0) {
            return round((($this->total_memory - $this->free_memory) / $this->total_memory) * 100, 2);
        }
        return 0;
    }

    public function getHddUsagePercentAttribute(): float
    {
        if ($this->total_hdd && $this->total_hdd > 0) {
            return round((($this->total_hdd - $this->free_hdd) / $this->total_hdd) * 100, 2);
        }
        return 0;
    }

    public function getUptimeFormattedAttribute(): string
    {
        if (!$this->uptime) return 'N/A';
        
        $days = floor($this->uptime / 86400);
        $hours = floor(($this->uptime % 86400) / 3600);
        $minutes = floor(($this->uptime % 3600) / 60);
        
        return "{$days}d {$hours}h {$minutes}m";
    }

    public function getActiveSubscribersCountAttribute(): int
    {
        return $this->subscribers()->where('status', 'active')->count();
    }

    public function getActiveSessionsCountAttribute(): int
    {
        return $this->activeSessions()->count();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }

    public function scopeForUser($query, User $user)
    {
        if ($user->role === 'super_admin') {
            return $query;
        }
        
        return $query->whereHas('admins', function ($q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }

    // WireGuard helpers
    public function getWgConnectedAttribute(): bool
    {
        if (!$this->wg_enabled || !$this->wg_last_handshake) {
            return false;
        }
        // Consider connected if handshake was within last 3 minutes
        return $this->wg_last_handshake->diffInMinutes(now()) < 3;
    }

    public function getEffectiveIpAttribute(): string
    {
        // If WireGuard is enabled and connected, use the VPN IP
        if ($this->wg_enabled && $this->wg_client_ip) {
            return $this->wg_client_ip;
        }
        return $this->ip_address;
    }
}
