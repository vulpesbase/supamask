<?php

namespace Supamask\Http;

final class RequestContextFactory
{
    public function fromRequest(Request $request): RequestContext
    {
        $uri = $request->uri();
        $parts = parse_url($uri);

        $path = isset($parts['path']) && is_string($parts['path'])
            ? $parts['path']
            : '/';

        if ($path === '' || $path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        $path = preg_replace('#/{2,}#', '/', $path) ?? $path;

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $query = isset($parts['query']) && is_string($parts['query'])
            ? $parts['query']
            : '';

        $host = strtolower(trim($request->host()));
        $port = null;

        if (str_starts_with($host, '[')) {
            $end = strpos($host, ']');
            if ($end !== false) {
                $hostOnly = substr($host, 0, $end + 1);
                $rest = substr($host, $end + 1);
                if (str_starts_with($rest, ':') && ctype_digit(substr($rest, 1))) {
                    $port = (int) substr($rest, 1);
                }
                $host = $hostOnly;
            }
        } elseif (str_contains($host, ':')) {
            [$hostOnly, $portString] = explode(':', $host, 2);
            if (ctype_digit($portString)) {
                $port = (int) $portString;
                $host = $hostOnly;
            }
        }

        $scheme = strtolower($request->scheme());
        if ($scheme === '') {
            $scheme = 'http';
        }

        $headers = [];
        foreach ($request->headers() as $name => $value) {
            $headers[strtolower($name)] = $value;
        }

        return new RequestContext(
            strtoupper($request->method()),
            $scheme,
            $host,
            $port,
            $path,
            $query,
            $request->ip(),
            $request->userAgent(),
            $request->referrer(),
            $headers,
        );
    }
}
