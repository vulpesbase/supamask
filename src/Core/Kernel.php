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
        protected Config $config
    ) {}

    public function handle(Request $request): ?Response
    {
        $context = new Context(
            $request,
            $this->config
        );

        $pipeline = new Pipeline();

        // IP Blocking
        if ($this->config->get('ip_blocking.enabled', true)) {
            $antiRedRules = [];

            if ($this->config->get('ip_blocking.antired', true)) {
                $antiRedRules = require $this->antiRedPath;
            }

            $antiRed = new AntiRed($antiRedRules);

            $customBlocklist = new CustomBlocklist(
                $this->config->get('ip_blocking.rules', [])
            );

            $pipeline->pipe(new IpBlockMiddleware($antiRed, $customBlocklist));
        }

        // Bot Blocking
        if ($this->config->get('bot_blocking.enabled', true)) {
            $botSignatures = [];

            // Fallback to ip_blocking.antired if bot_blocking.antired is not set, for BC
            $botAntired = $this->config->has('bot_blocking.antired')
                ? $this->config->get('bot_blocking.antired')
                : $this->config->get('ip_blocking.antired', true);
            if ($botAntired) {
                $botSignatures = require $this->antiRedBotsPath;
            }

            $customSignatures = $this->config->get('bot_blocking.signatures', []);
            $allSignatures = array_merge($botSignatures, $customSignatures);

            $botMatcher = new BotMatcher($allSignatures);

            $pipeline->pipe(new BotBlockMiddleware($botMatcher));
        }

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
