<?php

namespace Supamask\Tests\Unit\Routing;

use PHPUnit\Framework\TestCase;
use Supamask\Routing\RouteMatcher;

final class RouteMatcherTest extends TestCase
{
    private RouteMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new RouteMatcher();
    }

    public function testTrailingSlashesAreNormalized(): void
    {
        $this->assertTrue($this->matcher->pathMatches('/pricing/', ['/pricing']));
        $this->assertTrue($this->matcher->pathMatches('/pricing///', ['/pricing/']));
    }

    public function testQueryStringsDoNotAffectPathMatching(): void
    {
        $this->assertTrue(
            $this->matcher->pathMatches('/pricing?plan=pro', ['/pricing'])
        );
    }

    public function testWildcardMatchesRouteSubtreeButNotPrefixCollision(): void
    {
        $this->assertTrue($this->matcher->pathMatches('/app', ['/app/*']));
        $this->assertTrue($this->matcher->pathMatches('/app/dashboard', ['/app/*']));
        $this->assertFalse($this->matcher->pathMatches('/application', ['/app/*']));
    }

    public function testDuplicateSlashesAreNormalized(): void
    {
        $this->assertTrue($this->matcher->pathMatches('//app//dashboard/', ['/app/dashboard']));
    }

    public function testHostMatchingIgnoresCaseAndPort(): void
    {
        $this->assertTrue(
            $this->matcher->hostMatches('APP.Example.TEST:443', ['app.example.test'])
        );
    }

    public function testWildcardHostMatchesSubdomains(): void
    {
        $this->assertTrue(
            $this->matcher->hostMatches('api.example.test', ['*.example.test'])
        );

        $this->assertFalse(
            $this->matcher->hostMatches('example.test', ['*.example.test'])
        );
    }

    public function testIpv6HostPreservesLiteral(): void
    {
        $this->assertSame('[2001:db8::1]', $this->matcher->normalizeHost('[2001:db8::1]:443'));
    }

    public function testRegexPathPatternWorks(): void
    {
        $this->assertTrue(
            $this->matcher->pathMatches('/invoice/123', ['~^/invoice/[0-9]+$~'])
        );
    }
}
