# IP intelligence and logging

## IP intelligence

IP intelligence is off by default. It is used only when `block_vpn` or `detect_isp` is enabled.

```php
'block_vpn' => true,
'detect_isp' => true,
'isp_exclusions' => ['AS14061', 'DigitalOcean'],
'ip_intelligence' => [
    'provider' => 'ipinfo', // or 'ipapi.is'
    'token' => getenv('SUPAMASK_IPINFO_TOKEN'),
    'endpoint' => 'https://api.ipinfo.io/lookup/',
    'timeout' => 2,
    'cache_ttl' => 3600,
    'cache_directory' => __DIR__ . '/../storage/ip-intelligence',
    'skip_private' => true,
    'fail_closed' => false,
],
```

IPinfo requires credentials; ipapi.is supports its documented free anonymous
single-IP endpoint (and accepts an optional key via `token` or
`SUPAMASK_IPAPI_IS_KEY`). Enabling VPN/ASN/ISP intelligence without an IPinfo
token throws an explicit `RuntimeException` during construction; Supamask does
not silently disable an enabled feature. Lookups are cached by IP.
Private/reserved addresses are skipped by default. Timeout, network, HTTP,
invalid JSON, and unavailable-provider results are treated as unknown unless
`fail_closed` is enabled.

ASN exclusions match `AS<number>` exactly. Organization exclusions are normalized and matched exactly, not by unsafe substring.

## Request logging

```php
'logging' => [
    'enabled' => true,
    'directory' => __DIR__ . '/../storage/logs',
    'base_path' => null,
    'include_query_string' => false,
],
```

The file logger appends JSON lines to `bouncer.log`. Events include timestamp, IP, method, URI, decision, reason, User-Agent, referrer, and available intelligence data. Query strings are omitted unless explicitly enabled. Directory and write failures are swallowed so logging cannot change ALLOW, CHALLENGE, DENY, or redirect behavior.
