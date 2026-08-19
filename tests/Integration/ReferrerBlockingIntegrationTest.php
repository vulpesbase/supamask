<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Core\Config;
use Supamask\Core\Kernel;
use Supamask\Http\Request;

final class ReferrerBlockingIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_HOST'], $_SERVER['HTTP_REFERER']);
        parent::tearDown();
    }

    private function request(?string $referrer = null): Request
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['HTTP_HOST'] = 'localhost:8000';
        if ($referrer === null) {
            unset($_SERVER['HTTP_REFERER']);
        } else {
            $_SERVER['HTTP_REFERER'] = $referrer;
        }
        return new Request();
    }

    private function kernel(array $overrides = []): Kernel
    {
        $config = new Config(array_replace_recursive([
            'challenge' => [
                'middleware' => ['enabled' => false],
                'protection' => ['enabled' => false],
            ],
            'routing' => ['root' => ['behavior' => 'allow']],
            'ip_blocking' => ['enabled' => false],
            'bot_blocking' => ['enabled' => false],
            'block_referrers' => true,
            'referrer_blocklist' => ['badsite.com'],
            'block_missing_referrer' => false,
        ], $overrides));

        return new Kernel($config);
    }

    public function testBlockedReferrerUsesExistingDenyResponse(): void
    {
        $response = $this->kernel()->handle($this->request('https://badsite.com/path'));

        self::assertNotNull($response);
        self::assertSame(403, $response->status());
        self::assertSame('Access denied', $response->body());
    }

    public function testBlockedReferrerUsesExistingDenyRedirect(): void
    {
        $kernel = $this->kernel([
            'responses' => [
                'deny' => [
                    'action' => 'redirect',
                    'redirect' => 'https://freesite.co',
                ],
            ],
        ]);

        $response = $kernel->handle($this->request('https://badsite.com/path'));

        self::assertNotNull($response);
        self::assertSame(302, $response->status());
        self::assertSame('https://freesite.co', $response->headers()['Location'] ?? null);
    }

    public function testUnblockedReferrerContinuesToApplication(): void
    {
        self::assertNull($this->kernel()->handle($this->request('https://goodsite.com/path')));
    }

    public function testMissingReferrerIsAllowedByDefault(): void
    {
        self::assertNull($this->kernel()->handle($this->request(null)));
    }

    public function testMissingReferrerCanBeBlocked(): void
    {
        $kernel = $this->kernel(['block_missing_referrer' => true]);
        $response = $kernel->handle($this->request(null));

        self::assertNotNull($response);
        self::assertSame(403, $response->status());
    }

    public function testReferrerBlockPrecedesChallenge(): void
    {
        $kernel = $this->kernel([
            'challenge' => [
                'middleware' => ['enabled' => true],
                'protection' => ['enabled' => true],
            ],
            'routing' => ['root' => ['behavior' => 'challenge']],
        ]);

        $response = $kernel->handle($this->request('https://badsite.com/path'));

        self::assertNotNull($response);
        self::assertSame(403, $response->status());
        self::assertSame('Access denied', $response->body());
    }
}
