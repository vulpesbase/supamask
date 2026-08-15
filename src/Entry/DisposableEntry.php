<?php

namespace Supamask\Entry;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A single-use, time-limited entry path record.
 *
 * A disposable entry associates a short cryptographically-random slug
 * (e.g. "82f6cd2d2843") with an internal destination path and a lifecycle
 * state. When the slug is visited, Supamask intercepts the request,
 * creates a challenge, and redirects to it. The entry may be consumed at
 * most once and expires after the configured TTL.
 *
 * Security notes:
 * - Slugs are generated with CSPRNG (random_bytes); never use rand/mt_rand.
 * - Destination must be a local path; external URLs are rejected to prevent
 *   open-redirect attacks.
 * - Single-use enforcement is upheld by the CONSUMED state check in
 *   DisposableEntryManager::inspect().
 */
final class DisposableEntry
{
    /**
     * @param string               $slug            12-char (or configurable-length) lowercase hex.
     * @param string               $destination     Internal path (must start with /, no //).
     * @param DateTimeImmutable    $createdAt
     * @param DateTimeImmutable    $expiresAt
     * @param DisposableEntryState $state
     * @param bool                 $singleUse       Whether this entry may only be consumed once.
     * @param int                  $slugLength       Expected slug length (default 12).
     */
    public function __construct(
        private string $slug,
        private string $destination,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $expiresAt,
        private DisposableEntryState $state = DisposableEntryState::ACTIVE,
        private bool $singleUse = true,
        private int $slugLength = 12,
    ) {
        if (!preg_match('/^[a-f0-9]+$/', $slug) || strlen($slug) !== $slugLength) {
            throw new InvalidArgumentException(
                sprintf(
                    'DisposableEntry slug must be a %d-character lowercase hexadecimal string; got "%s".',
                    $slugLength,
                    $slug,
                )
            );
        }

        if (!self::isValidLocalPath($destination)) {
            throw new InvalidArgumentException(
                sprintf(
                    'DisposableEntry destination must be a local path starting with / (got "%s"). '
                    . 'External URLs are rejected to prevent open-redirect attacks.',
                    $destination,
                )
            );
        }

        if ($expiresAt <= $createdAt) {
            throw new InvalidArgumentException('DisposableEntry expiration must be after creation.');
        }
    }

    public function slug(): string { return $this->slug; }
    public function destination(): string { return $this->destination; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function expiresAt(): DateTimeImmutable { return $this->expiresAt; }
    public function state(): DisposableEntryState { return $this->state; }
    public function isSingleUse(): bool { return $this->singleUse; }
    public function slugLength(): int { return $this->slugLength; }

    public function isActive(): bool
    {
        return $this->state === DisposableEntryState::ACTIVE;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    /**
     * Marks this entry as consumed (single-use enforcement).
     *
     * @throws InvalidArgumentException If the entry is not currently active.
     */
    public function consume(): void
    {
        if (!$this->isActive()) {
            throw new InvalidArgumentException('Only active disposable entries can be consumed.');
        }

        $this->state = DisposableEntryState::CONSUMED;
    }

    /**
     * Marks this entry as expired.
     * Safe to call on already-consumed or already-expired entries (no-op).
     */
    public function expire(): void
    {
        if ($this->state === DisposableEntryState::ACTIVE) {
            $this->state = DisposableEntryState::EXPIRED;
        }
    }

    /**
     * Validates that a destination is a safe internal path.
     *
     * Accepted: /pricing, /dashboard, /?utm=x
     * Rejected: https://evil.example, //evil.example, javascript:alert(1)
     */
    public static function isValidLocalPath(string $destination): bool
    {
        if ($destination === '' || $destination[0] !== '/') {
            return false;
        }

        // Reject protocol-relative URLs (//) and anything that looks like a scheme
        if (str_starts_with($destination, '//')) {
            return false;
        }

        // Reject any embedded scheme (catches javascript:, data:, http:, etc.)
        if (preg_match('#^/[a-zA-Z][a-zA-Z0-9+\-.]*:#', $destination)) {
            return false;
        }

        return true;
    }
}
