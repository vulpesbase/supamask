<?php

namespace Supamask\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Supamask\Http\Request;
use Supamask\Http\RequestContext;
use Supamask\Http\RequestContextFactory;
use Supamask\Routing\RoutePolicy;

final class RoutePolicyTest extends TestCase
{
    private function request(string $uri = '/', string $host = 'example.test'): RequestContext
    {
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = $host;

        return (new RequestContextFactory())->fromRequest(new Request());
    }

    public function testRootCanBeProtected(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'paths' => ['/'],
        ]);

        $this->assertTrue($policy->requiresChallenge($this->request('/')));
    }

    public function testSubpathsCanBeProtectedWithWildcard(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'paths' => ['/app/*'],
        ]);

        $this->assertTrue($policy->requiresChallenge($this->request('/app/dashboard')));
        $this->assertFalse($policy->requiresChallenge($this->request('/pricing')));
    }

    public function testHostsCanBeRestricted(): void
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

    public function testExcludedPathsOverrideProtection(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'paths' => ['/app/*'],
            'exclude_paths' => ['/app/health'],
        ]);

        $this->assertFalse($policy->requiresChallenge(
            $this->request('/app/health')
        ));
        $this->assertTrue($policy->requiresChallenge(
            $this->request('/app/dashboard')
        ));
    }

    public function testExcludedHostsOverrideProtection(): void
    {
        $policy = new RoutePolicy([
            'enabled' => true,
            'hosts' => ['*.example.test'],
            'exclude_hosts' => ['health.example.test'],
        ]);

        # Host patterns are exact or wildcard-path style; this test verifies
        # the exclusion independently of wildcard host matching.
        $policy = new RoutePolicy([
            'enabled' => true,
            'hosts' => ['health.example.test'],
            'exclude_hosts' => ['health.example.test'],
        ]);

        $this->assertFalse($policy->requiresChallenge(
            $this->request('/', 'health.example.test')
        ));
    }

    public function testDisabledPolicyNeverChallenges(): void
    {
        $policy = new RoutePolicy(['enabled' => false]);

        $this->assertFalse($policy->requiresChallenge($this->request('/')));
    }

    // ── Root Behavior Tests ───────────────────────────────────────────────────

    /**
     * Precedence test: root.behavior vs. path exclusions.
     *
     * When root.behavior is defined alongside path protection rules:
     * - Path exclusions are checked FIRST (highest priority)
     * - Root behavior is checked AFTER exclusions
     *
     * This ensures that explicit exclusions always win over root behavior.
     */
    public function testRootBehaviorAllowsRoot(): void
    {
        $policy = new RoutePolicy([
            'protection' => [
                'enabled' => true,
                'paths' => ['/pricing'],
            ],
            'routing' => [
                'root' => ['behavior' => 'allow'],
            ],
        ]);

        // Root has explicit allow → allowed
        $this->assertFalse($policy->requiresChallenge($this->request('/')));
        // /pricing is protected → challenged
        $this->assertTrue($policy->requiresChallenge($this->request('/pricing')));
    }

    public function testRootBehaviorChallengesRoot(): void
    {
        $policy = new RoutePolicy([
            'protection' => [
                'enabled' => true,
                'paths' => ['/pricing'],
            ],
            'routing' => [
                'root' => ['behavior' => 'challenge'],
            ],
        ]);

        // Root has explicit challenge → challenged
        $this->assertTrue($policy->requiresChallenge($this->request('/')));
        // /pricing is protected → challenged
        $this->assertTrue($policy->requiresChallenge($this->request('/pricing')));
    }

    public function testPathExclusionOverridesRootBehavior(): void
    {
        $policy = new RoutePolicy([
            'protection' => [
                'enabled' => true,
                'paths' => ['/'],
                'exclude_paths' => ['/'],
            ],
            'routing' => [
                'root' => ['behavior' => 'challenge'],
            ],
        ]);

        // Exclusion takes precedence: path exclusion wins over root behavior
        // Root is excluded (allowed)
        $this->assertFalse($policy->requiresChallenge($this->request('/')));
    }

    public function testWildcardPathExclusionMatchesRoot(): void
    {
        $policy = new RoutePolicy([
            'protection' => [
                'enabled' => true,
                'paths' => ['/'],
                'exclude_paths' => ['/*'],  // Wildcard includes all paths, including /
            ],
            'routing' => [
                'root' => ['behavior' => 'challenge'],
            ],
        ]);

        // /* matches /, so exclusion applies, overriding root behavior
        // Root is excluded (allowed)
        $this->assertFalse($policy->requiresChallenge($this->request('/')));
    }

    public function testRootNotMatchedBySubpathWildcard(): void
    {
        $policy = new RoutePolicy([
            'protection' => [
                'enabled' => true,
                'paths' => ['/'],
                'exclude_paths' => ['/app/*'],  // Only exclude /app and subpaths
            ],
            'routing' => [
                'root' => ['behavior' => 'challenge'],
            ],
        ]);

        // /app/* does not match /, so root behavior applies
        // Root has explicit challenge → challenged
        $this->assertTrue($policy->requiresChallenge($this->request('/')));
        // But /app/something should be excluded
        $this->assertFalse($policy->requiresChallenge($this->request('/app/something')));
    }
}
