<?php

namespace Supamask\Middleware;

use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Context;
use Supamask\Core\Decision;

class AllowMiddleware implements MiddlewareInterface
{
    public function handle(Context $context): Decision
    {
        return Decision::ALLOW;
    }
}