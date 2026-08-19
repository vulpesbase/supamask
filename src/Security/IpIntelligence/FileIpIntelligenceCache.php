<?php

namespace Supamask\Security\IpIntelligence;

final class FileIpIntelligenceCache implements IpIntelligenceCacheInterface
{
    public function __construct(private string $directory)
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0700, true);
        }
    }

    public function get(string $ip): ?IpIntelligenceResult
    {
        $file = $this->fileFor($ip);
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw)) {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['expires'], $data['result']) || !is_array($data['result'])) {
            @unlink($file);
            return null;
        }

        if ((int) $data['expires'] < time()) {
            @unlink($file);
            return null;
        }

        return IpIntelligenceResult::fromArray($data['result']);
    }

    public function put(string $ip, IpIntelligenceResult $result, int $ttl): void
    {
        if ($ttl <= 0 || !is_dir($this->directory)) {
            return;
        }

        $data = json_encode([
            'expires' => time() + $ttl,
            'result' => $result->toArray(),
        ], JSON_UNESCAPED_SLASHES);

        if ($data !== false) {
            @file_put_contents($this->fileFor($ip), $data, LOCK_EX);
        }
    }

    private function fileFor(string $ip): string
    {
        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
    }
}
