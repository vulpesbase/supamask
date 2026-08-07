<?php

namespace Supamask\Http;

class Request
{
    private array $server;

    public function __construct()
    {
        $this->server = $_SERVER;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '';
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

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return $this->server[$key] ?? null;
    }
}