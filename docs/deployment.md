# Deployment and testing

## Web server and sessions

- Forward all protected routes, challenge paths, and disposable slugs to the front controller.
- Use HTTPS and secure, HttpOnly session cookies.
- Keep PHP session storage reliable across requests; challenges, verification, and default disposable registries are session-backed.
- Configure client IP handling at the proxy/web-server layer so `REMOTE_ADDR` is trustworthy.
- Keep logs and file-backed IP caches outside the public document root and grant PHP only the required write permission.

## Configuration defaults

New optional intelligence and observation features are non-disruptive by default:

| Setting | Default |
| --- | --- |
| VPN blocking | disabled |
| ISP/ASN detection | disabled |
| Referrer blocking | disabled |
| Missing referrer blocking | disabled |
| Request logging | disabled |
| Challenge middleware | disabled |
| Proof-of-work | enabled when a challenge is created |

## Testing

Run the complete suite:

```bash
composer test
```

The suite includes unit and integration coverage for IP/CIDR matching, decision precedence, challenge and disposable lifecycles, proof-of-work, replay rejection, query preservation, referrer boundaries, intelligence caching/failure handling, logging, and polymorphic presentation.

For application integration, verify these invariants in your own environment:

1. DENY does not execute origin code.
2. CHALLENGE does not execute origin code before successful verification.
3. Successful verification reaches the exact intended local destination.
4. A blocked request to an active disposable URL is still DENY.

