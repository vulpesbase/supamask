<?php

namespace Supamask\Tests\Unit\Security;

use PHPUnit\Framework\TestCase;
use Supamask\Security\BotMatcher;

class BotMatcherTest extends TestCase
{
    public function testMatchingSignature(): void
    {
        $matcher = new BotMatcher(['Googlebot']);

        $this->assertTrue($matcher->matches('Mozilla/5.0 (compatible; Googlebot/2.1)'));
    }

    public function testNonMatchingUserAgent(): void
    {
        $matcher = new BotMatcher(['Googlebot']);

        $this->assertFalse($matcher->matches('Mozilla/5.0 (Windows NT 10.0; Win64; x64)'));
    }

    public function testCaseInsensitiveMatching(): void
    {
        $matcher = new BotMatcher(['googlebot']);

        $this->assertTrue($matcher->matches('Mozilla/5.0 (compatible; GOOGLEBOT/2.1)'));
    }

    public function testMultipleSignaturesFirstMatches(): void
    {
        $matcher = new BotMatcher(['crawler', 'spider', 'bot']);

        $this->assertTrue($matcher->matches('MyCrawler/1.0'));
    }

    public function testMultipleSignaturesLastMatches(): void
    {
        $matcher = new BotMatcher(['crawler', 'spider', 'bot']);

        $this->assertTrue($matcher->matches('SomeBot/1.0'));
    }

    public function testMultipleSignaturesNoneMatch(): void
    {
        $matcher = new BotMatcher(['crawler', 'spider', 'bot']);

        $this->assertFalse($matcher->matches('Mozilla/5.0 (Windows NT 10.0)'));
    }

    public function testEmptySignaturesNeverMatches(): void
    {
        $matcher = new BotMatcher([]);

        $this->assertFalse($matcher->matches('Googlebot/2.1'));
    }

    public function testEmptyUserAgentDoesNotMatch(): void
    {
        $matcher = new BotMatcher(['bot']);

        $this->assertFalse($matcher->matches(''));
    }

    public function testPartialSubstringMatch(): void
    {
        $matcher = new BotMatcher(['spider']);

        $this->assertTrue($matcher->matches('Mozilla/5.0 BaiduSpider/2.0'));
    }
}
