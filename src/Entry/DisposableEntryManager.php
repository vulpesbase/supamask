<?php

namespace Supamask\Entry;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/**
 * Manages the lifecycle of DisposableEntry records.
 *
 * Responsibilities:
 * - Generating cryptographically-secure slugs (CSPRNG only).
 * - Creating, inspecting, and consuming entries.
 * - Enforcing TTL expiry.
 * - Enforcing single-use constraints.
 * - Validating destination paths (no open redirects).
 *
 * Security note:
 * Slug generation uses random_bytes() exclusively. The functions rand(),
 * mt_rand(), and uniqid() are NEVER used here.
 *
 * @see DisposableEntry::isValidLocalPath() for destination validation rules.
 */
final class DisposableEntryManager
{
    /**
     * @param DisposableEntryRegistry $registry
     * @param int                     $ttl        Seconds until expiry (must be > 0).
     * @param int                     $slugLength  Must be a positive even integer (default 12).
     * @param bool                    $singleUse   Whether entries are single-use (default true).
     */
    public function __construct(
        private DisposableEntryRegistry $registry,
        private int $ttl = 900,
        private int $slugLength = 12,
        private bool $singleUse = true,
    ) {
        if ($ttl <= 0) {
            throw new InvalidArgumentException('DisposableEntry TTL must be greater than zero.');
        }

        if ($slugLength < 2 || $slugLength % 2 !== 0) {
            throw new InvalidArgumentException(
                'Slug length must be a positive even integer (minimum 2).'
            );
        }
    }

    /**
     * Generates a new, active DisposableEntry associated with the given destination.
     *
     * @param string               $destination  Internal path (validated; no external URLs).
     * @param DateTimeImmutable|null $now
     *
     * @throws InvalidArgumentException If the destination is not a valid local path.
     */
    public function generate(string $destination, ?DateTimeImmutable $now = null): DisposableEntry
    {
        if (!DisposableEntry::isValidLocalPath($destination)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Disposable entry destination must be a local path; "%s" is not allowed.',
                    $destination,
                )
            );
        }

        $now ??= $this->now();
        $slug = $this->generateSlug();

        // Collision guard (low probability, but correct to handle)
        $attempts = 0;
        while ($this->registry->find($slug) !== null) {
            if (++$attempts > 10) {
                throw new RuntimeException('Unable to generate a unique disposable entry slug.');
            }
            $slug = $this->generateSlug();
        }

        $entry = new DisposableEntry(
            $slug,
            $destination,
            $now,
            $now->modify(sprintf('+%d seconds', $this->ttl)),
            DisposableEntryState::ACTIVE,
            $this->singleUse,
            $this->slugLength,
        );

        $this->registry->save($entry);

        return $entry;
    }

    /**
     * Inspects (reads) a disposable entry without consuming it.
     *
     * Validates format, existence, lifecycle state, and TTL.
     * Marks the entry as EXPIRED and persists it if the TTL has elapsed.
     *
     * @throws RuntimeException On invalid slug, not found, already consumed, or expired.
     */
    public function inspect(string $slug, ?DateTimeImmutable $now = null): DisposableEntry
    {
        return $this->findActive($slug, $now);
    }

    /**
     * Finds an entry regardless of its lifecycle state.
     *
     * Used to detect consumed/expired entries for rejection.
     * Marks expired entries and persists the change.
     *
     * Returns null only if:
     * - Slug format is invalid
     * - Entry not in registry
     *
     * Returns the entry with its current state (ACTIVE, CONSUMED, or EXPIRED).
     *
     * @return DisposableEntry|null
     */
    public function find(string $slug, ?DateTimeImmutable $now = null): ?DisposableEntry
    {
        if (!$this->matchesSlugFormat($slug)) {
            return null;
        }

        $entry = $this->registry->find($slug);

        if ($entry === null) {
            return null;
        }

        $now ??= $this->now();

        // Mark as expired if TTL has elapsed (and persist)
        if ($entry->isExpired($now) && $entry->isActive()) {
            $entry->expire();
            $this->registry->save($entry);
        }

        return $entry;
    }

    /**
     * Consumes the entry identified by slug.
     *
     * After consumption the slug can never be used again (replay protection).
     *
     * @throws RuntimeException On invalid slug, not found, already consumed, or expired.
     */
    public function consume(string $slug, ?DateTimeImmutable $now = null): DisposableEntry
    {
        $entry = $this->findActive($slug, $now);

        if ($entry->isSingleUse()) {
            $entry->consume();
            $this->registry->save($entry);
        }

        return $entry;
    }

    /**
     * Returns true if the given slug matches the expected format for this manager.
     */
    public function matchesSlugFormat(string $slug): bool
    {
        return (bool) preg_match(
            sprintf('/^[a-f0-9]{%d}$/', $this->slugLength),
            $slug
        );
    }

    public function slugLength(): int
    {
        return $this->slugLength;
    }

    // ── Private ────────────────────────────────────────────────────────────────

    private function findActive(string $slug, ?DateTimeImmutable $now = null): DisposableEntry
    {
        if (!$this->matchesSlugFormat($slug)) {
            throw new RuntimeException(
                sprintf('Invalid disposable entry slug format (got "%s").', $slug)
            );
        }

        $entry = $this->registry->find($slug);

        if ($entry === null) {
            throw new RuntimeException('Disposable entry not found.');
        }

        if (!$entry->isActive()) {
            throw new RuntimeException(
                sprintf('Disposable entry is no longer active (state: %s).', $entry->state()->value)
            );
        }

        $now ??= $this->now();

        if ($entry->isExpired($now)) {
            $entry->expire();
            $this->registry->save($entry);
            throw new RuntimeException('Disposable entry has expired.');
        }

        return $entry;
    }

    /**
     * Generates a cryptographically-secure lowercase hex slug.
     *
     * Uses random_bytes() — never rand(), mt_rand(), or uniqid().
     */
    private function generateSlug(): string
    {
        return bin2hex(random_bytes($this->slugLength / 2));
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
