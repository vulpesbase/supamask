<?php

namespace Supamask\Core;

use Supamask\Http\Request;
use Supamask\Http\Response;
use Supamask\Middleware\BotBlockMiddleware;
use Supamask\Middleware\IpBlockMiddleware;
use Supamask\Middleware\Pipeline;
use Supamask\Security\AntiRed;
use Supamask\Security\BotMatcher;
use Supamask\Security\CustomBlocklist;

class Kernel
{
    public function __construct(
        private Config $config
    ) {}

    public function handle(Request $request): void
    {
        $context = new Context(
            $request,
            $this->config
        );

        /*
         * AntiRed IP rules
         */
        $antiRedRules = [];

        if ($this->config->get('ip_blocking.antired', true)) {
            $antiRedRules = require __DIR__ . '/../Security/Data/antired.php';
        }

        $antiRed = new AntiRed($antiRedRules);

        /*
         * User-defined IP rules
         */
        $customBlocklist = new CustomBlocklist(
            $this->config->get('ip_blocking.rules', [])
        );

        /*
         * AntiRed bot signatures
         */
        $botSignatures = [];

        if ($this->config->get('ip_blocking.antired', true)) {
            $botSignatures = require __DIR__ . '/../Security/Data/antired-bots.php';
        }

        $botMatcher = new BotMatcher($botSignatures);

        /*
         * Middleware pipeline
         */
        $pipeline = new Pipeline();

        $pipeline
            ->pipe(
                new IpBlockMiddleware(
                    $antiRed,
                    $customBlocklist
                )
            )
            ->pipe(
                new BotBlockMiddleware(
                    $botMatcher
                )
            );

        /*
         * Process request
         */
        $decision = $pipeline->process($context);

        switch ($decision) {
            case Decision::ALLOW:
                return;

            case Decision::CHALLENGE:
                $response = $this->config->get(
                    'responses.challenge',
                    [
                        'status' => 403,
                        'body' => 'Challenge',
                    ]
                );

                (new Response(
                    $response['status'],
                    $response['body']
                ))->send();

            case Decision::DENY:
                $response = $this->config->get(
                    'responses.deny',
                    [
                        'status' => 403,
                        'body' => 'Access denied',
                    ]
                );

                (new Response(
                    $response['status'],
                    $response['body']
                ))->send();
        }
    }
}
