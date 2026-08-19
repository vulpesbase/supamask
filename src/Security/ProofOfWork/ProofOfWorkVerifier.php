<?php

namespace Supamask\Security\ProofOfWork;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class ProofOfWorkVerifier
{
    public function verify(ProofOfWorkChallenge $challenge, string $verificationToken, string $counter, ?DateTimeImmutable $now = null): void
    {
        if ($challenge->consumed()) {
            throw new RuntimeException('Proof-of-work challenge has already been consumed.');
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($challenge->isExpired($now)) {
            throw new RuntimeException('Proof-of-work challenge has expired.');
        }

        if (!preg_match('/^[0-9]{1,12}$/', $counter)) {
            throw new RuntimeException('Invalid proof-of-work counter.');
        }

        if ($verificationToken === '' || !preg_match('/^[a-f0-9]{64}$/', $verificationToken)) {
            throw new RuntimeException('Invalid verification token.');
        }

        $payload = $challenge->nonce() . ':' . $verificationToken . ':' . $counter;
        $digest = hash('sha256', $payload, true);

        if (!$this->meetsDifficulty($digest, $challenge->difficulty())) {
            throw new RuntimeException('Proof-of-work solution does not meet the required difficulty.');
        }
    }

    public function consume(ProofOfWorkChallenge $challenge): void
    {
        $challenge->consume();
    }

    public function meetsDifficulty(string $digest, int $difficulty): bool
    {
        $bytes = unpack('C*', $digest);
        if ($bytes === false) {
            return false;
        }

        $remaining = $difficulty;
        foreach ($bytes as $byte) {
            if ($remaining >= 8) {
                if ($byte !== 0) {
                    return false;
                }
                $remaining -= 8;
                continue;
            }

            if ($remaining <= 0) {
                return true;
            }

            return ($byte >> (8 - $remaining)) === 0;
        }

        return true;
    }
}
