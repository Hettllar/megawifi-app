<?php
$ip = '192.168.100.1';
$cf = '/tmp/zte_c2.txt';
$pw = 'Aa123455';

// Logout first
@unlink($cf);
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://$ip/goform/goform_set_cmd_process",
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'isTest=false&goformId=LOGOUT',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_HTTPHEADER => ['Referer: http://'.$ip.'/', 'X-Requested-With: XMLHttpRequest', 'Content-Type: application/x-www-form-urlencoded'],
]);
echo "Logout: " . curl_exec($ch) . "\n";
curl_close($ch);

sleep(1);

// Get LD
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://$ip/goform/goform_get_cmd_process?cmd=LD",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Referer: http://'.$ip.'/', 'X-Requested-With: XMLHttpRequest'],
    CURLOPT_COOKIEJAR => $cf,
    CURLOPT_COOKIEFILE => $cf,
]);
$r = json_decode(curl_exec($ch), true);
curl_close($ch);
$ld = $r['LD'] ?? '';
echo "LD: $ld (" . strlen($ld) . ")\n";

// Login
$h = strtoupper(hash('sha256', strtoupper(hash('sha256', $pw)) . $ld));
echo "Hash: $h\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://$ip/goform/goform_set_cmd_process",
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => "isTest=false&goformId=LOGIN&password=$h",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => ['Referer: http://'.$ip.'/', 'X-Requested-With: XMLHttpRequest', 'Content-Type: application/x-www-form-urlencoded'],
    CURLOPT_COOKIEJAR => $cf,
    CURLOPT_COOKIEFILE => $cf,
]);
$login = curl_exec($ch);
curl_close($ch);
echo "Login: $login\n";

$loginData = json_decode($login, true);
$result = $loginData['result'] ?? 'null';
echo "Result: $result\n";

if ($result === '0' || $result === '4') {
    echo "\n=== LOGGED IN - SENDING SMS ===\n";
    
    // Encode message as UCS-2
    $msg = 'تجربة - Test MEGA WIFI';
    $encoded = '';
    $len = mb_strlen($msg, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $encoded .= sprintf('%04X', mb_ord(mb_substr($msg, $i, 1, 'UTF-8'), 'UTF-8'));
    }
    
    $smsTime = date('y;m;d;H;i;s;') . '+12';
    $number = '0939122666';
    
    echo "Sending to: $number\n";
    echo "Message: $msg\n";
    echo "Time: $smsTime\n";
    
    // Try without AD
    $postFields = http_build_query([
        'isTest' => 'false',
        'goformId' => 'SEND_SMS',
        'notCallback' => 'true',
        'Number' => $number,
        'sms_time' => $smsTime,
        'MessageBody' => $encoded,
        'ID' => -1,
        'encode_type' => 'UNICODE',
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://$ip/goform/goform_set_cmd_process",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Referer: http://'.$ip.'/', 'X-Requested-With: XMLHttpRequest', 'Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_COOKIEJAR => $cf,
        CURLOPT_COOKIEFILE => $cf,
    ]);
    $sendResp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    echo "Send (no AD, HTTP $httpCode): $sendResp\n";
    
    if (strpos($sendResp, 'success') !== false) {
        echo "*** SMS SENT SUCCESSFULLY ***\n";
    } else {
        echo "Trying with AD...\n";
        
        // Get RD
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "http://$ip/goform/goform_get_cmd_process?cmd=RD&multi_data=1",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['Referer: http://'.$ip.'/', 'X-Requested-With: XMLHttpRequest'],
            CURLOPT_COOKIEJAR => $cf,
            CURLOPT_COOKIEFILE => $cf,
        ]);
        $rdResp = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $rd = $rdResp['RD'] ?? '';
        echo "RD: $rd\n";
        
        // AD with empty rd0+rd1
        $ad = md5(md5('') . $rd);
        echo "AD: $ad\n";
        
        $postFields .= '&AD=' . $ad;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "http://$ip/goform/goform_set_cmd_process",
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Referer: http://'.$ip.'/', 'X-Requested-With: XMLHttpRequest', 'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_COOKIEJAR => $cf,
            CURLOPT_COOKIEFILE => $cf,
        ]);
        echo "Send (with AD): " . curl_exec($ch) . "\n";
        curl_close($ch);
    }
    
    // Wait and check status
    sleep(3);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://$ip/goform/goform_get_cmd_process?cmd=sms_cmd_status_info&multi_data=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Referer: http://'.$ip.'/', 'X-Requested-With: XMLHttpRequest'],
        CURLOPT_COOKIEJAR => $cf,
        CURLOPT_COOKIEFILE => $cf,
    ]);
    echo "Send status: " . curl_exec($ch) . "\n";
    curl_close($ch);
}
