<?php

namespace Supamask\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Supamask\Http\Request;
use Supamask\Http\RequestContext;
use Supamask\Http\RequestContextFactory;
use Supamask\Routing\RoutePolicy;

final class RoutePolicyPrecedenceTest extends TestCase
{
    private function request(
        string $uri = '/',
        string $host = 'example.test'
    ): RequestContext {
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = $host;

        return (new RequestContextFactory())->fromRequest(new Request());
    }

    public function testDisabledPolicyAlwaysAllows(): void
    {
        $policy = new RoutePolicy([
            'enabled' => false,
            'paths' => ['/'],
        ]);

        $this->assertFalse($policy->requiresChallenge($this->request('/')));
    }

    public function testNoHostOrPathRestrictionsProtectsEverything(): void
    {
        $policy = new RoutePolicy(['enabled' => true]);

        $this->assertTrue($policy->requiresChallenge($this->request('/')));
        $this->assertTrue($policy->requiresChallenge($this->request('/pricing')));
        $this->assertTrue($policy->requiresChallenge(
            $this->request('/', 'api.example.test')
        ));
    }

    public function testHostRestrictionMustMatch(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'hosts' => ['app.example.test'],
        ]);

        $this->assertTrue($policy->requiresChallenge(
            $this->request('/', 'app.example.test')
        ));
        $this->assertFalse($policy->requiresChallenge(
            $this->request('/', 'www.example.test')
        ));
    }

    public function testPathRestrictionMustMatch(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'paths' => ['/pricing'],
        ]);

        $this->assertTrue($policy->requiresChallenge(
            $this->request('/pricing')
        ));
        $this->assertFalse($policy->requiresChallenge(
            $this->request('/dashboard')
        ));
    }

    public function testHostAndPathRestrictionsAreAnIntersection(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'hosts' => ['app.example.test'],
            'paths' => ['/billing/*'],
        ]);

        $this->assertTrue($policy->requiresChallenge(
            $this->request('/billing/invoices', 'app.example.test')
        ));

        $this->assertFalse($policy->requiresChallenge(
            $this->request('/billing/invoices', 'www.example.test')
        ));

        $this->assertFalse($policy->requiresChallenge(
            $this->request('/pricing', 'app.example.test')
        ));
    }

    public function testExcludedPathOverridesMatchingHostAndPath(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'hosts' => ['app.example.test'],
            'paths' => ['/app/*'],
            'exclude_paths' => ['/app/health'],
        ]);

        $this->assertFalse($policy->requiresChallenge(
            $this->request('/app/health', 'app.example.test')
        ));

        $this->assertTrue($policy->requiresChallenge(
            $this->request('/app/dashboard', 'app.example.test')
        ));
    }

    public function testExcludedHostOverridesMatchingHostAndPath(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'hosts' => ['*.example.test'],
            'paths' => ['/app/*'],
            'exclude_hosts' => ['health.example.test'],
        ]);

        $this->assertFalse($policy->requiresChallenge(
            $this->request('/app/health', 'health.example.test')
        ));

        $this->assertTrue($policy->requiresChallenge(
            $this->request('/app/dashboard', 'api.example.test')
        ));
    }

    public function testAnyMatchingExclusionIsSufficientToAllow(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'hosts' => ['*.example.test'],
            'paths' => ['/app/*'],
            'exclude_hosts' => ['trusted.example.test'],
            'exclude_paths' => ['/app/public/*'],
        ]);

        $this->assertFalse($policy->requiresChallenge(
            $this->request('/app/dashboard', 'trusted.example.test')
        ));

        $this->assertFalse($policy->requiresChallenge(
            $this->request('/app/public/docs', 'api.example.test')
        ));
    }

    public function testHostExclusionDoesNotNeedPathToMatch(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'hosts' => ['*.example.test'],
            'paths' => ['/app/*'],
            'exclude_hosts' => ['health.example.test'],
        ]);

        $this->assertFalse($policy->requiresChallenge(
            $this->request('/anything', 'health.example.test')
        ));
    }

    public function testPathExclusionDoesNotNeedHostToMatch(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'hosts' => ['app.example.test'],
            'paths' => ['/app/*'],
            'exclude_paths' => ['/health'],
        ]);

        $this->assertFalse($policy->requiresChallenge(
            $this->request('/health', 'unrelated.example.test')
        ));
    }

    public function testEmptyHostsMeansAllHosts(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'hosts' => [],
            'paths' => ['/'],
        ]);

        $this->assertTrue($policy->requiresChallenge(
            $this->request('/', 'one.example.test')
        ));
        $this->assertTrue($policy->requiresChallenge(
            $this->request('/', 'two.example.test')
        ));
    }

    public function testEmptyPathsMeansAllPaths(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'hosts' => ['app.example.test'],
            'paths' => [],
        ]);

        $this->assertTrue($policy->requiresChallenge(
            $this->request('/', 'app.example.test')
        ));
        $this->assertTrue($policy->requiresChallenge(
            $this->request('/anything', 'app.example.test')
        ));
    }
}
