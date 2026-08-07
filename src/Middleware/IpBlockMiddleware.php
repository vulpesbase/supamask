<?php

namespace Supamask\Middleware;

use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Security\AntiRed;
use Supamask\Security\CustomBlocklist;

class IpBlockMiddleware implements MiddlewareInterface
{
  public function handle(Context $context): Decision
{
    $ip = $context->request()->ip();

    $antiRed = new AntiRed();

    if ($antiRed->contains($ip)) {
        return Decision::DENY;
    }

    $customIps = $context
        ->config()
        ->get('ip_blocking.ips', []);

    $customBlocklist = new CustomBlocklist($customIps);

    if ($customBlocklist->contains($ip)) {
        return Decision::DENY;
    }

    return Decision::ALLOW;
}
}