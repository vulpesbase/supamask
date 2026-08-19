
## Request logging

Supamask request logging is disabled by default. Enable it explicitly when you want a `bouncer.log` audit trail:

```php
'logging' => [
    'enabled' => true,
    'directory' => __DIR__ . '/storage/logs',
    'include_query_string' => false,
],
```

Each request decision is appended as one JSON object per line and includes the timestamp, IP, method, path, decision, reason, User-Agent, Referer, and available IP-intelligence fields. Query strings are excluded by default because they can contain secrets or personal data; set `include_query_string` to `true` only when appropriate.

Relative directories are resolved against the configured `base_path`, then the web document root, then the entry script directory. Absolute paths are used as supplied. For production, prefer a directory outside the public web root and ensure the PHP process can write to it. Logging failures are intentionally non-fatal and never change the security decision.
