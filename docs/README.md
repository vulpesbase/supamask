# Supamask developer documentation

| Guide | Purpose |
| --- | --- |
| [Getting started](getting-started.md) | Install Supamask and protect a front controller. |
| [Configuration reference](configuration.md) | Default settings and all public configuration keys. |
| [Security model](security-model.md) | Decision precedence, denial behavior, and trust boundaries. |
| [Challenge and disposable URLs](challenge-lifecycle.md) | Challenge, PoW, query, and disposable-entry lifecycles. |
| [IP intelligence and logging](operations.md) | Provider setup, caching, failures, and request logs. |
| [Deployment and testing](deployment.md) | Web-server, proxy, session, and validation guidance. |

Supamask is a front-controller security gate. Call `Supamask::boot()` before any protected application work. It either returns control to the origin (`ALLOW`) or sends a terminal response (`CHALLENGE` or `DENY`).

