# Getting started

## Installation

```bash
composer require devgonerogue/supamask
```

Use the Composer autoloader and call Supamask at the top of the PHP entry point:

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Supamask\Supamask;

Supamask::boot([
    'challenge' => [
        'middleware' => ['enabled' => true],
        'protection' => ['enabled' => true, 'paths' => ['/account']],
    ],
    'ip_blocking' => [
        'enabled' => true,
        'antired' => false,
        'rules' => ['203.0.113.10'],
    ],
]);

// Protected origin application.
echo 'Application reached';
```

`Supamask::boot()` uses the current PHP request. On `DENY` or `CHALLENGE`, it sends the response and exits; do not add application work before it.

## Protecting routes

Route protection is opt-in. `paths`, `hosts`, and exclusions use the route matcher’s normalized host/path semantics.

```php
'challenge' => [
    'middleware' => ['enabled' => true],
    'protection' => [
        'enabled' => true,
        'hosts' => ['app.example.test', '*.customer.example.test'],
        'paths' => ['/account', '/checkout/*'],
        'exclude_paths' => ['/health'],
    ],
],
'routing' => ['root' => ['behavior' => 'allow']],
```

Exclusions override inclusions. For `/`, `routing.root.behavior` explicitly chooses `allow` or `challenge`. See [configuration](configuration.md) for full precedence.

