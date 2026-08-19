<?php

namespace Supamask\Middleware;

use Supamask\Challenge\SessionVerification;
use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Http\RequestContextFactory;
use Supamask\Routing\RoutePolicy;

final class ChallengeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private SessionVerification $verification,
        private RoutePolicy $policy,
        ?RequestContextFactory $contextFactory = null,
    ) {
        $this->contextFactory = $contextFactory ?? new RequestContextFactory();
    }

    private RequestContextFactory $contextFactory;

    public function handle(Context $context): Decision
    {
        $requestContext = $this->contextFactory->fromRequest($context->request());

        if (!$this->policy->requiresChallenge($requestContext)) {
            $context->setDecisionReason('route_allowed');
            return Decision::ALLOW;
        }

        if ($this->verification->isVerified()) {
            $context->setDecisionReason('challenge_verified');
            return Decision::ALLOW;
        }

        $context->setDecisionReason('challenge_required');
        return Decision::CHALLENGE;
    }
}
