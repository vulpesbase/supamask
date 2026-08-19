<?php

namespace Supamask\Tests\Unit\Security;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Supamask\Security\ProofOfWork\ProofOfWorkGenerator;
use Supamask\Security\ProofOfWork\ProofOfWorkVerifier;

final class ProofOfWorkTest extends TestCase
{
    private const TOKEN = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    public function testGeneratorCreatesValidChallenge(): void
    {
        $now = new DateTimeImmutable('2026-08-17 15:00:00', new DateTimeZone('UTC'));
        $challenge = (new ProofOfWorkGenerator(8, 60))->create($now);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $challenge->nonce());
        $this->assertSame(8, $challenge->difficulty());
        $this->assertSame('2026-08-17 15:01:00', $challenge->expiresAt()->format('Y-m-d H:i:s'));
        $this->assertFalse($challenge->consumed());
    }

    public function testVerifierAcceptsAValidSolution(): void
    {
        $now = new DateTimeImmutable('2026-08-17 15:00:00', new DateTimeZone('UTC'));
        $challenge = (new ProofOfWorkGenerator(4, 60))->create($now);
        $verifier = new ProofOfWorkVerifier();
        $counter = $this->findSolution($challenge->nonce(), self::TOKEN, $challenge->difficulty());

        $verifier->verify($challenge, self::TOKEN, (string) $counter, $now);
        $verifier->consume($challenge);

        $this->assertTrue($challenge->consumed());
    }

    public function testVerifierRejectsInvalidCounter(): void
    {
        $challenge = (new ProofOfWorkGenerator(8, 60))->create();
        $verifier = new ProofOfWorkVerifier();

        $this->expectException(RuntimeException::class);
        $verifier->verify($challenge, self::TOKEN, 'not-a-counter');
    }

    public function testVerifierRejectsSolutionBelowDifficulty(): void
    {
        $now = new DateTimeImmutable('2026-08-17 15:00:00', new DateTimeZone('UTC'));
        $challenge = (new ProofOfWorkGenerator(16, 60))->create($now);
        $verifier = new ProofOfWorkVerifier();
        $counter = $this->findNonSolution($challenge->nonce(), self::TOKEN, $challenge->difficulty());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not meet');
        $verifier->verify($challenge, self::TOKEN, (string) $counter, $now);
    }

    public function testVerifierRejectsWrongTokenBinding(): void
    {
        $now = new DateTimeImmutable('2026-08-17 15:00:00', new DateTimeZone('UTC'));
        $challenge = (new ProofOfWorkGenerator(8, 60))->create($now);
        $verifier = new ProofOfWorkVerifier();
        $counter = $this->findSolution($challenge->nonce(), self::TOKEN, $challenge->difficulty());

        $this->expectException(RuntimeException::class);
        $verifier->verify($challenge, str_repeat('a', 64), (string) $counter, $now);
    }

    public function testVerifierRejectsExpiredChallenge(): void
    {
        $now = new DateTimeImmutable('2026-08-17 15:00:00', new DateTimeZone('UTC'));
        $challenge = (new ProofOfWorkGenerator(4, 1))->create($now);
        $verifier = new ProofOfWorkVerifier();
        $counter = $this->findSolution($challenge->nonce(), self::TOKEN, $challenge->difficulty());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expired');
        $verifier->verify($challenge, self::TOKEN, (string) $counter, $now->modify('+1 second'));
    }

    public function testConsumedChallengeCannotBeVerifiedAgain(): void
    {
        $challenge = (new ProofOfWorkGenerator(4, 60))->create();
        $verifier = new ProofOfWorkVerifier();
        $counter = $this->findSolution($challenge->nonce(), self::TOKEN, $challenge->difficulty());
        $verifier->verify($challenge, self::TOKEN, (string) $counter);
        $verifier->consume($challenge);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already been consumed');
        $verifier->verify($challenge, self::TOKEN, (string) $counter);
    }

    private function findSolution(string $nonce, string $token, int $difficulty): int
    {
        for ($counter = 0; $counter <= 10_000_000; $counter++) {
            if ($this->meetsDifficulty(hash('sha256', $nonce . ':' . $token . ':' . $counter, true), $difficulty)) {
                return $counter;
            }
        }

        $this->fail('No proof-of-work solution found within the test bound.');
    }

    private function findNonSolution(string $nonce, string $token, int $difficulty): int
    {
        for ($counter = 0; $counter <= 10_000; $counter++) {
            if (!$this->meetsDifficulty(hash('sha256', $nonce . ':' . $token . ':' . $counter, true), $difficulty)) {
                return $counter;
            }
        }

        $this->fail('Unexpectedly found only valid proof-of-work counters.');
    }

    private function meetsDifficulty(string $digest, int $difficulty): bool
    {
        $remaining = $difficulty;
        for ($i = 0, $length = strlen($digest); $i < $length; $i++) {
            $byte = ord($digest[$i]);
            if ($remaining >= 8) {
                if ($byte !== 0) {
                    return false;
                }
                $remaining -= 8;
                continue;
            }

            return $remaining <= 0 || (($byte >> (8 - $remaining)) === 0);
        }

        return true;
    }
}
