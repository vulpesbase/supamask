<?php

namespace Supamask\Middleware;

use Supamask\Contracts\MiddlewareInterface;
use Supamask\Http\Request;
use Supamask\Core\Decision;

class AllowMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): Decision
    {
        return Decision::ALLOW;
    }
}