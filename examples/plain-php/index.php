<?php

require __DIR__ . '/../../vendor/autoload.php';

use Supamask\Supamask;

Supamask::boot([
    'ip_blocking' => [
        'antired' => true,
    ],
]);

echo "Hello World!";
