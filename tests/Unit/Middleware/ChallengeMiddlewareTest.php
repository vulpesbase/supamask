<?php

namespace Supamask\Tests\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\SessionVerification;
use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Config;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Routing\RoutePolicy;
use Supamask\Http\Request;
use Supamask\Middleware\ChallengeMiddleware;

final class ChallengeMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/pricing';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0';
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'example.test';
    }

    public function testUnverifiedSessionReturnsChallenge(): void
    {
        $verification = new SessionVerification();
        $policy = new \Supamask\Routing\RoutePolicy(['enabled' => true, 'paths' => ['/']]);
        $middleware = new ChallengeMiddleware($verification, $policy);

        $context = new Context(new Request());
        $decision = $middleware->handle($context);

        $this->assertSame(Decision::CHALLENGE, $decision);
    }

    public function testVerifiedSessionAllowsRequest(): void
    {
        $verification = new SessionVerification();
        $verification->markVerified(300);

        $middleware = new ChallengeMiddleware($verification, new RoutePolicy(['enabled' => true]));
        $decision = $middleware->handle(new Context(new Request(), new Config()));

        $this->assertSame(Decision::ALLOW, $decision);
    }
}
