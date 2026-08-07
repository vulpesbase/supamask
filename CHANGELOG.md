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
