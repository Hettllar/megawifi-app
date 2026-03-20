<?php
/**
 * Try ZTE via MikroTik /tool/fetch + try REBOOT_DEVICE
 */
require '/var/www/megawifi/vendor/autoload.php';
$app = require_once '/var/www/megawifi/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\MikroTikAPI;
use App\Models\Router;

echo "=== ZTE via MikroTik /tool/fetch ===\n\n";

$router = Router::find(14); // TEST router
$api = new MikroTikAPI(
    $router->wg_client_ip,
    $router->api_port,
    $router->api_username,
    $router->api_password
);

echo "1. Connecting to TEST router...\n";
if (!$api->connect()) {
    echo "   FAILED to connect to TEST router!\n";
    exit(1);
}
echo "   ✓ Connected to TEST\n\n";

// Try fetching ZTE status (no auth needed)
echo "2. Fetching ZTE status via MikroTik...\n";
$result = $api->comm([
    '/tool/fetch',
    '=url=http://192.168.100.1/goform/goform_get_cmd_process?isTest=false&cmd=wa_inner_version,signalbar,network_type,modem_main_state&multi_data=1',
    '=mode=http',
    '=as-value=',
    '=output=user',
]);
$data = '';
foreach ($result as $r) { if (isset($r['data'])) $data .= $r['data']; }
echo "   Response: {$data}\n\n";

// Try reboot command (might work without auth on some ZTE models)
echo "3. Trying ZTE reboot via goform...\n";
$result = $api->comm([
    '/tool/fetch',
    '=url=http://192.168.100.1/goform/goform_set_cmd_process',
    '=mode=http',
    '=http-method=post',
    '=http-data=isTest=false&goformId=REBOOT_DEVICE',
    '=http-header-field=Content-Type: application/x-www-form-urlencoded,Referer: http://192.168.100.1/index.html,X-Requested-With: XMLHttpRequest',
    '=as-value=',
    '=output=user',
]);
$data = '';
foreach ($result as $r) { if (isset($r['data'])) $data .= $r['data']; }
echo "   Reboot response: {$data}\n\n";

// Also try login via MikroTik fetch 
echo "4. Trying login via MikroTik fetch...\n";
// Get LD first
$result = $api->comm([
    '/tool/fetch',
    '=url=http://192.168.100.1/goform/goform_get_cmd_process?isTest=false&cmd=LD&multi_data=1',
    '=mode=http',
    '=as-value=',
    '=output=user',
]);
$data = '';
foreach ($result as $r) { if (isset($r['data'])) $data .= $r['data']; }
echo "   LD response: {$data}\n";

$ldData = json_decode($data, true);
$ld = $ldData['LD'] ?? '';
echo "   LD: {$ld}\n";

if ($ld) {
    $passHash = strtoupper(hash('sha256', 'admin'));
    $loginHash = strtoupper(hash('sha256', $passHash . $ld));
    
    $loginData = http_build_query([
        'isTest' => 'false',
        'goformId' => 'LOGIN',
        'password' => $loginHash,
    ]);
    
    $result = $api->comm([
        '/tool/fetch',
        '=url=http://192.168.100.1/goform/goform_set_cmd_process',
        '=mode=http',
        '=http-method=post',
        '=http-data=' . $loginData,
        '=http-header-field=Content-Type: application/x-www-form-urlencoded,Referer: http://192.168.100.1/index.html,X-Requested-With: XMLHttpRequest',
        '=as-value=',
        '=output=user',
    ]);
    $data = '';
    foreach ($result as $r) { if (isset($r['data'])) $data .= $r['data']; }
    echo "   Login response: {$data}\n\n";
    
    $loginResult = json_decode($data, true)['result'] ?? '';
    
    if ($loginResult === '0' || $loginResult === '3') {
        echo "5. Logged in (result={$loginResult}). Getting RD and sending SMS...\n";
        
        // Get wa_inner_version
        $result = $api->comm([
            '/tool/fetch',
            '=url=http://192.168.100.1/goform/goform_get_cmd_process?isTest=false&cmd=wa_inner_version&multi_data=1',
            '=mode=http',
            '=as-value=',
            '=output=user',
        ]);
        $data = '';
        foreach ($result as $r) { if (isset($r['data'])) $data .= $r['data']; }
        $version = json_decode($data, true)['wa_inner_version'] ?? 'MC801A1_Elisa1_B06';
        echo "   Version: {$version}\n";
        
        // Get RD
        $result = $api->comm([
            '/tool/fetch',
            '=url=http://192.168.100.1/goform/goform_get_cmd_process?isTest=false&cmd=RD&multi_data=1',
            '=mode=http',
            '=as-value=',
            '=output=user',
        ]);
        $data = '';
        foreach ($result as $r) { if (isset($r['data'])) $data .= $r['data']; }
        $rd = json_decode($data, true)['RD'] ?? '';
        $ad = md5(md5($version) . $rd);
        echo "   RD: {$rd}\n";
        echo "   AD: {$ad}\n";
        
        // Send SMS
        $phone = '+963939122666';
        $message = 'Test ZTE via MikroTik - MegaWiFi';
        $encoded = strtoupper(bin2hex(mb_convert_encoding($message, 'UCS-2BE', 'UTF-8')));
        $smsTime = date('y;m;d;H;i;s;+3');
        
        $smsData = http_build_query([
            'isTest' => 'false',
            'goformId' => 'SEND_SMS',
            'notCallback' => 'true',
            'Number' => $phone,
            'sms_time' => $smsTime,
            'MessageBody' => $encoded,
            'ID' => '-1',
            'encode_type' => 'UNICODE',
            'AD' => $ad,
        ]);
        
        echo "\n   SMS data: {$smsData}\n";
        
        $result = $api->comm([
            '/tool/fetch',
            '=url=http://192.168.100.1/goform/goform_set_cmd_process',
            '=mode=http',
            '=http-method=post',
            '=http-data=' . $smsData,
            '=http-header-field=Content-Type: application/x-www-form-urlencoded,Referer: http://192.168.100.1/index.html,X-Requested-With: XMLHttpRequest',
            '=as-value=',
            '=output=user',
        ]);
        $data = '';
        foreach ($result as $r) { if (isset($r['data'])) $data .= $r['data']; }
        echo "   SMS result: {$data}\n";
    } else {
        echo "   Login failed with result: {$loginResult}\n";
    }
}

$api->disconnect();
echo "\nDone.\n";
