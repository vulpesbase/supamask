<?php

namespace Supamask\Tests\Unit\Challenge;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Supamask\Challenge\Challenge;
use Supamask\Challenge\ChallengeManager;
use Supamask\Challenge\ChallengeState;
use Supamask\Challenge\InMemoryChallengeStore;

class ChallengeTest extends TestCase
{
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-14 12:00:00', new DateTimeZone('UTC'));
    }

    public function testCreateGeneratesTwelveCharacterLowercaseHexId(): void
    {
        $manager = new ChallengeManager(new InMemoryChallengeStore(), 300);
        $challenge = $manager->create('/pricing', $this->now());

        $this->assertMatchesRegularExpression('/^[a-f0-9]{12}$/', $challenge->id());
        $this->assertSame('/pricing', $challenge->originalUri());
        $this->assertSame(ChallengeState::PENDING, $challenge->state());
        $this->assertSame('2026-08-14 12:05:00', $challenge->expiresAt()->format('Y-m-d H:i:s'));
    }

    public function testCreatePersistsChallenge(): void
    {
        $store = new InMemoryChallengeStore();
        $manager = new ChallengeManager($store);
        $challenge = $manager->create('/account', $this->now());

        $this->assertSame($challenge, $store->find($challenge->id()));
    }

    public function testVerifyReturnsPendingUnexpiredChallenge(): void
    {
        $manager = new ChallengeManager(new InMemoryChallengeStore(), 300);
        $challenge = $manager->create('/pricing', $this->now());

        $verified = $manager->verify($challenge->id(), $challenge->verificationToken(), $this->now()->modify('+60 seconds'));

        $this->assertSame($challenge, $verified);
        $this->assertSame(ChallengeState::PENDING, $verified->state());
    }

    public function testExpiredChallengeCannotBeVerified(): void
    {
        $store = new InMemoryChallengeStore();
        $manager = new ChallengeManager($store, 300);
        $challenge = $manager->create('/pricing', $this->now());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Challenge has expired.');

        try {
            $manager->verify($challenge->id(), $challenge->verificationToken(), $this->now()->modify('+300 seconds'));
        } finally {
            $this->assertSame(ChallengeState::EXPIRED, $store->find($challenge->id())->state());
        }
    }

    public function testUnknownChallengeCannotBeVerified(): void
    {
        $manager = new ChallengeManager(new InMemoryChallengeStore());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Challenge not found.');

        $manager->verify('a1b2c3d4e5f6', str_repeat('0', 64), $this->now());
    }

    public function testConsumeMarksChallengeAsConsumed(): void
    {
        $store = new InMemoryChallengeStore();
        $manager = new ChallengeManager($store);
        $challenge = $manager->create('/pricing', $this->now());

        $consumed = $manager->consume($challenge->id(), $challenge->verificationToken(), $this->now()->modify('+30 seconds'));

        $this->assertSame(ChallengeState::CONSUMED, $consumed->state());
        $this->assertSame(ChallengeState::CONSUMED, $store->find($challenge->id())->state());
    }

    public function testConsumedChallengeCannotBeReused(): void
    {
        $manager = new ChallengeManager(new InMemoryChallengeStore());
        $challenge = $manager->create('/pricing', $this->now());
        $manager->consume($challenge->id(), $challenge->verificationToken(), $this->now());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Challenge is no longer valid.');

        $manager->verify($challenge->id(), $challenge->verificationToken(), $this->now());
    }

    public function testInvalidChallengeIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Challenge(
            'NOT-VALID',
            '/pricing',
            $this->now(),
            $this->now()->modify('+300 seconds'),
            str_repeat('0', 64),
        );
    }

    public function testVerificationTokenIsRandomAndStored(): void
    {
        $store = new InMemoryChallengeStore();
        $manager = new ChallengeManager($store);
        $challenge = $manager->create('/pricing', $this->now());

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $challenge->verificationToken());
        $this->assertSame($challenge->verificationToken(), $store->find($challenge->id())->verificationToken());
    }

    public function testWrongVerificationTokenIsRejected(): void
    {
        $manager = new ChallengeManager(new InMemoryChallengeStore());
        $challenge = $manager->create('/pricing', $this->now());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid challenge verification token.');

        $manager->verify($challenge->id(), str_repeat('0', 64), $this->now());
    }

    public function testOriginalUriMustBeLocal(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Challenge(
            'a1b2c3d4e5f6',
            'https://example.com',
            $this->now(),
            $this->now()->modify('+300 seconds'),
            str_repeat('0', 64),
        );
    }

    public function testZeroTtlIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ChallengeManager(new InMemoryChallengeStore(), 0);
    }
}
