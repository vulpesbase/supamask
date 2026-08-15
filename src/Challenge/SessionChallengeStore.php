<?php

namespace Supamask\Challenge;

use DateTimeImmutable;
use DateTimeZone;

final class SessionChallengeStore implements ChallengeStoreInterface
{
    private const SESSION_KEY = '_supamask_challenges';

    public function save(Challenge $challenge): void
    {
        $this->ensureSession();

        $_SESSION[self::SESSION_KEY][$challenge->id()] = [
            'original_uri' => $challenge->originalUri(),
            'created_at' => $challenge->createdAt()->format(DateTimeImmutable::ATOM),
            'expires_at' => $challenge->expiresAt()->format(DateTimeImmutable::ATOM),
            'verification_token' => $challenge->verificationToken(),
            'state' => $challenge->state()->value,
            'entry_slug' => $challenge->entrySlug(),
        ];
    }

    public function find(string $id): ?Challenge
    {
        if (!preg_match('/^[a-f0-9]{12}$/', $id)) {
            return null;
        }

        $this->ensureSession();
        $data = $_SESSION[self::SESSION_KEY][$id] ?? null;

        if (!is_array($data)) {
            return null;
        }

        try {
            return new Challenge(
                $id,
                (string) $data['original_uri'],
                new DateTimeImmutable((string) $data['created_at'], new DateTimeZone('UTC')),
                new DateTimeImmutable((string) $data['expires_at'], new DateTimeZone('UTC')),
                (string) $data['verification_token'],
                ChallengeState::from((string) $data['state']),
                $data['entry_slug'] ?? null,
            );
        } catch (\Throwable) {
            return null;
        }
    }

    public function clear(Challenge $challenge): void
    {
        $this->ensureSession();
        unset($_SESSION[self::SESSION_KEY][$challenge->id()]);
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
