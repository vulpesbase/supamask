<?php

namespace Supamask\Tests\Unit\Security;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Supamask\Security\IpIntelligence\IpApiIsProvider;
use Supamask\Security\IpIntelligence\IpIntelligenceProviderFactory;
use Supamask\Security\IpIntelligence\IpIntelligenceProviderException;

final class IpApiIsProviderTest extends TestCase
{
    public function testItNormalizesIpApiIsPayload(): void
    {
        $result = IpApiIsProvider::fromResponse([
            'ip' => '8.8.8.8',
            'is_vpn' => true,
            'is_proxy' => true,
            'is_tor' => false,
            'egress_service' => ['service' => 'Privacy Relay'],
            'company' => ['name' => 'Example Network'],
            'asn' => ['asn' => 'AS15169'],
        ], '203.0.113.10');

        $this->assertSame('8.8.8.8', $result->ip());
        $this->assertSame('AS15169', $result->asn());
        $this->assertSame('Example Network', $result->organization());
        $this->assertTrue($result->isVpn());
        $this->assertTrue($result->isProxy());
        $this->assertFalse($result->isTor());
        $this->assertTrue($result->isRelay());
    }

    public function testFactorySelectsIpApiIs(): void
    {
        $provider = IpIntelligenceProviderFactory::create(['provider' => 'ipapi.is']);
        $this->assertInstanceOf(IpApiIsProvider::class, $provider);
    }

    public function testInvalidIpIsRejectedBeforeNetworkAccess(): void
    {
        $this->expectException(IpIntelligenceProviderException::class);
        (new IpApiIsProvider())->lookup('not-an-ip');
    }

    public function testUnsupportedProviderIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        IpIntelligenceProviderFactory::create(['provider' => 'unknown']);
    }
}
