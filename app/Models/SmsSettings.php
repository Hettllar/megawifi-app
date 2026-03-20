<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'router_id',
        'usb_port',
        'country_code',
        'is_enabled',
        'reminder_days_before',
        'reminder_message',
        'sender_name',
        'send_time',
        'send_on_expiry',
        'send_after_expiry',
        'after_expiry_days',
        'welcome_enabled',
        'welcome_message',
        'disconnect_enabled',
        'disconnect_message',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'reminder_days_before' => 'integer',
        'send_on_expiry' => 'boolean',
        'send_after_expiry' => 'boolean',
        'after_expiry_days' => 'integer',
        'welcome_enabled' => 'boolean',
        'disconnect_enabled' => 'boolean',
    ];

    /**
     * Default reminder message template
     */
    public static function getDefaultMessage(): string
    {
        return "\xd8\xb9\xd8\xb2\xd9\x8a\xd8\xb2\xd9\x8a \xd8\xa7\xd9\x84\xd9\x85\xd8\xb4\xd8\xaa\xd8\xb1\xd9\x83 {name}\xd8\x8c \xd9\x8a\xd9\x86\xd8\xaa\xd9\x87\xd9\x8a \xd8\xa7\xd8\xb4\xd8\xaa\xd8\xb1\xd8\xa7\xd9\x83\xd9\x83 \xd9\x81\xd9\x8a {service} \xd8\xa8\xd8\xaa\xd8\xa7\xd8\xb1\xd9\x8a\xd8\xae {date}. \xd9\x84\xd9\x84\xd8\xaa\xd8\xac\xd8\xaf\xd9\x8a\xd8\xaf \xd8\xaa\xd9\x88\xd8\xa7\xd8\xb5\xd9\x84 \xd9\x85\xd8\xb9\xd9\x86\xd8\xa7.";
    }

    /**
     * Default welcome message template
     */
    public static function getDefaultWelcomeMessage(): string
    {
        return "\xd9\x85\xd8\xb1\xd8\xad\xd8\xa8\xd8\xa7\xd9\x8b {name}! \xd8\xaa\xd9\x85 \xd8\xaa\xd9\x81\xd8\xb9\xd9\x8a\xd9\x84 \xd8\xa7\xd8\xb4\xd8\xaa\xd8\xb1\xd8\xa7\xd9\x83\xd9\x83 \xd9\x81\xd9\x8a \xd8\xae\xd8\xaf\xd9\x85\xd8\xa9 {service}. \xd8\xa7\xd8\xb3\xd9\x85 \xd8\xa7\xd9\x84\xd9\x85\xd8\xb3\xd8\xaa\xd8\xae\xd8\xaf\xd9\x85: {username}. \xd8\xb4\xd9\x83\xd8\xb1\xd8\xa7\xd9\x8b \xd9\x84\xd8\xa7\xd8\xae\xd8\xaa\xd9\x8a\xd8\xa7\xd8\xb1\xd9\x83 MegaWiFi!";
    }

    /**
     * Default disconnect message template
     */
    public static function getDefaultDisconnectMessage(): string
    {
        return "\xd8\xb9\xd8\xb2\xd9\x8a\xd8\xb2\xd9\x8a \xd8\xa7\xd9\x84\xd9\x85\xd8\xb4\xd8\xaa\xd8\xb1\xd9\x83 {name}\xd8\x8c \xd8\xaa\xd9\x85 \xd8\xa5\xd9\x8a\xd9\x82\xd8\xa7\xd9\x81 \xd8\xae\xd8\xaf\xd9\x85\xd8\xaa\xd9\x83 ({service}). \xd9\x84\xd9\x84\xd8\xa7\xd8\xb3\xd8\xaa\xd9\x81\xd8\xb3\xd8\xa7\xd8\xb1 \xd8\xa3\xd9\x88 \xd8\xa5\xd8\xb9\xd8\xa7\xd8\xaf\xd8\xa9 \xd8\xa7\xd9\x84\xd8\xaa\xd9\x81\xd8\xb9\xd9\x8a\xd9\x84 \xd8\xaa\xd9\x88\xd8\xa7\xd8\xb5\xd9\x84 \xd9\x85\xd8\xb9\xd9\x86\xd8\xa7. MegaWiFi";
    }

    /**
     * Parse disconnect message template with subscriber data
     */
    public function parseDisconnectMessage(Subscriber $subscriber): string
    {
        $message = $this->disconnect_message ?? self::getDefaultDisconnectMessage();

        $replacements = [
            '{name}' => $subscriber->full_name ?? $subscriber->username,
            '{username}' => $subscriber->username,
            '{service}' => $subscriber->profile ?? "\xd8\xa7\xd9\x84\xd8\xa5\xd9\x86\xd8\xaa\xd8\xb1\xd9\x86\xd8\xaa",
            '{date}' => now()->format('Y-m-d'),
            '{phone}' => $subscriber->phone ?? '',
            '{plan}' => $subscriber->profile ?? '',
            '{router}' => $this->router->name ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Get the router that owns the SMS settings
     */
    public function router()
    {
        return $this->belongsTo(Router::class);
    }

    /**
     * Get SMS logs for this router
     */
    public function logs()
    {
        return $this->hasMany(SmsLog::class, 'router_id', 'router_id');
    }

    /**
     * Parse message template with subscriber data
     */
    public function parseMessage(Subscriber $subscriber): string
    {
        $message = $this->reminder_message ?? self::getDefaultMessage();

        $replacements = [
            '{name}' => $subscriber->full_name ?? $subscriber->username,
            '{username}' => $subscriber->username,
            '{service}' => $subscriber->profile ?? "\xd8\xa7\xd9\x84\xd8\xa5\xd9\x86\xd8\xaa\xd8\xb1\xd9\x86\xd8\xaa",
            '{date}' => $subscriber->expiration_date ? $subscriber->expiration_date->format('Y-m-d') : "\xd8\xba\xd9\x8a\xd8\xb1 \xd9\x85\xd8\xad\xd8\xaf\xd8\xaf",
            '{days}' => $subscriber->expiration_date ? now()->diffInDays($subscriber->expiration_date) : 0,
            '{phone}' => $subscriber->phone ?? '',
            '{plan}' => $subscriber->profile ?? '',
            '{router}' => $this->router->name ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Format phone number with country code
     */
    public function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = substr($phone, 1);
        }

        if (!str_starts_with($phone, ltrim($this->country_code, '+'))) {
            $phone = ltrim($this->country_code, '+') . $phone;
        }

        return '+' . $phone;
    }
}
