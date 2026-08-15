<?php

namespace Supamask\Routing;

final class RouteMatcher
{
    public function pathMatches(string $path, array $patterns): bool
    {
        $path = $this->normalizePath($path);

        foreach ($patterns as $pattern) {
            if (!is_string($pattern) || $pattern === '') {
                continue;
            }

            if (!$this->isRegex($pattern)) {
                $pattern = $this->normalizePath($pattern);
            }

            if ($pattern === $path) {
                return true;
            }

            if (str_ends_with($pattern, '/*')) {
                $prefix = rtrim(substr($pattern, 0, -1), '/');

                if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                    return true;
                }

                continue;
            }

            if ($this->isRegex($pattern) && preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    public function hostMatches(string $host, array $patterns): bool
    {
        $host = $this->normalizeHost($host);

        foreach ($patterns as $pattern) {
            if (!is_string($pattern) || $pattern === '') {
                continue;
            }

            $pattern = $this->normalizeHost($pattern);

            if ($pattern === $host) {
                return true;
            }

            if (str_starts_with($pattern, '*.')) {
                $suffix = substr($pattern, 1);

                if (str_ends_with($host, $suffix) && $host !== ltrim($suffix, '.')) {
                    return true;
                }

                continue;
            }

            if ($this->isRegex($pattern) && preg_match($pattern, $host) === 1) {
                return true;
            }
        }

        return false;
    }

    public function normalizePath(string $path): string
    {
        $path = explode('?', $path)[0];

        if (!is_string($path) || $path === '') {
            return '/';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        $path = preg_replace('#/{2,}#', '/', $path) ?? $path;

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    public function normalizeHost(string $host): string
    {
        $host = trim(strtolower($host));

        if ($host === '') {
            return '';
        }

        // HTTP_HOST may contain a port. IPv6 literals remain bracketed.
        if (str_starts_with($host, '[')) {
            $end = strpos($host, ']');

            if ($end !== false) {
                return substr($host, 0, $end + 1);
            }
        }

        return strtolower((string) (explode(':', $host, 2)[0]));
    }

    private function isRegex(string $pattern): bool
    {
        return strlen($pattern) >= 2
            && $pattern[0] === '~'
            && strrpos($pattern, '~') === strlen($pattern) - 1;
    }
}
