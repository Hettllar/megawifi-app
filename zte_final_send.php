<?php
/**
 * ZTE MC801A - SMS Send with CORRECT AD
 * rd0 = wa_inner_version = "MC801A1_Elisa1_B06"
 * rd1 = cr_version = ""
 * AD = md5(md5(rd0 + rd1) + RD)
 */
$zteIp = '192.168.100.1';
$password = 'Aa123455';
$cookieFile = '/tmp/zte_final.txt';
$ref = "Referer: http://{$zteIp}/";
$xhr = 'X-Requested-With: XMLHttpRequest';
$ct = 'Content-Type: application/x-www-form-urlencoded';

@unlink($cookieFile);

// Step 1: Logout any existing session
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://{$zteIp}/goform/goform_set_cmd_process",
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => 'isTest=false&goformId=LOGOUT',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_HTTPHEADER => [$ref, $xhr, $ct],
]);
curl_exec($ch);
curl_close($ch);
sleep(1);

// Step 2: Get LD token
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://{$zteIp}/goform/goform_get_cmd_process?cmd=LD&multi_data=1",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [$ref, $xhr],
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$ldResp = json_decode(curl_exec($ch), true);
curl_close($ch);
$ld = $ldResp['LD'] ?? '';
echo "1. LD: {$ld}\n";

// Step 3: Login with SHA256(SHA256(password) + LD) UPPERCASE
$hash = strtoupper(hash('sha256', strtoupper(hash('sha256', $password)) . $ld));

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://{$zteIp}/goform/goform_set_cmd_process",
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => "isTest=false&goformId=LOGIN&password={$hash}",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [$ref, $xhr, $ct],
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$loginResp = json_decode(curl_exec($ch), true);
curl_close($ch);
$loginResult = $loginResp['result'] ?? '?';
echo "2. Login: result={$loginResult}\n";

if ($loginResult !== '0' && $loginResult !== '4') {
    echo "LOGIN FAILED!\n";
    exit(1);
}

// Step 4: Get wa_inner_version and cr_version (= rd0 and rd1)
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://{$zteIp}/goform/goform_get_cmd_process?cmd=Language,cr_version,wa_inner_version&multi_data=1",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [$ref, $xhr],
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$langResp = json_decode(curl_exec($ch), true);
curl_close($ch);

$rd0 = $langResp['wa_inner_version'] ?? '';
$rd1 = $langResp['cr_version'] ?? '';
echo "3. rd0 (wa_inner_version): {$rd0}\n";
echo "   rd1 (cr_version): {$rd1}\n";

// Step 5: Get RD
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://{$zteIp}/goform/goform_get_cmd_process?cmd=RD&multi_data=1",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [$ref, $xhr],
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$rdResp = json_decode(curl_exec($ch), true);
curl_close($ch);
$rd = $rdResp['RD'] ?? '';
echo "4. RD: {$rd}\n";

// Step 6: Compute AD = md5(md5(rd0 + rd1) + RD)
$ad = md5(md5($rd0 . $rd1) . $rd);
echo "5. AD = md5(md5('{$rd0}' + '{$rd1}') + '{$rd}') = {$ad}\n";

// Step 7: Encode message as UCS-2
$message = 'تجربة - Test MEGA WIFI';
$encodedMsg = '';
$len = mb_strlen($message, 'UTF-8');
for ($i = 0; $i < $len; $i++) {
    $char = mb_substr($message, $i, 1, 'UTF-8');
    $cp = mb_ord($char, 'UTF-8');
    $encodedMsg .= sprintf('%04X', $cp);
}

$smsTime = date('y;m;d;H;i;s;') . '+12';
$number = '0939122666';

echo "\n6. Sending SMS:\n";
echo "   To: {$number}\n";
echo "   Message: {$message}\n";
echo "   Encoded: {$encodedMsg}\n";
echo "   Time: {$smsTime}\n";
echo "   AD: {$ad}\n";

// Step 8: Send SMS with AD
$postData = http_build_query([
    'isTest' => 'false',
    'goformId' => 'SEND_SMS',
    'notCallback' => 'true',
    'Number' => $number,
    'sms_time' => $smsTime,
    'MessageBody' => $encodedMsg,
    'ID' => '-1',
    'encode_type' => 'UNICODE',
    'AD' => $ad,
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://{$zteIp}/goform/goform_set_cmd_process",
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $postData,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [$ref, $xhr, $ct],
    CURLOPT_COOKIEJAR => $cookieFile,
    CURLOPT_COOKIEFILE => $cookieFile,
]);
$sendResp = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "\n7. Send response (HTTP {$httpCode}): {$sendResp}\n";

$sendData = json_decode($sendResp, true);
if (($sendData['result'] ?? '') === 'success') {
    echo "\n*** SMS SENT SUCCESSFULLY! ***\n";
} else {
    echo "\nSend failed. Checking sms_cmd_status_info...\n";
    sleep(2);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$zteIp}/goform/goform_get_cmd_process?cmd=sms_cmd_status_info&multi_data=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [$ref, $xhr],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $statusResp = curl_exec($ch);
    curl_close($ch);
    echo "Status: {$statusResp}\n";
    
    // Also try without notCallback
    echo "\n8. Retry without notCallback...\n";
    
    // Get fresh RD and AD
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$zteIp}/goform/goform_get_cmd_process?cmd=RD&multi_data=1",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [$ref, $xhr],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $freshRd = json_decode(curl_exec($ch), true)['RD'] ?? '';
    curl_close($ch);
    
    $freshAd = md5(md5($rd0 . $rd1) . $freshRd);
    echo "   Fresh RD: {$freshRd}\n";
    echo "   Fresh AD: {$freshAd}\n";
    
    $postData2 = http_build_query([
        'isTest' => 'false',
        'goformId' => 'SEND_SMS',
        'Number' => $number,
        'sms_time' => $smsTime,
        'MessageBody' => $encodedMsg,
        'ID' => '-1',
        'encode_type' => 'UNICODE',
        'AD' => $freshAd,
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "http://{$zteIp}/goform/goform_set_cmd_process",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData2,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [$ref, $xhr, $ct],
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
    ]);
    $sendResp2 = curl_exec($ch);
    curl_close($ch);
    echo "   Result: {$sendResp2}\n";
    
    if (strpos($sendResp2, 'success') !== false) {
        echo "\n*** SMS SENT SUCCESSFULLY (retry)! ***\n";
    }
}
