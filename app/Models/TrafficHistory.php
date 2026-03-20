<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficHistory extends Model
{
    use HasFactory;

    protected $table = 'traffic_history';

    protected $fillable = [
        'router_id',
        'subscriber_id',
        'bytes_in',
        'bytes_out',
        'session_start',
        'session_end',
        'uptime',
        'recorded_at',
    ];

    protected $casts = [
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
        'uptime' => 'integer',
        'session_start' => 'datetime',
        'session_end' => 'datetime',
        'recorded_at' => 'datetime',
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
    
    // Get formatted session duration
    public function getFormattedDuration(): string
    {
        if (!$this->uptime) return '-';
        
        $hours = floor($this->uptime / 3600);
        $minutes = floor(($this->uptime % 3600) / 60);
        $seconds = $this->uptime % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    }
}
