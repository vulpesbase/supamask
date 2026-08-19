# Configuration reference

Pass an array to `Supamask::boot()`. Supamask recursively merges supplied values over its defaults.

## Top-level settings

| Key | Default | Description |
| --- | --- | --- |
| `ip_blocking.enabled` | `true` | Enables exact IP/CIDR and AntiRed IP checks. |
| `ip_blocking.antired` | `true` | Loads the bundled AntiRed IP dataset. |
| `ip_blocking.rules` | `[]` | Exact IPs or CIDR rules. |
| `bot_blocking.enabled` | `true` | Enables User-Agent bot checks. |
| `bot_blocking.antired` | `true` | Loads bundled AntiRed bot signatures. |
| `bot_blocking.signatures` | `[]` | Additional case-insensitive signatures. |
| `block_vpn` | `false` | Denies a provider-confirmed VPN. |
| `detect_isp` | `false` | Enables ASN/organization exclusions. |
| `isp_exclusions` | `[]` | Exact `AS<number>` values or organization names. |
| `block_referrers` | `false` | Enables referrer hostname blocking. |
| `referrer_blocklist` | `[]` | Blocked hostnames; subdomains match. |
| `block_missing_referrer` | `false` | Denies a missing/empty referrer. |

## Challenge settings

| Key | Default | Description |
| --- | --- | --- |
| `challenge.enabled` | `true` | Enables real challenge creation; when false, a challenge decision yields a plain `403 Challenge` response. |
| `challenge.ttl` | `300` | Pending challenge lifetime in seconds. |
| `challenge.verification_ttl` | `1800` | Verified-session lifetime in seconds. |
| `challenge.middleware.enabled` | `false` | Turns route challenge enforcement on. |
| `challenge.protection.enabled` | `false` | Enables route policy matching. |
| `challenge.protection.hosts` | `[]` | Included hosts; empty means any host. |
| `challenge.protection.paths` | `[]` | Included paths; empty means any path. |
| `challenge.protection.exclude_hosts` | `[]` | Host exclusions, evaluated first. |
| `challenge.protection.exclude_paths` | `[]` | Path exclusions, evaluated first. |
| `challenge.proof_of_work.enabled` | `true` | Adds server-verified PoW to created challenges. |
| `challenge.proof_of_work.difficulty` | `16` | Required leading zero bits. |
| `challenge.proof_of_work.ttl` | `300` | PoW lifetime in seconds. |
| `routing.root.behavior` | `allow` | Explicit root behavior: `allow` or `challenge`. |

Path matching normalizes query strings, duplicate separators, and trailing slashes. `/*` and subtree patterns are supported; host matching supports `*.example.test`.

## Disposable and entry settings

| Key | Default | Description |
| --- | --- | --- |
| `disposable.enabled` | `false` | Enables disposable-entry classification. |
| `disposable.slug_length` | `12` | Positive even hex slug length. |
| `disposable.ttl` | `900` | Entry lifetime in seconds. |
| `disposable.single_use` | `true` | Consumes the entry after successful verification. |
| `entry.enabled` | `false` | Enables direct/referred/seeded classification policy. |
| `entry.referrers` | `[]` | Referrers classified as referred traffic. |
| `entry.policy` | see below | Per-classification decision. |

Default `entry.policy` values are `direct: allow`, `referred: allow`, `seeded: challenge`, and `unknown: allow`. Each accepts `allow`, `challenge`, or `deny`.

## Response, intelligence, and logging settings

| Key | Default | Description |
| --- | --- | --- |
| `responses.deny.action` | `block` | `block` or `redirect`. |
| `responses.deny.status` | `403` | Status for a blocking denial. |
| `responses.deny.body` | `Access denied` | Blocking denial body. |
| `responses.deny.headers` | `[]` | Additional denial headers. |
| `responses.deny.redirect` | `null` | Required trusted URL for redirects. |
| `responses.deny.redirect_status` | `302` | A 3xx redirect status. |
| `ip_intelligence.provider` | `ipinfo` | Supported provider identifier. |
| `ip_intelligence.token` | `''` | Provider token; may also come from `SUPAMASK_IPINFO_TOKEN`. |
| `ip_intelligence.timeout` | `2` | Provider HTTP timeout in seconds. |
| `ip_intelligence.cache_ttl` | `3600` | Cache lifetime in seconds. |
| `ip_intelligence.cache_directory` | `null` | Use file cache when set; otherwise in-memory cache. |
| `ip_intelligence.skip_private` | `true` | Skips provider lookup for private/reserved IPs. |
| `ip_intelligence.fail_closed` | `false` | Denies an unavailable provider when true. |
| `logging.enabled` | `false` | Enables the file logger. |
| `logging.directory` | `storage/logs` | Directory containing `bouncer.log`. |
| `logging.base_path` | `null` | Base directory for a relative log directory. |
| `logging.include_query_string` | `false` | Includes query strings in logged URIs. |

See [security model](security-model.md) for precedence and [operations](operations.md) for credential and failure behavior.
