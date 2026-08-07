<?php

namespace Supamask\Security;

use Supamask\Contracts\BlocklistInterface;

class AntiRed implements BlocklistInterface
{
    public function __construct(
        private array $rules = [],
        private IpMatcher $matcher = new IpMatcher()
    ) {
    }

    public function contains(string $ip): bool
    {
        foreach ($this->rules as $rule) {
            if ($this->matcher->matches($ip, $rule)) {
                return true;
            }
        }

        return false;
    }
}