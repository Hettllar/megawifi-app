<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VpnConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'client_private_key',
        'client_public_key',
        'client_ip',
        'server_public_key',
        'server_endpoint',
        'dns',
        'allowed_ips',
        'is_active',
    ];

    protected $hidden = [
        'client_private_key',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}