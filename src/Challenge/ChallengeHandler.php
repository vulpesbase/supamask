<?php

namespace Supamask\Challenge;

use RuntimeException;
use Supamask\Core\Config;
use Supamask\Entry\DisposableEntryManager;
use Supamask\Http\Request;
use Supamask\Http\Response;

final class ChallengeHandler
{
    public function __construct(
        private ChallengeManager $manager,
        private SessionVerification $verification,
        private Config $config,
        private ChallengePresentationInterface $presentation,
        private ?DisposableEntryManager $disposableEntryManager = null,
    ) {
    }

    public function matches(Request $request): bool
    {
        $path = parse_url($request->uri(), PHP_URL_PATH);
        $prefix = $this->path();

        return is_string($path)
            && preg_match(
                '#^' . preg_quote($prefix, '#') . '([a-f0-9]{12})/?$#',
                $path
            ) === 1;
    }

    public function handle(Request $request): Response
    {
        $id = $this->challengeId($request);

        try {
            if ($request->method() === 'POST') {
                return $this->verify($request, $id);
            }

            if ($request->method() !== 'GET') {
                return new Response(405, 'Method not allowed.', [
                    'Allow' => 'GET, POST',
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'Cache-Control' => 'no-store',
                ]);
            }

            $challenge = $this->manager->inspect($id);

            return new Response(
                200,
                $this->render($challenge->id(), $challenge->verificationToken()),
                $this->noStoreHeaders('text/html; charset=UTF-8'),
            );
        } catch (RuntimeException) {
            return new Response(404, 'Challenge not found or expired.', [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }
    }

    public function create(Request $request): Response
    {
        if (!$this->config->get('challenge.enabled', true)) {
            return new Response(403, 'Challenge', [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $challenge = $this->manager->create($this->safeOriginalUri($request));

        return new Response(302, '', [
            'Location' => $this->path() . $challenge->id(),
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Creates a challenge for an explicit, pre-validated destination path.
     *
     * Used by DisposableEntryHandler so the post-challenge redirect goes to
     * the entry's stored destination, not to the slug path that was visited.
     *
     * The destination must already be a validated local path.
     *
     * @param string $destination  A local path starting with / (pre-validated).
     */
    public function createForDestination(Request $request, string $destination, ?string $entrySlug = null): Response
    {
        if (!$this->config->get('challenge.enabled', true)) {
            return new Response(403, 'Challenge', [
                'Content-Type'  => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $challenge = $this->manager->create($destination, null, $entrySlug);

        return new Response(302, '', [
            'Location'      => $this->path() . $challenge->id(),
            'Cache-Control' => 'no-store',
        ]);
    }

    private function verify(Request $request, string $id): Response
    {
        $token = $request->input('token');

        if (!is_string($token) || $token === '') {
            return new Response(400, 'Invalid verification request.', [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $challenge = $this->manager->consume($id, $token);

        if ($challenge->entrySlug() !== null && $this->disposableEntryManager !== null) {
            $this->disposableEntryManager->consume($challenge->entrySlug());
        }

        $this->verification->markVerified($this->manager->verificationTtl());

        return new Response(302, '', [
            'Location' => $challenge->originalUri(),
            'Cache-Control' => 'no-store',
        ]);
    }

    private function challengeId(Request $request): string
    {
        $path = parse_url($request->uri(), PHP_URL_PATH);
        $prefix = $this->path();

        return trim(substr((string) $path, strlen($prefix)), '/');
    }

    private function path(): string
    {
        $path = (string) $this->config->get(
            'challenge.path',
            '/_supamask/challenge/'
        );

        return '/' . trim($path, '/') . '/';
    }

    private function safeOriginalUri(Request $request): string
    {
        $uri = $request->uri();

        if ($uri === '' || $uri[0] !== '/' || str_starts_with($uri, '//')) {
            return '/';
        }

        return $uri;
    }

    private function render(string $id, string $token): string
    {
        $presentation = $this->config->get('challenge.presentation', []);

        return $this->presentation->render([
            'id' => $id,
            'token' => $token,
            'action' => $this->path() . $id,
            'title' => $presentation['title'] ?? 'Security verification',
            'heading' => $presentation['heading'] ?? 'Security verification',
            'message' => $presentation['message']
                ?? 'Please confirm to continue to the requested page.',
            'button' => $presentation['button'] ?? 'Continue',
        ]);
    }

    private function noStoreHeaders(string $contentType): array
    {
        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ];
    }
}
