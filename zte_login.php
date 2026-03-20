<?php
/**
 * ZTE MC801A Login with correct SHA256 (UPPERCASE hex)
 * Algorithm: password = SHA256(SHA256(plain_password) + LD)
 * SHA256 returns UPPERCASE hex per ZTE's util.js implementation
 */

$zteIp = '192.168.100.1';
$password = 'Aa123455';

echo "=== ZTE MC801A Correct Login Test ===\n\n";

// Step 1: Get LD token with cookie support
$cookieFile = '/tmp/zte_cookies.txt';
@unlink($cookieFile);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://{$zteIp}/goform/goform_get_cmd_process?cmd=LD",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Referer: http://192.168.100.1/',
        'X-Requested-With: XMLHttpRequest',
    ],
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$ldResp = curl_exec($ch);
curl_close($ch);

$ldData = json_decode($ldResp, true);
$ld = $ldData['LD'] ?? '';
echo "1. LD token: {$ld}\n";
echo "   LD length: " . strlen($ld) . "\n";

if (empty($ld)) {
    echo "ERROR: No LD token received!\n";
    exit(1);
}

// Step 2: Compute password hash with UPPERCASE SHA256
$sha256_password = strtoupper(hash('sha256', $password));
echo "\n2. SHA256(password) [UPPER]: {$sha256_password}\n";

$sha256_final = strtoupper(hash('sha256', $sha256_password . $ld));
echo "3. SHA256(SHA256(pwd) + LD) [UPPER]: {$sha256_final}\n";

// Step 3: Login
$postData = http_build_query([
    'isTest' => 'false',
    'goformId' => 'LOGIN',
    'password' => $sha256_final,
]);

echo "\n4. POST data: {$postData}\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://{$zteIp}/goform/goform_set_cmd_process",
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Referer: http://192.168.100.1/',
        'X-Requested-With: XMLHttpRequest',
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$loginResp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\n5. Login response (HTTP {$httpCode}): {$loginResp}\n";

$loginData = json_decode($loginResp, true);
$result = $loginData['result'] ?? 'unknown';

$meanings = [
    '0' => 'SUCCESS!',
    '1' => 'Login Fail',
    '2' => 'Duplicate User',
    '3' => 'Bad Password',
    '4' => 'SUCCESS (alt)',
    '5' => 'Account Locked',
];

echo "   Result: {$result} = " . ($meanings[$result] ?? 'Unknown') . "\n";

// Show cookies
echo "\n6. Cookies:\n";
if (file_exists($cookieFile)) {
    echo file_get_contents($cookieFile) . "\n";
}

// If login succeeded, try to read SMS
if ($result === '0' || $result === '4') {
    echo "\n=== LOGIN SUCCESS! Testing SMS read ===\n";
    
    // First get RD for AD computation
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$zteIp}/goform/goform_get_cmd_process?cmd=RD&multi_data=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Referer: http://192.168.100.1/',
            'X-Requested-With: XMLHttpRequest',
        ],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $rdResp = curl_exec($ch);
    curl_close($ch);
    echo "\n7. RD response: {$rdResp}\n";
    
    // Try SMS read without AD first
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$zteIp}/goform/goform_get_cmd_process?" . http_build_query([
            'cmd' => 'sms_data_total',
            'page' => 0,
            'data_per_page' => 10,
            'mem_store' => 1,
            'tags' => 10,
            'order_by' => 'order by id desc',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Referer: http://192.168.100.1/',
            'X-Requested-With: XMLHttpRequest',
        ],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $smsResp = curl_exec($ch);
    curl_close($ch);
    echo "\n8. SMS data: {$smsResp}\n";
    
    // Try modem status
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$zteIp}/goform/goform_get_cmd_process?cmd=sms_received_flag,sms_unread_num,sms_capacity_info&multi_data=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Referer: http://192.168.100.1/',
            'X-Requested-With: XMLHttpRequest',
        ],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $smsInfo = curl_exec($ch);
    curl_close($ch);
    echo "\n9. SMS info: {$smsInfo}\n";
} else {
    echo "\n=== Login failed, trying lowercase SHA256 as comparison ===\n";
    
    // Try with lowercase
    @unlink($cookieFile);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$zteIp}/goform/goform_get_cmd_process?cmd=LD",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Referer: http://192.168.100.1/', 'X-Requested-With: XMLHttpRequest'],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $ldResp2 = curl_exec($ch);
    curl_close($ch);
    $ld2 = json_decode($ldResp2, true)['LD'] ?? '';
    
    $sha1_lower = hash('sha256', $password);
    $sha2_lower = hash('sha256', $sha1_lower . $ld2);
    
    echo "  lowercase: SHA256(SHA256(pwd)+LD) = {$sha2_lower}\n";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$zteIp}/goform/goform_set_cmd_process",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'isTest' => 'false',
            'goformId' => 'LOGIN',
            'password' => $sha2_lower,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Referer: http://192.168.100.1/',
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $r2 = curl_exec($ch);
    curl_close($ch);
    echo "  lowercase result: {$r2}\n";
}
