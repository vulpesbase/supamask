## [Unreleased]

### Added
- Added immutable `RequestContext` snapshots for normalized request metadata.
- Added `RequestContextFactory` for centralized request parsing and normalization.
- Added context-level accessors for method, scheme, host, port, path, query, IP, user agent, referrer, and headers.
- Added request-context unit and integration tests.

### Changed
- `ChallengeMiddleware` now creates one request context before evaluating route policy.
- `RoutePolicy` now consumes `RequestContext` instead of parsing the raw HTTP request.

## [Unreleased]

### Added
- Added end-to-end challenge routing integration coverage.
- Added integration coverage for protected, excluded, normalized, verified, and replayed requests.

### Verified
- Protected routes reach `Decision::CHALLENGE`.
- Excluded routes remain `ALLOW`.
- Successful challenge verification returns the original URI.
- Verified sessions allow protected routes.
- Consumed challenges cannot be replayed.
- Host/path normalization survives the middleware boundary.

## [Unreleased]

### Added
- Added explicit route-policy precedence documentation.
- Added a precedence test matrix covering disabled policies, host/path intersections, exclusions, and unrestricted dimensions.

### Changed
- Route-policy evaluation now has a documented deterministic order: disablement, exclusions, then host/path inclusion matching.

## [Unreleased]

### Added
- Added `RouteMatcher` for normalized host/path matching.
- Added wildcard host matching (`*.example.test`).
- Added robust subtree matching for `/app/*`.
- Added path normalization for query strings, trailing slashes, and duplicate separators.
- Added tests for host ports, case normalization, IPv6 literals, regex paths, and wildcard boundaries.

### Changed
- `RoutePolicy` now delegates matching and normalization to `RouteMatcher`.

## [Unreleased]

### Added
- Added deterministic `RoutePolicy` host/path matching for challenge protection.
- Added root, wildcard subpath, host, and exclusion policy tests.
- Added `Request::referrer()` for request metadata access.

### Changed
- `ChallengeMiddleware` now evaluates an explicit route policy before requiring verification.
- Added configurable `challenge.protection` settings.

## [Unreleased]

### Added
- Added `ChallengePresentationInterface` for pluggable challenge rendering.
- Added `DefaultChallengePresentation` as the built-in presentation.
- Added a custom presentation example.
- Added presentation unit tests.

### Changed
- Removed HTML/template responsibilities from `ChallengeHandler`.
- `ChallengeHandler` now supplies challenge context to the configured presentation.
- `Kernel` falls back to the default presentation when no custom implementation is configured.

## [Unreleased]

### Changed
- Extracted challenge HTTP routing, rendering, and verification handling from `Kernel` into `ChallengeHandler`.
- Kept `Kernel` focused on request orchestration and middleware decisions.
- Added focused challenge handler tests for route recognition, malformed IDs, challenge creation, and HTML escaping.


## Unreleased — v0.2

### Added
- Challenge request lifecycle integration in the kernel.
- Configurable challenge TTL and route path.
- Session-backed challenge persistence for multi-request verification.
- Challenge GET rendering and POST consumption with redirect to the original URI.
- Challenge integration tests.
# Changelog

All notable changes to Supamask are documented here.

## [Unreleased]

Release status has not been declared yet.

## [0.1.0]

Supamask v0.1.0 provides the initial security middleware and response architecture:

- IP blocking with exact IPv4 and IPv6 matching.
- IPv4 and IPv6 CIDR matching.
- Custom IP rules and built-in AntiRed IP rules.
- Bot blocking based on request User-Agent signatures.
- Case-insensitive matching for bot signatures.
- Built-in AntiRed bot signatures and custom bot signatures.
- Configurable enable/disable controls for IP and bot blocking.
- Kernel-owned middleware pipeline assembly.
- `ALLOW`, `DENY`, and `CHALLENGE` decisions.
- Configurable deny and challenge responses, including status, body, and headers.
- Plain PHP integration through `Supamask::boot()`.
- PHPUnit unit and integration test coverage.

This entry documents the v0.1.0 implementation and does not claim that the version has been officially released.
- Added cryptographically random challenge verification tokens and constant-time token validation.
- Added session-backed verification state with configurable verification lifetime and session ID rotation after successful verification.
- Added dedicated `ChallengeMiddleware` for opt-in session verification enforcement.
- Added configurable challenge presentation copy with HTML escaping.
- Added challenge response cache-control headers and method validation.
- Added expanded challenge security and middleware integration tests.
