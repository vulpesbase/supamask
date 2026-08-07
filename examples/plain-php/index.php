<?php

require __DIR__ . '/../../vendor/autoload.php';

use Supamask\Supamask;

Supamask::boot([
    'ip_blocking' => [
    'antired' => true,

    'rules' => [
        '127.0.0.1',
        '192.168.1.0/24',
        '2001:db8::/32',
    ],
],
]);

echo "Hello World!";
