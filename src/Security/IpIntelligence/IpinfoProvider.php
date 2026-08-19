<?php

namespace Supamask\Security\IpIntelligence;

use RuntimeException;

final class IpinfoProvider implements IpIntelligenceProviderInterface
{
    public function __construct(
        private string $token,
        private int $timeout = 2,
        private string $endpoint = 'https://api.ipinfo.io/lookup/',
    ) {
        if ($this->token === '') {
            throw new RuntimeException('IPinfo token is required when IP intelligence is enabled.');
        }
    }

    public function lookup(string $ip): IpIntelligenceResult
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new IpIntelligenceProviderException('Invalid IP address.');
        }

        $url = rtrim($this->endpoint, '/') . '/' . rawurlencode($ip) . '?token=' . rawurlencode($this->token);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => max(1, $this->timeout),
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: Supamask-IP-Intelligence\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if (!is_string($body)) {
            throw new IpIntelligenceProviderException('IP intelligence provider request failed.');
        }

        $status = $this->responseStatus($http_response_header ?? []);
        if ($status < 200 || $status >= 300) {
            throw new IpIntelligenceProviderException('IP intelligence provider returned HTTP ' . $status . '.');
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new IpIntelligenceProviderException('IP intelligence provider returned invalid JSON.');
        }

        $asn = $data['as']['asn'] ?? $data['asn'] ?? null;
        $organization = $data['as']['name'] ?? $data['as_name'] ?? null;
        $anonymous = is_array($data['anonymous'] ?? null) ? $data['anonymous'] : [];

        return new IpIntelligenceResult(
            (string) ($data['ip'] ?? $ip),
            is_string($asn) && $asn !== '' ? strtoupper($asn) : null,
            is_string($organization) && $organization !== '' ? $organization : null,
            (bool) ($anonymous['is_vpn'] ?? $data['privacy']['vpn'] ?? false),
            (bool) ($anonymous['is_proxy'] ?? $data['privacy']['proxy'] ?? false),
            (bool) ($anonymous['is_tor'] ?? $data['privacy']['tor'] ?? false),
            (bool) ($anonymous['is_relay'] ?? $data['privacy']['relay'] ?? false),
        );
    }

    /** @param array<int,string> $headers */
    private function responseStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
                return (int) $matches[1];
            }
        }

        return 0;
    }
}
