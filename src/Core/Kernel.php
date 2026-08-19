<?php

namespace Supamask\Core;

use RuntimeException;
use Supamask\Challenge\ChallengeHandler;
use Supamask\Challenge\ChallengePresentationInterface;
use Supamask\Challenge\Presentation\PolymorphicChallengePresentation;
use Supamask\Challenge\ChallengeManager;
use Supamask\Contracts\RequestLoggerInterface;
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
use Supamask\Middleware\IpIntelligenceMiddleware;
use Supamask\Middleware\ReferrerBlockMiddleware;
use Supamask\Middleware\Pipeline;
use Supamask\Security\AntiRed;
use Supamask\Security\BotMatcher;
use Supamask\Security\CustomBlocklist;
use Supamask\Security\ProofOfWork\ProofOfWorkGenerator;
use Supamask\Security\ProofOfWork\ProofOfWorkVerifier;
use Supamask\Security\IpIntelligence\CachedIpIntelligenceProvider;
use Supamask\Security\IpIntelligence\FileIpIntelligenceCache;
use Supamask\Security\IpIntelligence\InMemoryIpIntelligenceCache;
use Supamask\Security\IpIntelligence\IpIntelligenceProviderInterface;
use Supamask\Security\IpIntelligence\IpIntelligenceService;
use Supamask\Security\IpIntelligence\IpIntelligenceProviderFactory;
use Supamask\Routing\RoutePolicy;
use Supamask\Security\RequestLogger\FileRequestLogger;

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
 *        ├── IpBlockMiddleware        (hard DENY)
 *        ├── BotBlockMiddleware       (hard DENY)
 *        └── ChallengeMiddleware      (route protection)
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
    private ?IpIntelligenceProviderInterface $ipIntelligenceProvider = null;
    private ?RequestLoggerInterface $requestLogger = null;

    public function __construct(
        protected Config $config,
        ?ChallengeManager $challengeManager = null,
        ?SessionVerification $verification = null,
        ?DisposableEntryManager $disposableEntryManager = null,
        ?IpIntelligenceProviderInterface $ipIntelligenceProvider = null,
    ) {
        $proofOfWorkEnabled = (bool) $this->config->get('challenge.proof_of_work.enabled', true);
        $proofOfWorkGenerator = $proofOfWorkEnabled
            ? new ProofOfWorkGenerator(
                (int) $this->config->get('challenge.proof_of_work.difficulty', 16),
                min(
                    (int) $this->config->get('challenge.proof_of_work.ttl', 300),
                    (int) $this->config->get('challenge.ttl', 300),
                ),
            )
            : null;

        $configuredLogger = $this->config->get('logging.logger');
        if ($configuredLogger instanceof RequestLoggerInterface) {
            $this->requestLogger = $configuredLogger;
        } elseif ($this->config->get('logging.enabled', false)) {
            $this->requestLogger = new FileRequestLogger(
                (string) $this->config->get('logging.directory', 'storage/logs'),
                (bool) $this->config->get('logging.include_query_string', false),
                $this->config->get('logging.base_path'),
            );
        }

        if ($ipIntelligenceProvider !== null) {
            $this->ipIntelligenceProvider = $ipIntelligenceProvider;
        } elseif ($this->config->get('block_vpn', false) || $this->config->get('detect_isp', false)) {
            $token = (string) $this->config->get('ip_intelligence.token', getenv('SUPAMASK_IPINFO_TOKEN') ?: '');
            $provider = IpIntelligenceProviderFactory::create([
                'provider' => $this->config->get('ip_intelligence.provider', 'ipinfo'),
                'token' => $token,
                'timeout' => $this->config->get('ip_intelligence.timeout', 2),
                'endpoint' => $this->config->get('ip_intelligence.endpoint', 'https://api.ipinfo.io/lookup/'),
            ]);

            $cacheDirectory = $this->config->get('ip_intelligence.cache_directory');
            $cache = is_string($cacheDirectory) && $cacheDirectory !== ''
                ? new FileIpIntelligenceCache($cacheDirectory)
                : new InMemoryIpIntelligenceCache();

            $provider = new CachedIpIntelligenceProvider(
                $provider,
                $cache,
                (int) $this->config->get('ip_intelligence.cache_ttl', 3600),
            );

            $this->ipIntelligenceProvider = new IpIntelligenceService(
                $provider,
                (bool) $this->config->get('ip_intelligence.skip_private', true),
            );
        }

        $this->challengeManager = $challengeManager ?? new ChallengeManager(
            new SessionChallengeStore(),
            (int) $this->config->get('challenge.ttl', 300),
            (int) $this->config->get('challenge.verification_ttl', 1800),
            $proofOfWorkGenerator,
            $proofOfWorkEnabled ? new ProofOfWorkVerifier() : null,
        );
        $this->verification = $verification ?? new SessionVerification();
        $presentation = $this->config->get('challenge.presentation.handler');

        if (!$presentation instanceof ChallengePresentationInterface) {
            $presentation = new PolymorphicChallengePresentation();
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
        // ── 1. Request Context and terminal security policies ─────────────────
        // A hard DENY is always terminal. It must be evaluated before challenge
        // routes and disposable entries so denied traffic cannot create or use a
        // challenge as a side effect of routing.
        $context = new Context($request, $this->config);
        $hardDecision = $this->buildHardSecurityPipeline()->process($context);

        if ($hardDecision === Decision::DENY) {
            return $this->respondToDecision($context, $request, Decision::DENY);
        }

        // ── 2. Challenge serve/verify paths ──────────────────────────────────
        // This remains before entry classification, but only after hard DENY
        // policies have been allowed to terminate the request.
        if ($this->challengeHandler->matches($request)) {
            return $this->challengeHandler->handle($request);
        }

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
                $context->setDecisionReason('invalid_disposable_entry');
                $this->logDecision($context, Decision::DENY);
                return new Response($statusCode, 'Gone.', [
                    'Content-Type'  => 'text/plain; charset=UTF-8',
                    'Cache-Control' => 'no-store',
                ]);
            }

            if ($classification === EntryClassification::SEEDED) {
                // Disposable entry -> challenge flow
                $context->setDecisionReason('disposable_entry_challenge');
                $this->logDecision($context, Decision::CHALLENGE);
                return $this->buildDisposableEntryHandler()->handle($request, $context->getDisposableEntry());
            }

            if ($this->config->get('entry.enabled', false)) {
                $policy = new EntryClassificationPolicy((array) $this->config->get('entry.policy', []));
                $decision = $policy->decide($classification);

                if ($decision === Decision::DENY) {
                    $context->setDecisionReason('entry_policy_deny');
                    return $this->respondToDecision($context, $request, Decision::DENY);
                }
                if ($decision === Decision::CHALLENGE) {
                    $context->setDecisionReason('entry_policy_challenge');
                    return $this->respondToDecision($context, $request, Decision::CHALLENGE);
                }
            }
        }

        // ── 4. Normal Policy Pipeline ─────────────────────────────────────────
        $pipeline = new Pipeline();

        // Hard security policies were evaluated above, before disposable-entry
        // handling. Only challenge policy remains at this stage.
        if ($this->config->get('challenge.middleware.enabled', false)) {
            $pipeline->pipe(new ChallengeMiddleware(
                $this->verification,
                new RoutePolicy([
                    'protection' => $this->config->get('challenge.protection', []),
                    'routing'    => $this->config->get('routing', []),
                ]),
            ));
        }

        // ── 5. Decision ───────────────────────────────────────────────────────
        $decision = $pipeline->process($context);

        return $this->respondToDecision($context, $request, $decision);
    }

    private function buildHardSecurityPipeline(): Pipeline
    {
        $pipeline = new Pipeline();

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

        if ($this->ipIntelligenceProvider !== null) {
            $pipeline->pipe(new IpIntelligenceMiddleware(
                $this->ipIntelligenceProvider,
                (bool) $this->config->get('block_vpn', false),
                (bool) $this->config->get('detect_isp', false),
                (array) $this->config->get('isp_exclusions', []),
                (bool) $this->config->get('ip_intelligence.fail_closed', false),
            ));
        }

        if ($this->config->get('block_referrers', false)) {
            $pipeline->pipe(new ReferrerBlockMiddleware(
                true,
                (array) $this->config->get('referrer_blocklist', []),
                (bool) $this->config->get('block_missing_referrer', false),
            ));
        }

        return $pipeline;
    }

    private function respondToDecision(Context $context, Request $request, Decision $decision): ?Response
    {
        $this->logDecision($context, $decision);

        return match ($decision) {
            Decision::ALLOW     => null,
            Decision::CHALLENGE => $this->createChallengeResponse($request),
            Decision::DENY      => $this->createConfiguredResponse('deny', 403, 'Access denied'),
        };
    }

    private function logDecision(Context $context, Decision $decision): void
    {
        if ($this->requestLogger === null) {
            return;
        }

        $this->requestLogger->log($context, $decision);
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

        if ($key === 'deny' && ($response['action'] ?? 'block') === 'redirect') {
            $destination = $response['redirect'] ?? null;

            if (!is_string($destination) || !$this->isTrustedRedirectDestination($destination)) {
                // A malformed redirect configuration must never turn into an
                // open redirect or silently bypass the denial decision.
                throw new RuntimeException(
                    'DENY redirect requires a trusted absolute HTTP(S) URL.'
                );
            }

            $headers = is_array($response['headers'] ?? null)
                ? $response['headers']
                : [];

            $redirectStatus = (int) ($response['redirect_status'] ?? 302);
            if ($redirectStatus < 300 || $redirectStatus > 399) {
                throw new RuntimeException('DENY redirect_status must be a 3xx HTTP status.');
            }

            $headers['Location'] = $destination;

            return new Response(
                $redirectStatus,
                '',
                $headers,
            );
        }

        return new Response($response['status'], $response['body'], $response['headers']);
    }

    private function isTrustedRedirectDestination(string $destination): bool
    {
        $parts = parse_url($destination);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);

        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        // A configured redirect is intentionally absolute. Reject credentials,
        // fragments, control characters, and protocol-relative/relative forms.
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return false;
        }

        return !preg_match('/[\\x00-\\x20\\x7f]/', $destination);
    }
}
