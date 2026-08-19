<?php

namespace Supamask\Security\IpIntelligence;

final class CachedIpIntelligenceProvider implements IpIntelligenceProviderInterface
{
    public function __construct(
        private IpIntelligenceProviderInterface $provider,
        private IpIntelligenceCacheInterface $cache,
        private int $ttl = 3600,
    ) {
    }

    public function lookup(string $ip): IpIntelligenceResult
    {
        $cached = $this->cache->get($ip);
        if ($cached !== null) {
            return $cached;
        }

        $result = $this->provider->lookup($ip);
        $this->cache->put($ip, $result, $this->ttl);

        return $result;
    }
}
