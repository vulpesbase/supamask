<?php

namespace Supamask\Entry;

/**
 * Lifecycle states for a DisposableEntry.
 *
 * ACTIVE   – Created and not yet used; valid for challenge redemption.
 * CONSUMED – Successfully used exactly once; no further use permitted.
 * EXPIRED  – TTL elapsed before consumption; invalid.
 */
enum DisposableEntryState: string
{
    case ACTIVE   = 'active';
    case CONSUMED = 'consumed';
    case EXPIRED  = 'expired';
}
