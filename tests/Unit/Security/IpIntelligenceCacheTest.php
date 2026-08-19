<?php

namespace Supamask\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Supamask\Security\IpIntelligence\CachedIpIntelligenceProvider;
use Supamask\Security\IpIntelligence\InMemoryIpIntelligenceCache;
use Supamask\Security\IpIntelligence\IpIntelligenceProviderInterface;
use Supamask\Security\IpIntelligence\IpIntelligenceResult;

final class IpIntelligenceCacheTest extends TestCase
{
    public function testSecondLookupUsesCache(): void
    {
        $provider = new class implements IpIntelligenceProviderInterface {
            public int $calls = 0;
            public function lookup(string $ip): IpIntelligenceResult
            {
                $this->calls++;
                return new IpIntelligenceResult($ip, 'AS15169', 'Google LLC');
            }
        };

        $cached = new CachedIpIntelligenceProvider($provider, new InMemoryIpIntelligenceCache(), 3600);

        $first = $cached->lookup('8.8.8.8');
        $second = $cached->lookup('8.8.8.8');

        $this->assertSame('AS15169', $first->asn());
        $this->assertSame('AS15169', $second->asn());
        $this->assertSame(1, $provider->calls);
    }
}
