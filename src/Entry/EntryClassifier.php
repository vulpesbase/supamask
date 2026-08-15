<?php

namespace Supamask\Entry;

use Supamask\Http\RequestContext;
use Supamask\Routing\RouteMatcher;

/**
 * Classifies an incoming request into an EntryClassification value.
 *
 * The classifier consults:
 *   1. Whether the request path matches an active disposable entry slug → SEEDED
 *   2. Whether a Referer header is present and matches a configured trusted
 *      referrer → REFERRED
 *   3. Whether no Referer header is present → DIRECT
 *   4. Any other state (e.g. malformed referrer, unrecognised origin) → UNKNOWN
 *
 * ─── Security Warning ─────────────────────────────────────────────────────────
 * The Referer (HTTP) header is user-controlled. Any actor can forge an
 * arbitrary Referer value. Classification based on Referer is a *routing hint*,
 * not an access-control guarantee. A SEEDED classification is backed by the
 * server-side DisposableEntry record, which is trustworthy. A REFERRED
 * classification is backed only by the header, which is not.
 *
 * Do not use REFERRED as the sole basis for granting privileged access.
 * Use it only to decide which policy branch a request flows through.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * Configuration:
 *
 * ```php
 * 'entry' => [
 *     'enabled'   => true,
 *     'referrers' => [
 *         'https://trusted.example/',     // exact URL prefix
 *         '*.trusted.example',            // wildcard subdomain
 *     ],
 * ]
 * ```
 */
final class EntryClassifier
{
    private RouteMatcher $matcher;

    /**
     * @param DisposableEntryManager $manager  Used to test whether a path matches a slug.
     * @param array<int,string>      $trustedReferrers  Configured trusted referrer patterns.
     * @param RouteMatcher|null      $matcher
     */
    public function __construct(
        private DisposableEntryManager $manager,
        private array $trustedReferrers = [],
        ?RouteMatcher $matcher = null,
    ) {
        $this->matcher = $matcher ?? new RouteMatcher();
    }

    /**
     * Classifies the request represented by the given RequestContext.
     *
     * Detects three classes of disposable-entry paths:
     * 1. ACTIVE entries → stored in fullContext, return SEEDED
     * 2. CONSUMED/EXPIRED entries → stored as invalidDisposableEntryState in fullContext
     * 3. Unknown or not-found entries → fall through to referrer-based classification
     */
    public function classify(RequestContext $context, ?\Supamask\Core\Context $fullContext = null): EntryClassification
    {
        // Check for disposable-entry paths (slug format).
        $slug = ltrim($context->path(), '/');
        if ($this->manager->matchesSlugFormat($slug)) {
            // Use find() to get the entry regardless of its state.
            $entry = $this->manager->find($slug);

            if ($entry !== null) {
                if ($entry->isActive()) {
                    // SEEDED: active disposable entry
                    if ($fullContext !== null) {
                        $fullContext->setDisposableEntry($entry);
                    }
                    return EntryClassification::SEEDED;
                } else {
                    // CONSUMED or EXPIRED: mark for rejection by Kernel
                    if ($fullContext !== null) {
                        $fullContext->setInvalidDisposableEntryState($entry->state());
                    }
                    // Return DIRECT so entry policy doesn't apply to invalid entries
                    return EntryClassification::DIRECT;
                }
            }
            // Slug format matched but entry not in registry: treat as normal path
        }

        $referrer = $context->referrer();

        // DIRECT: no referrer header present.
        if ($referrer === null || $referrer === '') {
            return EntryClassification::DIRECT;
        }

        // UNKNOWN: referrer is present but malformed (cannot be parsed).
        $parsed = parse_url($referrer);
        if ($parsed === false || !isset($parsed['host'])) {
            return EntryClassification::UNKNOWN;
        }

        // REFERRED: referrer is present and matches a trusted pattern.
        if ($this->trustedReferrers !== [] && $this->referrerMatches($referrer, $parsed)) {
            return EntryClassification::REFERRED;
        }

        // Otherwise, a referrer is present but it's not in the trusted list.
        // Treat as UNKNOWN (not DIRECT, not REFERRED).
        return EntryClassification::UNKNOWN;
    }

    // ── Private ────────────────────────────────────────────────────────────────

    /**
     * Checks whether the referrer URL matches any of the trusted patterns.
     *
     * Patterns may be:
     *  - An exact URL prefix: "https://trusted.example/path"
     *  - A wildcard subdomain pattern: "*.trusted.example"
     *
     * Wildcard patterns are matched against the referrer *host only*.
     * Exact patterns are matched as a URL prefix (scheme + host + path).
     *
     * @param array<string,mixed> $parsed  Result of parse_url($referrer).
     */
    private function referrerMatches(string $referrer, array $parsed): bool
    {
        $referrerHost = strtolower((string) ($parsed['host'] ?? ''));

        foreach ($this->trustedReferrers as $pattern) {
            if (!is_string($pattern) || $pattern === '') {
                continue;
            }

            // Wildcard subdomain pattern: *.example.com
            if (str_starts_with($pattern, '*.')) {
                if ($this->matcher->hostMatches($referrerHost, [$pattern])) {
                    return true;
                }
                continue;
            }

            // Exact URL prefix match (normalised, lowercased scheme+host)
            $patternParsed = parse_url($pattern);
            if ($patternParsed === false) {
                continue;
            }

            $patternHost   = strtolower((string) ($patternParsed['host'] ?? ''));
            $patternScheme = strtolower((string) ($patternParsed['scheme'] ?? ''));
            $referrerScheme = strtolower((string) ($parsed['scheme'] ?? ''));

            if ($patternHost !== $referrerHost) {
                continue;
            }

            if ($patternScheme !== '' && $patternScheme !== $referrerScheme) {
                continue;
            }

            // If pattern has a path, referrer must start with that path.
            $patternPath  = (string) ($patternParsed['path'] ?? '/');
            $referrerPath = (string) ($parsed['path'] ?? '/');

            if ($patternPath !== '' && !str_starts_with($referrerPath, $patternPath)) {
                continue;
            }

            return true;
        }

        return false;
    }
}
