<?php

namespace Supamask\Routing;

use Supamask\Http\RequestContext;

/**
 * Determines whether a request requires challenge based on routing rules.
 *
 * ─── PRECEDENCE MODEL ─────────────────────────────────────────────────────
 *
 * The following precedence (highest to lowest) is applied:
 *
 * 1. DISABLED
 *    If protection.enabled is false, no challenge is required.
 *
 * 2. HOST EXCLUSIONS
 *    If the request host matches exclude_hosts, no challenge is required.
 *    This overrides any host or path inclusion rules.
 *
 * 3. PATH EXCLUSIONS
 *    If the request path matches exclude_paths, no challenge is required.
 *    This overrides root behavior and inclusion rules.
 *    NOTE: exclude_paths patterns use wildcard semantics (e.g., /* matches /).
 *
 * 4. ROOT BEHAVIOR (if path is /)
 *    If path is exactly "/" and routing.root.behavior is defined:
 *    - 'challenge' → challenge required
 *    - 'allow' → no challenge required
 *    - undefined → fall through to inclusion rules
 *
 * 5. HOST AND PATH INCLUSION RULES
 *    If protection.hosts is empty OR host matches, AND
 *    If protection.paths is empty OR path matches,
 *    then challenge is required.
 *
 * ─── EXAMPLES ─────────────────────────────────────────────────────────────
 *
 * Example 1: Protect /api except /api/health
 *   [ 'enabled' => true, 'paths' => ['/api/*'], 'exclude_paths' => ['/api/health'] ]
 *   → /api requires challenge, /api/health does not, others allowed
 *
 * Example 2: Root allows, /pricing requires challenge
 *   [
 *       'enabled' => true,
 *       'paths' => ['/pricing'],
 *       'routing' => ['root' => ['behavior' => 'allow']],
 *   ]
 *   → / allowed, /pricing requires challenge, others allowed
 *
 * Example 3: Root requires challenge, except when excluded
 *   [
 *       'enabled' => true,
 *       'paths' => ['/'],
 *       'exclude_paths' => ['/'],
 *       'routing' => ['root' => ['behavior' => 'challenge']],
 *   ]
 *   → / allowed (excluded overrides root behavior)
 *
 * Example 4: Root requires challenge, subpaths excluded
 *   [
 *       'enabled' => true,
 *       'paths' => ['/'],
 *       'exclude_paths' => ['/app/*'],
 *       'routing' => ['root' => ['behavior' => 'challenge']],
 *   ]
 *   → / requires challenge (excluded pattern doesn't match /), /app/x allowed
 */
final class RoutePolicy
{
    private RouteMatcher $matcher;

    public function __construct(
        private array $config = [],
        ?RouteMatcher $matcher = null,
    ) {
        $this->matcher = $matcher ?? new RouteMatcher();
    }

    /**
     * Determines if the given request requires a challenge based on routing rules.
     *
     * Applies the precedence model documented in the class docblock.
     *
     * @return bool True if challenge is required, false otherwise.
     */
    public function requiresChallenge(RequestContext $context): bool
    {
        $protection = $this->config['protection'] ?? $this->config;
        $routing    = $this->config['routing'] ?? [];

        if (!($protection['enabled'] ?? false)) {
            return false;
        }

        $host = $context->host();
        $path = $context->path();

        if ($this->matcher->hostMatches($host, $protection['exclude_hosts'] ?? [])) {
            return false;
        }

        if ($this->matcher->pathMatches($path, $protection['exclude_paths'] ?? [])) {
            return false;
        }

        if ($path === '/') {
            $rootBehavior = $routing['root']['behavior'] ?? null;
            if ($rootBehavior !== null) {
                return strtolower($rootBehavior) === 'challenge';
            }
        }

        $hosts = $protection['hosts'] ?? [];
        $paths = $protection['paths'] ?? [];

        $hostMatched = $hosts === []
            || $this->matcher->hostMatches($host, $hosts);

        $pathMatched = $paths === []
            || $this->matcher->pathMatches($path, $paths);

        return $hostMatched && $pathMatched;
    }
}
