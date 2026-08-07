<?php

namespace Supamask\Contracts;

interface BlocklistInterface
{
    public function contains(string $ip): bool;
}