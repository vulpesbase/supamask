<?php

namespace Supamask\Http;

final class RequestContext
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private string $method,
        private string $scheme,
        private string $host,
        private ?int $port,
        private string $path,
        private string $query,
        private string $ip,
        private string $userAgent,
        private ?string $referrer,
        private array $headers,
    ) {
    }

    public function method(): string { return $this->method; }
    public function scheme(): string { return $this->scheme; }
    public function host(): string { return $this->host; }
    public function port(): ?int { return $this->port; }
    public function path(): string { return $this->path; }
    public function query(): string { return $this->query; }
    public function ip(): string { return $this->ip; }
    public function userAgent(): string { return $this->userAgent; }
    public function referrer(): ?string { return $this->referrer; }

    public function isSecure(): bool
    {
        return $this->scheme === 'https';
    }

    public function header(string $name): ?string
    {
        $key = strtolower($name);
        return $this->headers[$key] ?? null;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }
}
