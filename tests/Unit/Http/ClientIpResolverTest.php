<?php

namespace Supamask\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Supamask\Core\Config;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Http\Request;
use Supamask\Middleware\IpBlockMiddleware;
use Supamask\Security\AntiRed;
use Supamask\Security\CustomBlocklist;

final class ClientIpResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SERVER = [];
        parent::tearDown();
    }

    private function context(array $server, array $proxy = []): Context
    {
        $_SERVER = $server;
        return new Context(new Request(), new Config(['proxy' => $proxy]));
    }

    public function testDirectRequestsUseRemoteAddress(): void
    {
        $context = $this->context(['REMOTE_ADDR' => '203.0.113.9']);

        $this->assertSame('203.0.113.9', $context->request()->ip());
        $this->assertSame('203.0.113.9', $context->requestContext()->ip());
    }

    public function testUntrustedPeerCannotSpoofForwardedAddress(): void
    {
        $context = $this->context([
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.42',
        ], ['enabled' => true, 'trusted' => ['10.0.0.0/8']]);

        $this->assertSame('203.0.113.9', $context->request()->ip());
    }

    public function testTrustedProxyUsesForwardedClientAddress(): void
    {
        $context = $this->context([
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.42',
        ], ['enabled' => true, 'trusted' => ['10.0.0.0/8']]);

        $this->assertSame('198.51.100.42', $context->request()->ip());
    }

    public function testIpv4MappedIpv6ForwardedAddressIsNormalized(): void
    {
        $context = $this->context([
            'REMOTE_ADDR' => '127.0.0.100',
            'HTTP_X_FORWARDED_FOR' => '::ffff:105.112.76.240',
        ], ['enabled' => true, 'trusted' => ['127.0.0.100']]);

        $this->assertSame('105.112.76.240', $context->request()->ip());
    }

    public function testIpBlockingUsesTheResolvedClientAddress(): void
    {
        $context = $this->context([
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.42',
        ], ['enabled' => true, 'trusted' => ['10.0.0.0/8']]);
        $middleware = new IpBlockMiddleware(
            new AntiRed([]),
            new CustomBlocklist(['198.51.100.42']),
        );

        $this->assertSame(Decision::DENY, $middleware->handle($context));
    }

    public function testMultipleTrustedProxiesRespectTheTrustBoundary(): void
    {
        $context = $this->context([
            'REMOTE_ADDR' => '10.0.1.10',
            'HTTP_X_FORWARDED_FOR' => '198.51.100.42, 10.0.0.10',
        ], ['enabled' => true, 'trusted' => ['10.0.0.0/8']]);

        $this->assertSame('198.51.100.42', $context->request()->ip());
    }

    public function testForwardedHeaderSupportsIpv6ClientAddresses(): void
    {
        $context = $this->context([
            'REMOTE_ADDR' => '2001:db8:1::10',
            'HTTP_FORWARDED' => 'for="[2001:db8:abcd::42]:443";proto=https',
        ], ['enabled' => true, 'trusted' => ['2001:db8:1::/64']]);

        $this->assertSame('2001:db8:abcd::42', $context->request()->ip());
    }

    public function testMalformedForwardingHeaderFallsBackToRemoteAddress(): void
    {
        $context = $this->context([
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_FORWARDED_FOR' => 'not-an-ip, unknown',
        ], ['enabled' => true, 'trusted' => ['10.0.0.10']]);

        $this->assertSame('10.0.0.10', $context->request()->ip());
    }
}
