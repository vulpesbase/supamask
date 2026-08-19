<?php

namespace Supamask\Challenge;

use RuntimeException;
use Supamask\Core\Config;
use Supamask\Entry\DisposableEntryManager;
use Supamask\Http\Request;
use Supamask\Http\Response;
use Supamask\Challenge\Presentation\ChallengeStateEnhancer;

final class ChallengeHandler
{
    public function __construct(
        private ChallengeManager $manager,
        private SessionVerification $verification,
        private Config $config,
        private ChallengePresentationInterface $presentation,
        private ?DisposableEntryManager $disposableEntryManager = null,
        private ?ChallengeSecurityPolicy $securityPolicy = null,
    ) {
        $this->securityPolicy ??= new ChallengeSecurityPolicy();
    }

    public function matches(Request $request): bool
    {
        $path = parse_url($request->uri(), PHP_URL_PATH);
        $prefix = $this->path();

        if (!is_string($path)) {
            return false;
        }

        if (preg_match('#^' . preg_quote($prefix, '#') . '([a-f0-9]{12})/?$#', $path, $matches) !== 1) {
            return false;
        }

        $id = $matches[1];

        // Disposable entries share the same 12-hex format and root-level namespace.
        // If this ID corresponds to any disposable entry (active, consumed, or expired),
        // we must NOT intercept it here. It must fall through to EntryClassification
        // so its strict lifecycle (e.g. 410 Gone) is enforced.
        if ($this->disposableEntryManager !== null && $this->disposableEntryManager->find($id) !== null) {
            return false;
        }

        return true;
    }

    public function handle(Request $request): Response
    {
        $id = $this->challengeId($request);

        try {
            if ($request->method() === 'POST') {
                try {
                    return $this->verify($request, $id);
                } catch (RuntimeException) {
                    // Invalid verification attempts do not consume a pending challenge.
                    // Keep the same challenge available and render its retry state.
                    $challenge = $this->manager->inspect($id);

                    $rendered = $this->renderProtected($challenge->id(), $challenge->verificationToken(), 'retry');

                    return new Response(
                        200,
                        $rendered['body'],
                        array_merge($this->noStoreHeaders('text/html; charset=UTF-8'), $rendered['headers']),
                    );
                }
            }

            if ($request->method() !== 'GET') {
                return new Response(405, 'Method not allowed.', [
                    'Allow' => 'GET, POST',
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'Cache-Control' => 'no-store',
                ]);
            }

            $challenge = $this->manager->inspect($id);

            $rendered = $this->renderProtected($challenge->id(), $challenge->verificationToken());

            return new Response(
                200,
                $rendered['body'],
                array_merge($this->noStoreHeaders('text/html; charset=UTF-8'), $rendered['headers']),
            );
        } catch (RuntimeException) {
            if ($request->method() === 'GET') {
                return $this->restartPresentation($id);
            }

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
        $proofOfWorkCounter = $request->input('pow_counter');

        if (!is_string($token) || $token === '') {
            return new Response(400, 'Invalid verification request.', [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $challenge = $this->manager->consume(
            $id,
            $token,
            null,
            is_string($proofOfWorkCounter) ? $proofOfWorkCounter : null,
        );

        if ($challenge->entrySlug() !== null && $this->disposableEntryManager !== null) {
            $this->disposableEntryManager->consume($challenge->entrySlug());
        }

        $this->verification->markVerified($this->manager->verificationTtl());

        $rendered = $this->renderProtected(
            $challenge->id(),
            $challenge->verificationToken(),
            'success',
            $challenge->originalUri(),
        );

        return new Response(
            200,
            $rendered['body'],
            array_merge($this->noStoreHeaders('text/html; charset=UTF-8'), $rendered['headers']),
        );
    }

    private function challengeId(Request $request): string
    {
        $path = parse_url($request->uri(), PHP_URL_PATH);
        $prefix = $this->path();

        return trim(substr((string) $path, strlen($prefix)), '/');
    }

    private function path(): string
    {
        // presentation_path is the new explicit browser-facing route. Honor
        // an explicitly configured legacy path for integrations that opted
        // into their own route before this setting existed.
        $path = $this->config->has('challenge.presentation_path')
            ? (string) $this->config->get('challenge.presentation_path')
            : ($this->config->has('challenge.path')
                ? (string) $this->config->get('challenge.path')
                : '/');

        $trimmed = trim($path, '/');
        return $trimmed === '' ? '/' : '/' . $trimmed . '/';
    }

    /**
     * Starts a new presentation challenge after an invalid GET request.
     *
     * A restart never revives the rejected challenge. Entry-bound challenges
     * are excluded because their disposable-entry lifecycle remains
     * authoritative and may require a 410 response.
     */
    private function restartPresentation(string $id): Response
    {
        $previous = $this->manager->find($id);

        if ($previous !== null && $previous->entrySlug() !== null) {
            return new Response(410, 'Gone.', [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $destination = $previous?->originalUri() ?? '/';
        $challenge = $this->manager->create($destination);

        return new Response(302, '', [
            'Location' => $this->path() . $challenge->id(),
            'Cache-Control' => 'no-store',
        ]);
    }

    private function safeOriginalUri(Request $request): string
    {
        $uri = $request->uri();

        if ($uri === '' || $uri[0] !== '/' || str_starts_with($uri, '//')) {
            return '/';
        }

        return $uri;
    }

    private function render(string $id, string $token, string $state = 'challenge', ?string $redirect = null, ?string $nonce = null): string
    {
        $presentation = $this->config->get('challenge.presentation', []);

        $context = [
            'id' => $id,
            'token' => $token,
            'action' => $this->path() . $id,
            'state' => $state,
        ];

        if ($nonce !== null) {
            $context['csp_nonce'] = $nonce;
        }

        if ($redirect !== null) {
            $context['redirect'] = $redirect;
        }

        $proofOfWork = $this->manager->find($id)?->proofOfWork();
        if ($proofOfWork !== null) {
            $context['pow_nonce'] = $proofOfWork->nonce();
            $context['pow_difficulty'] = (string) $proofOfWork->difficulty();
        }

        foreach (['title', 'heading', 'message', 'button', 'trust_footer'] as $key) {
            if (isset($presentation[$key])) {
                $context[$key] = $presentation[$key];
            }
        }

        $html = $this->presentation->render($context);

        return ChallengeStateEnhancer::enhance(
            $html,
            $state,
            $redirect,
            $nonce,
            isset($context['pow_nonce']) ? (string) $context['pow_nonce'] : null,
            isset($context['pow_difficulty']) ? (int) $context['pow_difficulty'] : null,
        );
    }

    /**
     * @return array{body: string, headers: array<string, string>}
     */
    private function renderProtected(string $id, string $token, string $state = 'challenge', ?string $redirect = null): array
    {
        $nonce = $this->securityPolicy->nonce();
        $rendered = $this->render($id, $token, $state, $redirect, $nonce);

        return $this->securityPolicy->protect($rendered, $nonce);
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
