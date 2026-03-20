<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IptvSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'subscriber_id',
        'username',
        'password',
        'expires_at',
        'is_active',
        'max_connections',
        'allowed_ips',
        'notes'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'max_connections' => 'integer'
    ];

    protected $hidden = [
        'password'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($subscription) {
            if (!$subscription->username) {
                $subscription->username = 'iptv_' . Str::random(8);
            }
            if (!$subscription->password) {
                $subscription->password = Str::random(12);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function getPlaylistUrlAttribute(): string
    {
        return route('iptv.playlist', [
            'username' => $this->username,
            'password' => $this->password
        ]);
    }
}
