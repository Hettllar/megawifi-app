<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IptvChannel extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'source_url',
        'logo',
        'category',
        'language',
        'stream_format',
        'is_active',
        'sort_order',
        'description'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($channel) {
            if (!$channel->slug) {
                $channel->slug = Str::slug($channel->name);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    public function getStreamUrlAttribute()
    {
        return route('iptv.stream', ['channel' => $this->slug]);
    }
}
