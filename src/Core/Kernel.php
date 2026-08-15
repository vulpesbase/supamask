<?php

namespace Supamask\Core;

use RuntimeException;
use Supamask\Challenge\ChallengeHandler;
use Supamask\Challenge\ChallengePresentationInterface;
use Supamask\Challenge\DefaultChallengePresentation;
use Supamask\Challenge\ChallengeManager;
use Supamask\Challenge\SessionChallengeStore;
use Supamask\Challenge\SessionVerification;
use Supamask\Entry\DisposableEntryHandler;
use Supamask\Entry\DisposableEntryManager;
use Supamask\Entry\EntryClassification;
use Supamask\Entry\EntryClassificationPolicy;
use Supamask\Entry\EntryClassifier;
use Supamask\Entry\SessionDisposableEntryRegistry;
use Supamask\Http\Request;
use Supamask\Http\Response;
use Supamask\Middleware\BotBlockMiddleware;
use Supamask\Middleware\ChallengeMiddleware;
use Supamask\Middleware\IpBlockMiddleware;
use Supamask\Middleware\Pipeline;
use Supamask\Security\AntiRed;
use Supamask\Security\BotMatcher;
use Supamask\Security\CustomBlocklist;
use Supamask\Routing\RoutePolicy;

/**
 * Main request orchestrator.
 *
 * Request flow (strict pipeline ordering):
 *
 *   1. Challenge serve/verify paths — intercepts /_supamask/challenge/{id}
 *   2. Request Context built once
 *   3. Entry / Routing Classification
 *        ├── SEEDED (active entry)
 *        │       └── DisposableEntryHandler (challenge flow)
 *        └── DIRECT / REFERRED / UNKNOWN
 *                ├── Policy = CHALLENGE/DENY → Immediate Response
 *                └── Policy = ALLOW → Normal Policy Pipeline
 *   4. Normal Policy Pipeline
 *        ├── ChallengeMiddleware      (route protection)
 *        ├── IpBlockMiddleware
 *        └── BotBlockMiddleware
 *   5. Decision → Response (or null = pass to application)
 */
class Kernel
{
    protected string $antiRedPath     = __DIR__ . '/../Security/Data/antired.php';
    protected string $antiRedBotsPath = __DIR__ . '/../Security/Data/antired-bots.php';

    private ChallengeManager $challengeManager;
    private SessionVerification $verification;
    private ChallengeHandler $challengeHandler;
    private DisposableEntryManager $disposableEntryManager;

    public function __construct(
        protected Config $config,
        ?ChallengeManager $challengeManager = null,
        ?SessionVerification $verification = null,
        ?DisposableEntryManager $disposableEntryManager = null,
    ) {
        $this->challengeManager = $challengeManager ?? new ChallengeManager(
            new SessionChallengeStore(),
            (int) $this->config->get('challenge.ttl', 300),
            (int) $this->config->get('challenge.verification_ttl', 1800),
        );
        $this->verification = $verification ?? new SessionVerification();
        $presentation = $this->config->get('challenge.presentation.handler');

        if (!$presentation instanceof ChallengePresentationInterface) {
            $presentation = new DefaultChallengePresentation();
        }

        // Create or inject a single, shared DisposableEntryManager instance.
        // This ensures all components (ClassificationEntry, DisposableEntryHandler, ChallengeHandler)
        // use the same registry and authoritative state.
        $this->disposableEntryManager = $disposableEntryManager ?? new DisposableEntryManager(
            new SessionDisposableEntryRegistry(),
            (int) $this->config->get('disposable.ttl', 900),
            (int) $this->config->get('disposable.slug_length', 12),
            (bool) $this->config->get('disposable.single_use', true),
        );

        $this->challengeHandler = new ChallengeHandler(
            $this->challengeManager,
            $this->verification,
            $this->config,
            $presentation,
            $this->disposableEntryManager,
        );
    }

    public function handle(Request $request): ?Response
    {
        // ── 1. Challenge serve/verify paths ──────────────────────────────────
        // MUST be checked before classification so verification requests are not intercepted.
        if ($this->challengeHandler->matches($request)) {
            return $this->challengeHandler->handle($request);
        }

        // ── 2. Request Context ────────────────────────────────────────────────
        $context = new Context($request, $this->config);

        // ── 3. Entry / Routing Classification ─────────────────────────────────
        $classification = EntryClassification::DIRECT;

        if ($this->config->get('entry.enabled', false) || $this->config->get('disposable.enabled', false)) {
            $classifier = $this->buildEntryClassifier();
            $classification = $classifier->classify($context->requestContext(), $context);
            $context->setClassification($classification);

            // ── 3.5. Reject Invalid Disposable Entries ─────────────────────────
            // CONSUMED and EXPIRED disposable entries must not reach the application.
            $invalidEntryState = $context->getInvalidDisposableEntryState();
            if ($invalidEntryState !== null) {
                // Entry was found but is consumed or expired. Reject it.
                // Use 410 Gone for consumed entries, 404 for expired (don't leak timing info).
                $statusCode = 410; // Gone — entry was valid but is no longer available
                return new Response($statusCode, 'Gone.', [
                    'Content-Type'  => 'text/plain; charset=UTF-8',
                    'Cache-Control' => 'no-store',
                ]);
            }

            if ($classification === EntryClassification::SEEDED) {
                // Disposable entry -> challenge flow
                return $this->buildDisposableEntryHandler()->handle($request, $context->getDisposableEntry());
            }

            if ($this->config->get('entry.enabled', false)) {
                $policy = new EntryClassificationPolicy((array) $this->config->get('entry.policy', []));
                $decision = $policy->decide($classification);

                if ($decision === Decision::DENY) {
                    return $this->createConfiguredResponse('deny', 403, 'Access denied');
                }
                if ($decision === Decision::CHALLENGE) {
                    return $this->createChallengeResponse($request);
                }
            }
        }

        // ── 4. Normal Policy Pipeline ─────────────────────────────────────────
        $pipeline = new Pipeline();

        if ($this->config->get('challenge.middleware.enabled', false)) {
            $pipeline->pipe(new ChallengeMiddleware(
                $this->verification,
                new RoutePolicy([
                    'protection' => $this->config->get('challenge.protection', []),
                    'routing'    => $this->config->get('routing', []),
                ]),
            ));
        }

        if ($this->config->get('ip_blocking.enabled', true)) {
            $antiRedRules = [];

            if ($this->config->get('ip_blocking.antired', true)) {
                $antiRedRules = require $this->antiRedPath;
            }

            $antiRed       = new AntiRed($antiRedRules);
            $customBlocklist = new CustomBlocklist(
                $this->config->get('ip_blocking.rules', [])
            );

            $pipeline->pipe(new IpBlockMiddleware($antiRed, $customBlocklist));
        }

        if ($this->config->get('bot_blocking.enabled', true)) {
            $botSignatures = [];
            $botAntired    = $this->config->has('bot_blocking.antired')
                ? $this->config->get('bot_blocking.antired')
                : $this->config->get('ip_blocking.antired', true);

            if ($botAntired) {
                $botSignatures = require $this->antiRedBotsPath;
            }

            $customSignatures = $this->config->get('bot_blocking.signatures', []);
            $allSignatures    = array_merge($botSignatures, $customSignatures);
            $pipeline->pipe(new BotBlockMiddleware(new BotMatcher($allSignatures)));
        }

        // ── 5. Decision ───────────────────────────────────────────────────────
        $decision = $pipeline->process($context);

        return match ($decision) {
            Decision::ALLOW     => null,
            Decision::CHALLENGE => $this->createChallengeResponse($request),
            Decision::DENY      => $this->createConfiguredResponse('deny', 403, 'Access denied'),
        };
    }

    protected function createChallengeResponse(Request $request): Response
    {
        return $this->challengeHandler->create($request);
    }

    // ── Private builders ──────────────────────────────────────────────────────

    private function buildDisposableEntryHandler(): DisposableEntryHandler
    {
        return new DisposableEntryHandler($this->disposableEntryManager, $this->challengeHandler);
    }

    private function buildEntryClassifier(): EntryClassifier
    {
        return new EntryClassifier(
            $this->disposableEntryManager,
            (array) $this->config->get('entry.referrers', []),
        );
    }

    private function createConfiguredResponse(string $key, int $status, string $body): Response
    {
        $response = $this->config->get('responses.' . $key, [
            'status'  => $status,
            'body'    => $body,
            'headers' => [],
        ]);

        return new Response($response['status'], $response['body'], $response['headers']);
    }
}
