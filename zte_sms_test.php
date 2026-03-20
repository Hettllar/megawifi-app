<?php
/**
 * ZTE MC801A - Full SMS Test (Read + Send)
 * Working login: SHA256(SHA256(password) + LD) with UPPERCASE hex
 */

$zteIp = '192.168.100.1';
$password = 'Aa123455';
$cookieFile = '/tmp/zte_cookies.txt';

// ====== HELPER FUNCTIONS ======

function zteLogin($ip, $password, $cookieFile) {
    @unlink($cookieFile);
    
    // Get LD token
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
    
    // SHA256(SHA256(password) + LD) UPPERCASE
    $hash1 = strtoupper(hash('sha256', $password));
    $hash2 = strtoupper(hash('sha256', $hash1 . $ld));
    
    // Login
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$ip}/goform/goform_set_cmd_process",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'isTest' => 'false',
            'goformId' => 'LOGIN',
            'password' => $hash2,
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

function ztePost($ip, $data, $cookieFile) {
    // Get AD for authenticated requests
    $rdResp = zteGet($ip, ['cmd' => 'RD', 'multi_data' => 1], $cookieFile);
    $rd = $rdResp['RD'] ?? '';
    
    if (!empty($rd)) {
        // AD = md5(md5(rd0 + rd1) + RD)  
        // But rd0/rd1 come from getLanguage... for now try without AD first
        // Actually looking at JS: rd0 and rd1 are stored from initial page load
        // Let's try the wa_inner_version approach
    }
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$ip}/goform/goform_set_cmd_process",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Referer: http://'.$ip.'/',
            'X-Requested-With: XMLHttpRequest',
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return json_decode($resp, true);
}

function decodeUcs2($hex) {
    $text = '';
    for ($i = 0; $i < strlen($hex); $i += 4) {
        $cp = hexdec(substr($hex, $i, 4));
        $text .= mb_chr($cp, 'UTF-8');
    }
    return $text;
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

// ====== MAIN ======

echo "=== ZTE MC801A SMS Full Test ===\n\n";

// Step 1: Login
echo "1. Logging in...\n";
$loggedIn = zteLogin($zteIp, $password, $cookieFile);
echo "   Login: " . ($loggedIn ? "SUCCESS" : "FAILED") . "\n";

if (!$loggedIn) {
    echo "Cannot proceed without login.\n";
    exit(1);
}

// Step 2: Read inbox
echo "\n2. Reading SMS inbox...\n";
$smsData = zteGet($zteIp, [
    'cmd' => 'sms_data_total',
    'page' => 0,
    'data_per_page' => 20,
    'mem_store' => 1,
    'tags' => 10,
    'order_by' => 'order by id desc',
], $cookieFile);

if (!empty($smsData['messages'])) {
    echo "   Found " . count($smsData['messages']) . " messages:\n\n";
    foreach ($smsData['messages'] as $msg) {
        $decoded = decodeUcs2($msg['content']);
        echo "   ID: {$msg['id']}\n";
        echo "   From: {$msg['number']}\n";
        echo "   Date: {$msg['date']}\n";
        echo "   Tag: {$msg['tag']} (0=unread, 1=read)\n";
        echo "   Content: " . mb_substr($decoded, 0, 100) . "...\n";
        echo "   ---\n";
    }
} else {
    echo "   No messages found or response: " . json_encode($smsData) . "\n";
}

// Step 3: Get AD for sending
echo "\n3. Getting RD for AD computation...\n";
$rdResp = zteGet($zteIp, ['cmd' => 'RD', 'multi_data' => 1], $cookieFile);
$rd = $rdResp['RD'] ?? '';
echo "   RD: {$rd}\n";

// Get language info for rd0/rd1
$langResp = zteGet($zteIp, ['cmd' => 'Language,cr_version,wa_inner_version', 'multi_data' => 1], $cookieFile);
echo "   Language resp: " . json_encode($langResp) . "\n";

// Step 4: Try sending SMS
echo "\n4. Sending test SMS...\n";

// Format the phone number and message
$testNumber = '0944065aborting'; // Don't actually send to real number yet
// Let's first check if we can send without AD

$testMessage = 'Test from ZTE MC801A';
$encodedMsg = encodeToUcs2($testMessage);
$smsTime = date('y;m;d;H;i;s;') . '+12'; // timezone offset

echo "   Encoded message: {$encodedMsg}\n";
echo "   SMS time: {$smsTime}\n";

// Step 5: Check modem status
echo "\n5. Modem status check...\n";
$statusCmds = 'modem_main_state,network_type,network_provider,signalbar,wan_ipaddr,sim_imsi,sim_iccid,sms_capacity_info,ppp_status';
$status = zteGet($zteIp, ['cmd' => $statusCmds, 'multi_data' => 1], $cookieFile);
echo "   Status: " . json_encode($status, JSON_PRETTY_PRINT) . "\n";

// Step 6: Test AD computation
echo "\n6. AD computation test...\n";
// From service.js: AD = hex_md5(hex_md5(rd0 + rd1) + RD)
// rd0 and rd1 are from /goform/goform_get_cmd_process?cmd=Language response?
// Actually rd0 and rd1 seem to be from the initial getLanguage call
// Let's try: in service.js, the Sr function fetches data synchronously
// rd0 is from getLanguage response, rd1 too
// Let me check if we can get them

// Try fetching rd0 and rd1
$rdVars = zteGet($zteIp, ['cmd' => 'rd0,rd1', 'multi_data' => 1], $cookieFile);
echo "   rd0/rd1: " . json_encode($rdVars) . "\n";

// Try wa_inner_version as potential rd value
$waResp = zteGet($zteIp, ['cmd' => 'wa_inner_version,cr_version', 'multi_data' => 1], $cookieFile);
echo "   wa/cr versions: " . json_encode($waResp) . "\n";

// Compute AD regardless - try with cr_version and wa_inner_version as rd0/rd1
$cr = $waResp['cr_version'] ?? '';
$wa = $waResp['wa_inner_version'] ?? '';
if ($cr && $wa && $rd) {
    // Try: AD = md5(md5(cr + wa) + RD)
    $ad1 = md5(md5($cr . $wa) . $rd);
    echo "   AD attempt (cr+wa): {$ad1}\n";
    
    // Try: AD = md5(md5(wa + cr) + RD)
    $ad2 = md5(md5($wa . $cr) . $rd);
    echo "   AD attempt (wa+cr): {$ad2}\n";
}

// Step 7: Try test SMS send (without AD first, then with AD)
echo "\n7. SMS Send test (to self number for safety)...\n";

// First, what's our own number?
$ownNum = zteGet($zteIp, ['cmd' => 'msisdn', 'multi_data' => 1], $cookieFile);
echo "   Own number: " . json_encode($ownNum) . "\n";

$phoneNum = zteGet($zteIp, ['cmd' => 'imsi,LocalDomain,wan_ipaddr,rmcc_mnc', 'multi_data' => 1], $cookieFile);
echo "   Phone info: " . json_encode($phoneNum) . "\n";
