<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActiveSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'router_id',
        'subscriber_id',
        'username',
        'session_id',
        'type',
        'mac_address',
        'ip_address',
        'caller_id',
        'nas_port_id',
        'started_at',
        'uptime',
        'bytes_in',
        'bytes_out',
        'rate_limit',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'uptime' => 'integer',
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
    ];

    // Relationships
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    // Accessors
    public function getUptimeFormattedAttribute(): string
    {
        $seconds = $this->uptime;
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    public function getTotalBytesAttribute(): int
    {
        return $this->bytes_in + $this->bytes_out;
    }

    public function getBytesFormattedAttribute(): string
    {
        return $this->formatBytes($this->bytes_in) . ' / ' . $this->formatBytes($this->bytes_out);
    }

    // Scopes
    public function scopeHotspot($query)
    {
        return $query->where('type', 'hotspot');
    }

    public function scopePpp($query)
    {
        return $query->where('type', 'ppp');
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
