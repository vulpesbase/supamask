<?php

namespace Supamask\Tests\Unit\Entry;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Supamask\Entry\DisposableEntry;
use Supamask\Entry\DisposableEntryState;

/**
 * Unit tests for DisposableEntry — validation, lifecycle, and open-redirect protection.
 */
final class DisposableEntryTest extends TestCase
{
    private function validEntry(
        string $slug = 'a1b2c3d4e5f6',
        string $destination = '/pricing',
        string $createdAt = '2025-01-01T00:00:00+00:00',
        string $expiresAt = '2025-01-01T00:15:00+00:00',
    ): DisposableEntry {
        return new DisposableEntry(
            $slug,
            $destination,
            new DateTimeImmutable($createdAt),
            new DateTimeImmutable($expiresAt),
        );
    }

    // ── Slug validation ────────────────────────────────────────────────────────

    public function testValidSlugIsAccepted(): void
    {
        $entry = $this->validEntry('a1b2c3d4e5f6');
        $this->assertSame('a1b2c3d4e5f6', $entry->slug());
    }

    public function testSlugWithUppercaseIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/12-character lowercase hexadecimal/i');
        $this->validEntry('A1B2C3D4E5F6');
    }

    public function testSlugWithWrongLengthIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validEntry('a1b2c3');   // 6 chars, not 12
    }

    public function testSlugWithNonHexCharsIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validEntry('g1b2c3d4e5f6');   // 'g' is not hex
    }

    public function testEmptySlugIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validEntry('');
    }

    // ── Destination validation / open-redirect protection ─────────────────────

    public function testLocalPathIsAccepted(): void
    {
        $entry = $this->validEntry(destination: '/pricing');
        $this->assertSame('/pricing', $entry->destination());
    }

    public function testRootPathIsAccepted(): void
    {
        $entry = $this->validEntry(destination: '/');
        $this->assertSame('/', $entry->destination());
    }

    public function testPathWithQueryStringIsAccepted(): void
    {
        $entry = $this->validEntry(destination: '/pricing?plan=pro');
        $this->assertSame('/pricing?plan=pro', $entry->destination());
    }

    public function testExternalHttpsUrlIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/open-redirect/i');
        $this->validEntry(destination: 'https://evil.example');
    }

    public function testProtocolRelativeUrlIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validEntry(destination: '//evil.example');
    }

    public function testJavascriptSchemeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validEntry(destination: '/javascript:alert(1)');
    }

    public function testEmptyDestinationIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validEntry(destination: '');
    }

    public function testRelativePathWithoutLeadingSlashIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->validEntry(destination: 'pricing');
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function testNewEntryIsActive(): void
    {
        $entry = $this->validEntry();
        $this->assertTrue($entry->isActive());
        $this->assertSame(DisposableEntryState::ACTIVE, $entry->state());
    }

    public function testConsumeTransitionsToConsumedState(): void
    {
        $entry = $this->validEntry();
        $entry->consume();
        $this->assertSame(DisposableEntryState::CONSUMED, $entry->state());
        $this->assertFalse($entry->isActive());
    }

    public function testConsumedEntryCannotBeConsumedAgain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/only active/i');
        $entry = $this->validEntry();
        $entry->consume();
        $entry->consume();  // replay — should throw
    }

    public function testExpireTransitionsToExpiredState(): void
    {
        $entry = $this->validEntry();
        $entry->expire();
        $this->assertSame(DisposableEntryState::EXPIRED, $entry->state());
    }

    public function testExpireOnAlreadyConsumedEntryIsNoop(): void
    {
        $entry = $this->validEntry();
        $entry->consume();
        $entry->expire();  // should be a no-op
        $this->assertSame(DisposableEntryState::CONSUMED, $entry->state());
    }

    public function testExpireOnAlreadyExpiredEntryIsNoop(): void
    {
        $entry = $this->validEntry();
        $entry->expire();
        $entry->expire();  // idempotent
        $this->assertSame(DisposableEntryState::EXPIRED, $entry->state());
    }

    // ── Expiration check ──────────────────────────────────────────────────────

    public function testIsExpiredReturnsFalseBeforeTtl(): void
    {
        $entry = $this->validEntry(
            createdAt: '2025-01-01T00:00:00+00:00',
            expiresAt: '2025-01-01T00:15:00+00:00',
        );
        $now = new DateTimeImmutable('2025-01-01T00:10:00+00:00');
        $this->assertFalse($entry->isExpired($now));
    }

    public function testIsExpiredReturnsTrueAtExpirationInstant(): void
    {
        $entry = $this->validEntry(
            createdAt: '2025-01-01T00:00:00+00:00',
            expiresAt: '2025-01-01T00:15:00+00:00',
        );
        $now = new DateTimeImmutable('2025-01-01T00:15:00+00:00');
        $this->assertTrue($entry->isExpired($now));
    }

    public function testIsExpiredReturnsTrueAfterTtl(): void
    {
        $entry = $this->validEntry(
            createdAt: '2025-01-01T00:00:00+00:00',
            expiresAt: '2025-01-01T00:15:00+00:00',
        );
        $now = new DateTimeImmutable('2025-01-01T01:00:00+00:00');
        $this->assertTrue($entry->isExpired($now));
    }

    // ── Expiry timing constraint ───────────────────────────────────────────────

    public function testExpiresAtBeforeCreatedAtIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DisposableEntry(
            'a1b2c3d4e5f6',
            '/pricing',
            new DateTimeImmutable('2025-01-01T00:15:00+00:00'),
            new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
        );
    }

    public function testExpiresAtEqualToCreatedAtIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DisposableEntry(
            'a1b2c3d4e5f6',
            '/pricing',
            new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
        );
    }

    // ── Custom slug length ────────────────────────────────────────────────────

    public function testCustomSlugLengthIsAccepted(): void
    {
        $entry = new DisposableEntry(
            'a1b2c3d4',      // 8 chars
            '/pricing',
            new DateTimeImmutable('2025-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2025-01-01T00:15:00+00:00'),
            DisposableEntryState::ACTIVE,
            true,
            8,               // slug_length = 8
        );
        $this->assertSame('a1b2c3d4', $entry->slug());
        $this->assertSame(8, $entry->slugLength());
    }

    // ── isValidLocalPath static helper ────────────────────────────────────────

    #[DataProvider('validLocalPaths')]
    public function testIsValidLocalPathAcceptsValidPaths(string $path): void
    {
        $this->assertTrue(DisposableEntry::isValidLocalPath($path), "Expected '$path' to be valid");
    }

    /** @return array<string, array{string}> */
    public static function validLocalPaths(): array
    {
        return [
            'root'              => ['/'],
            'simple path'       => ['/pricing'],
            'nested path'       => ['/app/dashboard'],
            'path with query'   => ['/pricing?plan=pro'],
            'path with fragment'=> ['/pricing#section'],
        ];
    }

    #[DataProvider('invalidLocalPaths')]
    public function testIsValidLocalPathRejectsInvalidPaths(string $path): void
    {
        $this->assertFalse(DisposableEntry::isValidLocalPath($path), "Expected '$path' to be invalid");
    }

    /** @return array<string, array{string}> */
    public static function invalidLocalPaths(): array
    {
        return [
            'empty string'            => [''],
            'no leading slash'        => ['pricing'],
            'absolute http url'       => ['http://evil.example'],
            'absolute https url'      => ['https://evil.example/path'],
            'protocol-relative url'   => ['//evil.example'],
            'javascript scheme'       => ['/javascript:alert(1)'],
            'data scheme'             => ['/data:text/html,<h1>xss</h1>'],
        ];
    }
}
