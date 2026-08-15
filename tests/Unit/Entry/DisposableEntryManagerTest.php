<?php

namespace Supamask\Tests\Unit\Entry;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Supamask\Entry\DisposableEntry;
use Supamask\Entry\DisposableEntryManager;
use Supamask\Entry\DisposableEntryState;
use Supamask\Entry\InMemoryDisposableEntryRegistry;

/**
 * Unit tests for DisposableEntryManager — generation, lifecycle, security.
 */
final class DisposableEntryManagerTest extends TestCase
{
    private InMemoryDisposableEntryRegistry $registry;
    private DisposableEntryManager $manager;

    protected function setUp(): void
    {
        $this->registry = new InMemoryDisposableEntryRegistry();
        $this->manager  = new DisposableEntryManager($this->registry, 900, 12, true);
    }

    // ── Constructor validation ─────────────────────────────────────────────────

    public function testNegativeTtlThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DisposableEntryManager($this->registry, -1);
    }

    public function testZeroTtlThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DisposableEntryManager($this->registry, 0);
    }

    public function testOddSlugLengthThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DisposableEntryManager($this->registry, 900, 11);
    }

    public function testSlugLengthOfZeroThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new DisposableEntryManager($this->registry, 900, 0);
    }

    // ── Generation ────────────────────────────────────────────────────────────

    public function testGenerateProducesValidEntry(): void
    {
        $entry = $this->manager->generate('/pricing');

        $this->assertSame('/pricing', $entry->destination());
        $this->assertTrue($entry->isActive());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}$/', $entry->slug());
    }

    public function testGeneratedSlugIsLowercaseHex(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $entry = $this->manager->generate('/test');
            $this->assertMatchesRegularExpression(
                '/^[a-f0-9]{12}$/',
                $entry->slug(),
                'Slug must be 12 lowercase hex chars'
            );
        }
    }

    public function testGenerateWithCustomSlugLength(): void
    {
        $manager = new DisposableEntryManager($this->registry, 900, 16);
        $entry   = $manager->generate('/pricing');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $entry->slug());
    }

    public function testGeneratePersistsEntryToRegistry(): void
    {
        $entry = $this->manager->generate('/pricing');
        $found = $this->registry->find($entry->slug());

        $this->assertNotNull($found);
        $this->assertSame($entry->slug(), $found->slug());
    }

    public function testGenerateRejectsExternalDestination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/local path/i');
        $this->manager->generate('https://evil.example');
    }

    public function testGenerateRejectsProtocolRelativeDestination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager->generate('//evil.example');
    }

    public function testGenerateRejectsEmptyDestination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->manager->generate('');
    }

    public function testGenerateSetsCorrectTtl(): void
    {
        $now   = new DateTimeImmutable('2025-01-01T00:00:00+00:00');
        $entry = $this->manager->generate('/pricing', $now);

        $expected = new DateTimeImmutable('2025-01-01T00:15:00+00:00');
        $this->assertEquals($expected, $entry->expiresAt());
    }

    // ── Inspect ───────────────────────────────────────────────────────────────

    public function testInspectReturnsActiveEntry(): void
    {
        $created = $this->manager->generate('/pricing');
        $found   = $this->manager->inspect($created->slug());

        $this->assertSame($created->slug(), $found->slug());
        $this->assertTrue($found->isActive());
    }

    public function testInspectDoesNotConsumeEntry(): void
    {
        $created = $this->manager->generate('/pricing');
        $this->manager->inspect($created->slug());
        $this->manager->inspect($created->slug());  // second inspect — still active

        $this->assertTrue($this->registry->find($created->slug())->isActive());
    }

    public function testInspectUnknownSlugThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found/i');
        $this->manager->inspect('000000000000');
    }

    public function testInspectMalformedSlugThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/invalid/i');
        $this->manager->inspect('not-a-slug');
    }

    public function testInspectUppercaseSlugThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->manager->inspect('A1B2C3D4E5F6');
    }

    public function testInspectExpiredEntryThrows(): void
    {
        $past  = new DateTimeImmutable('2020-01-01T00:00:00+00:00');
        $entry = $this->manager->generate('/pricing', $past);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/expired/i');
        $this->manager->inspect($entry->slug());  // now is after expiry
    }

    public function testInspectMarksExpiredEntryInRegistry(): void
    {
        $past  = new DateTimeImmutable('2020-01-01T00:00:00+00:00');
        $entry = $this->manager->generate('/pricing', $past);

        try {
            $this->manager->inspect($entry->slug());
        } catch (RuntimeException) {
        }

        $stored = $this->registry->find($entry->slug());
        $this->assertNotNull($stored);
        $this->assertSame(DisposableEntryState::EXPIRED, $stored->state());
    }

    // ── Consume ───────────────────────────────────────────────────────────────

    public function testConsumeMarksEntryAsConsumed(): void
    {
        $entry = $this->manager->generate('/pricing');
        $this->manager->consume($entry->slug());

        $stored = $this->registry->find($entry->slug());
        $this->assertSame(DisposableEntryState::CONSUMED, $stored->state());
    }

    public function testConsumeReturnsTheEntry(): void
    {
        $entry    = $this->manager->generate('/pricing');
        $consumed = $this->manager->consume($entry->slug());

        $this->assertSame($entry->slug(), $consumed->slug());
    }

    public function testConsumedEntryCannotBeInspectedAgain(): void
    {
        $entry = $this->manager->generate('/pricing');
        $this->manager->consume($entry->slug());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no longer active/i');
        $this->manager->inspect($entry->slug());
    }

    public function testConsumedEntryCannotBeConsumedAgain(): void
    {
        $entry = $this->manager->generate('/pricing');
        $this->manager->consume($entry->slug());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/no longer active/i');
        $this->manager->consume($entry->slug());  // replay
    }

    // ── Non-single-use behaviour ──────────────────────────────────────────────

    public function testNonSingleUseEntryStaysActiveAfterConsume(): void
    {
        $manager = new DisposableEntryManager($this->registry, 900, 12, false);
        $entry   = $manager->generate('/pricing');
        $manager->consume($entry->slug());

        $stored = $this->registry->find($entry->slug());
        $this->assertSame(DisposableEntryState::ACTIVE, $stored->state());
    }

    // ── matchesSlugFormat ─────────────────────────────────────────────────────

    #[DataProvider('validSlugs')]
    public function testMatchesSlugFormatAcceptsValidSlugs(string $slug): void
    {
        $this->assertTrue($this->manager->matchesSlugFormat($slug));
    }

    /** @return array<string, array{string}> */
    public static function validSlugs(): array
    {
        return [
            'all zeros'     => ['000000000000'],
            'all fs'        => ['ffffffffffff'],
            'mixed'         => ['a1b2c3d4e5f6'],
            'realistic'     => ['82f6cd2d2843'],
        ];
    }

    #[DataProvider('invalidSlugs')]
    public function testMatchesSlugFormatRejectsInvalidSlugs(string $slug): void
    {
        $this->assertFalse($this->manager->matchesSlugFormat($slug));
    }

    /** @return array<string, array{string}> */
    public static function invalidSlugs(): array
    {
        return [
            'too short'      => ['a1b2c3'],
            'too long'       => ['a1b2c3d4e5f6aa'],
            'uppercase'      => ['A1B2C3D4E5F6'],
            'non-hex chars'  => ['g1b2c3d4e5f6'],
            'empty'          => [''],
            'with spaces'    => ['a1b2 3d4e5f6'],
            'with slashes'   => ['/a1b2c3d4e5f6'],
        ];
    }

    // ── Token prediction / entropy spot-check ─────────────────────────────────

    public function testGeneratedSlugsAreUnique(): void
    {
        $slugs = [];
        for ($i = 0; $i < 100; $i++) {
            $entry  = $this->manager->generate('/test-' . $i);
            $slugs[] = $entry->slug();
        }

        // All 100 slugs should be distinct (collision probability is ~7×10⁻¹³)
        $this->assertCount(100, array_unique($slugs));
    }
}
