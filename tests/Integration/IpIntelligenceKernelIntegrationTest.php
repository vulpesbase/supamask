<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Core\Config;
use Supamask\Core\Kernel;
use Supamask\Http\Request;
use Supamask\Security\IpIntelligence\IpIntelligenceProviderInterface;
use Supamask\Security\IpIntelligence\IpIntelligenceResult;

final class IpIntelligenceKernelIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER = [];
        $_POST = [];
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        $_POST = [];
        $_SESSION = [];
        parent::tearDown();
    }

    private function request(string $uri = '/'): Request
    {
        $_SERVER['REMOTE_ADDR'] = '8.8.8.8';
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'localhost:8000';
        return new Request();
    }

    private function config(): Config
    {
        return new Config([
            'challenge' => [
                'middleware' => ['enabled' => true],
                'protection' => ['enabled' => true],
            ],
            'routing' => ['root' => ['behavior' => 'challenge']],
            'ip_blocking' => ['enabled' => false],
            'bot_blocking' => ['enabled' => false],
            'block_vpn' => true,
        ]);
    }

    public function testVpnDenyPrecedesChallenge(): void
    {
        $provider = new class implements IpIntelligenceProviderInterface {
            public function lookup(string $ip): IpIntelligenceResult
            {
                return new IpIntelligenceResult($ip, 'AS64500', 'VPN Network', true);
            }
        };

        $kernel = new Kernel($this->config(), null, null, null, $provider);
        $response = $kernel->handle($this->request('/'));

        $this->assertNotNull($response);
        $this->assertSame(403, $response->status());
    }

    public function testNormalIpStillReachesChallengeAfterIntelligenceAllowsIt(): void
    {
        $provider = new class implements IpIntelligenceProviderInterface {
            public function lookup(string $ip): IpIntelligenceResult
            {
                return new IpIntelligenceResult($ip, 'AS15169', 'Google LLC', false);
            }
        };

        $kernel = new Kernel($this->config(), null, null, null, $provider);
        $response = $kernel->handle($this->request('/'));

        $this->assertNotNull($response);
        $this->assertSame(302, $response->status());
        $this->assertArrayHasKey('Location', $response->headers());
    }
}
