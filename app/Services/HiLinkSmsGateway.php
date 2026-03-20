<?php

namespace App\Services;

use App\Models\Router;
use App\Models\SmsLog;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Central SMS Gateway using Huawei HiLink USB modem via MikroTik router.
 * All routers send SMS through the gateway router (TEST) which has the physical modem.
 */
class HiLinkSmsGateway
{
    private MikroTikAPI $api;
    private Router $gatewayRouter;
    private string $modemIp;
    private bool $connected = false;

    /**
     * Gateway router ID (TEST router with Huawei E303 modem)
     */
    const GATEWAY_ROUTER_ID = 14;
    const MODEM_IP = '192.168.1.1';
    const DELAY_BETWEEN_SMS = 3; // seconds between messages (E303 is slow)
    const DAILY_LIMIT = 200; // max SMS per day

    public function __construct(?Router $gatewayRouter = null)
    {
        $this->gatewayRouter = $gatewayRouter ?? Router::find(self::GATEWAY_ROUTER_ID);
        $this->modemIp = self::MODEM_IP;

        if (!$this->gatewayRouter) {
            throw new Exception('Gateway router (TEST) not found in database');
        }

        $connectionIP = $this->gatewayRouter->wg_enabled && $this->gatewayRouter->wg_client_ip
            ? $this->gatewayRouter->wg_client_ip
            : $this->gatewayRouter->ip_address;

        $this->api = new MikroTikAPI(
            $connectionIP,
            $this->gatewayRouter->api_port,
            $this->gatewayRouter->api_username,
            $this->gatewayRouter->api_password
        );
    }

    /**
     * Connect to gateway router
     */
    public function connect(): bool
    {
        $this->connected = $this->api->connect();
        return $this->connected;
    }

    /**
     * Disconnect from gateway router
     */
    public function disconnect(): void
    {
        if ($this->connected) {
            $this->api->disconnect();
            $this->connected = false;
        }
    }

    /**
     * Check if daily limit reached
     */
    public function isDailyLimitReached(): bool
    {
        $todayCount = SmsLog::whereDate('created_at', today())
            ->where('status', SmsLog::STATUS_SENT)
            ->count();

        return $todayCount >= self::DAILY_LIMIT;
    }

    /**
     * Get today's sent count
     */
    public function getTodaySentCount(): int
    {
        return SmsLog::whereDate('created_at', today())
            ->where('status', SmsLog::STATUS_SENT)
            ->count();
    }

    /**
     * Check modem status via HiLink API
     */
    public function checkModemStatus(): array
    {
        try {
            // Get device info
            $deviceInfo = $this->fetchFromModem('/api/device/information');
            $smsCount = $this->fetchFromModem('/api/sms/sms-count');
            $signalInfo = $this->fetchFromModem('/api/device/signal');

            $deviceName = $this->parseXml($deviceInfo, 'DeviceName') ?? 'Unknown';
            $imei = $this->parseXml($deviceInfo, 'Imei') ?? '';
            $imsi = $this->parseXml($deviceInfo, 'Imsi') ?? '';

            $localInbox = (int) ($this->parseXml($smsCount, 'LocalInbox') ?? 0);
            $localOutbox = (int) ($this->parseXml($smsCount, 'LocalOutbox') ?? 0);
            $localMax = (int) ($this->parseXml($smsCount, 'LocalMax') ?? 500);

            return [
                'connected' => true,
                'device_name' => $deviceName,
                'imei' => $imei,
                'imsi' => $imsi,
                'inbox_count' => $localInbox,
                'outbox_count' => $localOutbox,
                'storage_max' => $localMax,
                'gateway_router' => $this->gatewayRouter->name,
                'modem_ip' => $this->modemIp,
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'gateway_router' => $this->gatewayRouter->name ?? 'N/A',
            ];
        }
    }

    /**
     * Send SMS via HiLink API
     */
    public function sendSms(string $phone, string $message, ?int $routerId = null, ?int $subscriberId = null, string $type = SmsLog::TYPE_MANUAL): SmsLog
    {
        // Create log entry
        $log = SmsLog::create([
            'router_id' => $routerId ?? $this->gatewayRouter->id,
            'subscriber_id' => $subscriberId,
            'phone_number' => $phone,
            'message' => $message,
            'type' => $type,
            'status' => SmsLog::STATUS_PENDING,
        ]);

        // Check daily limit
        if ($this->isDailyLimitReached()) {
            $log->markAsFailed('تم تجاوز الحد اليومي للرسائل (' . self::DAILY_LIMIT . ')');
            return $log;
        }

        try {
            // Format phone number
            $formattedPhone = $this->formatPhone($phone);

            // Build SMS XML
            $smsXml = '<?xml version="1.0" encoding="UTF-8"?>' .
                '<request>' .
                '<Index>-1</Index>' .
                '<Phones><Phone>' . htmlspecialchars($formattedPhone) . '</Phone></Phones>' .
                '<Sca></Sca>' .
                '<Content>' . htmlspecialchars($message) . '</Content>' .
                '<Length>' . mb_strlen($message) . '</Length>' .
                '<Reserved>1</Reserved>' .
                '<Date>' . date('Y-m-d H:i:s') . '</Date>' .
                '</request>';

            // Send via MikroTik fetch → HiLink API
            $result = $this->api->comm([
                '/tool/fetch',
                '=url=http://' . $this->modemIp . '/api/sms/send-sms',
                '=mode=http',
                '=http-method=post',
                '=http-data=' . $smsXml,
                '=http-header-field=Content-Type: text/xml',
                '=as-value=',
                '=output=user',
            ]);

            // Check response
            $responseData = '';
            foreach ($result as $r) {
                if (isset($r['data'])) {
                    $responseData .= $r['data'];
                }
            }

            if (str_contains($responseData, '<response>OK</response>')) {
                // Wait and verify send status
                sleep(2);
                $sendStatus = $this->fetchFromModem('/api/sms/send-status');
                $sucPhone = $this->parseXml($sendStatus, 'SucPhone') ?? '';
                $failPhone = $this->parseXml($sendStatus, 'FailPhone') ?? '';

                if (!empty($failPhone)) {
                    $log->markAsFailed('فشل في إرسال الرسالة إلى: ' . $failPhone);
                } else {
                    $log->markAsSent();
                    Log::info("SMS sent via HiLink to {$formattedPhone}", [
                        'router_id' => $routerId,
                        'type' => $type,
                    ]);
                }
            } elseif (str_contains($responseData, '<error>')) {
                $errorCode = $this->parseXml($responseData, 'code') ?? 'unknown';
                $log->markAsFailed('HiLink error code: ' . $errorCode);
            } else {
                // If we get !done without error, assume success
                $hasDone = false;
                foreach ($result as $r) {
                    if (isset($r[0]) && $r[0] === '!done') {
                        $hasDone = true;
                    }
                }
                if ($hasDone && empty($responseData)) {
                    $log->markAsSent();
                } else {
                    $log->markAsFailed('Unexpected response: ' . substr($responseData, 0, 200));
                }
            }

            return $log;

        } catch (Exception $e) {
            $log->markAsFailed($e->getMessage());
            Log::error("HiLink SMS failed to {$phone}: " . $e->getMessage());
            return $log;
        }
    }

    /**
     * Send SMS to multiple numbers with delay
     */
    public function sendBulk(array $recipients, string $message, ?int $routerId = null, string $type = SmsLog::TYPE_MANUAL): array
    {
        $results = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'logs' => []];

        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'] ?? null;
            $subscriberId = $recipient['subscriber_id'] ?? null;
            $customMessage = $recipient['message'] ?? $message;

            if (empty($phone)) {
                $results['skipped']++;
                continue;
            }

            if ($this->isDailyLimitReached()) {
                $results['skipped'] += count($recipients) - ($results['sent'] + $results['failed'] + $results['skipped']);
                Log::warning('SMS daily limit reached, stopping bulk send');
                break;
            }

            try {
                $log = $this->sendSms($phone, $customMessage, $routerId, $subscriberId, $type);
                $results['logs'][] = $log;

                if ($log->status === SmsLog::STATUS_SENT) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }

                // Delay between messages
                sleep(self::DELAY_BETWEEN_SMS);

            } catch (Exception $e) {
                $results['failed']++;
                Log::error("Bulk SMS failed for {$phone}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Read inbox messages from modem
     */
    public function readInbox(int $page = 1, int $count = 20): array
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' .
            '<request>' .
            '<PageIndex>' . $page . '</PageIndex>' .
            '<ReadCount>' . $count . '</ReadCount>' .
            '<BoxType>1</BoxType>' .
            '<SortType>0</SortType>' .
            '<Ascending>0</Ascending>' .
            '<UnreadPreferred>0</UnreadPreferred>' .
            '</request>';

        try {
            $result = $this->api->comm([
                '/tool/fetch',
                '=url=http://' . $this->modemIp . '/api/sms/sms-list',
                '=mode=http',
                '=http-method=post',
                '=http-data=' . $xml,
                '=http-header-field=Content-Type: text/xml',
                '=as-value=',
                '=output=user',
            ]);

            $responseData = '';
            foreach ($result as $r) {
                if (isset($r['data'])) {
                    $responseData .= $r['data'];
                }
            }

            // Parse messages
            $messages = [];
            preg_match_all('/<Message>(.*?)<\/Message>/s', $responseData, $matches);

            foreach ($matches[1] as $msgXml) {
                $messages[] = [
                    'phone' => $this->parseXml($msgXml, 'Phone') ?? '',
                    'content' => $this->parseXml($msgXml, 'Content') ?? '',
                    'date' => $this->parseXml($msgXml, 'Date') ?? '',
                    'index' => $this->parseXml($msgXml, 'Index') ?? '',
                ];
            }

            $totalCount = (int) ($this->parseXml($responseData, 'Count') ?? 0);

            return [
                'success' => true,
                'messages' => $messages,
                'total' => $totalCount,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'messages' => [],
            ];
        }
    }

    /**
     * Fetch data from HiLink modem via MikroTik
     */
    private function fetchFromModem(string $apiPath): string
    {
        $result = $this->api->comm([
            '/tool/fetch',
            '=url=http://' . $this->modemIp . $apiPath,
            '=mode=http',
            '=as-value=',
            '=output=user',
        ]);

        $data = '';
        foreach ($result as $r) {
            if (isset($r['data'])) {
                $data .= $r['data'];
            }
        }

        return $data;
    }

    /**
     * Parse XML tag value
     */
    private function parseXml(string $xml, string $tag): ?string
    {
        if (preg_match('/<' . preg_quote($tag, '/') . '>(.*?)<\/' . preg_quote($tag, '/') . '>/s', $xml, $match)) {
            return $match[1];
        }
        return null;
    }

    /**
     * Format phone number for Syria
     */
    private function formatPhone(string $phone): string
    {
        // Remove spaces, dashes, special chars
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // If starts with 00, replace with +
        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        // If starts with 0, add +963
        if (str_starts_with($phone, '0')) {
            $phone = '+963' . substr($phone, 1);
        }

        // If doesn't start with +, add +963
        if (!str_starts_with($phone, '+')) {
            if (str_starts_with($phone, '963')) {
                $phone = '+' . $phone;
            } else {
                $phone = '+963' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Get gateway statistics
     */
    public static function getGlobalStats(): array
    {
        return [
            'today' => [
                'total' => SmsLog::whereDate('created_at', today())->count(),
                'sent' => SmsLog::whereDate('created_at', today())->where('status', SmsLog::STATUS_SENT)->count(),
                'failed' => SmsLog::whereDate('created_at', today())->where('status', SmsLog::STATUS_FAILED)->count(),
            ],
            'week' => [
                'total' => SmsLog::whereBetween('created_at', [now()->startOfWeek(), now()])->count(),
                'sent' => SmsLog::whereBetween('created_at', [now()->startOfWeek(), now()])->where('status', SmsLog::STATUS_SENT)->count(),
                'failed' => SmsLog::whereBetween('created_at', [now()->startOfWeek(), now()])->where('status', SmsLog::STATUS_FAILED)->count(),
            ],
            'month' => [
                'total' => SmsLog::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
                'sent' => SmsLog::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->where('status', SmsLog::STATUS_SENT)->count(),
                'failed' => SmsLog::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->where('status', SmsLog::STATUS_FAILED)->count(),
            ],
            'daily_limit' => self::DAILY_LIMIT,
            'today_remaining' => max(0, self::DAILY_LIMIT - SmsLog::whereDate('created_at', today())->where('status', SmsLog::STATUS_SENT)->count()),
        ];
    }
}
