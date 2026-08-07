<?php

require __DIR__ . '/../../vendor/autoload.php';

use Supamask\Supamask;

Supamask::boot([
    'ip_blocking' => [
        'ips' => [
            '::1/128',
        ],
    ],
]);

echo "Hello World!";
