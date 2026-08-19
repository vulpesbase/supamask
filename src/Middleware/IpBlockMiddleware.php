<?php

namespace Supamask\Middleware;

use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Security\AntiRed;
use Supamask\Security\CustomBlocklist;

class IpBlockMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AntiRed $antiRed,
        private CustomBlocklist $customBlocklist
    ) {
    }

    public function handle(Context $context): Decision
    {
        $ip = $context->request()->ip();

        if ($this->antiRed->contains($ip)) {
            $context->setDecisionReason('blocked_ip_antired');
            return Decision::DENY;
        }

        if ($this->customBlocklist->contains($ip)) {
            $context->setDecisionReason('blocked_ip');
            return Decision::DENY;
        }

        return Decision::ALLOW;
    }
}