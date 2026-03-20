<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'subscriber_id',
        'router_id',
        'username',
        'ip_address',
        'mac_address',
        'started_at',
        'created_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
