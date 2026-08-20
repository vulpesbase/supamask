# Changelog

All notable changes to Supamask are documented in this file.

This project follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and uses [Semantic Versioning](https://semver.org/).

## [0.2.3] - 2026-08-20

### Fixed

- Normalized IPv4-mapped IPv6 forwarded addresses so existing IPv4 rules match proxied clients.

## [0.2.2] - 2026-08-20

### Added

- Trusted-proxy client IP resolution for `Forwarded`, `X-Forwarded-For`, and `X-Real-IP`, including IPv4, IPv6, and CIDR trust rules.
- `ipapi.is` IP-intelligence provider with normalized ASN, organization, VPN, proxy, Tor, and relay information.

### Changed

- All IP-based middleware and request logging now consume the centrally resolved client IP.

## [0.2.1] - 2026-08-19

### Changed

- Resolved composer dependency and distribution involving development-only files.

## [0.2.0] - 2026-08-19

### Added

- Disposable entry URLs with configurable TTL, slug length, local-destination validation, and optional single-use enforcement.
- Session-backed challenge lifecycle handling, including expiry, consumption, replay rejection, and post-verification return to the original destination.
- Query-string preservation across challenge and disposable-entry flows.
- Polymorphic challenge presentation with randomized identifiers, rotating copy, stable per-challenge variants, preparation and success states, and honeypot markup.
- Server-enforced proof-of-work challenges with configurable difficulty and expiry.
- Configurable DENY redirects restricted to trusted absolute HTTP(S) destinations.
- Referrer blocking with normalized exact-host and subdomain matching, plus an optional missing-referrer policy.
- Optional IP intelligence abstraction with IPinfo support, caching, VPN detection, ASN matching, ISP matching, and safe provider-failure handling.
- Request logging with optional query-string inclusion and non-fatal write failures.

### Changed

- Clarified the security decision model as `DENY > CHALLENGE > ALLOW`.
- Moved hard security evaluation ahead of challenge and disposable-entry routing.
- Kept IP intelligence opt-in; enabling VPN/ASN/ISP intelligence without required provider credentials remains an explicit configuration error.
- Updated challenge-presentation tests to validate the current polymorphic markup and client-side success redirect behavior.
- Isolated affected integration tests from prior `$_SERVER` and session state.

### Security

- Enforced terminal hard-deny precedence before challenge-route and disposable-entry handling.
- Ensured a denied request cannot create a challenge, generate proof-of-work, mutate disposable-entry state, or reach the application origin.
- Added regression coverage for active disposable entries combined with IP, CIDR, bot, VPN, ASN, ISP, and referrer denials.

### Deprecated

- Nothing.

### Removed

- Nothing.

### Fixed

- Blocked traffic using an active disposable URL now receives DENY rather than a challenge redirect.
- Challenge, route-policy, and presentation test expectations now align with the current lifecycle and presentation architecture.

## [0.1.0] - 2026-08-07

### Added

- Core `ALLOW`, `DENY`, and `CHALLENGE` decisions.
- Exact IPv4/IPv6 and CIDR IP blocking.
- Custom and AntiRed IP rules.
- User-Agent bot blocking with custom and AntiRed signatures.
- Configurable IP and bot middleware controls.
- Configurable security response status, body, and headers.
- Plain PHP front-controller integration through `Supamask::boot()`.
- PHPUnit unit and integration coverage.
