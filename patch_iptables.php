<?php
// Patch RouterController.php to use IptablesHelper instead of sudo iptables

$file = '/var/www/megawifi/app/Http/Controllers/RouterController.php';
$content = file_get_contents($file);

// Add use statement for IptablesHelper
if (strpos($content, 'use App\Services\IptablesHelper') === false) {
    $content = str_replace(
        'use App\Models\ActivityLog;',
        "use App\Models\ActivityLog;\nuse App\Services\IptablesHelper;",
        $content
    );
}

// Replace the openPort method body
$oldOpenPort = <<<'PHP'
            // Check if rule already exists
            exec("sudo /usr/sbin/iptables -t nat -L PREROUTING -n 2>/dev/null | grep 'dpt:{$port}'", $checkOutput);
            $ruleExists = !empty($checkOutput);

            if ($ruleExists) {
                // Remove old rule first (in case target changed)
                exec("sudo /usr/sbin/iptables -t nat -D PREROUTING -p tcp --dport {$port} -j DNAT --to-destination {$ip}:8291 2>/dev/null");
                exec("sudo /usr/sbin/iptables -t nat -D POSTROUTING -p tcp -d {$ip} --dport 8291 -j MASQUERADE 2>/dev/null");
            }

            // Add DNAT rule: external_port -> router_wg_ip:8291
            $dnatCmd = "sudo /usr/sbin/iptables -t nat -A PREROUTING -p tcp --dport {$port} -j DNAT --to-destination {$ip}:8291 2>&1";
            exec($dnatCmd, $dnatOutput, $dnatReturn);

            if ($dnatReturn !== 0) {
                throw new Exception('فشل إضافة قاعدة DNAT: ' . implode(' ', $dnatOutput));
            }

            // Add MASQUERADE rule
            $masqCmd = "sudo /usr/sbin/iptables -t nat -A POSTROUTING -p tcp -d {$ip} --dport 8291 -j MASQUERADE 2>&1";
            exec($masqCmd, $masqOutput, $masqReturn);

            if ($masqReturn !== 0) {
                throw new Exception('فشل إضافة قاعدة MASQUERADE: ' . implode(' ', $masqOutput));
            }

            // Add FORWARD rule
            exec("sudo /usr/sbin/iptables -C FORWARD -p tcp -d {$ip} --dport 8291 -j ACCEPT 2>/dev/null", $fwdCheck, $fwdCheckReturn);
            if ($fwdCheckReturn !== 0) {
                exec("sudo /usr/sbin/iptables -A FORWARD -p tcp -d {$ip} --dport 8291 -j ACCEPT 2>/dev/null");
            }

            // Save rules for persistence
            exec('sudo /usr/sbin/iptables-save | sudo tee /etc/iptables/rules.v4 > /dev/null 2>&1');
PHP;

$newOpenPort = <<<'PHP'
            // Use IptablesHelper to manage port forwarding via host service
            $iptables = new IptablesHelper();

            if (!$iptables->addRule($port, $ip)) {
                throw new Exception('فشل إضافة قاعدة DNAT عبر iptables-helper');
            }
PHP;

$content = str_replace($oldOpenPort, $newOpenPort, $content);

// Replace checkPort method
$oldCheckPort = <<<'PHP'
        exec("sudo /usr/sbin/iptables -t nat -L PREROUTING -n 2>/dev/null | grep 'dpt:{$port}'", $output);

        return response()->json([
            'open' => !empty($output),
PHP;

$newCheckPort = <<<'PHP'
        $iptables = new IptablesHelper();
        $result = $iptables->checkRule($port);

        return response()->json([
            'open' => $result === 'EXISTS',
PHP;

$content = str_replace($oldCheckPort, $newCheckPort, $content);

file_put_contents($file, $content);
echo "RouterController.php patched successfully!\n";

// Also patch RouterObserver.php
$obsFile = '/var/www/megawifi/app/Observers/RouterObserver.php';
if (file_exists($obsFile)) {
    $obsContent = file_get_contents($obsFile);

    // Add use statement
    if (strpos($obsContent, 'use App\Services\IptablesHelper') === false) {
        $obsContent = str_replace(
            'namespace App\Observers;',
            "namespace App\\Observers;\n\nuse App\\Services\\IptablesHelper;",
            $obsContent
        );
    }

    // Replace all sudo iptables exec calls with IptablesHelper
    // Remove rules
    $obsContent = preg_replace(
        '/exec\("sudo \/usr\/sbin\/iptables -t nat -D PREROUTING -p tcp --dport \{\$port\} -j DNAT --to-destination \{\$ip\}:8291 2>\/dev\/null"\);/',
        '(new IptablesHelper())->removeRule($port, $ip);',
        $obsContent
    );

    // Remove the standalone masquerade/forward/save lines that follow removeRule
    $obsContent = preg_replace(
        '/\s*exec\("sudo \/usr\/sbin\/iptables -t nat -D POSTROUTING.*?;\n/',
        "\n",
        $obsContent
    );
    $obsContent = preg_replace(
        '/\s*exec\("sudo \/usr\/sbin\/iptables -D FORWARD.*?;\n/',
        "\n",
        $obsContent
    );

    // Add rules
    $obsContent = preg_replace(
        '/exec\("sudo \/usr\/sbin\/iptables -t nat -A PREROUTING -p tcp --dport \{\$port\} -j DNAT --to-destination \{\$ip\}:8291 2>\/dev\/null"\);/',
        '(new IptablesHelper())->addRule($port, $ip);',
        $obsContent
    );

    // Remove remaining standalone masquerade/forward/save after add
    $obsContent = preg_replace(
        '/\s*exec\("sudo \/usr\/sbin\/iptables -t nat -A POSTROUTING.*?;\n/',
        "\n",
        $obsContent
    );
    $obsContent = preg_replace(
        '/\s*exec\("sudo \/usr\/sbin\/iptables -C FORWARD.*?;\n/',
        "\n",
        $obsContent
    );

    // Remove iptables-save lines
    $obsContent = preg_replace(
        "/\s*exec\('sudo \/usr\/sbin\/iptables-save.*?;\n/",
        "\n",
        $obsContent
    );

    file_put_contents($obsFile, $obsContent);
    echo "RouterObserver.php patched!\n";
}

// Patch UpdateWinboxPorts command
$cmdFile = '/var/www/megawifi/app/Console/Commands/UpdateWinboxPorts.php';
if (file_exists($cmdFile)) {
    $cmdContent = file_get_contents($cmdFile);

    if (strpos($cmdContent, 'use App\Services\IptablesHelper') === false) {
        $cmdContent = str_replace(
            'namespace App\Console\Commands;',
            "namespace App\\Console\\Commands;\n\nuse App\\Services\\IptablesHelper;",
            $cmdContent
        );
    }

    // Replace flush
    $cmdContent = preg_replace(
        "/exec\('sudo iptables -t nat -F PREROUTING.*?;\n/",
        "// Flush handled per-rule via IptablesHelper\n",
        $cmdContent
    );

    // Replace add rules
    $cmdContent = preg_replace(
        '/exec\("sudo iptables -t nat -A PREROUTING -p tcp --dport \{\$port\} -j DNAT --to-destination \{\$ip\}:8291"\);/',
        '(new IptablesHelper())->addRule($port, $ip);',
        $cmdContent
    );

    // Remove remaining sudo iptables lines
    $cmdContent = preg_replace(
        '/\s*exec\("sudo iptables -A FORWARD.*?;\n/',
        "\n",
        $cmdContent
    );
    $cmdContent = preg_replace(
        "/\s*exec\('sudo iptables-save.*?;\n/",
        "\n",
        $cmdContent
    );

    file_put_contents($cmdFile, $cmdContent);
    echo "UpdateWinboxPorts.php patched!\n";
}

echo "\nAll files patched to use IptablesHelper!\n";
