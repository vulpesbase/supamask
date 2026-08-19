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
        'enabled' => true,
        'antired' => false,
        'rules' => [
            '127.0.0.2',
        ],
    ],

    'responses' => [
        'deny' => [
            'action' => 'block',
        ],
    ],

    'bot_blocking' => [
        'enabled' => false,
    ],

    // Optional IP intelligence (disabled by default).
    // 'block_vpn' => true,
    // 'detect_isp' => true,
    // 'isp_exclusions' => ['AS14061', 'DigitalOcean'],
    // 'ip_intelligence' => [
    //     'provider' => 'ipinfo',
    //     'token' => getenv('SUPAMASK_IPINFO_TOKEN'),
    // ],
]);

$user = $_GET['user'] ?? 'World';

echo 'Hello, ' . htmlspecialchars($user, ENT_QUOTES, 'UTF-8') . '!';
