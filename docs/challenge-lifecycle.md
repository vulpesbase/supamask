# Challenge and disposable-entry lifecycle

## Challenge flow

```text
protected request → challenge redirect → challenge page → proof-of-work → verify → success page → original local URI
```

Challenges have a random ID and verification token, are session-backed, expire after `challenge.ttl` (default 300 seconds), and are consumed after successful verification. A verified session lasts `challenge.verification_ttl` seconds (default 1800).

When proof-of-work is enabled, the browser receives a nonce and difficulty and submits `pow_counter`. The server validates it before consuming the challenge. Invalid or missing proof cannot reach the origin.

Successful verification renders a short client-side success state before navigating to the original URI. It is not a second challenge.

## Query preservation

The original local request URI is retained, including its query string. For example, `/index.php?user=Rogue%20One&source=test` returns to that application URI after verification. Parameters remain application data and are never interpreted as Supamask control-plane options.

## Disposable entries

Disposable entries are random lowercase-hex slugs that map to safe local destinations. Their configuration is under `disposable`:

```php
'disposable' => [
    'enabled' => true,
    'slug_length' => 12,
    'ttl' => 900,
    'single_use' => true,
],
```

An active entry creates a challenge for its stored destination. A successful verification consumes a single-use entry. Expired and consumed entries return lifecycle rejection (`410 Gone`) and cannot be replayed. External and protocol-relative destinations are rejected when an entry is created.

Entry classification is configured separately under `entry`. Its `direct`, `referred`, `seeded`, and `unknown` policies accept `allow`, `challenge`, or `deny`; hard security DENY always overrides them.

