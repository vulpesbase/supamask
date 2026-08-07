<?php

namespace Supamask\Core;

use Supamask\Http\Request;
use Supamask\Middleware\IpBlockMiddleware;
use Supamask\Middleware\Pipeline;
use Supamask\Security\AntiRed;
use Supamask\Security\CustomBlocklist;

class Kernel
{
    public function __construct(
        private Config $config
    ) {
    }

    public function handle(Request $request): void
    {
        $context = new Context(
            $request,
            $this->config
        );

        $antiRedRules = [];

        if ($this->config->get('ip_blocking.antired', true)) {
            $antiRedRules = require __DIR__ . '/../Security/Data/antired.php';
        }

        $antiRed = new AntiRed($antiRedRules);

        $customBlocklist = new CustomBlocklist(
            $this->config->get('ip_blocking.ips', [])
        );

        $pipeline = new Pipeline();

        $pipeline->pipe(
            new IpBlockMiddleware(
                $antiRed,
                $customBlocklist
            )
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