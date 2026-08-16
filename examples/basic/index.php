<?php

require __DIR__ . '/../../vendor/autoload.php';

use Supamask\Supamask;

Supamask::boot([
    'challenge' => [
        'middleware' => [
            'enabled' => true,
        ],
        'protection' => [
            'enabled' => true,
        ],
    ],

    'routing' => [
        'root' => [
            'behavior' => 'challenge',
        ],
    ],

    'ip_blocking' => [
        'enabled' => false,
    ],

    'bot_blocking' => [
        'enabled' => false,
    ],
]);

echo 'Hello World';