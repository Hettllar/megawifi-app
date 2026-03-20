<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hostname',
        'ssh_port',
        'ssh_username',
        'ssh_password',
        'location',
        'description',
        'status',
        'last_seen',
        'is_active',
        'public_host',
        'public_port',
        'connection_errors',
        'last_error',
        'last_error_at',
        'last_checked_at',
        'os_info',
        'hostname_resolved',
    ];

    protected $casts = [
        'last_seen' => 'datetime',
        'last_error_at' => 'datetime',
        'last_checked_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['ssh_password'];

    // ======================================================
    // Accessors
    // ======================================================

    public function getIsOnlineAttribute(): bool
    {
        return $this->status === 'online';
    }

    public function getSshAddressAttribute(): string
    {
        $host = $this->public_host ?? $this->hostname;
        $port = $this->public_port ?? $this->ssh_port;
        return "{$host}:{$port}";
    }

    public function getDecryptedPasswordAttribute(): ?string
    {
        if (!$this->ssh_password) return null;
        try {
            return Crypt::decryptString($this->ssh_password);
        } catch (\Exception $e) {
            return $this->ssh_password;
        }
    }

    // ======================================================
    // Scopes
    // ======================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOnline($query)
    {
        return $query->where('status', 'online');
    }
}
