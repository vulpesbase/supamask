<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Config;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Core\Kernel;
use Supamask\Http\Request;
use Supamask\Http\Response;
use Supamask\Middleware\Pipeline;

class SecurityFlowTest extends TestCase
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

    private function createRequest(string $ip = '192.168.1.1', string $userAgent = 'Mozilla/5.0'): Request
    {
        $_SERVER['REMOTE_ADDR'] = $ip;
        $_SERVER['HTTP_USER_AGENT'] = $userAgent;
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'example.test';
        return new Request();
    }

    private function getBaseConfigArray(): array
    {
        return [
            'ip_blocking' => [
                'enabled' => true,
                'antired' => true,
                'rules' => [
                    '198.51.100.1', // Custom exact
                    '203.0.113.0/24', // Custom IPv4 CIDR
                    '2001:db8:1234::/48', // Custom IPv6 CIDR
                ],
            ],
            'bot_blocking' => [
                'enabled' => true,
                'antired' => true,
                'signatures' => [],
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

    public function testIpBlockingDisabledAllowsIp(): void
    {
        $kernel = $this->createKernel(['ip_blocking' => ['enabled' => false]]);
        $this->assertAllowResponse($kernel, $this->createRequest('192.0.2.100'));
    }

    public function testBotBlockingDisabledAllowsBot(): void
    {
        $kernel = $this->createKernel(['bot_blocking' => ['enabled' => false]]);
        $this->assertAllowResponse($kernel, $this->createRequest('192.168.1.1', 'EvilBot'));
    }

    public function testIpBlockingAntiRedDisabledAllowsAntiRedIp(): void
    {
        $kernel = $this->createKernel(['ip_blocking' => ['antired' => false]]);
        $this->assertAllowResponse($kernel, $this->createRequest('192.0.2.100'));
    }

    public function testBotBlockingAntiRedDisabledAllowsAntiRedBot(): void
    {
        $kernel = $this->createKernel(['bot_blocking' => ['antired' => false]]);
        $this->assertAllowResponse($kernel, $this->createRequest('192.168.1.1', 'EvilBot'));
    }

    public function testBotBlockingCustomSignaturesAreRespected(): void
    {
        $kernel = $this->createKernel(['bot_blocking' => ['signatures' => ['CustomBadBot']]]);
        $this->assertDenyResponse($kernel, $this->createRequest('192.168.1.1', 'Mozilla/5.0 (CustomBadBot/2.0)'));
    }

    public function testBackwardsCompatibilityIpAntiRedDisablesBotAntiRedIfBotConfigMissing(): void
    {
        // Old configs won't have bot_blocking at all.
        $config = new Config([
            'ip_blocking' => ['antired' => false],
            'responses' => ['deny' => ['status' => 403, 'body' => 'Denied', 'headers' => []]]
        ]);
        
        $kernel = new class($config) extends Kernel {
            public function __construct(Config $config)
            {
                parent::__construct($config);
                $this->antiRedPath = __DIR__ . '/Fixtures/antired.php';
                $this->antiRedBotsPath = __DIR__ . '/Fixtures/antired-bots.php';
            }
        };

        $this->assertAllowResponse($kernel, $this->createRequest('192.168.1.1', 'EvilBot'));
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


    public function testDenyCanRedirectToConfiguredTrustedHttpsDestination(): void
    {
        $kernel = $this->createKernel([
            'responses' => [
                'deny' => [
                    'action' => 'redirect',
                    'redirect' => 'https://freesite.co/blocked',
                ],
            ],
        ]);

        $response = $kernel->handle($this->createRequest('192.0.2.100'));

        $this->assertNotNull($response);
        $this->assertSame(302, $response->status());
        $this->assertSame('', $response->body());
        $this->assertSame('https://freesite.co/blocked', $response->headers()['Location']);
    }

    public function testDenyRedirectAppliesToBotBlocking(): void
    {
        $kernel = $this->createKernel([
            'responses' => [
                'deny' => [
                    'action' => 'redirect',
                    'redirect' => 'https://freesite.co/bots',
                ],
            ],
        ]);

        $response = $kernel->handle($this->createRequest('192.168.1.1', 'EvilBot'));

        $this->assertNotNull($response);
        $this->assertSame(302, $response->status());
        $this->assertSame('https://freesite.co/bots', $response->headers()['Location']);
    }

    public function testDenyRedirectRejectsUntrustedRelativeDestination(): void
    {
        $kernel = $this->createKernel([
            'responses' => [
                'deny' => [
                    'action' => 'redirect',
                    'redirect' => '/unsafe',
                ],
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('trusted absolute HTTP(S) URL');

        $kernel->handle($this->createRequest('192.0.2.100'));
    }

    public function testDenyRedirectRejectsRequestLikeProtocolRelativeDestination(): void
    {
        $kernel = $this->createKernel([
            'responses' => [
                'deny' => [
                    'action' => 'redirect',
                    'redirect' => '//evil.example',
                ],
            ],
        ]);

        $this->expectException(\RuntimeException::class);
        $kernel->handle($this->createRequest('192.0.2.100'));
    }


    // 4.5. Hard DENY takes precedence over CHALLENGE

    public function testBlockedIpDeniesBeforeChallengeMiddleware(): void
    {
        $kernel = $this->createKernel([
            'challenge' => [
                'middleware' => ['enabled' => true],
                'protection' => ['enabled' => true],
            ],
            'routing' => [
                'root' => ['behavior' => 'challenge'],
            ],
        ]);

        $response = $kernel->handle($this->createRequest('198.51.100.1'));

        $this->assertNotNull($response);
        $this->assertSame(403, $response->status());
        $this->assertSame('Access denied', $response->body());
    }

    public function testBlockedIpRedirectsBeforeChallengeMiddleware(): void
    {
        $kernel = $this->createKernel([
            'challenge' => [
                'middleware' => ['enabled' => true],
                'protection' => ['enabled' => true],
            ],
            'routing' => [
                'root' => ['behavior' => 'challenge'],
            ],
            'responses' => [
                'deny' => [
                    'action' => 'redirect',
                    'redirect' => 'https://freesite.co/blocked',
                ],
            ],
        ]);

        $response = $kernel->handle($this->createRequest('198.51.100.1'));

        $this->assertNotNull($response);
        $this->assertSame(302, $response->status());
        $this->assertSame('https://freesite.co/blocked', $response->headers()['Location']);
    }

    public function testBlockedCidrDeniesBeforeChallengeMiddleware(): void
    {
        $kernel = $this->createKernel([
            'challenge' => [
                'middleware' => ['enabled' => true],
                'protection' => ['enabled' => true],
            ],
            'routing' => [
                'root' => ['behavior' => 'challenge'],
            ],
        ]);

        $response = $kernel->handle($this->createRequest('203.0.113.50'));

        $this->assertNotNull($response);
        $this->assertSame(403, $response->status());
        $this->assertSame('Access denied', $response->body());
    }

    public function testBlockedBotDeniesBeforeChallengeMiddleware(): void
    {
        $kernel = $this->createKernel([
            'challenge' => [
                'middleware' => ['enabled' => true],
                'protection' => ['enabled' => true],
            ],
            'routing' => [
                'root' => ['behavior' => 'challenge'],
            ],
        ]);

        $response = $kernel->handle($this->createRequest('192.168.1.1', 'EvilBot'));

        $this->assertNotNull($response);
        $this->assertSame(403, $response->status());
        $this->assertSame('Access denied', $response->body());
    }

    public function testAllowedRequestReachesChallengeAfterHardDenyChecks(): void
    {
        $kernel = $this->createKernel([
            'challenge' => [
                'middleware' => ['enabled' => true],
                'protection' => ['enabled' => true],
            ],
            'routing' => [
                'root' => ['behavior' => 'challenge'],
            ],
        ]);

        $response = $kernel->handle($this->createRequest('192.168.1.1', 'Mozilla/5.0'));

        $this->assertNotNull($response);
        $this->assertNotSame('Access denied', $response->body());
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

    public function testHttpResponsesChallengeProducesConfiguredStatusCodeAndBodyAndHeaders(): void
    {
        $config = new Config(array_replace_recursive($this->getBaseConfigArray(), [
            'responses' => [
                'challenge' => [
                    'status' => 429,
                    'body' => 'Slow down',
                    'headers' => ['X-Rate-Limit' => '1'],
                ]
            ]
        ]));

        $kernel = new class($config) extends Kernel {
            public function __construct(Config $config)
            {
                parent::__construct($config);
                $this->antiRedPath = __DIR__ . '/Fixtures/antired.php';
                $this->antiRedBotsPath = __DIR__ . '/Fixtures/antired-bots.php';
            }

            public function handle(Request $request): ?Response
            {
                $context = new Context($request, $this->config);
                $pipeline = new Pipeline();
                $pipeline->pipe(new class implements MiddlewareInterface {
                    public function handle(Context $context): Decision
                    {
                        return Decision::CHALLENGE;
                    }
                });

                $decision = $pipeline->process($context);

                switch ($decision) {
                    case Decision::ALLOW:
                        return null;

                    case Decision::CHALLENGE:
                        $response = $this->config->get('responses.challenge', [
                            'status' => 403,
                            'body' => 'Challenge',
                            'headers' => [],
                        ]);

                        return new Response($response['status'], $response['body'], $response['headers']);

                    case Decision::DENY:
                        $response = $this->config->get('responses.deny', [
                            'status' => 403,
                            'body' => 'Access denied',
                            'headers' => [],
                        ]);

                        return new Response($response['status'], $response['body'], $response['headers']);
                }
            }
        };

        $response = $kernel->handle($this->createRequest('192.168.1.1', 'EvilBot'));

        $this->assertNotNull($response);
        $this->assertSame(429, $response->status());
        $this->assertSame('Slow down', $response->body());
        $this->assertSame(['X-Rate-Limit' => '1'], $response->headers());
    }
}
