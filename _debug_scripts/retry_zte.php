<?php
/**
 * Retry ZTE login after session timeout + send SMS
 */
echo "Waiting 180s for ZTE session timeout...\n";
sleep(180);
echo "Session should be expired now. Trying fresh login...\n\n";

$modemIp = '192.168.100.1';
$password = 'admin';
$cookieFile = '/tmp/zte_cookie_' . getmypid();
@unlink($cookieFile);

function zte($modemIp, $method, $url, $cookieFile, $postData = null) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => array_merge([
            "Referer: http://{$modemIp}/index.html",
            'X-Requested-With: XMLHttpRequest',
        ], $method === 'POST' ? ['Content-Type: application/x-www-form-urlencoded'] : []),
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_COOKIEJAR => $cookieFile,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    $r = curl_exec($ch);
    curl_close($ch);
    return json_decode($r, true);
}

// Get LD
$r = zte($modemIp, 'GET', "http://{$modemIp}/goform/goform_get_cmd_process?isTest=false&cmd=LD&multi_data=1", $cookieFile);
$ld = $r['LD'] ?? '';
echo "LD: {$ld}\n";

// Login
$passHash = strtoupper(hash('sha256', $password));
$loginHash = strtoupper(hash('sha256', $passHash . $ld));
$r = zte($modemIp, 'POST', "http://{$modemIp}/goform/goform_set_cmd_process", $cookieFile,
    http_build_query(['isTest' => 'false', 'goformId' => 'LOGIN', 'password' => $loginHash]));
echo "Login result: " . ($r['result'] ?? 'null') . "\n\n";

if (($r['result'] ?? '') === '3') {
    echo "STILL locked! Waiting 120s more...\n";
    sleep(120);
    
    @unlink($cookieFile);
    $r = zte($modemIp, 'GET', "http://{$modemIp}/goform/goform_get_cmd_process?isTest=false&cmd=LD&multi_data=1", $cookieFile);
    $ld = $r['LD'] ?? '';
    $passHash = strtoupper(hash('sha256', $password));
    $loginHash = strtoupper(hash('sha256', $passHash . $ld));
    $r = zte($modemIp, 'POST', "http://{$modemIp}/goform/goform_set_cmd_process", $cookieFile,
        http_build_query(['isTest' => 'false', 'goformId' => 'LOGIN', 'password' => $loginHash]));
    echo "Login result (retry2): " . ($r['result'] ?? 'null') . "\n\n";
}

$loginOk = in_array($r['result'] ?? '', ['0', '3']);

if ($loginOk) {
    // Get version
    $r = zte($modemIp, 'GET', "http://{$modemIp}/goform/goform_get_cmd_process?isTest=false&cmd=wa_inner_version&multi_data=1", $cookieFile);
    $version = $r['wa_inner_version'] ?? '';
    echo "Version: {$version}\n";
    
    // Get IMEI/IMSI (need auth)
    $r = zte($modemIp, 'GET', "http://{$modemIp}/goform/goform_get_cmd_process?isTest=false&cmd=imei_number,sim_imsi,modem_main_state,signalbar,network_type,rssi,lte_rsrp&multi_data=1", $cookieFile);
    echo "IMEI: " . ($r['imei_number'] ?? 'empty') . "\n";
    echo "IMSI: " . ($r['sim_imsi'] ?? 'empty') . "\n";
    echo "Signal: " . ($r['signalbar'] ?? '') . " bars\n";
    echo "Network: " . ($r['network_type'] ?? '') . "\n";
    echo "RSSI: " . ($r['rssi'] ?? '') . "\n\n";
    
    // Get RD for AD
    $r = zte($modemIp, 'GET', "http://{$modemIp}/goform/goform_get_cmd_process?isTest=false&cmd=RD&multi_data=1", $cookieFile);
    $rd = $r['RD'] ?? '';
    $ad = md5(md5($version) . $rd);
    echo "RD: {$rd}\n";
    echo "AD: {$ad}\n\n";
    
    // Send SMS
    $phone = '+963939122666';
    $message = 'Test ZTE SMS - MegaWiFi';
    $encoded = strtoupper(bin2hex(mb_convert_encoding($message, 'UCS-2BE', 'UTF-8')));
    $smsTime = date('y;m;d;H;i;s;+3');
    
    echo "Sending SMS to {$phone}...\n";
    echo "Encoded: {$encoded}\n";
    echo "Time: {$smsTime}\n";
    
    $r = zte($modemIp, 'POST', "http://{$modemIp}/goform/goform_set_cmd_process", $cookieFile,
        http_build_query([
            'isTest' => 'false',
            'goformId' => 'SEND_SMS',
            'notCallback' => 'true',
            'Number' => $phone,
            'sms_time' => $smsTime,
            'MessageBody' => $encoded,
            'ID' => '-1',
            'encode_type' => 'UNICODE',
            'AD' => $ad,
        ]));
    echo "Send result: " . json_encode($r) . "\n\n";
    
    // Logout 
    $r2 = zte($modemIp, 'GET', "http://{$modemIp}/goform/goform_get_cmd_process?isTest=false&cmd=RD&multi_data=1", $cookieFile);
    $ad2 = md5(md5($version) . ($r2['RD'] ?? ''));
    zte($modemIp, 'POST', "http://{$modemIp}/goform/goform_set_cmd_process", $cookieFile,
        http_build_query(['isTest' => 'false', 'goformId' => 'LOGOUT', 'AD' => $ad2]));
    echo "Logged out.\n";
} else {
    echo "Cannot login to ZTE. Result: " . ($r['result'] ?? 'null') . "\n";
    echo "Someone may be logged into the ZTE web interface.\n";
    echo "Please close any browser tabs open to http://192.168.100.1/\n";
}

@unlink($cookieFile);
echo "\nDone.\n";
