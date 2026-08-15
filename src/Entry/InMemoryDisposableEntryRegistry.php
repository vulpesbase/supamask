<?php

namespace Supamask\Entry;

/**
 * In-process (non-persistent) DisposableEntryRegistry for testing.
 *
 * Not suitable for production use — data is lost when the process ends.
 * Use SessionDisposableEntryRegistry for PHP web request contexts.
 */
final class InMemoryDisposableEntryRegistry implements DisposableEntryRegistry
{
    /** @var array<string, DisposableEntry> */
    private array $entries = [];

    public function save(DisposableEntry $entry): void
    {
        $this->entries[$entry->slug()] = $entry;
    }

    public function find(string $slug): ?DisposableEntry
    {
        return $this->entries[$slug] ?? null;
    }

    public function delete(string $slug): void
    {
        unset($this->entries[$slug]);
    }
}
