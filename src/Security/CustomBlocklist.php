<?php

namespace Supamask\Security;

use Supamask\Contracts\BlocklistInterface;

class CustomBlocklist implements BlocklistInterface
{
    public function __construct(
        private array $ips = []
    ) {
    }

    public function contains(string $ip): bool
    {
        return in_array($ip, $this->ips, true);
    }
}