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
    protected string $antiRedPath = __DIR__ . '/../Security/Data/antired.php';
    protected string $antiRedBotsPath = __DIR__ . '/../Security/Data/antired-bots.php';

    public function __construct(
        private Config $config
    ) {}

    public function handle(Request $request): ?Response
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
            $antiRedRules = require $this->antiRedPath;
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
            $botSignatures = require $this->antiRedBotsPath;
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
                return null;

            case Decision::CHALLENGE:
                $response = $this->config->get(
                    'responses.challenge',
                    [
                        'status' => 403,
                        'body' => 'Challenge',
                        'headers' => [],
                    ]
                );

                return new Response(
                    $response['status'],
                    $response['body'],
                    $response['headers']
                );

            case Decision::DENY:
                $response = $this->config->get(
                    'responses.deny',
                    [
                        'status' => 403,
                        'body' => 'Access denied',
                        'headers' => [],
                    ]
                );

                return new Response(
                    $response['status'],
                    $response['body'],
                    $response['headers']
                );
        }
    }
}
