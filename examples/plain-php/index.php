<?php

require __DIR__ . '/../../vendor/autoload.php';

use Supamask\Supamask;

Supamask::boot([
    'ip_blocking' => [
        'antired' => true,

        'ips' => [
            '203.0.113.10',
        ],
    ],
]);

echo "Hello World!";
