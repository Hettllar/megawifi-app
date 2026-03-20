<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SessionHistory extends Model
{
    use HasFactory;

    protected $table = 'session_history';

    protected $fillable = [
        'router_id',
        'subscriber_id',
        'username',
        'session_id',
        'um_session_id',
        'type',
        'mac_address',
        'ip_address',
        'started_at',
        'ended_at',
        'duration',
        'bytes_in',
        'bytes_out',
        'total_bytes',
        'source',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'bytes_in' => 'integer',
        'bytes_out' => 'integer',
        'total_bytes' => 'integer',
        'duration' => 'integer',
    ];

    public function router()
    {
        return $this->belongsTo(Router::class);
    }

    public function subscriber()
    {
        return $this->belongsTo(Subscriber::class);
    }

    /**
     * Save active session to history when it ends
     */
    public static function saveFromActiveSession($activeSession)
    {
        return self::create([
            'router_id' => $activeSession->router_id,
            'subscriber_id' => $activeSession->subscriber_id,
            'username' => $activeSession->username,
            'session_id' => $activeSession->session_id,
            'type' => $activeSession->type,
            'mac_address' => $activeSession->mac_address,
            'ip_address' => $activeSession->ip_address,
            'started_at' => $activeSession->started_at,
            'ended_at' => now(),
            'duration' => $activeSession->uptime ?? 0,
            'bytes_in' => $activeSession->bytes_in ?? 0,
            'bytes_out' => $activeSession->bytes_out ?? 0,
            'total_bytes' => ($activeSession->bytes_in ?? 0) + ($activeSession->bytes_out ?? 0),
        ]);
    }
}
