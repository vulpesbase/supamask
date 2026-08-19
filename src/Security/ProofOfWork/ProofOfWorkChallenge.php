<?php

namespace Supamask\Security\ProofOfWork;

use DateTimeImmutable;
use InvalidArgumentException;

final class ProofOfWorkChallenge
{
    public function __construct(
        private string $nonce,
        private int $difficulty,
        private DateTimeImmutable $expiresAt,
        private bool $consumed = false,
    ) {
        if (!preg_match('/^[a-f0-9]{64}$/', $nonce)) {
            throw new InvalidArgumentException('Proof-of-work nonce must be a 64-character hexadecimal string.');
        }

        if ($difficulty < 1 || $difficulty > 24) {
            throw new InvalidArgumentException('Proof-of-work difficulty must be between 1 and 24 bits.');
        }
    }

    public function nonce(): string { return $this->nonce; }
    public function difficulty(): int { return $this->difficulty; }
    public function expiresAt(): DateTimeImmutable { return $this->expiresAt; }
    public function consumed(): bool { return $this->consumed; }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function consume(): void
    {
        if ($this->consumed) {
            throw new InvalidArgumentException('Proof-of-work challenge has already been consumed.');
        }

        $this->consumed = true;
    }
}
