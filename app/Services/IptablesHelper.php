<?php

namespace App\Services;

class IptablesHelper
{
    private string $cmdDir = '/opt/megawifi/iptables-cmd';
    private string $resultDir = '/opt/megawifi/iptables-result';
    private int $timeout = 5; // seconds

    /**
     * Add DNAT port forwarding rule: external_port -> wg_ip:8291
     */
    public function addRule(int $port, string $ip): bool
    {
        return $this->sendCommand("add {$port} {$ip}");
    }

    /**
     * Remove DNAT port forwarding rule
     */
    public function removeRule(int $port, string $ip): bool
    {
        return $this->sendCommand("remove {$port} {$ip}");
    }

    /**
     * Check if a DNAT rule exists for the given port
     */
    public function checkRule(int $port): string
    {
        $id = $this->writeCommand("check {$port}");
        $result = $this->readResult($id);
        return trim($result ?: 'NOT_FOUND');
    }

    /**
     * Save iptables rules
     */
    public function saveRules(): bool
    {
        return $this->sendCommand("save");
    }

    private function sendCommand(string $command): bool
    {
        $id = $this->writeCommand($command);
        $result = $this->readResult($id);
        return $result !== null && str_starts_with(trim($result), 'OK');
    }

    private function writeCommand(string $command): string
    {
        $id = uniqid('ipt_', true);
        $file = "{$this->cmdDir}/{$id}";

        // Ensure directory exists
        if (!is_dir($this->cmdDir)) {
            @mkdir($this->cmdDir, 0777, true);
        }

        file_put_contents($file, $command);
        return $id;
    }

    private function readResult(string $id): ?string
    {
        $file = "{$this->resultDir}/{$id}";
        $start = time();

        while (time() - $start < $this->timeout) {
            if (file_exists($file)) {
                $result = file_get_contents($file);
                @unlink($file);
                return $result;
            }
            usleep(100000); // 100ms
        }

        return null;
    }
}
