<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SyncSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get setting value by key
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("sync_setting_{$key}", 60, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set setting value
     */
    public static function set(string $key, $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        Cache::forget("sync_setting_{$key}");
    }

    /**
     * Check if auto sync is enabled
     */
    public static function isAutoSyncEnabled(): bool
    {
        return self::get('auto_sync_enabled', 'true') === 'true';
    }

    /**
     * Get sync interval in minutes
     */
    public static function getSyncInterval(): int
    {
        return (int) self::get('sync_interval', 5);
    }

    /**
     * Get full sync interval in minutes
     */
    public static function getFullSyncInterval(): int
    {
        return (int) self::get('full_sync_interval', 60);
    }

    /**
     * Check if toggle refresh is enabled
     */
    public static function isToggleRefreshEnabled(): bool
    {
        return self::get('toggle_refresh_enabled', 'false') === 'true';
    }

    /**
     * Get toggle refresh interval in minutes
     */
    public static function getToggleRefreshInterval(): int
    {
        return (int) self::get('toggle_refresh_interval', 1440); // Default 24 hours
    }

    /**
     * Get last toggle refresh time
     */
    public static function getLastToggleRefresh(): ?string
    {
        return self::get('last_toggle_refresh');
    }

    /**
     * Set last toggle refresh time
     */
    public static function setLastToggleRefresh(): void
    {
        self::set('last_toggle_refresh', now()->format('Y-m-d H:i:s'));
    }
}
