<?php

namespace Supamask\Tests\Unit\Challenge;

use PHPUnit\Framework\TestCase;
use Supamask\Challenge\ChallengeSecurityPolicy;

final class ChallengeSecurityPolicyTest extends TestCase
{
    public function testProtectAddsRequiredMetadataAndSecurityHeaders(): void
    {
        $policy = new ChallengeSecurityPolicy();
        $result = $policy->protect('<!doctype html><html><head><title>Test</title></head><body><script>console.log(1);</script></body></html>');

        $this->assertStringContainsString('<meta name="robots" content="noindex,nofollow">', $result['body']);
        $this->assertStringContainsString('<meta name="referrer" content="no-referrer">', $result['body']);
        $this->assertSame('no-referrer', $result['headers']['Referrer-Policy']);
        $this->assertSame('noindex, nofollow', $result['headers']['X-Robots-Tag']);
        $this->assertStringContainsString("default-src 'none'", $result['headers']['Content-Security-Policy']);
        $this->assertStringContainsString("script-src 'nonce-", $result['headers']['Content-Security-Policy']);
        $this->assertStringContainsString("style-src 'unsafe-inline'", $result['headers']['Content-Security-Policy']);
        $this->assertStringContainsString("connect-src 'self'", $result['headers']['Content-Security-Policy']);
    }

    public function testEveryInlineScriptReceivesTheResponseNonce(): void
    {
        $policy = new ChallengeSecurityPolicy();
        $result = $policy->protect('<html><head></head><body><script>one();</script><script type="application/javascript">two();</script></body></html>');

        preg_match('/script-src \'nonce-([^\']+)\'/i', $result['headers']['Content-Security-Policy'], $headerMatch);
        $this->assertArrayHasKey(1, $headerMatch);
        $nonce = $headerMatch[1];

        $this->assertSame(2, preg_match_all('/<script\b[^>]*\bnonce="([^"]+)"[^>]*>/i', $result['body'], $matches));
        $this->assertSame([$nonce, $nonce], $matches[1]);
    }

    public function testExistingMetadataIsNotDuplicated(): void
    {
        $policy = new ChallengeSecurityPolicy();
        $html = '<html><head><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"></head><body></body></html>';
        $result = $policy->protect($html);

        $this->assertSame(1, substr_count($result['body'], 'name="robots"'));
        $this->assertSame(1, substr_count($result['body'], 'name="referrer"'));
    }
}
