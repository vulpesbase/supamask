<?php

namespace Supamask\Security\ProofOfWork;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class ProofOfWorkGenerator
{
    public function __construct(
        private int $difficulty = 16,
        private int $ttl = 300,
    ) {
        if ($difficulty < 1 || $difficulty > 24) {
            throw new InvalidArgumentException('Proof-of-work difficulty must be between 1 and 24 bits.');
        }

        if ($ttl <= 0) {
            throw new InvalidArgumentException('Proof-of-work TTL must be greater than zero.');
        }
    }

    public function create(?DateTimeImmutable $now = null, ?int $ttl = null): ProofOfWorkChallenge
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $effectiveTtl = $ttl ?? $this->ttl;

        if ($effectiveTtl <= 0) {
            throw new InvalidArgumentException('Proof-of-work TTL must be greater than zero.');
        }

        return new ProofOfWorkChallenge(
            bin2hex(random_bytes(32)),
            $this->difficulty,
            $now->modify(sprintf('+%d seconds', $effectiveTtl)),
        );
    }
}
