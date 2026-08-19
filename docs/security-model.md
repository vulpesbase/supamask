# Security model

## Decisions and precedence

Supamask has three decisions: `ALLOW`, `CHALLENGE`, and `DENY`. The non-negotiable order is:

```text
DENY > CHALLENGE > ALLOW
```

For every request, exact IP/CIDR and AntiRed rules, bot signatures, enabled IP-intelligence policies, and enabled referrer policies are evaluated first. A `DENY` returns immediately before challenge routing, challenge generation, proof-of-work, disposable-entry handling, or origin execution.

Only an allowed hard-security evaluation reaches disposable lifecycle handling and route challenge policy.

## Hard-deny controls

- `ip_blocking`: exact IPv4/IPv6 rules and CIDR ranges.
- `bot_blocking`: case-insensitive User-Agent signatures.
- `block_vpn`: confirmed provider VPN result.
- `detect_isp` plus `isp_exclusions`: exact ASN or normalized organization match.
- `block_referrers`: normalized hostname/subdomain match.

User-Agent is used for bot matching only. It is not used to infer VPN, ASN, ISP, or network ownership.

## Redirect safety

Only a configured DENY response can redirect. Its destination must be an absolute HTTP(S) URL without credentials, fragments, or control characters. Request query parameters never configure redirects.

## Trust boundaries

- Supamask reads client IP from `REMOTE_ADDR`; configure trusted reverse-proxy address handling before PHP.
- Referrer is client-controlled metadata and must not be treated as authentication.
- Unknown provider intelligence is allowed by default. Use `ip_intelligence.fail_closed` only when an outage should deny traffic.
- Place logs and caches outside the public web root.

