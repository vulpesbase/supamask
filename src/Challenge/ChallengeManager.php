<?php

namespace Supamask\Challenge;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

final class ChallengeManager
{
    public function __construct(
        private ChallengeStoreInterface $store,
        private int $ttl = 300,
        private int $verificationTtl = 1800,
    ) {
        if ($ttl <= 0) {
            throw new InvalidArgumentException('Challenge TTL must be greater than zero.');
        }

        if ($verificationTtl <= 0) {
            throw new InvalidArgumentException('Verification TTL must be greater than zero.');
        }
    }

    public function create(string $originalUri, ?DateTimeImmutable $now = null, ?string $entrySlug = null): Challenge
    {
        $now ??= $this->now();
        $challenge = new Challenge(
            $this->generateId(),
            $originalUri,
            $now,
            $now->modify(sprintf('+%d seconds', $this->ttl)),
            bin2hex(random_bytes(32)),
            ChallengeState::PENDING,
            $entrySlug,
        );

        $this->store->save($challenge);

        return $challenge;
    }

    public function inspect(string $id, ?DateTimeImmutable $now = null): Challenge
    {
        return $this->findPending($id, $now);
    }

    public function verify(string $id, string $token, ?DateTimeImmutable $now = null): Challenge
    {
        $challenge = $this->findPending($id, $now);

        if (!hash_equals($challenge->verificationToken(), $token)) {
            throw new RuntimeException('Invalid challenge verification token.');
        }

        return $challenge;
    }

    public function consume(string $id, string $token, ?DateTimeImmutable $now = null): Challenge
    {
        $challenge = $this->verify($id, $token, $now);
        $challenge->consume();
        $this->store->save($challenge);

        return $challenge;
    }

    public function verificationTtl(): int
    {
        return $this->verificationTtl;
    }

    private function findPending(string $id, ?DateTimeImmutable $now = null): Challenge
    {
        if (!preg_match('/^[a-f0-9]{12}$/', $id)) {
            throw new RuntimeException('Invalid challenge identifier.');
        }

        $challenge = $this->store->find($id);

        if ($challenge === null) {
            throw new RuntimeException('Challenge not found.');
        }

        if (!$challenge->isPending()) {
            throw new RuntimeException('Challenge is no longer valid.');
        }

        $now ??= $this->now();

        if ($challenge->isExpired($now)) {
            $challenge->expire();
            $this->store->save($challenge);
            throw new RuntimeException('Challenge has expired.');
        }

        return $challenge;
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(6));
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
