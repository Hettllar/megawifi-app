<?php
/**
 * ZTE MC801A - Deep dive into AD computation and SMS send
 */

$zteIp = '192.168.100.1';
$password = 'Aa123455';
$cookieFile = '/tmp/zte_deep.txt';

function zteLogin($ip, $pw, $cf) {
    @unlink($cf);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$ip}/goform/goform_get_cmd_process?cmd=LD&multi_data=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Referer: http://'.$ip.'/', 'X-Requested-With: XMLHttpRequest'],
        CURLOPT_COOKIEJAR => $cf, CURLOPT_COOKIEFILE => $cf,
    ]);
    $r = json_decode(curl_exec($ch), true);
    curl_close($ch);
    $ld = $r['LD'] ?? '';
    if (empty($ld)) { echo "No LD!\n"; return false; }
    echo "LD: {$ld}\n";
    
    $h = strtoupper(hash('sha256', strtoupper(hash('sha256', $pw)) . $ld));
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$ip}/goform/goform_set_cmd_process",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => "isTest=false&goformId=LOGIN&password={$h}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Referer: http://'.$ip.'/', 'X-Requested-With: XMLHttpRequest', 'Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_COOKIEJAR => $cf, CURLOPT_COOKIEFILE => $cf,
    ]);
    $login = json_decode(curl_exec($ch), true);
    curl_close($ch);
    echo "Login result: " . ($login['result'] ?? '?') . "\n";
    return ($login['result'] ?? '') === '0';
}

function zteGet($ip, $cmd, $cf) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$ip}/goform/goform_get_cmd_process?cmd={$cmd}&multi_data=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Referer: http://'.$ip.'/', 'X-Requested-With: XMLHttpRequest'],
        CURLOPT_COOKIEJAR => $cf, CURLOPT_COOKIEFILE => $cf,
    ]);
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
}

echo "=== ZTE Deep SMS Analysis ===\n\n";

// Step 1: Download service.js and find exact SEND_SMS function
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://{$zteIp}/js/service.js",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Referer: http://192.168.100.1/'],
]);
$js = curl_exec($ch);
curl_close($ch);

// Find SEND_SMS
$pos = strpos($js, 'SEND_SMS');
if ($pos !== false) {
    echo "=== SEND_SMS context ===\n";
    echo substr($js, max(0, $pos - 200), 800) . "\n\n";
}

// Find AD usage in set commands
echo "\n=== AD usage in service.js ===\n";
$offset = 0;
$count = 0;
while (($pos = strpos($js, '.AD=', $offset)) !== false) {
    $count++;
    echo "--- AD #{$count} at {$pos} ---\n";
    echo substr($js, max(0, $pos - 100), 300) . "\n\n";
    $offset = $pos + 1;
    if ($count >= 5) break;
}

// Find how AD is generated (Sr function or similar)
echo "\n=== AD generation ===\n";
$patterns = ['accessibleId', 'hex_md5', 'getAD', 'function Sr', 'rd0', 'rd1'];
foreach ($patterns as $pat) {
    $pos = strpos($js, $pat);
    if ($pos !== false) {
        echo "--- [{$pat}] at {$pos} ---\n";
        echo substr($js, max(0, $pos - 100), 400) . "\n\n";
    }
}

// Now login and test various SMS send approaches
echo "\n\n=== Login & Send Tests ===\n";
$logged = zteLogin($zteIp, $password, $cookieFile);
if (!$logged) { echo "Login failed\n"; exit(1); }

// Get RD
$rdData = zteGet($zteIp, 'RD', $cookieFile);
$rd = $rdData['RD'] ?? '';
echo "RD: {$rd}\n";

// Get wa_inner_version and cr_version for rd0/rd1 computation
$versions = zteGet($zteIp, 'wa_inner_version,cr_version', $cookieFile);
echo "Versions: " . json_encode($versions) . "\n";

$wa = $versions['wa_inner_version'] ?? '';
$cr = $versions['cr_version'] ?? '';

// Try various AD computations
echo "\n=== AD computation variants ===\n";
$ads = [
    'empty_rd0_rd1' => md5(md5('') . $rd),
    'wa_only' => md5(md5($wa) . $rd),
    'cr_only' => md5(md5($cr) . $rd),
    'wa_cr' => md5(md5($wa . $cr) . $rd),
    'cr_wa' => md5(md5($cr . $wa) . $rd),
    'just_rd' => md5($rd),
];
foreach ($ads as $name => $ad) {
    echo "  {$name}: {$ad}\n";
}

// Encode message
$msg = 'Test MEGA';
$encoded = '';
for ($i = 0; $i < mb_strlen($msg, 'UTF-8'); $i++) {
    $encoded .= sprintf('%04X', mb_ord(mb_substr($msg, $i, 1, 'UTF-8'), 'UTF-8'));
}

$smsTime = date('y;m;d;H;i;s;') . '+12';

// Try each AD variant
echo "\n=== Sending with each AD variant ===\n";
foreach ($ads as $name => $ad) {
    // Re-login for each attempt to avoid session issues
    zteLogin($zteIp, $password, $cookieFile);
    
    // Get fresh RD
    $rdData = zteGet($zteIp, 'RD', $cookieFile);
    $freshRd = $rdData['RD'] ?? '';
    
    // Recompute AD with fresh RD
    switch ($name) {
        case 'empty_rd0_rd1': $freshAd = md5(md5('') . $freshRd); break;
        case 'wa_only': $freshAd = md5(md5($wa) . $freshRd); break;
        case 'cr_only': $freshAd = md5(md5($cr) . $freshRd); break;
        case 'wa_cr': $freshAd = md5(md5($wa . $cr) . $freshRd); break;
        case 'cr_wa': $freshAd = md5(md5($cr . $wa) . $freshRd); break;
        case 'just_rd': $freshAd = md5($freshRd); break;
    }
    
    $postData = http_build_query([
        'isTest' => 'false',
        'goformId' => 'SEND_SMS',
        'notCallback' => 'true',
        'Number' => '0939122666',
        'sms_time' => $smsTime,
        'MessageBody' => $encoded,
        'ID' => '-1',
        'encode_type' => 'UNICODE',
        'AD' => $freshAd,
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$zteIp}/goform/goform_set_cmd_process",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Referer: http://'.$zteIp.'/', 'X-Requested-With: XMLHttpRequest', 'Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_COOKIEJAR => $cookieFile, CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    
    echo "  [{$name}] AD={$freshAd} Result: {$resp}\n";
    
    if (strpos($resp, 'success') !== false) {
        echo "  *** SUCCESS with {$name}! ***\n";
        break;
    }
    
    sleep(1);
}
