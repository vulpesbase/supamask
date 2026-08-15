<?php

namespace Supamask\Tests\Unit\Entry;

use PHPUnit\Framework\TestCase;
use Supamask\Entry\EntryClassification;
use Supamask\Entry\EntryClassifier;
use Supamask\Entry\DisposableEntryManager;
use Supamask\Entry\InMemoryDisposableEntryRegistry;
use Supamask\Http\RequestContext;

/**
 * Unit tests for EntryClassifier.
 *
 * Each test constructs a RequestContext manually (avoiding any Request or
 * $_SERVER coupling) to keep tests hermetic.
 */
final class EntryClassifierTest extends TestCase
{
    private InMemoryDisposableEntryRegistry $registry;
    private DisposableEntryManager $manager;

    protected function setUp(): void
    {
        $this->registry = new InMemoryDisposableEntryRegistry();
        $this->manager  = new DisposableEntryManager($this->registry, 900, 12);
    }

    private function classifier(array $trustedReferrers = []): EntryClassifier
    {
        return new EntryClassifier($this->manager, $trustedReferrers);
    }

    private function context(
        string $path = '/',
        ?string $referrer = null,
        string $method = 'GET',
        string $host = 'example.test',
    ): RequestContext {
        return new RequestContext(
            $method,
            'https',
            $host,
            443,
            $path,
            '',
            '127.0.0.1',
            'Mozilla/5.0',
            $referrer,
            [],
        );
    }

    // ── DIRECT ────────────────────────────────────────────────────────────────

    public function testNoReferrerIsDirect(): void
    {
        $ctx = $this->context('/', null);
        $this->assertSame(EntryClassification::DIRECT, $this->classifier()->classify($ctx));
    }

    public function testEmptyReferrerIsDirect(): void
    {
        $ctx = $this->context('/', '');
        $this->assertSame(EntryClassification::DIRECT, $this->classifier()->classify($ctx));
    }

    // ── SEEDED ────────────────────────────────────────────────────────────────

    public function testPathMatchingActiveSlugIsSeeded(): void
    {
        $entry = $this->manager->generate('/pricing');
        $ctx   = $this->context('/' . $entry->slug());
        $this->assertSame(EntryClassification::SEEDED, $this->classifier()->classify($ctx));
    }

    public function testSlugShapedPathWithNoEntryIsNotSeeded(): void
    {
        // Path looks like a slug but there's no active entry registered.
        // It must NOT be classified as SEEDED.
        $ctx = $this->context('/a1b2c3d4e5f6');
        // No referrer -> DIRECT
        $this->assertSame(EntryClassification::DIRECT, $this->classifier()->classify($ctx));
    }

    public function testConsumedSlugIsNotSeeded(): void
    {
        $entry = $this->manager->generate('/pricing');
        $this->manager->consume($entry->slug());

        $ctx = $this->context('/' . $entry->slug());
        // Since it's consumed, inspect() will throw and it should fall through to DIRECT
        $this->assertSame(EntryClassification::DIRECT, $this->classifier()->classify($ctx));
    }

    public function testNonSlugPathIsNotSeeded(): void
    {
        $ctx = $this->context('/pricing');
        // No referrer → DIRECT
        $this->assertSame(EntryClassification::DIRECT, $this->classifier()->classify($ctx));
    }

    // ── REFERRED ─────────────────────────────────────────────────────────────

    public function testTrustedReferrerIsReferred(): void
    {
        $ctx = $this->context('/', 'https://trusted.example/page');
        $classifier = $this->classifier(['https://trusted.example/']);
        $this->assertSame(EntryClassification::REFERRED, $classifier->classify($ctx));
    }

    public function testWildcardReferrerPatternIsReferred(): void
    {
        $ctx = $this->context('/', 'https://shop.trusted.example/deals');
        $classifier = $this->classifier(['*.trusted.example']);
        $this->assertSame(EntryClassification::REFERRED, $classifier->classify($ctx));
    }

    public function testReferrerMatchingRootPatternIsReferred(): void
    {
        $ctx = $this->context('/', 'https://trusted.example/');
        $classifier = $this->classifier(['https://trusted.example/']);
        $this->assertSame(EntryClassification::REFERRED, $classifier->classify($ctx));
    }

    public function testReferrerWithDifferentSchemeIsNotReferred(): void
    {
        $ctx = $this->context('/', 'http://trusted.example/');
        $classifier = $this->classifier(['https://trusted.example/']);
        // scheme mismatch → UNKNOWN
        $this->assertSame(EntryClassification::UNKNOWN, $classifier->classify($ctx));
    }

    // ── UNKNOWN ───────────────────────────────────────────────────────────────

    public function testUntrustedReferrerIsUnknown(): void
    {
        $ctx = $this->context('/', 'https://untrusted.example/');
        $classifier = $this->classifier(['https://trusted.example/']);
        $this->assertSame(EntryClassification::UNKNOWN, $classifier->classify($ctx));
    }

    public function testMalformedReferrerIsUnknown(): void
    {
        $ctx = $this->context('/', ':::not-a-url:::');
        $this->assertSame(EntryClassification::UNKNOWN, $this->classifier()->classify($ctx));
    }

    public function testReferrerWithNoHostIsUnknown(): void
    {
        // A bare path referrer has no host component
        $ctx = $this->context('/', '/just-a-path');
        $this->assertSame(EntryClassification::UNKNOWN, $this->classifier()->classify($ctx));
    }

    public function testReferrerWithNoTrustedListIsUnknown(): void
    {
        // Referrer is present, but no trusted list is configured
        $ctx = $this->context('/', 'https://some.example/');
        $classifier = $this->classifier([]);  // empty trusted list
        $this->assertSame(EntryClassification::UNKNOWN, $classifier->classify($ctx));
    }

    // ── SEEDED takes precedence over referrer ──────────────────────────────────

    public function testSeededPathWithReferrerIsStillSeeded(): void
    {
        $entry = $this->manager->generate('/pricing');
        $ctx   = $this->context(
            '/' . $entry->slug(),
            'https://trusted.example/'
        );
        $classifier = $this->classifier(['https://trusted.example/']);
        $this->assertSame(EntryClassification::SEEDED, $classifier->classify($ctx));
    }

    // ── Cross-origin referrer ─────────────────────────────────────────────────

    public function testCrossOriginReferrerNotInTrustedListIsUnknown(): void
    {
        $ctx = $this->context('/', 'https://social-media.example/post/123');
        $classifier = $this->classifier(['https://trusted.example/']);
        $this->assertSame(EntryClassification::UNKNOWN, $classifier->classify($ctx));
    }

    // ── Wildcard subdomain edge cases ─────────────────────────────────────────

    public function testWildcardDoesNotMatchRootDomain(): void
    {
        // *.trusted.example should NOT match trusted.example itself
        $ctx = $this->context('/', 'https://trusted.example/');
        $classifier = $this->classifier(['*.trusted.example']);
        $this->assertSame(EntryClassification::UNKNOWN, $classifier->classify($ctx));
    }

    public function testWildcardMatchesMultipleLevels(): void
    {
        $ctx = $this->context('/', 'https://deep.sub.trusted.example/');
        $classifier = $this->classifier(['*.trusted.example']);
        // RouteMatcher wildcard matches any subdomain of trusted.example
        $this->assertSame(EntryClassification::REFERRED, $classifier->classify($ctx));
    }
}
