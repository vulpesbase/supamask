<?php

namespace Supamask\Entry;

use RuntimeException;
use Supamask\Challenge\ChallengeHandler;
use Supamask\Http\Request;
use Supamask\Http\Response;

/**
 * Intercepts requests to disposable entry paths and initiates a challenge.
 *
 * A disposable entry path is a short random slug at the root level, e.g.:
 *
 *   GET /82f6cd2d2843
 *
 * This is NOT the challenge serve path (/_supamask/challenge/{id}), which
 * is handled by ChallengeHandler. The entry handler:
 *
 *   1. Recognises a slug-shaped path.
 *   2. Looks up the corresponding DisposableEntry.
 *   3. Consumes it (single-use enforcement).
 *   4. Delegates to ChallengeHandler::create() to build a challenge and
 *      redirect to the challenge serve path.
 *
 * If the slug is invalid, not found, already consumed, or expired, the
 * handler returns a 404 response. It does NOT reveal whether the slug
 * ever existed, to prevent enumeration attacks.
 *
 * Deployment note:
 * The application's front controller (Apache mod_rewrite, nginx try_files,
 * PHP built-in server, etc.) must forward all paths — including unknown
 * short paths — to the PHP entry point so Supamask can intercept them.
 * See docs/routing-architecture.md §Deployment for environment-specific
 * configuration examples.
 */
final class DisposableEntryHandler
{
    public function __construct(
        private DisposableEntryManager $manager,
        private ChallengeHandler $challengeHandler,
    ) {
    }

    /**
     * Returns true if this request path looks like a disposable entry slug.
     *
     * Only GET requests are matched; POST to a slug path is always rejected.
     */
    public function matches(Request $request): bool
    {
        if ($request->method() !== 'GET') {
            return false;
        }

        $path = $this->parsePath($request);

        return $this->manager->matchesSlugFormat(ltrim($path, '/'));
    }

    /**
     * Handles the disposable entry request.
     *
     * Successful path: create challenge → 302 to challenge URL.
     */
    public function handle(Request $request, \Supamask\Entry\DisposableEntry $entry): Response
    {
        // Override the request URI so the challenge records the *destination*,
        // not the slug path, as the original URI.
        // We pass the slug so the Challenge can be linked to the DisposableEntry.
        return $this->challengeHandler->createForDestination($request, $entry->destination(), $entry->slug());
    }

    private function parsePath(Request $request): string
    {
        $path = parse_url($request->uri(), PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        // Normalise: collapse duplicate slashes, remove trailing slash
        $path = preg_replace('#/{2,}#', '/', $path) ?? $path;
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    private function notFound(): Response
    {
        return new Response(404, 'Not found.', [
            'Content-Type'  => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
