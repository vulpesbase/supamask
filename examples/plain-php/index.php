<?php

require __DIR__ . '/../../vendor/autoload.php';

use Supamask\Supamask;

Supamask::boot([
    'ip_blocking' => [
        'ips' => [
            '127.0.0.1',
            '::1',
        ],
    ],
]);

echo "Hello World!";
