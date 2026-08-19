<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\SessionVerification;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Http\Request;
use Supamask\Middleware\ChallengeMiddleware;
use Supamask\Routing\RoutePolicy;

final class RoutePolicyMiddlewareIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SERVER = [];
        $_SESSION = [];
        parent::tearDown();
    }

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        parent::setUp();
    }

    public function testNormalizedUrlStillMatchesProtectedRoute(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/pricing/?plan=pro';
        $_SERVER['HTTP_HOST'] = 'APP.EXAMPLE.TEST:443';

        $middleware = new ChallengeMiddleware(
            new SessionVerification(),
            new RoutePolicy([
                'enabled' => true,
                'hosts' => ['app.example.test'],
                'paths' => ['/pricing'],
            ])
        );

        $decision = $middleware->handle(new Context(new Request()));

        $this->assertSame(Decision::CHALLENGE, $decision);
    }
}
