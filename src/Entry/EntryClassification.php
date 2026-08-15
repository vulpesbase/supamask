<?php

namespace Supamask\Entry;

/**
 * Represents how a request arrived at the application.
 *
 * DIRECT  – No referrer header. The visitor navigated directly (typed URL,
 *           bookmark, or the browser did not forward a referrer).
 *
 * REFERRED – A referrer header is present but the origin is not in the
 *            configured trusted/seeded referrer list.
 *
 * SEEDED   – The request arrived via a configured disposable entry path
 *            (e.g. /82f6cd2d2843). The path is associated with an active
 *            DisposableEntry record.
 *
 * UNKNOWN  – The referrer header is present but malformed or otherwise
 *            unrecognisable (e.g. cannot be parsed as a URL).
 *
 * Classification is a routing hint, not an access-control guarantee.
 * The Referer header is user-supplied and must not be trusted as proof
 * of origin. See docs/routing-architecture.md §Security.
 */
enum EntryClassification
{
    case DIRECT;
    case REFERRED;
    case SEEDED;
    case UNKNOWN;
}
