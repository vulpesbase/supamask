<?php

require __DIR__ . '/../../vendor/autoload.php';

use Supamask\Supamask;

Supamask::boot([
    'ip_blocking' => [
        'antired' => true,

        'rules' => [
            '127.0.0.1',
        ],
    ],
]);

echo "Hello World!";
