<?php
/**
 * Migration script: Switch from HiLinkSmsGateway to ZteSmsGateway
 * Run once on server to update SmsService.php and SmsController.php
 */

echo "=== Migrating SMS Gateway: HiLink → ZTE ===\n\n";

// 1. Update SmsService.php
$smsServiceFile = '/var/www/megawifi/app/Services/SmsService.php';
$content = file_get_contents($smsServiceFile);

if ($content === false) {
    echo "ERROR: Cannot read SmsService.php\n";
    exit(1);
}

$original = $content;

// Replace import
$content = str_replace(
    'use App\Services\HiLinkSmsGateway;',
    'use App\Services\ZteSmsGateway;',
    $content
);

// Replace property type
$content = str_replace(
    'private ?HiLinkSmsGateway $gateway = null;',
    'private ?ZteSmsGateway $gateway = null;',
    $content
);

// Replace getGateway return type and instantiation
$content = str_replace(
    'private function getGateway(): HiLinkSmsGateway',
    'private function getGateway(): ZteSmsGateway',
    $content
);
$content = str_replace(
    '$this->gateway = new HiLinkSmsGateway();',
    '$this->gateway = new ZteSmsGateway();',
    $content
);

// Replace DELAY_BETWEEN_SMS references
$content = str_replace(
    'HiLinkSmsGateway::DELAY_BETWEEN_SMS',
    'ZteSmsGateway::DELAY_BETWEEN_SMS',
    $content
);

if ($content !== $original) {
    // Backup original
    copy($smsServiceFile, $smsServiceFile . '.bak');
    file_put_contents($smsServiceFile, $content);
    echo "✓ SmsService.php updated successfully\n";

    // Verify changes
    $verify = file_get_contents($smsServiceFile);
    $checks = [
        'ZteSmsGateway' => strpos($verify, 'ZteSmsGateway') !== false,
        'No HiLink refs' => strpos($verify, 'HiLinkSmsGateway') === false,
    ];
    foreach ($checks as $check => $ok) {
        echo "  " . ($ok ? '✓' : '✗') . " {$check}\n";
    }
} else {
    echo "⚠ SmsService.php - no changes needed (already migrated?)\n";
}

echo "\n";

// 2. Update SmsController.php
$controllerFile = '/var/www/megawifi/app/Http/Controllers/SmsController.php';
$content = file_get_contents($controllerFile);

if ($content === false) {
    echo "ERROR: Cannot read SmsController.php\n";
    exit(1);
}

$original = $content;

// Replace import
$content = str_replace(
    'use App\Services\HiLinkSmsGateway;',
    'use App\Services\ZteSmsGateway;',
    $content
);

// Replace all instantiations
$content = str_replace(
    'new HiLinkSmsGateway()',
    'new ZteSmsGateway()',
    $content
);

// Replace static method calls
$content = str_replace(
    'HiLinkSmsGateway::getGlobalStats()',
    'ZteSmsGateway::getGlobalStats()',
    $content
);

// Replace any other static references
$content = str_replace(
    'HiLinkSmsGateway::',
    'ZteSmsGateway::',
    $content
);

if ($content !== $original) {
    copy($controllerFile, $controllerFile . '.bak');
    file_put_contents($controllerFile, $content);
    echo "✓ SmsController.php updated successfully\n";

    $verify = file_get_contents($controllerFile);
    $checks = [
        'ZteSmsGateway' => strpos($verify, 'ZteSmsGateway') !== false,
        'No HiLink refs' => strpos($verify, 'HiLinkSmsGateway') === false,
    ];
    foreach ($checks as $check => $ok) {
        echo "  " . ($ok ? '✓' : '✗') . " {$check}\n";
    }
} else {
    echo "⚠ SmsController.php - no changes needed\n";
}

echo "\n";

// 3. Check for any other files referencing HiLinkSmsGateway
echo "=== Searching for remaining HiLink references ===\n";
$searchDirs = [
    '/var/www/megawifi/app/',
    '/var/www/megawifi/routes/',
    '/var/www/megawifi/config/',
];

$found = [];
foreach ($searchDirs as $dir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') continue;
        $path = $file->getPathname();
        // Skip backups
        if (str_ends_with($path, '.bak')) continue;
        $fileContent = file_get_contents($path);
        if (strpos($fileContent, 'HiLinkSmsGateway') !== false) {
            $found[] = $path;
        }
    }
}

if (empty($found)) {
    echo "✓ No remaining HiLinkSmsGateway references found\n";
} else {
    echo "⚠ Found HiLinkSmsGateway references in:\n";
    foreach ($found as $f) {
        echo "  - {$f}\n";
    }
}

echo "\n=== Migration complete ===\n";
