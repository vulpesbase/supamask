<?php

namespace Supamask\Security\IpIntelligence;

interface IpIntelligenceCacheInterface
{
    public function get(string $ip): ?IpIntelligenceResult;
    public function put(string $ip, IpIntelligenceResult $result, int $ttl): void;
}
