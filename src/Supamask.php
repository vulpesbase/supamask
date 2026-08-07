<?php

namespace Supamask;

use Supamask\Http\Request;
use Supamask\Core\Kernel;
use Supamask\Core\Config;

class Supamask
{
    public static function boot(array $config = []): void
    {
        $kernel = new Kernel(
            new Config($config)
        );

        $kernel->handle(
            new Request()
        );
    }
}