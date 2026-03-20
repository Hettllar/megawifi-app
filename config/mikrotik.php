<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MikroTik Connection Settings
    |--------------------------------------------------------------------------
    |
    | These settings control how the application connects to MikroTik routers
    |
    */

    'connection' => [
        // Default connection timeout in seconds
        'timeout' => env('MIKROTIK_TIMEOUT', 5),

        // Maximum number of connection retry attempts
        'max_retries' => env('MIKROTIK_MAX_RETRIES', 1),

        // Delay between retries in seconds (uses exponential backoff)
        'retry_delay' => env('MIKROTIK_RETRY_DELAY', 1),

        // Default API port
        'api_port' => env('MIKROTIK_API_PORT', 8728),
    ],

    /*
    |--------------------------------------------------------------------------
    | Synchronization Settings
    |--------------------------------------------------------------------------
    |
    | Control how often and when synchronization occurs
    |
    */

    'sync' => [
        // How often to sync router data (in minutes)
        'interval' => env('MIKROTIK_SYNC_INTERVAL', 5),

        // How often to perform full sync (in minutes)
        'full_sync_interval' => env('MIKROTIK_FULL_SYNC_INTERVAL', 30),

        // Enable automatic synchronization
        'auto_sync' => env('MIKROTIK_AUTO_SYNC', true),

        // Maximum concurrent sync jobs
        'max_concurrent_jobs' => env('MIKROTIK_MAX_SYNC_JOBS', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    |
    | Settings for queue workers handling router sync
    |
    */

    'queue' => [
        // Queue name for router sync jobs
        'name' => env('MIKROTIK_QUEUE_NAME', 'router-sync'),

        // Maximum job timeout in seconds
        'timeout' => env('MIKROTIK_JOB_TIMEOUT', 180),

        // Number of times to retry a failed job
        'max_tries' => env('MIKROTIK_JOB_MAX_TRIES', 5),

        // Backoff time between retries in seconds
        'backoff' => env('MIKROTIK_JOB_BACKOFF', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Settings
    |--------------------------------------------------------------------------
    |
    | Control logging behavior for MikroTik operations
    |
    */

    'logging' => [
        // Enable detailed connection logging
        'detailed_logs' => env('MIKROTIK_DETAILED_LOGS', true),

        // Log successful syncs
        'log_success' => env('MIKROTIK_LOG_SUCCESS', true),

        // Log failed connection attempts
        'log_failures' => env('MIKROTIK_LOG_FAILURES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    |
    | Configure how errors are handled
    |
    */

    'errors' => [
        // Mark router offline after this many consecutive errors
        'offline_threshold' => env('MIKROTIK_OFFLINE_THRESHOLD', 3),

        // Send notification after this many consecutive errors
        'notification_threshold' => env('MIKROTIK_NOTIFICATION_THRESHOLD', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | WireGuard VPN Settings
    |--------------------------------------------------------------------------
    |
    | Settings for WireGuard VPN connections to routers
    |
    */

    'wireguard' => [
        // Enable WireGuard connections
        'enabled' => env('WIREGUARD_ENABLED', true),

        // Prefer WireGuard over direct connection when available
        'prefer_vpn' => env('WIREGUARD_PREFER_VPN', true),

        // VPN connection timeout
        'timeout' => env('WIREGUARD_TIMEOUT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Throttle Settings
    |--------------------------------------------------------------------------
    |
    | Settings for throttling users when they exceed data limits
    |
    */

    'throttle' => [
        // Profile name to use when throttling users (must exist on router)
        'profile' => env('THROTTLE_PROFILE', 'STOP'),
    ],

];
