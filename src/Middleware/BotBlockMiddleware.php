<?php

namespace Supamask\Middleware;

use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Security\BotMatcher;

class BotBlockMiddleware implements MiddlewareInterface
{
    public function __construct(
        private BotMatcher $matcher
    ) {
    }

    public function handle(Context $context): Decision
    {
        $userAgent = $context->request()->userAgent();

        if ($this->matcher->matches($userAgent)) {
            $context->setDecisionReason('blocked_bot');
            return Decision::DENY;
        }

        return Decision::ALLOW;
    }
}