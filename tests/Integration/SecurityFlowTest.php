<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Core\Config;
use Supamask\Core\Kernel;
use Supamask\Http\Request;

class SecurityFlowTest extends TestCase
{
    protected function setUp(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_USER_AGENT']);
    }

    private function createRequest(string $ip = '192.168.1.1', string $userAgent = 'Mozilla/5.0'): Request
    {
        $_SERVER['REMOTE_ADDR'] = $ip;
        $_SERVER['HTTP_USER_AGENT'] = $userAgent;
        return new Request();
    }

    private function getBaseConfigArray(): array
    {
        return [
            'ip_blocking' => [
                'antired' => true,
                'rules' => [
                    '198.51.100.1', // Custom exact
                    '203.0.113.0/24', // Custom IPv4 CIDR
                    '2001:db8:1234::/48', // Custom IPv6 CIDR
                ],
            ],
            'responses' => [
                'deny' => [
                    'status' => 403,
                    'body' => 'Access denied',
                    'headers' => [],
                ]
            ]
        ];
    }

    private function createKernel(array $configOverrides = []): Kernel
    {
        $config = new Config(array_replace_recursive($this->getBaseConfigArray(), $configOverrides));
        
        return new class($config) extends Kernel {
            public function __construct(Config $config)
            {
                parent::__construct($config);
                $this->antiRedPath = __DIR__ . '/Fixtures/antired.php';
                $this->antiRedBotsPath = __DIR__ . '/Fixtures/antired-bots.php';
            }
        };
    }

    private function assertDenyResponse(Kernel $kernel, Request $request, int $expectedStatus = 403, string $expectedBody = 'Access denied', array $expectedHeaders = []): void
    {
        $response = $kernel->handle($request);
        
        $this->assertNotNull($response, 'Expected a DENY response to be returned, got null.');
        $this->assertSame($expectedStatus, $response->status(), 'Status code mismatch');
        $this->assertSame($expectedBody, $response->body(), 'Response body mismatch');
        $this->assertSame($expectedHeaders, $response->headers(), 'Response headers mismatch');
    }

    private function assertAllowResponse(Kernel $kernel, Request $request): void
    {
        $response = $kernel->handle($request);
        $this->assertNull($response, 'Expected an ALLOW decision (null), but a response was returned.');
    }

    // 1. IP blocking

    public function testIpBlockingAntiRedExactIpv4RuleDenies(): void
    {
        $kernel = $this->createKernel();
        $this->assertDenyResponse($kernel, $this->createRequest('192.0.2.100'));
    }

    public function testIpBlockingCustomIpv4RuleDenies(): void
    {
        $kernel = $this->createKernel();
        $this->assertDenyResponse($kernel, $this->createRequest('198.51.100.1'));
    }

    public function testIpBlockingIpv4CidrDenies(): void
    {
        $kernel = $this->createKernel();
        $this->assertDenyResponse($kernel, $this->createRequest('203.0.113.50'));
    }

    public function testIpBlockingAntiRedExactIpv6RuleDenies(): void
    {
        $kernel = $this->createKernel();
        $this->assertDenyResponse($kernel, $this->createRequest('2001:db8::100'));
    }

    public function testIpBlockingIpv6CidrDenies(): void
    {
        $kernel = $this->createKernel();
        $this->assertDenyResponse($kernel, $this->createRequest('2001:db8:1234::5555'));
    }

    public function testIpBlockingNonMatchingIpAllows(): void
    {
        $kernel = $this->createKernel();
        $this->assertAllowResponse($kernel, $this->createRequest('192.168.1.1'));
    }

    // 2. Bot blocking

    public function testBotBlockingMatchingUserAgentDenies(): void
    {
        $kernel = $this->createKernel();
        $this->assertDenyResponse($kernel, $this->createRequest('192.168.1.1', 'Mozilla/5.0 (EvilBot/1.0)'));
    }

    public function testBotBlockingNonMatchingUserAgentAllows(): void
    {
        $kernel = $this->createKernel();
        $this->assertAllowResponse($kernel, $this->createRequest('192.168.1.1', 'Mozilla/5.0 (GoodBot/1.0)'));
    }

    public function testBotBlockingCaseInsensitiveSignatureDenies(): void
    {
        $kernel = $this->createKernel();
        $this->assertDenyResponse($kernel, $this->createRequest('192.168.1.1', 'mozilla/5.0 (EVILBOT/1.0)'));
    }

    // 3. Middleware pipeline

    public function testMiddlewarePipelineIpBlockPreventsLaterMiddleware(): void
    {
        $kernel = $this->createKernel();
        // IpBlock runs first. If it denies, BotBlock shouldn't run.
        $this->assertDenyResponse($kernel, $this->createRequest('192.0.2.100', 'GoodBot'));
    }

    public function testMiddlewarePipelineAllowedIpReachesBotBlockMiddleware(): void
    {
        $kernel = $this->createKernel();
        // Good IP, Bad Bot -> Denies (proving BotBlock executed)
        $this->assertDenyResponse($kernel, $this->createRequest('192.168.1.1', 'EvilBot'));
    }

    public function testMiddlewarePipelineAllowedRequestReachesEnd(): void
    {
        $kernel = $this->createKernel();
        // Good IP, Good Bot -> Allows
        $this->assertAllowResponse($kernel, $this->createRequest('192.168.1.1', 'GoodBot'));
    }

    // 4. Configuration

    public function testConfigurationAntiRedEnabled(): void
    {
        $kernel = $this->createKernel(['ip_blocking' => ['antired' => true]]);
        $this->assertDenyResponse($kernel, $this->createRequest('192.0.2.100'));
    }

    public function testConfigurationAntiRedDisabled(): void
    {
        $kernel = $this->createKernel(['ip_blocking' => ['antired' => false]]);
        // With AntiRed disabled, the AntiRed IP and Bot rules should be ignored.
        $this->assertAllowResponse($kernel, $this->createRequest('192.0.2.100', 'EvilBot'));
    }

    public function testConfigurationCustomRulesAreRespected(): void
    {
        $kernel = $this->createKernel();
        $this->assertDenyResponse($kernel, $this->createRequest('198.51.100.1'));
    }

    public function testConfigurationDenyResponseIsRespected(): void
    {
        $kernel = $this->createKernel([
            'responses' => [
                'deny' => [
                    'status' => 418,
                    'body' => 'I am a teapot',
                    'headers' => ['X-Reason' => 'Tea'],
                ]
            ]
        ]);
        
        $this->assertDenyResponse(
            $kernel,
            $this->createRequest('192.0.2.100'),
            418,
            'I am a teapot',
            ['X-Reason' => 'Tea']
        );
    }

    // 5. HTTP responses

    public function testHttpResponsesDenyProducesConfiguredStatusCodeAndBodyAndHeaders(): void
    {
        $kernel = $this->createKernel([
            'responses' => [
                'deny' => [
                    'status' => 401,
                    'body' => 'Custom Body',
                    'headers' => ['X-Custom' => 'Value'],
                ]
            ]
        ]);
        
        $this->assertDenyResponse(
            $kernel,
            $this->createRequest('192.0.2.100'),
            401,
            'Custom Body',
            ['X-Custom' => 'Value']
        );
    }
}
