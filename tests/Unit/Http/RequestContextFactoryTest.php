<?php

namespace Supamask\Tests\Unit\Http;

use PHPUnit\Framework\TestCase;
use Supamask\Http\Request;
use Supamask\Http\RequestContextFactory;

final class RequestContextFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        $_SERVER = [];
        parent::tearDown();
    }

    private function request(
        string $uri,
        string $host = 'APP.Example.TEST:443',
        string $method = 'get'
    ): Request {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_SERVER['HTTP_HOST'] = $host;
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_USER_AGENT'] = 'Test Agent';
        $_SERVER['HTTP_REFERER'] = 'https://source.example/campaign';
        $_SERVER['HTTP_X_REQUEST_ID'] = 'abc123';

        return new Request();
    }

    public function testFactoryBuildsNormalizedContext(): void
    {
        $context = (new RequestContextFactory())->fromRequest(
            $this->request('/pricing/?plan=pro')
        );

        $this->assertSame('GET', $context->method());
        $this->assertSame('https', $context->scheme());
        $this->assertSame('app.example.test', $context->host());
        $this->assertSame(443, $context->port());
        $this->assertSame('/pricing', $context->path());
        $this->assertSame('plan=pro', $context->query());
        $this->assertSame('203.0.113.10', $context->ip());
        $this->assertSame('Test Agent', $context->userAgent());
        $this->assertSame(
            'https://source.example/campaign',
            $context->referrer()
        );
        $this->assertSame('abc123', $context->header('X-Request-Id'));
        $this->assertTrue($context->isSecure());
    }

    public function testMissingRefererIsNull(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = 'example.test';

        $context = (new RequestContextFactory())->fromRequest(new Request());

        $this->assertNull($context->referrer());
        $this->assertSame('/', $context->path());
    }

    public function testIpv6HostAndPortArePreserved(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_HOST'] = '[2001:db8::1]:8443';

        $context = (new RequestContextFactory())->fromRequest(new Request());

        $this->assertSame('[2001:db8::1]', $context->host());
        $this->assertSame(8443, $context->port());
    }
}
