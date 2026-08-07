<?php

namespace Supamask\Security;

use Supamask\Contracts\BlocklistInterface;

class AntiRed implements BlocklistInterface
{
    private array $ips = [];

    public function contains(string $ip): bool
    {
        return in_array($ip, $this->ips, true);
    }
}