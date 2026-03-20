<?php
/**
 * ZTE MC801A - SMS Send Test
 * Send to 0939122666
 */

$zteIp = '192.168.100.1';
$password = 'Aa123455';
$cookieFile = '/tmp/zte_cookies.txt';
$testNumber = '0939122666';

function zteLogin($ip, $password, $cookieFile) {
    @unlink($cookieFile);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$ip}/goform/goform_get_cmd_process?cmd=LD",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Referer: http://'.$ip.'/', 'X-Requested-With: XMLHttpRequest'],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $ld = $resp['LD'] ?? '';
    if (empty($ld)) return false;
    
    $hash = strtoupper(hash('sha256', strtoupper(hash('sha256', $password)) . $ld));
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$ip}/goform/goform_set_cmd_process",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'isTest' => 'false',
            'goformId' => 'LOGIN',
            'password' => $hash,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Referer: http://'.$ip.'/',
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $resp = json_decode(curl_exec($ch), true);
    curl_close($ch);
    return ($resp['result'] ?? '') === '0' || ($resp['result'] ?? '') === '4';
}

function zteGet($ip, $params, $cookieFile) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$ip}/goform/goform_get_cmd_process?" . http_build_query($params),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Referer: http://'.$ip.'/', 'X-Requested-With: XMLHttpRequest'],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

function encodeToUcs2($text) {
    $hex = '';
    $len = mb_strlen($text, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($text, $i, 1, 'UTF-8');
        $cp = mb_ord($char, 'UTF-8');
        $hex .= sprintf('%04X', $cp);
    }
    return $hex;
}

echo "=== ZTE SMS Send Test ===\n\n";

// Login
echo "1. Logging in...\n";
if (!zteLogin($zteIp, $password, $cookieFile)) {
    echo "   FAILED!\n";
    exit(1);
}
echo "   SUCCESS\n";

// Get RD for AD
$rdResp = zteGet($zteIp, ['cmd' => 'RD', 'multi_data' => 1], $cookieFile);
$rd = $rdResp['RD'] ?? '';
echo "\n2. RD: {$rd}\n";

// Compute AD with empty rd0/rd1 (since they returned empty)
$ad = md5(md5('') . $rd);
echo "   AD (empty rd0+rd1): {$ad}\n";

// Prepare SMS
$message = 'تجربة - Test from MEGA WIFI System';
$encodedMsg = encodeToUcs2($message);
$smsTime = date('y;m;d;H;i;s;') . '+12';

echo "\n3. Sending SMS to {$testNumber}...\n";
echo "   Message: {$message}\n";
echo "   Encoded: {$encodedMsg}\n";
echo "   Time: {$smsTime}\n";

// Attempt 1: Without AD
echo "\n4. Attempt 1: WITHOUT AD...\n";
$sendData = [
    'isTest' => 'false',
    'goformId' => 'SEND_SMS',
    'notCallback' => 'true',
    'Number' => $testNumber,
    'sms_time' => $smsTime,
    'MessageBody' => $encodedMsg,
    'ID' => -1,
    'encode_type' => 'UNICODE',
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://{$zteIp}/goform/goform_set_cmd_process",
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($sendData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Referer: http://'.$zteIp.'/',
        'X-Requested-With: XMLHttpRequest',
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "   Response (HTTP {$code}): {$resp}\n";

$result = json_decode($resp, true);
if (($result['result'] ?? '') === 'success') {
    echo "   *** SMS SENT SUCCESSFULLY WITHOUT AD! ***\n";
} else {
    // Attempt 2: WITH AD
    echo "\n5. Attempt 2: WITH AD...\n";
    $sendData['AD'] = $ad;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$zteIp}/goform/goform_set_cmd_process",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($sendData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Referer: http://'.$zteIp.'/',
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $resp2 = curl_exec($ch);
    curl_close($ch);
    echo "   Response: {$resp2}\n";
    
    $result2 = json_decode($resp2, true);
    if (($result2['result'] ?? '') === 'success') {
        echo "   *** SMS SENT SUCCESSFULLY WITH AD! ***\n";
    } else {
        echo "\n   Both attempts failed. Let me try with re-login...\n";
        
        // Re-login and try again
        zteLogin($zteIp, $password, $cookieFile);
        
        // Fresh RD
        $rdResp2 = zteGet($zteIp, ['cmd' => 'RD', 'multi_data' => 1], $cookieFile);
        $rd2 = $rdResp2['RD'] ?? '';
        $ad2 = md5(md5('') . $rd2);
        
        $sendData['AD'] = $ad2;
        unset($sendData['notCallback']);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "http://{$zteIp}/goform/goform_set_cmd_process",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($sendData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Referer: http://'.$zteIp.'/',
                'X-Requested-With: XMLHttpRequest',
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_COOKIEFILE => $cookieFile,
        ]);
        $resp3 = curl_exec($ch);
        curl_close($ch);
        echo "   Attempt 3 response: {$resp3}\n";
    }
}

// Check send status
echo "\n6. Check SMS send status...\n";
sleep(3);
$sendStatus = zteGet($zteIp, ['cmd' => 'sms_cmd_status_info', 'multi_data' => 1], $cookieFile);
echo "   Send status: " . json_encode($sendStatus) . "\n";
