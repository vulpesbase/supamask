<?php

namespace Supamask\Security;

class BotMatcher
{
    public function __construct(
        private array $signatures = []
    ) {
    }

    public function matches(string $userAgent): bool
    {
        $userAgent = strtolower($userAgent);

        foreach ($this->signatures as $signature) {
            if (str_contains($userAgent, strtolower($signature))) {
                return true;
            }
        }

        return false;
    }
}