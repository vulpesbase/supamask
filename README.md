# Supamask

Supamask is a lightweight PHP security gate that protects an application before its origin code runs. It combines route challenges, disposable URLs, proof-of-work, IP and CIDR rules, bot detection, referrer controls, and optional IP intelligence behind one decision model:

```text
DENY > CHALLENGE > ALLOW
```

Hard-denied traffic is stopped before challenge creation, proof-of-work, disposable-entry handling, or application execution.

## Features

- Route challenges with session verification
- Disposable, expiring, single-use entry URLs and replay protection
- Query-string preservation through the challenge flow
- Server-verified proof-of-work
- Polymorphic challenge presentation and honeypot markup
- Exact IP, IPv4/IPv6 CIDR, AntiRed, and User-Agent bot blocking
- Optional VPN, ASN, and ISP exclusions through IPinfo
- Safe hostname-based referrer blocking
- Configurable DENY responses and optional JSON-lines request logging

## Requirements

- PHP 8.1+
- Composer

## Install

```bash
composer require devgonerogue/supamask
```

Configure your web server to send protected, disposable, and challenge routes to the PHP front controller.

## Quick start

Place Supamask at the start of your front controller, before application output or protected work:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Supamask\Supamask;

Supamask::boot([
    'challenge' => [
        'middleware' => ['enabled' => true],
        'protection' => [
            'enabled' => true,
            'paths' => ['/account', '/checkout'],
        ],
        'proof_of_work' => [
            'enabled' => true,
            'difficulty' => 16,
        ],
    ],
    'ip_blocking' => [
        'enabled' => true,
        'antired' => false,
        'rules' => ['203.0.113.10', '198.51.100.0/24'],
    ],
    'bot_blocking' => [
        'enabled' => true,
        'antired' => false,
        'signatures' => ['ExampleBadBot'],
    ],
]);

// Runs only when Supamask allows the request.
$user = $_GET['user'] ?? 'World';
echo 'Hello, ' . htmlspecialchars($user, ENT_QUOTES, 'UTF-8') . '!';
```

`Supamask::boot()` sends a Supamask response and terminates execution for DENY or CHALLENGE decisions. Origin code therefore runs only on ALLOW.

## Security decision order

```text
IP / CIDR / AntiRed / bot / IP intelligence / referrer
                         │
                ┌────────┴────────┐
                │                 │
              DENY             not denied
                │                 │
                ▼                 ▼
             response     disposable entry / challenge policy
                                      │
                             CHALLENGE or ALLOW
```

A blocked IP, bot, VPN, excluded ASN/ISP, or blocked referrer can never receive a challenge instead of a denial, including for an active disposable URL.

## Configuration

Optional controls are independently configurable:

```php
Supamask::boot([
    'ip_blocking' => [
        'enabled' => true,
        'antired' => true,
        'rules' => ['203.0.113.10', '2001:db8:1234::/48'],
    ],
    'bot_blocking' => [
        'enabled' => true,
        'antired' => true,
        'signatures' => ['ExampleBot'],
    ],
    'challenge' => [
        'enabled' => true,
        'middleware' => ['enabled' => true],
        'protection' => [
            'enabled' => true,
            'paths' => ['/members/*'],
            'exclude_paths' => ['/health'],
        ],
        'ttl' => 300,
        'verification_ttl' => 1800,
        'proof_of_work' => [
            'enabled' => true,
            'difficulty' => 16,
            'ttl' => 300,
        ],
    ],
    'routing' => [
        'root' => ['behavior' => 'allow'], // allow or challenge
    ],
    'disposable' => [
        'enabled' => true,
        'single_use' => true,
        'ttl' => 900,
        'slug_length' => 12,
    ],
]);
```

### DENY response

The default DENY response is `403`. To redirect denied traffic, supply a trusted absolute HTTP(S) destination:

```php
'responses' => [
    'deny' => [
        'action' => 'redirect',
        'redirect' => 'https://example.com/access-denied',
        'redirect_status' => 302,
    ],
],
```

Supamask never reads this destination from request parameters. Relative, protocol-relative, malformed, and non-HTTP(S) destinations are rejected.

### Referrer controls

Referrer matching is hostname-based, case-insensitive, and includes subdomains:

```php
'block_referrers' => true,
'referrer_blocklist' => ['badsite.com'],
'block_missing_referrer' => false,
```

`badsite.com`, `www.badsite.com`, and `sub.badsite.com` match. `badsite.com.evil.example` does not. Missing referrers are allowed unless `block_missing_referrer` is enabled.

### VPN, ASN, and ISP controls

IP intelligence is disabled by default. Enable it with provider credentials:

```php
'block_vpn' => true,
'detect_isp' => true,
'isp_exclusions' => ['AS14061', 'DigitalOcean'],
'ip_intelligence' => [
    'provider' => 'ipinfo',
    'token' => getenv('SUPAMASK_IPINFO_TOKEN'),
    'timeout' => 2,
    'cache_ttl' => 3600,
    'cache_directory' => __DIR__ . '/../storage/ip-intelligence',
],
```

When VPN/ASN/ISP intelligence is disabled, no provider credentials are required and no external lookup is made. When enabled without an IPinfo token, Supamask throws an explicit `RuntimeException` during setup; it does not silently disable the enabled security feature.

Provider failures are unknown intelligence—not confirmed malicious traffic—unless you explicitly set `ip_intelligence.fail_closed` to `true`.

## Disposable entries and query strings

Disposable entries accept local destinations only, support expiry and optional single-use consumption, and return a successfully verified visitor to the exact stored application destination, including its query string.

Application parameters remain application data. For example, `?redirect=https://evil.example` is preserved for the application; it is never treated as Supamask redirect configuration.

## Request logging

Logging is disabled by default. Enable it explicitly:

```php
'logging' => [
    'enabled' => true,
    'directory' => __DIR__ . '/../storage/logs',
    'include_query_string' => false,
],
```

Each line in `bouncer.log` is JSON containing timestamp, IP, method, URI, decision, and reason. Query strings are excluded by default because they can contain sensitive data. Logging is observational: a logging failure never changes a security decision.

Use a directory outside the public web root in production. Supamask does not expose logs through its routing.

## Challenge presentation

Fresh challenges vary presentation identifiers, copy, and layout while preserving the same server-side verification contract. When enabled, proof-of-work is solved in the browser and verified by the server before the challenge is consumed.

## Testing

```bash
composer test
```

The suite covers decision precedence, disposable lifecycle, replay prevention, proof-of-work, query preservation, IP intelligence, referrer boundaries, logging, and presentation behavior.

## Production notes

- Keep Supamask at the start of the front controller.
- Supamask uses `REMOTE_ADDR`; configure trusted proxy handling at your web-server layer.
- Use HTTPS and secure session-cookie settings.
- Keep logs and IP-intelligence caches outside the public web root.

## License

MIT.
