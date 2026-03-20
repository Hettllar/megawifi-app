<?php
// Direct line-based replacement for RouterController.php
$file = '/var/www/megawifi/app/Http/Controllers/RouterController.php';
$lines = file($file);

// Replace openPort body (lines 567-598 approximately - the iptables section)
$newOpenPort = <<<'PHP'
            // Use IptablesHelper to manage port forwarding via host service
            $iptables = new IptablesHelper();

            if (!$iptables->addRule($port, $ip)) {
                throw new \Exception('فشل إضافة قاعدة DNAT عبر iptables-helper');
            }

PHP;

// Find and replace the iptables block in openPort
$inBlock = false;
$blockStart = -1;
$blockEnd = -1;
for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], '// Check if rule already exists') !== false && $blockStart === -1) {
        $blockStart = $i;
        $inBlock = true;
    }
    if ($inBlock && strpos($lines[$i], "iptables-save") !== false && strpos($lines[$i], "tee") !== false) {
        $blockEnd = $i;
        break;
    }
}

if ($blockStart >= 0 && $blockEnd > $blockStart) {
    // Replace lines blockStart to blockEnd with new code
    $before = array_slice($lines, 0, $blockStart);
    $after = array_slice($lines, $blockEnd + 1);
    $lines = array_merge($before, [rtrim($newOpenPort) . "\n"], $after);
    echo "openPort: replaced lines $blockStart-$blockEnd\n";
}

// Now replace checkPort iptables
$newCheckPort = <<<'PHP'
        $iptables = new IptablesHelper();
        $result = $iptables->checkRule($port);

        return response()->json([
            'open' => $result === 'EXISTS',
PHP;

$checkBlockStart = -1;
$checkBlockEnd = -1;
for ($i = 0; $i < count($lines); $i++) {
    if (strpos($lines[$i], 'function checkPort') !== false) {
        // Find the exec line after this
        for ($j = $i; $j < min($i + 20, count($lines)); $j++) {
            if (strpos($lines[$j], 'exec("sudo') !== false && strpos($lines[$j], 'PREROUTING') !== false) {
                $checkBlockStart = $j;
            }
            if ($checkBlockStart > 0 && strpos($lines[$j], "'open' => !empty") !== false) {
                $checkBlockEnd = $j;
                break 2;
            }
        }
    }
}

if ($checkBlockStart >= 0 && $checkBlockEnd > $checkBlockStart) {
    $before = array_slice($lines, 0, $checkBlockStart);
    $after = array_slice($lines, $checkBlockEnd + 1);
    $newLines = explode("\n", $newCheckPort);
    $newLinesFormatted = [];
    foreach ($newLines as $nl) {
        $newLinesFormatted[] = $nl . "\n";
    }
    $lines = array_merge($before, $newLinesFormatted, $after);
    echo "checkPort: replaced lines $checkBlockStart-$checkBlockEnd\n";
}

file_put_contents($file, implode('', $lines));
echo "Done! Remaining sudo iptables: ";
echo substr_count(implode('', $lines), 'sudo') . "\n";

// Verify
$content = file_get_contents($file);
preg_match_all('/sudo.*iptables/', $content, $matches);
echo "sudo iptables occurrences: " . count($matches[0]) . "\n";
foreach ($matches[0] as $m) {
    echo "  - $m\n";
}
