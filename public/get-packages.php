<?php
// Direct packages API endpoint for testing

require_once __DIR__ . "/../vendor/autoload.php";

$app = require_once __DIR__ . "/../bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\UserManagerService;

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

try {
    $routerId = $_GET["router_id"] ?? "12";
    
    // Create router object
    $router = new stdClass();
    $router->ip_address = "10.10.0.10";
    $router->api_port = 8728;
    $router->api_username = "admin";
    $router->api_password = "wes";
    
    // Adjust for different routers
    if ($routerId == "13") {
        $router->ip_address = "10.10.0.11";
    } elseif ($routerId == "14") {
        $router->ip_address = "10.10.0.12";
    }
    
    $service = new UserManagerService($router);
    
    if (!$service->connect()) {
        throw new Exception("فشل الاتصال بالراوتر");
    }
    
    $profiles = $service->getProfiles();
    $limitations = $service->getLimitations();
    
    $service->disconnect();
    
    echo json_encode([
        "success" => true,
        "profiles" => $profiles,
        "limitations" => $limitations,
        "router_id" => $routerId,
        "router_ip" => $router->ip_address
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}