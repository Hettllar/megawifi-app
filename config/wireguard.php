<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WireGuard Server Configuration
    |--------------------------------------------------------------------------
    */

    'endpoint' => env('WIREGUARD_ENDPOINT', '152.53.128.114:51820'),
    
    'server_public_key' => env('WIREGUARD_SERVER_PUBLIC_KEY', 'grZHPw4NCDEFciiKoNbjZLGeldZpltru+5CUOF66l2I='),
    
    'server_ip' => env('WIREGUARD_SERVER_IP', '10.10.0.1'),
    
    'subnet' => env('WIREGUARD_SUBNET', '10.10.0.0/24'),
    
    'interface' => env('WIREGUARD_INTERFACE', 'wg0'),
    
    'port' => env('WIREGUARD_PORT', 51820),
    
    'router_listen_port' => env('WIREGUARD_ROUTER_PORT', 13231),
];
