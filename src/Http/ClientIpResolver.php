<?php

namespace Supamask\Http;

use Supamask\Security\IpMatcher;

/** Resolves client addresses without trusting headers from untrusted peers. */
final class ClientIpResolver
{
    /** @param array<string,mixed> $config */
    public function __construct(private array $config = [])
    {
    }

    public function resolve(Request $request): string
    {
        $remote = $this->normalizeIp(trim($request->remoteAddress()));
        if (!$this->enabled() || !$this->isIp($remote) || !$this->isTrusted($remote)) {
            return $remote;
        }

        $forwarded = $this->forwardedChain($request);
        if ($forwarded === []) {
            return $remote;
        }

        // X-Forwarded-For and Forwarded are ordered client -> closest proxy.
        // Walk back through the trusted proxy chain; the first non-trusted
        // address is the client at our configured trust boundary.
        $chain = [...$forwarded, $remote];
        for ($index = count($chain) - 1; $index >= 0; $index--) {
            $address = $chain[$index];
            if (!$this->isTrusted($address)) {
                return $address;
            }
        }

        // An all-trusted chain has no verifiable client address. Preserve the
        // direct peer instead of accepting an arbitrary left-most header value.
        return $remote;
    }

    private function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    private function isTrusted(string $ip): bool
    {
        foreach ((array) ($this->config['trusted'] ?? []) as $rule) {
            if (is_string($rule) && (new IpMatcher())->matches($ip, trim($rule))) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function forwardedChain(Request $request): array
    {
        $forwarded = $this->parseForwarded($request->header('Forwarded'));
        if ($forwarded !== []) {
            return $forwarded;
        }

        $xff = $this->parseCommaSeparated($request->header('X-Forwarded-For'));
        if ($xff !== []) {
            return $xff;
        }

        $realIp = trim((string) $request->header('X-Real-IP'));
        return $this->isIp($realIp) ? [$realIp] : [];
    }

    /** @return list<string> */
    private function parseForwarded(?string $header): array
    {
        if (!is_string($header) || trim($header) === '') {
            return [];
        }

        $addresses = [];
        foreach (explode(',', $header) as $element) {
            foreach (explode(';', $element) as $parameter) {
                [$name, $value] = array_pad(explode('=', trim($parameter), 2), 2, null);
                if (strtolower((string) $name) !== 'for' || $value === null) {
                    continue;
                }

                $value = trim($value, " \t\"");
                if (str_starts_with($value, '[') && preg_match('/^\[([^\]]+)\](?::\d+)?$/', $value, $match)) {
                    $value = $match[1];
                }
                $value = $this->normalizeIp($value);
                if ($this->isIp($value)) {
                    $addresses[] = $value;
                }
                break;
            }
        }

        return $addresses;
    }

    /** @return list<string> */
    private function parseCommaSeparated(?string $header): array
    {
        if (!is_string($header) || trim($header) === '') {
            return [];
        }

        $addresses = [];
        foreach (explode(',', $header) as $value) {
            $value = $this->normalizeIp(trim($value));
            if ($this->isIp($value)) {
                $addresses[] = $value;
            }
        }

        return $addresses;
    }

    private function isIp(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }

    /** Converts IPv4-mapped IPv6 notation (for example ::ffff:192.0.2.1). */
    private function normalizeIp(string $value): string
    {
        if (str_starts_with(strtolower($value), '::ffff:')) {
            $ipv4 = substr($value, 7);
            if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $ipv4;
            }
        }

        return $value;
    }
}
