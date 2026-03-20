<?php

namespace App\Services;

use App\Models\SmsLog;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * ZTE MC801A SMS Gateway - Direct HTTP API
 * Sends SMS directly to ZTE router at 192.168.100.1 via WireGuard tunnel
 * Path: Server → WireGuard → TEST Router → ZTE MC801A
 * No MikroTik intermediary needed for SMS operations
 */
class ZteSmsGateway
{
    private string $modemIp;
    private string $password;
    private ?string $cookie = null;
    private bool $connected = false;
    private string $waInnerVersion = '';

    const GATEWAY_ROUTER_ID = 14;
    const MODEM_IP = '192.168.100.1';
    const MODEM_PASSWORD = 'Aa123455';
    const DELAY_BETWEEN_SMS = 2;
    const DAILY_LIMIT = 500;
    const TIMEOUT = 15;

    public function __construct(?string $modemIp = null, ?string $password = null)
    {
        $this->modemIp = $modemIp ?? self::MODEM_IP;
        $this->password = $password ?? self::MODEM_PASSWORD;
    }

    /**
     * Connect and login to ZTE modem
     */
    public function connect(): bool
    {
        try {
            // Get firmware version for AD calculation
            $info = $this->getCmd('wa_inner_version,cr_version');
            $this->waInnerVersion = $info['wa_inner_version'] ?? '';
            $crVersion = $info['cr_version'] ?? '';

            // Use cr_version if available, otherwise wa_inner_version
            if (!empty($crVersion)) {
                $this->waInnerVersion = $crVersion;
            }

            // Get LD for login hash computation
            $ldResponse = $this->getCmd('LD');
            $ld = $ldResponse['LD'] ?? '';

            if (empty($ld)) {
                throw new Exception('Failed to get LD from ZTE modem');
            }

            // ZTE auth: SHA256(SHA256(password).UPPER + LD).UPPER
            $passHash = strtoupper(hash('sha256', $this->password));
            $loginHash = strtoupper(hash('sha256', $passHash . $ld));

            // Login
            $loginResult = $this->postCmd('LOGIN', [
                'password' => $loginHash,
            ]);

            $result = $loginResult['result'] ?? '';

            if ($result === '0') {
                $this->connected = true;
                Log::info('ZTE SMS Gateway: Connected to ' . $this->modemIp);
                return true;
            } elseif ($result === '3') {
                // Already logged in
                $this->connected = true;
                return true;
            } else {
                throw new Exception('ZTE login failed, result: ' . $result);
            }
        } catch (Exception $e) {
            Log::error('ZTE SMS Gateway connection failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Disconnect (logout)
     */
    public function disconnect(): void
    {
        if ($this->connected) {
            try {
                $this->postCmd('LOGOUT', []);
            } catch (Exception $e) {
                // Ignore logout errors
            }
            $this->connected = false;
            $this->cookie = null;
        }
    }

    /**
     * Check daily limit
     */
    public function isDailyLimitReached(): bool
    {
        return SmsLog::whereDate('created_at', today())
            ->where('status', SmsLog::STATUS_SENT)
            ->count() >= self::DAILY_LIMIT;
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
     * Check modem status
     */
    public function checkModemStatus(): array
    {
        try {
            $status = $this->getCmd(
                'modem_main_state,signalbar,network_type,network_provider,' .
                'ppp_status,simcard_roam,lan_ipaddr,imei_number,imsi,' .
                'rssi,rscp,lte_rsrp,Z5g_snr,Z5g_rsrp,wan_ipaddr,' .
                'wa_inner_version,hardware_version'
            );

            $smsInfo = $this->getCmd('sms_received_flag,sms_unread_num');

            return [
                'connected' => true,
                'device_name' => 'ZTE MC801A',
                'firmware' => $status['wa_inner_version'] ?? '',
                'hardware' => $status['hardware_version'] ?? '',
                'imei' => $status['imei_number'] ?? '',
                'imsi' => $status['imsi'] ?? '',
                'signal_strength' => (int)($status['signalbar'] ?? 0),
                'network_type' => $status['network_type'] ?? '',
                'network_provider' => $status['network_provider'] ?? '',
                'connection_status' => $status['ppp_status'] ?? '',
                'wan_ip' => $status['wan_ipaddr'] ?? '',
                'lan_ip' => $status['lan_ipaddr'] ?? '',
                'rssi' => $status['rssi'] ?? '',
                'rsrp' => $status['lte_rsrp'] ?? $status['Z5g_rsrp'] ?? '',
                'snr' => $status['Z5g_snr'] ?? '',
                'unread_sms' => (int)($smsInfo['sms_unread_num'] ?? 0),
                'gateway_router' => 'TEST',
                'modem_ip' => $this->modemIp,
                // Compatibility fields for views expecting HiLink format
                'inbox_count' => (int)($smsInfo['sms_unread_num'] ?? 0),
                'outbox_count' => 0,
                'storage_max' => 500,
            ];
        } catch (Exception $e) {
            return [
                'connected' => false,
                'error' => $e->getMessage(),
                'gateway_router' => 'TEST',
            ];
        }
    }

    /**
     * Send SMS via ZTE API
     */
    public function sendSms(string $phone, string $message, ?int $routerId = null, ?int $subscriberId = null, string $type = SmsLog::TYPE_MANUAL): SmsLog
    {
        // Create log entry
        $log = SmsLog::create([
            'router_id' => $routerId ?? self::GATEWAY_ROUTER_ID,
            'subscriber_id' => $subscriberId,
            'phone_number' => $phone,
            'message' => $message,
            'type' => $type,
            'status' => SmsLog::STATUS_PENDING,
        ]);

        if ($this->isDailyLimitReached()) {
            $log->markAsFailed('تم تجاوز الحد اليومي للرسائل (' . self::DAILY_LIMIT . ')');
            return $log;
        }

        try {
            $formattedPhone = $this->formatPhone($phone);

            // Get RD for AD calculation
            $rdResponse = $this->getCmd('RD');
            $rd = $rdResponse['RD'] ?? '';

            // Compute AD = md5(md5(version) + RD)
            $ad = md5(md5($this->waInnerVersion) . $rd);

            // Encode message to UCS-2 hex
            $encodedMessage = $this->encodeUCS2($message);

            // Format SMS time: YY;MM;DD;HH;MM;SS;+TZ
            $tz = (int)date('P'); // timezone offset hours
            $smsTime = date('y;m;d;H;i;s;') . ($tz >= 0 ? '+' : '') . $tz;

            // Send SMS via goform
            $result = $this->postCmd('SEND_SMS', [
                'notCallback' => 'true',
                'Number' => $formattedPhone,
                'sms_time' => $smsTime,
                'MessageBody' => $encodedMessage,
                'ID' => '-1',
                'encode_type' => 'UNICODE',
                'AD' => $ad,
            ]);

            $resultCode = $result['result'] ?? '';

            if ($resultCode === 'success') {
                sleep(2);
                $log->markAsSent();
                Log::info("SMS sent via ZTE to {$formattedPhone}", [
                    'router_id' => $routerId,
                    'type' => $type,
                ]);
            } elseif ($resultCode === 'failure') {
                $log->markAsFailed('ZTE returned failure');
            } else {
                // Unknown result - log it but mark as sent if no error
                Log::warning("ZTE SMS unknown result: {$resultCode}");
                $log->markAsFailed('ZTE unexpected result: ' . $resultCode);
            }

            return $log;

        } catch (Exception $e) {
            $log->markAsFailed($e->getMessage());
            Log::error("ZTE SMS failed to {$phone}: " . $e->getMessage());
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

                sleep(self::DELAY_BETWEEN_SMS);
            } catch (Exception $e) {
                $results['failed']++;
                Log::error("Bulk SMS failed for {$phone}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Read inbox messages from ZTE
     */
    public function readInbox(int $page = 1, int $count = 20): array
    {
        try {
            $zeroPage = max(0, $page - 1);

            $result = $this->getCmd('sms_data_total', [
                'page' => (string)$zeroPage,
                'data_per_page' => (string)$count,
                'mem_store' => '1',
                'tags' => '10',
                'order_by' => 'order by id desc',
            ]);

            $messages = [];
            $messagesData = $result['messages'] ?? [];

            if (is_array($messagesData)) {
                foreach ($messagesData as $msg) {
                    $content = $msg['content'] ?? '';
                    // Decode UCS-2 hex if applicable
                    if (!empty($content) && preg_match('/^[0-9A-Fa-f]+$/', $content) && strlen($content) % 4 === 0) {
                        $content = $this->decodeUCS2($content);
                    }

                    $messages[] = [
                        'phone' => $msg['number'] ?? '',
                        'content' => $content,
                        'date' => $msg['date'] ?? '',
                        'index' => $msg['id'] ?? '',
                    ];
                }
            }

            return [
                'success' => true,
                'messages' => $messages,
                'total' => (int)($result['sms_data_total'] ?? 0),
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
     * GET command to ZTE goform API
     */
    private function getCmd(string $cmd, array $extraParams = []): array
    {
        $params = array_merge([
            'isTest' => 'false',
            'cmd' => $cmd,
            'multi_data' => '1',
        ], $extraParams);

        $url = 'http://' . $this->modemIp . '/goform/goform_get_cmd_process?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Referer: http://' . $this->modemIp . '/index.html',
                'X-Requested-With: XMLHttpRequest',
            ],
        ]);

        if ($this->cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
        }

        // Capture Set-Cookie headers
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) {
            if (stripos($header, 'Set-Cookie:') === 0) {
                $cookiePart = trim(substr($header, 11));
                $parts = explode(';', $cookiePart);
                $this->cookie = trim($parts[0]);
            }
            return strlen($header);
        });

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('ZTE API GET failed: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('ZTE API GET returned HTTP ' . $httpCode);
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new Exception('Invalid JSON from ZTE: ' . substr($response, 0, 200));
        }

        return $data;
    }

    /**
     * POST command to ZTE goform API
     */
    private function postCmd(string $goformId, array $params = []): array
    {
        $url = 'http://' . $this->modemIp . '/goform/goform_set_cmd_process';

        $postData = array_merge([
            'isTest' => 'false',
            'goformId' => $goformId,
        ], $params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Referer: http://' . $this->modemIp . '/index.html',
                'X-Requested-With: XMLHttpRequest',
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        if ($this->cookie) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->cookie);
        }

        // Capture Set-Cookie headers
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) {
            if (stripos($header, 'Set-Cookie:') === 0) {
                $cookiePart = trim(substr($header, 11));
                $parts = explode(';', $cookiePart);
                $this->cookie = trim($parts[0]);
            }
            return strlen($header);
        });

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('ZTE API POST failed: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('ZTE API POST returned HTTP ' . $httpCode);
        }

        $data = json_decode($response, true);
        if ($data === null) {
            throw new Exception('Invalid JSON from ZTE POST: ' . substr($response, 0, 200));
        }

        return $data;
    }

    /**
     * Encode text to UCS-2 hex for ZTE SMS
     */
    private function encodeUCS2(string $text): string
    {
        $encoded = mb_convert_encoding($text, 'UCS-2BE', 'UTF-8');
        return strtoupper(bin2hex($encoded));
    }

    /**
     * Decode UCS-2 hex to UTF-8
     */
    private function decodeUCS2(string $hex): string
    {
        $binary = hex2bin($hex);
        if ($binary === false) return $hex;
        return mb_convert_encoding($binary, 'UTF-8', 'UCS-2BE');
    }

    /**
     * Format phone number for Syria
     */
    private function formatPhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }

        if (str_starts_with($phone, '0')) {
            $phone = '+963' . substr($phone, 1);
        }

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
     * Get global gateway statistics
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
