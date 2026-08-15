<?php

namespace Supamask\Entry;

/**
 * Storage contract for DisposableEntry records.
 */
interface DisposableEntryRegistry
{
    /**
     * Persists a disposable entry (create or update).
     */
    public function save(DisposableEntry $entry): void;

    /**
     * Returns the entry for the given slug, or null if not found.
     */
    public function find(string $slug): ?DisposableEntry;

    /**
     * Removes the entry for the given slug (used for cleanup).
     */
    public function delete(string $slug): void;
}
