<?php

namespace Supamask\Core;

class Config
{
    public function __construct(
        private array $config = []
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}