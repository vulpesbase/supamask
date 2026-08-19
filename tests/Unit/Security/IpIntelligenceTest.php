<?php

namespace Supamask\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Supamask\Core\Config;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Http\Request;
use Supamask\Middleware\IpIntelligenceMiddleware;
use Supamask\Security\IpIntelligence\IpIntelligenceProviderException;
use Supamask\Security\IpIntelligence\IpIntelligenceProviderInterface;
use Supamask\Security\IpIntelligence\IpIntelligenceResult;

final class IpIntelligenceTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
        parent::tearDown();
    }

    private function context(string $ip = '8.8.8.8'): Context
    {
        $_SERVER['REMOTE_ADDR'] = $ip;
        return new Context(new Request(), new Config());
    }

    private function provider(?IpIntelligenceResult $result = null, ?\Throwable $failure = null): IpIntelligenceProviderInterface
    {
        return new class($result, $failure) implements IpIntelligenceProviderInterface {
            public int $calls = 0;
            public function __construct(private ?IpIntelligenceResult $result, private ?\Throwable $failure) {}
            public function lookup(string $ip): IpIntelligenceResult
            {
                $this->calls++;
                if ($this->failure !== null) {
                    throw $this->failure;
                }
                return $this->result ?? new IpIntelligenceResult($ip);
            }
        };
    }

    public function testDisabledPoliciesDoNotLookup(): void
    {
        $provider = $this->provider(new IpIntelligenceResult('8.8.8.8', 'AS15169', 'Google LLC'));
        $middleware = new IpIntelligenceMiddleware($provider, false, false);

        $this->assertSame(Decision::ALLOW, $middleware->handle($this->context()));
        $this->assertSame(0, $provider->calls);
    }

    public function testVpnIsDeniedWhenBlockingIsEnabled(): void
    {
        $provider = $this->provider(new IpIntelligenceResult('8.8.8.8', 'AS64500', 'VPN Network', true));
        $middleware = new IpIntelligenceMiddleware($provider, true, false);

        $this->assertSame(Decision::DENY, $middleware->handle($this->context()));
        $this->assertNotNull($this->contextWithMiddleware($middleware)->ipIntelligence());
    }

    private function contextWithMiddleware(IpIntelligenceMiddleware $middleware): Context
    {
        $context = $this->context();
        $middleware->handle($context);
        return $context;
    }

    public function testNormalIpIsAllowedWhenVpnBlockingEnabled(): void
    {
        $provider = $this->provider(new IpIntelligenceResult('8.8.8.8', 'AS15169', 'Google LLC', false));
        $middleware = new IpIntelligenceMiddleware($provider, true, false);

        $this->assertSame(Decision::ALLOW, $middleware->handle($this->context()));
    }

    public function testProviderFailureFailsOpenByDefault(): void
    {
        $provider = $this->provider(null, new IpIntelligenceProviderException('timeout'));
        $middleware = new IpIntelligenceMiddleware($provider, true, false, [], false);

        $this->assertSame(Decision::ALLOW, $middleware->handle($this->context()));
    }

    public function testProviderFailureCanFailClosed(): void
    {
        $provider = $this->provider(null, new IpIntelligenceProviderException('timeout'));
        $middleware = new IpIntelligenceMiddleware($provider, true, false, [], true);

        $this->assertSame(Decision::DENY, $middleware->handle($this->context()));
    }

    public function testPrivateIpIsNotLookedUpByService(): void
    {
        $provider = $this->provider(new IpIntelligenceResult('127.0.0.1'));
        $service = new \Supamask\Security\IpIntelligence\IpIntelligenceService($provider, true);

        $result = $service->lookup('127.0.0.1');

        $this->assertSame('127.0.0.1', $result->ip());
        $this->assertNull($result->asn());
        $this->assertFalse($result->isVpn());
        $this->assertSame(0, $provider->calls);
    }

    public function testAsnExclusionDenies(): void
    {
        $provider = $this->provider(new IpIntelligenceResult('8.8.8.8', 'AS15169', 'Google LLC'));
        $middleware = new IpIntelligenceMiddleware($provider, false, true, ['AS15169']);

        $this->assertSame(Decision::DENY, $middleware->handle($this->context()));
    }

    public function testOrganizationExclusionDeniesAsConvenience(): void
    {
        $provider = $this->provider(new IpIntelligenceResult('8.8.8.8', 'AS15169', 'Google LLC'));
        $middleware = new IpIntelligenceMiddleware($provider, false, true, ['google llc']);

        $this->assertSame(Decision::DENY, $middleware->handle($this->context()));
    }

    public function testUnknownAsnDoesNotProduceFalsePositive(): void
    {
        $provider = $this->provider(new IpIntelligenceResult('8.8.8.8', null, null));
        $middleware = new IpIntelligenceMiddleware($provider, false, true, ['AS15169', 'Google LLC']);

        $this->assertSame(Decision::ALLOW, $middleware->handle($this->context()));
    }

    public function testProxyAndTorAreExposedButDoNotTriggerVpnPolicy(): void
    {
        $provider = $this->provider(new IpIntelligenceResult('8.8.8.8', 'AS64500', 'Anonymizer', false, true, true));
        $middleware = new IpIntelligenceMiddleware($provider, true, false);
        $context = $this->context();

        $this->assertSame(Decision::ALLOW, $middleware->handle($context));
        $this->assertTrue($context->ipIntelligence()?->isProxy());
        $this->assertTrue($context->ipIntelligence()?->isTor());
    }
}
