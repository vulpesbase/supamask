<?php

namespace Supamask\Security\IpIntelligence;

final class InMemoryIpIntelligenceCache implements IpIntelligenceCacheInterface
{
    /** @var array<string,array{expires:int,result:IpIntelligenceResult}> */
    private array $items = [];

    public function get(string $ip): ?IpIntelligenceResult
    {
        $item = $this->items[$ip] ?? null;
        if ($item === null) {
            return null;
        }

        if ($item['expires'] < time()) {
            unset($this->items[$ip]);
            return null;
        }

        return $item['result'];
    }

    public function put(string $ip, IpIntelligenceResult $result, int $ttl): void
    {
        if ($ttl <= 0) {
            return;
        }

        $this->items[$ip] = [
            'expires' => time() + $ttl,
            'result' => $result,
        ];
    }
}
