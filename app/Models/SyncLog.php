<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'router_id',
        'type',
        'status',
        'records_synced',
        'records_failed',
        'error_message',
        'duration',
    ];

    protected $casts = [
        'records_synced' => 'integer',
        'records_failed' => 'integer',
        'duration' => 'integer',
    ];

    // Relationships
    public function router(): BelongsTo
    {
        return $this->belongsTo(Router::class);
    }
}
