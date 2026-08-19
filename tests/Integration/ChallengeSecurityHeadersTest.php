<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\ChallengeManager;
use Supamask\Challenge\InMemoryChallengeStore;
use Supamask\Core\Config;
use Supamask\Core\Kernel;
use Supamask\Http\Request;

final class ChallengeSecurityHeadersTest extends TestCase
{
    public function testChallengeResponseCarriesChallengeOnlySecurityPolicy(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/82f6cd2d2843';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_POST = [];

        $store = new InMemoryChallengeStore();
        $manager = new ChallengeManager($store);
        $challenge = $manager->create('/');
        $_SERVER['REQUEST_URI'] = '/' . $challenge->id();

        $kernel = new Kernel(new Config([
            'ip_blocking' => ['enabled' => false],
            'bot_blocking' => ['enabled' => false],
        ]), $manager);

        $response = $kernel->handle(new Request());

        $this->assertSame(200, $response->status());
        $this->assertSame('no-referrer', $response->headers()['Referrer-Policy']);
        $this->assertSame('noindex, nofollow', $response->headers()['X-Robots-Tag']);
        $this->assertStringContainsString("default-src 'none'", $response->headers()['Content-Security-Policy']);
        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $response->body());
        $this->assertStringContainsString('<meta name="referrer" content="no-referrer">', $response->body());

        preg_match('/script-src \'nonce-([^\']+)\'/i', $response->headers()['Content-Security-Policy'], $headerMatch);
        $this->assertArrayHasKey(1, $headerMatch);
        $this->assertSame(1, preg_match('/<script\b[^>]*\bnonce="' . preg_quote($headerMatch[1], '/') . '"/i', $response->body()));
    }

    public function testNonChallengeResponsesDoNotReceiveChallengeCsp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_POST = [];

        $kernel = new Kernel(new Config([
            'ip_blocking' => ['enabled' => false],
            'bot_blocking' => ['enabled' => false],
            'challenge' => ['enabled' => false],
        ]));

        $response = $kernel->handle(new Request());

        if ($response !== null) {
            $this->assertArrayNotHasKey('Content-Security-Policy', $response->headers());
            $this->assertArrayNotHasKey('Referrer-Policy', $response->headers());
        } else {
            $this->assertNull($response);
        }
    }
}
