<?php

namespace Supamask\Core;

use Supamask\Core\Config;
use Supamask\Core\Decision;
use Supamask\Http\Request;
use Supamask\Middleware\AllowMiddleware;
use Supamask\Middleware\Pipeline;

class Kernel
{
    public function __construct(
        private Config $config
    ) {
    }

    public function handle(Request $request): void
    {
        $pipeline = new Pipeline();

        $pipeline->pipe(
            new AllowMiddleware()
        );

        $context = new Context(
    $request,
    $this->config
);

$decision = $pipeline->process($context);

        switch ($decision) {
            case Decision::ALLOW:
                return;

            case Decision::CHALLENGE:
                exit('Challenge');

            case Decision::DENY:
                exit('Access denied');
        }
    }
}