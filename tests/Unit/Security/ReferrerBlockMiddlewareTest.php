<?php

namespace Supamask\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Supamask\Core\Config;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Http\Request;
use Supamask\Middleware\ReferrerBlockMiddleware;

final class ReferrerBlockMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_REFERER']);
        parent::tearDown();
    }

    private function context(?string $referrer): Context
    {
        if ($referrer === null) {
            unset($_SERVER['HTTP_REFERER']);
        } else {
            $_SERVER['HTTP_REFERER'] = $referrer;
        }

        return new Context(new Request(), new Config());
    }

    public function testDisabledDoesNotEvaluateReferrer(): void
    {
        $middleware = new ReferrerBlockMiddleware(false, ['badsite.com']);
        self::assertSame(Decision::ALLOW, $middleware->handle($this->context('https://badsite.com/path')));
    }

    public function testExactHostIsDenied(): void
    {
        $middleware = new ReferrerBlockMiddleware(true, ['badsite.com']);
        self::assertSame(Decision::DENY, $middleware->handle($this->context('https://badsite.com/some/path?x=1')));
    }

    public function testSubdomainIsDenied(): void
    {
        $middleware = new ReferrerBlockMiddleware(true, ['badsite.com']);
        self::assertSame(Decision::DENY, $middleware->handle($this->context('https://sub.badsite.com/path')));
        self::assertSame(Decision::DENY, $middleware->handle($this->context('https://www.badsite.com/path')));
    }

    public function testLookalikeDomainsAreNotDenied(): void
    {
        $middleware = new ReferrerBlockMiddleware(true, ['badsite.com']);
        self::assertSame(Decision::ALLOW, $middleware->handle($this->context('https://badsite.com.evil.com/')));
        self::assertSame(Decision::ALLOW, $middleware->handle($this->context('https://notbadsite.com/')));
    }

    public function testCaseAndTrailingDotAreNormalized(): void
    {
        $middleware = new ReferrerBlockMiddleware(true, ['BADsite.com.']);
        self::assertSame(Decision::DENY, $middleware->handle($this->context('https://WWW.BADsite.COM./')));
    }

    public function testMultipleHostsAreSupported(): void
    {
        $middleware = new ReferrerBlockMiddleware(true, ['badsite.com', 'example-spam.com']);
        self::assertSame(Decision::DENY, $middleware->handle($this->context('https://example-spam.com/')));
        self::assertSame(Decision::ALLOW, $middleware->handle($this->context('https://good.example/')));
    }

    public function testMissingReferrerIsAllowedByDefault(): void
    {
        $middleware = new ReferrerBlockMiddleware(true, ['badsite.com']);
        self::assertSame(Decision::ALLOW, $middleware->handle($this->context(null)));
    }

    public function testMissingReferrerCanBeDeniedExplicitly(): void
    {
        $middleware = new ReferrerBlockMiddleware(true, ['badsite.com'], true);
        self::assertSame(Decision::DENY, $middleware->handle($this->context(null)));
        self::assertSame(Decision::DENY, $middleware->handle($this->context('')));
    }

    public function testMalformedReferrerIsHandledSafely(): void
    {
        $middleware = new ReferrerBlockMiddleware(true, ['badsite.com']);
        self::assertSame(Decision::ALLOW, $middleware->handle($this->context('not a valid url')));
    }

    public function testConfiguredHostDoesNotUseNaiveSubstringMatching(): void
    {
        $middleware = new ReferrerBlockMiddleware(true, ['example-spam.com']);
        self::assertSame(Decision::ALLOW, $middleware->handle($this->context('https://very-example-spam.com.evil/')));
    }

    public function testInternationalizedDomainCanNormalizeWhenIntlIsAvailable(): void
    {
        if (!function_exists('idn_to_ascii')) {
            self::markTestSkipped('ext-intl is not available.');
        }

        $middleware = new ReferrerBlockMiddleware(true, ['münich.example']);
        self::assertSame(Decision::DENY, $middleware->handle($this->context('https://xn--mnich-kva.example/')));
    }
}
