<?php

namespace Supamask\Entry;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Session-backed DisposableEntryRegistry for production use.
 *
 * Entries are serialised into $_SESSION under the key '_supamask_entries'.
 * Session must be started before this store is used (it calls session_start()
 * automatically if none is active).
 *
 * Security note: Session data is server-side; clients cannot manipulate
 * entry state directly. However, the session ID must be protected with
 * appropriate cookie flags (Secure, HttpOnly, SameSite) by the host application.
 */
final class SessionDisposableEntryRegistry implements DisposableEntryRegistry
{
    private const SESSION_KEY = '_supamask_entries';

    public function save(DisposableEntry $entry): void
    {
        $this->ensureSession();

        $_SESSION[self::SESSION_KEY][$entry->slug()] = [
            'destination' => $entry->destination(),
            'created_at'  => $entry->createdAt()->format(DateTimeImmutable::ATOM),
            'expires_at'  => $entry->expiresAt()->format(DateTimeImmutable::ATOM),
            'state'       => $entry->state()->value,
            'single_use'  => $entry->isSingleUse(),
            'slug_length' => $entry->slugLength(),
        ];
    }

    public function find(string $slug): ?DisposableEntry
    {
        $this->ensureSession();

        $data = $_SESSION[self::SESSION_KEY][$slug] ?? null;

        if (!is_array($data)) {
            return null;
        }

        try {
            return new DisposableEntry(
                $slug,
                (string) $data['destination'],
                new DateTimeImmutable((string) $data['created_at'], new DateTimeZone('UTC')),
                new DateTimeImmutable((string) $data['expires_at'], new DateTimeZone('UTC')),
                DisposableEntryState::from((string) $data['state']),
                (bool) ($data['single_use'] ?? true),
                (int) ($data['slug_length'] ?? 12),
            );
        } catch (\Throwable) {
            // Corrupt session data — treat as missing
            return null;
        }
    }

    public function delete(string $slug): void
    {
        $this->ensureSession();
        unset($_SESSION[self::SESSION_KEY][$slug]);
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
