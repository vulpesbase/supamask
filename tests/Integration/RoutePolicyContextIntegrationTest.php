<?php

namespace Supamask\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Supamask\Http\Request;
use Supamask\Http\RequestContextFactory;
use Supamask\Routing\RoutePolicy;

final class RoutePolicyContextIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SERVER = [];
        parent::tearDown();
    }

    public function testRoutePolicyConsumesNormalizedRequestContext(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/pricing/?utm_source=test';
        $_SERVER['HTTP_HOST'] = 'APP.EXAMPLE.TEST:443';

        $request = new Request();
        $context = (new RequestContextFactory())->fromRequest($request);

        $policy = new RoutePolicy([
            'enabled' => true,
            'hosts' => ['app.example.test'],
            'paths' => ['/pricing'],
        ]);

        $this->assertTrue($policy->requiresChallenge($context));
        $this->assertSame('/pricing', $context->path());
        $this->assertSame('utm_source=test', $context->query());
    }
}
