# Supamask v0.1.0

Supamask is a lightweight PHP-only middleware library for blocking unwanted IPs and bot-like user agents in plain PHP applications. It is designed for simple deployments that do not depend on Laravel, Symfony, or any other framework.

This release focuses on the v0.1.0 feature set: configurable IP blocking, configurable bot blocking, and configurable deny/challenge responses.

## Features

Supamask v0.1.0 currently provides:

- IP blocking
- Exact IPv4 matching
- Exact IPv6 matching
- IPv4 CIDR matching
- IPv6 CIDR matching
- Custom IP rules
- AntiRed IP rules
- Bot blocking
- User-Agent matching
- Case-insensitive bot matching
- AntiRed bot signatures
- Custom bot signatures
- Configurable enable/disable controls
- `ALLOW`, `DENY`, and `CHALLENGE` decisions
- Configurable HTTP responses

## Requirements

- PHP 8.1 or newer
- Composer

## Installation

Install Supamask with Composer:

```bash
composer require devgonerogue/supamask
```

## Basic Usage

The public bootstrap flow is:

```php
<?php

require 'vendor/autoload.php';

use Supamask\Supamask;

Supamask::boot([
    'ip_blocking' => [
        'enabled' => true,
        'antired' => true,
        'rules' => [],
    ],
    'bot_blocking' => [
        'enabled' => true,
        'antired' => true,
        'signatures' => [],
    ],
    'responses' => [
        'deny' => [
            'status' => 403,
            'body' => 'Access denied',
            'headers' => [],
        ],
        'challenge' => [
            'status' => 403,
            'body' => 'Challenge',
            'headers' => [],
        ],
    ],
]);

// Application code continues here when no deny/challenge response is produced.
```

If you need to inspect the response object directly instead of sending it immediately, you can use the underlying kernel API:

```php
<?php

use Supamask\Core\Config;
use Supamask\Core\Kernel;
use Supamask\Http\Request;

$kernel = new Kernel(new Config([
    'ip_blocking' => ['enabled' => true],
    'bot_blocking' => ['enabled' => true],
]));

$response = $kernel->handle(new Request());
```

## Configuration

Supamask v0.1.0 accepts a configuration array passed to `Supamask::boot()`.

```php
Supamask::boot([
    'ip_blocking' => [
        'enabled' => true,
        'antired' => true,
        'rules' => [],
    ],

    'bot_blocking' => [
        'enabled' => true,
        'antired' => true,
        'signatures' => [],
    ],

    'responses' => [
        'deny' => [
            'status' => 403,
            'body' => 'Access denied',
            'headers' => [],
        ],
        'challenge' => [
            'status' => 403,
            'body' => 'Challenge',
            'headers' => [],
        ],
    ],
]);
```

### Configuration Options

- `ip_blocking.enabled`: Enables or disables IP blocking.
- `ip_blocking.antired`: Enables the built-in AntiRed IP rules.
- `ip_blocking.rules`: Custom IP rules. Supports exact IPv4/IPv6 literals and IPv4/IPv6 CIDR blocks.
- `bot_blocking.enabled`: Enables or disables bot blocking.
- `bot_blocking.antired`: Enables the built-in AntiRed bot signatures.
- `bot_blocking.signatures`: Custom bot signatures to match against the request User-Agent.
- `responses.deny`: The response returned when a request is denied.
- `responses.challenge`: The response returned when a request is challenged.

Each response configuration supports:

- `status`: HTTP status code
- `body`: Response body
- `headers`: Response headers as an associative array

> For backward compatibility, if `bot_blocking.antired` is omitted, Supamask falls back to `ip_blocking.antired` when evaluating bot AntiRed behavior.

## IP Blocking Examples

Exact IP:

```php
Supamask::boot([
    'ip_blocking' => [
        'enabled' => true,
        'antired' => true,
        'rules' => ['198.51.100.1'],
    ],
]);
```

IPv4 CIDR:

```php
Supamask::boot([
    'ip_blocking' => [
        'enabled' => true,
        'antired' => true,
        'rules' => ['203.0.113.0/24'],
    ],
]);
```

IPv6 address or CIDR:

```php
Supamask::boot([
    'ip_blocking' => [
        'enabled' => true,
        'antired' => true,
        'rules' => ['2001:db8:1234::/48'],
    ],
]);
```

## Bot Blocking Examples

Custom bot signatures are matched against the request User-Agent using case-insensitive substring matching:

```php
Supamask::boot([
    'bot_blocking' => [
        'enabled' => true,
        'antired' => true,
        'signatures' => ['CustomBadBot'],
    ],
]);
```

## Response Configuration

Deny and challenge responses are configurable through the `responses` section:

```php
Supamask::boot([
    'responses' => [
        'deny' => [
            'status' => 403,
            'body' => 'Access denied',
            'headers' => ['X-Reason' => 'Blocked'],
        ],
        'challenge' => [
            'status' => 429,
            'body' => 'Please try again later',
            'headers' => ['Retry-After' => '60'],
        ],
    ],
]);
```

The current v0.1.0 release uses these configured responses for deny and challenge decisions. It does not introduce additional challenge workflows beyond returning the configured response.

## Request Flow

```text
Application
    │
    ▼
Supamask::boot()
    │
    ▼
Config
    │
    ▼
Kernel
    │
    ▼
Context
    │
    ▼
Pipeline
    │
    ├── IP blocking
    │
    └── Bot blocking
    │
    ▼
Decision
    │
    ├── ALLOW
    ├── DENY
    └── CHALLENGE
    │
    ▼
Response
```

## Testing

Run the test suite with:

```bash
composer test
```

## Version / Status

This README documents the current Supamask v0.1.0 implementation. It does not describe future features such as fingerprinting, advanced behavioral analysis, telemetry, or richer challenge systems.
