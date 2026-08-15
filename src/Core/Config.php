<?php

namespace Supamask\Core;

class Config
{
    private array $userConfig = [];

    private array $defaults = [
        'ip_blocking' => [
            'enabled' => true,
            'antired' => true,
            'rules' => [],
        ],
        'bot_blocking' => [
            'enabled' => true,
            'antired' => true,
            'signatures' => [],
        ],
        'challenge' => [
            'enabled' => true,
            'ttl' => 300,
            'verification_ttl' => 1800,
            'path' => '/_supamask/challenge/',
            'middleware' => [
                'enabled' => false,
            ],
            'protection' => [
                'enabled' => false,
                'hosts' => [],
                'paths' => [],
                'exclude_hosts' => [],
                'exclude_paths' => [],
            ],
            'presentation' => [
                'title' => 'Security verification',
                'heading' => 'Security verification',
                'message' => 'Please confirm to continue to the requested page.',
                'button' => 'Continue',
            ],
        ],
        'routing' => [
            // Explicit root-domain behavior.
            // 'allow'     — GET / passes through without challenge.
            // 'challenge' — GET / is subjected to the challenge policy.
            'root' => [
                'behavior' => 'allow',
            ],
        ],
        'entry' => [
            // Entry classification controls how direct vs. referred vs.
            // seeded traffic is handled.
            //
            // SECURITY: Referer-based classification is advisory only.
            // The Referer header is user-controlled and must not be used
            // as a sole access-control mechanism.
            'enabled' => false,

            // Patterns for referrers that classify as REFERRED (trusted).
            // Accepts:
            //   - Exact URL prefix: 'https://trusted.example/path'
            //   - Wildcard subdomain: '*.trusted.example'
            'referrers' => [],

            // Decision per classification. Values: 'allow', 'challenge', 'deny'.
            'policy' => [
                'direct'   => 'allow',
                'referred' => 'allow',
                'seeded'   => 'challenge',
                'unknown'  => 'allow',
            ],
        ],
        'disposable' => [
            // Disposable entry paths: short random slugs at the root level,
            // e.g. /82f6cd2d2843.
            'enabled'    => false,
            'slug_length' => 12,   // Must be a positive even integer.
            'ttl'        => 900,   // Seconds until expiry (15 minutes default).
            'single_use' => true,  // Whether each slug may be used exactly once.
        ],
        'responses' => [
            'deny' => [
                'status' => 403,
                'body' => 'Access denied',
                'headers' => [],
            ],
            'challenge' => [
                'status' => 403,
                'body' => 'Challenge',
                'headers' => [],
            ],
        ],
    ];

    public function __construct(
        private array $config = []
    ) {
        $this->userConfig = $config;
        $this->config = $this->merge($this->defaults, $this->config);
    }

    public function has(string $key): bool
    {
        $value = $this->userConfig;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->config;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function merge(array $defaults, array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_array($value) && array_key_exists($key, $defaults) && is_array($defaults[$key])) {
                $defaults[$key] = $this->merge($defaults[$key], $value);
                continue;
            }

            $defaults[$key] = $value;
        }

        return $defaults;
    }
}