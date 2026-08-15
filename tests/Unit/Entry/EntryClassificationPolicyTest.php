<?php

namespace Supamask\Tests\Unit\Entry;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Supamask\Core\Decision;
use Supamask\Entry\EntryClassification;
use Supamask\Entry\EntryClassificationPolicy;

/**
 * Unit tests for EntryClassificationPolicy.
 *
 * Verifies that classification → Decision mapping is:
 * - Configurable
 * - Validated at construction time
 * - Deterministic
 */
final class EntryClassificationPolicyTest extends TestCase
{
    // ── Defaults ──────────────────────────────────────────────────────────────

    public function testDefaultPolicyAllowsDirect(): void
    {
        $policy = new EntryClassificationPolicy();
        $this->assertSame(Decision::ALLOW, $policy->decide(EntryClassification::DIRECT));
    }

    public function testDefaultPolicyAllowsReferred(): void
    {
        $policy = new EntryClassificationPolicy();
        $this->assertSame(Decision::ALLOW, $policy->decide(EntryClassification::REFERRED));
    }

    public function testDefaultPolicyChallengesSeeded(): void
    {
        $policy = new EntryClassificationPolicy();
        $this->assertSame(Decision::CHALLENGE, $policy->decide(EntryClassification::SEEDED));
    }

    public function testDefaultPolicyAllowsUnknown(): void
    {
        $policy = new EntryClassificationPolicy();
        $this->assertSame(Decision::ALLOW, $policy->decide(EntryClassification::UNKNOWN));
    }

    // ── Custom configuration ───────────────────────────────────────────────────

    public function testCustomPolicyChallengesAllClassifications(): void
    {
        $policy = new EntryClassificationPolicy([
            'direct'   => 'challenge',
            'referred' => 'challenge',
            'seeded'   => 'challenge',
            'unknown'  => 'challenge',
        ]);

        $this->assertSame(Decision::CHALLENGE, $policy->decide(EntryClassification::DIRECT));
        $this->assertSame(Decision::CHALLENGE, $policy->decide(EntryClassification::REFERRED));
        $this->assertSame(Decision::CHALLENGE, $policy->decide(EntryClassification::SEEDED));
        $this->assertSame(Decision::CHALLENGE, $policy->decide(EntryClassification::UNKNOWN));
    }

    public function testCustomPolicyDeniesUnknown(): void
    {
        $policy = new EntryClassificationPolicy(['unknown' => 'deny']);
        $this->assertSame(Decision::DENY, $policy->decide(EntryClassification::UNKNOWN));
    }

    public function testCustomPolicyAllowsSeeded(): void
    {
        $policy = new EntryClassificationPolicy(['seeded' => 'allow']);
        $this->assertSame(Decision::ALLOW, $policy->decide(EntryClassification::SEEDED));
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function testInvalidDecisionValueThrowsAtConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/invalid.*decision/i');
        new EntryClassificationPolicy(['direct' => 'pass_through']);
    }

    public function testNonStringDecisionValueThrowsAtConstruction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EntryClassificationPolicy(['direct' => 42]);
    }

    // ── Case-insensitive decision values ─────────────────────────────────────

    public function testDecisionValueIsCaseInsensitive(): void
    {
        $policy = new EntryClassificationPolicy([
            'direct'   => 'ALLOW',
            'referred' => 'Challenge',
            'seeded'   => 'DENY',
            'unknown'  => 'Allow',
        ]);

        $this->assertSame(Decision::ALLOW,     $policy->decide(EntryClassification::DIRECT));
        $this->assertSame(Decision::CHALLENGE,  $policy->decide(EntryClassification::REFERRED));
        $this->assertSame(Decision::DENY,       $policy->decide(EntryClassification::SEEDED));
        $this->assertSame(Decision::ALLOW,      $policy->decide(EntryClassification::UNKNOWN));
    }

    // ── Partial config (merges with defaults) ─────────────────────────────────

    public function testPartialConfigMergesWithDefaults(): void
    {
        // Only override seeded; others should use defaults
        $policy = new EntryClassificationPolicy(['seeded' => 'deny']);

        $this->assertSame(Decision::ALLOW,  $policy->decide(EntryClassification::DIRECT));
        $this->assertSame(Decision::ALLOW,  $policy->decide(EntryClassification::REFERRED));
        $this->assertSame(Decision::DENY,   $policy->decide(EntryClassification::SEEDED));
        $this->assertSame(Decision::ALLOW,  $policy->decide(EntryClassification::UNKNOWN));
    }
}
