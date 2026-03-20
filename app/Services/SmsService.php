<?php

namespace App\Services;

use App\Models\Router;
use App\Models\Subscriber;
use App\Models\SmsSettings;
use App\Models\SmsLog;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * SMS Service - Uses centralized HiLink Gateway for sending
 * Connects to individual routers only for subscriber data (UserManager)
 */
class SmsService
{
    private ?MikroTikAPI $api = null;
    private Router $router;
    private ?SmsSettings $settings;
    private ?ZteSmsGateway $gateway = null;

    public function __construct(Router $router)
    {
        $this->router = $router;
        $this->settings = SmsSettings::where('router_id', $router->id)->first();

        // Use WireGuard IP if enabled, otherwise use public IP
        $connectionIP = $router->wg_enabled && $router->wg_client_ip
            ? $router->wg_client_ip
            : $router->ip_address;

        $this->api = new MikroTikAPI(
            $connectionIP,
            $router->api_port,
            $router->api_username,
            $router->api_password
        );
    }

    /**
     * Get/create the centralized gateway
     */
    private function getGateway(): ZteSmsGateway
    {
        if (!$this->gateway) {
            $this->gateway = new ZteSmsGateway();
        }
        return $this->gateway;
    }

    /**
     * Connect to router (for subscriber data)
     */
    public function connect(): bool
    {
        return $this->api->connect();
    }

    /**
     * Disconnect from router
     */
    public function disconnect(): void
    {
        $this->api->disconnect();
        if ($this->gateway) {
            $this->gateway->disconnect();
            $this->gateway = null;
        }
    }

    /**
     * Check gateway modem status (centralized)
     */
    public function checkModemStatus(): array
    {
        try {
            $gateway = $this->getGateway();
            $gateway->connect();
            $status = $gateway->checkModemStatus();
            $gateway->disconnect();
            return $status;
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send SMS via centralized HiLink gateway
     */
    public function sendSms(string $phoneNumber, string $message, ?int $subscriberId = null, string $type = SmsLog::TYPE_MANUAL): SmsLog
    {
        try {
            $gateway = $this->getGateway();
            $gateway->connect();

            $log = $gateway->sendSms(
                $phoneNumber,
                $message,
                $this->router->id,
                $subscriberId,
                $type
            );

            return $log;
        } catch (Exception $e) {
            // Create failed log
            $log = SmsLog::create([
                'router_id' => $this->router->id,
                'subscriber_id' => $subscriberId,
                'phone_number' => $phoneNumber,
                'message' => $message,
                'type' => $type,
                'status' => SmsLog::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
            Log::error("SMS failed to {$phoneNumber}: " . $e->getMessage());
            return $log;
        }
    }

    /**
     * Send test SMS
     */
    public function testSms(string $phoneNumber, string $message = null): SmsLog
    {
        $message = $message ?? 'رسالة اختبارية من نظام MegaWiFi - ' . now()->format('Y-m-d H:i');
        return $this->sendSms($phoneNumber, $message, null, SmsLog::TYPE_MANUAL);
    }

    /**
     * Send welcome SMS to new subscriber
     */
    public function sendWelcomeSms(Subscriber $subscriber): ?SmsLog
    {
        if (!$subscriber->phone) {
            return null;
        }

        $settings = $this->settings;
        if (!$settings || !$settings->welcome_enabled) {
            return null;
        }

        $message = $this->buildWelcomeMessage($subscriber);

        return $this->sendSms(
            $subscriber->phone,
            $message,
            $subscriber->id,
            SmsLog::TYPE_WELCOME
        );
    }

    /**
     * Build welcome message from template
     */
    private function buildWelcomeMessage(Subscriber $subscriber): string
    {
        $message = $this->settings->welcome_message
            ?? 'مرحباً {name}! تم تفعيل اشتراكك في خدمة الإنترنت. اسم المستخدم: {username}. شكراً لاختيارك MegaWiFi!';

        $replacements = [
            '{name}' => $subscriber->full_name ?: $subscriber->username,
            '{username}' => $subscriber->username,
            '{service}' => $subscriber->profile ?: 'الإنترنت',
            '{date}' => $subscriber->expiration_date ? $subscriber->expiration_date->format('Y-m-d') : '',
            '{phone}' => $subscriber->phone ?? '',
            '{plan}' => $subscriber->profile ?: '',
            '{router}' => $this->router->name ?? '',
            '{password}' => $subscriber->password ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Send reminder SMS to subscriber
     */
    public function sendReminder(Subscriber $subscriber): ?SmsLog
    {
        if (!$subscriber->phone) {
            return null;
        }

        if (!$this->settings) {
            throw new Exception('SMS settings not configured for this router');
        }

        $message = $this->settings->parseMessage($subscriber);

        return $this->sendSms(
            $subscriber->phone,
            $message,
            $subscriber->id,
            SmsLog::TYPE_REMINDER
        );
    }

    /**
     * Get UserManager users with phone numbers from MikroTik
     */
    public function getUserManagerUsers(): array
    {
        try {
            $users = $this->api->comm(['/tool/user-manager/user/print']);
            return is_array($users) ? $users : [];
        } catch (Exception $e) {
            Log::error("Failed to get UserManager users from {$this->router->name}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get expiring UserManager users with phone numbers
     */
    public function getExpiringUserManagerUsers(int $daysBeforeExpiry): array
    {
        $users = $this->getUserManagerUsers();
        $expiringUsers = [];
        $targetDate = now()->addDays($daysBeforeExpiry)->startOfDay();
        $targetDateEnd = $targetDate->copy()->endOfDay();

        foreach ($users as $user) {
            $phone = $user['phone'] ?? $user['caller-id'] ?? null;
            if (empty($phone)) continue;

            $expiresAt = null;
            if (!empty($user['actual-profile-end-time'])) {
                $expiresAt = $this->parseUserManagerDate($user['actual-profile-end-time']);
            } elseif (!empty($user['end-time'])) {
                $expiresAt = $this->parseUserManagerDate($user['end-time']);
            }

            if (!$expiresAt) continue;

            if ($expiresAt >= $targetDate && $expiresAt <= $targetDateEnd) {
                $expiringUsers[] = [
                    'username' => $user['name'] ?? $user['username'] ?? '',
                    'name' => $user['comment'] ?? $user['name'] ?? '',
                    'phone' => $phone,
                    'expires_at' => $expiresAt,
                    'profile' => $user['actual-profile'] ?? $user['group'] ?? '',
                    'um_id' => $user['.id'] ?? null,
                ];
            }
        }

        return $expiringUsers;
    }

    /**
     * Parse UserManager date format
     */
    private function parseUserManagerDate(string $dateStr): ?\Carbon\Carbon
    {
        try {
            return \Carbon\Carbon::parse($dateStr);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Get subscribers with expiring subscriptions (from local DB)
     */
    public function getExpiringSubscribers(int $daysBeforeExpiry): Collection
    {
        $targetDate = now()->addDays($daysBeforeExpiry)->startOfDay();

        return Subscriber::where('router_id', $this->router->id)
            ->whereNotNull('phone')
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', $targetDate)
            ->where(function($query) use ($daysBeforeExpiry) {
                $query->whereNull('last_sms_sent_at')
                    ->orWhere('last_sms_sent_at', '<', now()->subDays($daysBeforeExpiry + 1));
            })
            ->get();
    }

    /**
     * Send bulk reminders for expiring subscriptions
     */
    public function sendExpiryReminders(): array
    {
        if (!$this->settings || !$this->settings->is_enabled) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'error' => 'SMS disabled'];
        }

        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        // First try to get users directly from UserManager
        $umUsers = $this->getExpiringUserManagerUsers($this->settings->reminder_days_before);

        if (!empty($umUsers)) {
            foreach ($umUsers as $user) {
                try {
                    // Check if we already sent reminder today
                    $alreadySent = SmsLog::where('router_id', $this->router->id)
                        ->where('phone_number', 'like', '%' . preg_replace('/[^0-9]/', '', $user['phone']))
                        ->where('type', SmsLog::TYPE_REMINDER)
                        ->whereDate('created_at', today())
                        ->exists();

                    if ($alreadySent) {
                        $results['skipped']++;
                        continue;
                    }

                    $message = $this->buildReminderMessage($user);

                    $log = $this->sendSms(
                        $user['phone'],
                        $message,
                        null,
                        SmsLog::TYPE_REMINDER
                    );

                    if ($log && $log->status === SmsLog::STATUS_SENT) {
                        $results['sent']++;
                    } else {
                        $results['failed']++;
                    }

                    sleep(ZteSmsGateway::DELAY_BETWEEN_SMS);

                } catch (Exception $e) {
                    $results['failed']++;
                    Log::error("Failed to send reminder to {$user['username']}: " . $e->getMessage());
                }
            }
        }

        // Fallback to local DB subscribers
        $subscribers = $this->getExpiringSubscribers($this->settings->reminder_days_before);

        // Skip phones already sent to today (from UserManager path above)
        $alreadySentPhones = SmsLog::where('router_id', $this->router->id)
            ->where('type', SmsLog::TYPE_REMINDER)
            ->where('status', SmsLog::STATUS_SENT)
            ->whereDate('created_at', today())
            ->pluck('phone_number')
            ->map(fn($p) => preg_replace('/[^0-9]/', '', $p))
            ->toArray();

        foreach ($subscribers as $subscriber) {
            // Skip if already got a reminder today
            $cleanPhone = preg_replace('/[^0-9]/', '', $subscriber->phone ?? '');
            if (!empty($cleanPhone) && in_array($cleanPhone, $alreadySentPhones)) {
                $results['skipped']++;
                continue;
            }

            try {
                $log = $this->sendReminder($subscriber);

                if ($log && $log->status === SmsLog::STATUS_SENT) {
                    $results['sent']++;
                    $subscriber->update(['last_sms_sent_at' => now()]);
                } else {
                    $results['skipped']++;
                }

                sleep(ZteSmsGateway::DELAY_BETWEEN_SMS);

            } catch (Exception $e) {
                $results['failed']++;
                Log::error("Failed to send reminder to subscriber {$subscriber->id}: " . $e->getMessage());
            }
        }

        Log::info("Expiry reminders sent for router {$this->router->id}", $results);
        return $results;
    }

    /**
     * Build reminder message from UserManager user data
     */
    private function buildReminderMessage(array $user): string
    {
        $message = $this->settings->reminder_message ?? SmsSettings::getDefaultMessage();

        $replacements = [
            '{name}' => $user['name'] ?: $user['username'],
            '{username}' => $user['username'],
            '{service}' => $user['profile'] ?: 'الإنترنت',
            '{date}' => $user['expires_at']->format('Y-m-d'),
            '{days}' => now()->diffInDays($user['expires_at']),
            '{phone}' => $user['phone'],
            '{plan}' => $user['profile'] ?: '',
            '{router}' => $this->router->name ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Send SMS to all UserManager users with phone numbers
     */
    public function sendToAllUsers(string $message): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $users = $this->getUserManagerUsers();

        foreach ($users as $user) {
            $phone = $user['phone'] ?? $user['caller-id'] ?? null;

            if (empty($phone)) {
                $results['skipped']++;
                continue;
            }

            $phone = preg_replace('/[^0-9+]/', '', $phone);
            if (strlen($phone) < 9) {
                $results['skipped']++;
                continue;
            }

            try {
                $log = $this->sendSms(
                    $phone,
                    $message,
                    null,
                    SmsLog::TYPE_MANUAL
                );

                if ($log && $log->status === SmsLog::STATUS_SENT) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }

                sleep(ZteSmsGateway::DELAY_BETWEEN_SMS);

            } catch (Exception $e) {
                $results['failed']++;
                Log::error("Failed to send SMS to {$phone}: " . $e->getMessage());
            }
        }

        Log::info("Bulk SMS sent for router {$this->router->id}", $results);
        return $results;
    }


    /**
     * Send SMS to unpaid subscribers only (is_paid = false or remaining_amount > 0)
     */
    public function sendToUnpaidUsers(string $message): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        
        // Get unpaid subscribers from database who have phone numbers
        $unpaidSubscribers = \App\Models\Subscriber::where('router_id', $this->router->id)
            ->where(function($q) {
                $q->where('is_paid', false)
                  ->orWhere('remaining_amount', '>', 0);
            })
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where('status', '!=', 'disabled')
            ->get();

        foreach ($unpaidSubscribers as $subscriber) {
            $phone = $subscriber->phone;
            $phone = preg_replace('/[^0-9+]/', '', $phone);
            
            if (strlen($phone) < 9) {
                $results['skipped']++;
                continue;
            }

            try {
                $log = $this->sendSms(
                    $phone,
                    $message,
                    $subscriber->id,
                    SmsLog::TYPE_MANUAL
                );

                if ($log && $log->status === SmsLog::STATUS_SENT) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }

                sleep(ZteSmsGateway::DELAY_BETWEEN_SMS);

            } catch (Exception $e) {
                $results['failed']++;
                Log::error("Failed to send SMS to unpaid {$phone}: " . $e->getMessage());
            }
        }

        Log::info("Bulk SMS to UNPAID sent for router {$this->router->id}", $results);
        return $results;
    }

        /**
     * Get SMS statistics for router
     */
    public function getStatistics(string $period = 'today'): array
    {
        $query = SmsLog::where('router_id', $this->router->id);

        switch ($period) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereMonth('created_at', now()->month)
                      ->whereYear('created_at', now()->year);
                break;
        }

        return [
            'total' => $query->count(),
            'sent' => (clone $query)->where('status', SmsLog::STATUS_SENT)->count(),
            'failed' => (clone $query)->where('status', SmsLog::STATUS_FAILED)->count(),
            'pending' => (clone $query)->where('status', SmsLog::STATUS_PENDING)->count(),
            'by_type' => [
                'reminder' => (clone $query)->where('type', SmsLog::TYPE_REMINDER)->count(),
                'manual' => (clone $query)->where('type', SmsLog::TYPE_MANUAL)->count(),
                'expiry' => (clone $query)->where('type', SmsLog::TYPE_EXPIRY)->count(),
                'welcome' => (clone $query)->where('type', SmsLog::TYPE_WELCOME)->count(),
            ],
        ];
    }

    /**
     * Get recent SMS logs
     */
    public function getRecentLogs(int $limit = 50): Collection
    {
        return SmsLog::where('router_id', $this->router->id)
            ->with('subscriber')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
