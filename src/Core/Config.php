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