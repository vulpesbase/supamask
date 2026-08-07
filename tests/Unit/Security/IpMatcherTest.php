<?php

namespace Supamask\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Supamask\Security\IpMatcher;

class IpMatcherTest extends TestCase
{
    private IpMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new IpMatcher();
    }

    // ── IPv4 exact match ──

    public function testIpv4ExactMatch(): void
    {
        $this->assertTrue(
            $this->matcher->matches('192.168.1.1', '192.168.1.1')
        );
    }

    public function testIpv4ExactNonMatch(): void
    {
        $this->assertFalse(
            $this->matcher->matches('192.168.1.1', '192.168.1.2')
        );
    }

    // ── IPv4 CIDR ──

    public function testIpv4CidrMatch(): void
    {
        $this->assertTrue(
            $this->matcher->matches('10.0.0.5', '10.0.0.0/24')
        );
    }

    public function testIpv4CidrNonMatch(): void
    {
        $this->assertFalse(
            $this->matcher->matches('10.0.1.5', '10.0.0.0/24')
        );
    }

    public function testIpv4CidrSlash8(): void
    {
        $this->assertTrue(
            $this->matcher->matches('10.255.255.255', '10.0.0.0/8')
        );
    }

    public function testIpv4CidrSlash32(): void
    {
        $this->assertTrue(
            $this->matcher->matches('10.0.0.1', '10.0.0.1/32')
        );
    }

    public function testIpv4CidrSlash32NonMatch(): void
    {
        $this->assertFalse(
            $this->matcher->matches('10.0.0.2', '10.0.0.1/32')
        );
    }

    public function testIpv4CidrSlash16(): void
    {
        $this->assertTrue(
            $this->matcher->matches('172.16.255.1', '172.16.0.0/16')
        );
    }

    public function testIpv4CidrSlash16NonMatch(): void
    {
        $this->assertFalse(
            $this->matcher->matches('172.17.0.1', '172.16.0.0/16')
        );
    }

    // ── IPv6 exact match ──

    public function testIpv6ExactMatch(): void
    {
        $this->assertTrue(
            $this->matcher->matches('::1', '::1')
        );
    }

    public function testIpv6ExactNonMatch(): void
    {
        $this->assertFalse(
            $this->matcher->matches('::1', '::2')
        );
    }

    public function testIpv6FullExactMatch(): void
    {
        $this->assertTrue(
            $this->matcher->matches(
                '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
                '2001:0db8:85a3:0000:0000:8a2e:0370:7334'
            )
        );
    }

    // ── IPv6 CIDR ──

    public function testIpv6CidrMatch(): void
    {
        $this->assertTrue(
            $this->matcher->matches('2001:db8::1', '2001:db8::/32')
        );
    }

    public function testIpv6CidrNonMatch(): void
    {
        $this->assertFalse(
            $this->matcher->matches('2001:db9::1', '2001:db8::/32')
        );
    }

    public function testIpv6CidrSlash64(): void
    {
        $this->assertTrue(
            $this->matcher->matches(
                '2001:db8:abcd:0012:ffff::1',
                '2001:db8:abcd:0012::/64'
            )
        );
    }

    public function testIpv6CidrSlash128(): void
    {
        $this->assertTrue(
            $this->matcher->matches('::1', '::1/128')
        );
    }

    public function testIpv6CidrSlash128NonMatch(): void
    {
        $this->assertFalse(
            $this->matcher->matches('::2', '::1/128')
        );
    }

    // ── Invalid rules ──

    public function testInvalidIpReturnsfalse(): void
    {
        $this->assertFalse(
            $this->matcher->matches('not-an-ip', '10.0.0.0/24')
        );
    }

    public function testInvalidRuleReturnsfalse(): void
    {
        $this->assertFalse(
            $this->matcher->matches('10.0.0.1', 'not-a-cidr/24')
        );
    }

    public function testMixedIpv4Ipv6ReturnsFalse(): void
    {
        $this->assertFalse(
            $this->matcher->matches('192.168.1.1', '::1/128')
        );
    }

    public function testInvalidPrefixTooLargeReturnsFalse(): void
    {
        $this->assertFalse(
            $this->matcher->matches('10.0.0.1', '10.0.0.0/33')
        );
    }

    public function testInvalidNegativePrefixReturnsFalse(): void
    {
        $this->assertFalse(
            $this->matcher->matches('10.0.0.1', '10.0.0.0/-1')
        );
    }
}
