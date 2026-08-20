<?php

namespace Supamask\Http;

class Request
{
    private array $server;
    private ?string $clientIp = null;

    public function __construct()
    {
        $this->server = $_SERVER;
    }

    public function ip(): string
    {
        return $this->clientIp ?? $this->remoteAddress();
    }

    /**
     * The address of the peer that connected directly to PHP. This must not
     * be used for security decisions once client-IP resolution has run.
     */
    public function remoteAddress(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '';
    }

    /** @internal Set once by the request context's client-IP resolver. */
    public function setClientIp(string $ip): void
    {
        $this->clientIp = $ip;
    }

    public function method(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function host(): string
    {
        return $this->server['HTTP_HOST'] ?? '';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function referrer(): ?string
    {
        return $this->server['HTTP_REFERER'] ?? null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$key] ?? null;
    }

    public function input(string $name, mixed $default = null): mixed
    {
        return $_POST[$name] ?? $default;
    }
    public function scheme(): string
    {
        if (($this->server['HTTPS'] ?? '') !== '' && strtolower((string) $this->server['HTTPS']) !== 'off') {
            return 'https';
        }

        return strtolower((string) ($this->server['REQUEST_SCHEME'] ?? 'http'));
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        $headers = [];

        foreach ($this->server as $key => $value) {
            if (!is_string($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

}
