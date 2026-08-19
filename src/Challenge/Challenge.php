<?php

namespace Supamask\Challenge;

use DateTimeImmutable;
use InvalidArgumentException;
use Supamask\Security\ProofOfWork\ProofOfWorkChallenge;

final class Challenge
{
    public function __construct(
        private string $id,
        private string $originalUri,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $expiresAt,
        private string $verificationToken,
        private ChallengeState $state = ChallengeState::PENDING,
        private ?string $entrySlug = null,
        private ?ProofOfWorkChallenge $proofOfWork = null,
    ) {
        if (!preg_match('/^[a-f0-9]{12}$/', $id)) {
            throw new InvalidArgumentException('Challenge ID must be a 12-character lowercase hexadecimal string.');
        }

        if ($originalUri === '' || $originalUri[0] !== '/' || str_starts_with($originalUri, '//')) {
            throw new InvalidArgumentException('Challenge original URI must be a local URI.');
        }

        if ($expiresAt <= $createdAt) {
            throw new InvalidArgumentException('Challenge expiration must be after creation.');
        }

        if (!preg_match('/^[a-f0-9]{64}$/', $verificationToken)) {
            throw new InvalidArgumentException('Challenge verification token must be a 64-character hexadecimal string.');
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function originalUri(): string
    {
        return $this->originalUri;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function verificationToken(): string
    {
        return $this->verificationToken;
    }

    public function state(): ChallengeState
    {
        return $this->state;
    }

    public function entrySlug(): ?string
    {
        return $this->entrySlug;
    }

    public function proofOfWork(): ?ProofOfWorkChallenge
    {
        return $this->proofOfWork;
    }

    public function isPending(): bool
    {
        return $this->state === ChallengeState::PENDING;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function consume(): void
    {
        if (!$this->isPending()) {
            throw new InvalidArgumentException('Only pending challenges can be consumed.');
        }

        $this->state = ChallengeState::CONSUMED;
    }

    public function expire(): void
    {
        if (!$this->isPending()) {
            return;
        }

        $this->state = ChallengeState::EXPIRED;
    }
}
