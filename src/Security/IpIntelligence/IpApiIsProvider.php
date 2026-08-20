<?php

namespace Supamask\Security\IpIntelligence;

final class IpApiIsProvider implements IpIntelligenceProviderInterface
{
    public function __construct(
        private string $token = '',
        private int $timeout = 2,
        private string $endpoint = 'https://api.ipapi.is',
    ) {
    }

    public function lookup(string $ip): IpIntelligenceResult
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            throw new IpIntelligenceProviderException('Invalid IP address.');
        }

        $query = ['q' => $ip];
        if ($this->token !== '') {
            $query['key'] = $this->token;
        }
        $url = rtrim($this->endpoint, '/') . '/?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => max(1, $this->timeout),
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nUser-Agent: Supamask-IP-Intelligence\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
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

        return self::fromResponse($data, $ip);
    }

    /**
     * Converts an ipapi.is payload into Supamask's provider-neutral result.
     *
     * @param array<string,mixed> $data
     */
    public static function fromResponse(array $data, string $fallbackIp): IpIntelligenceResult
    {
        $asn = is_array($data['asn'] ?? null) ? $data['asn'] : [];
        $company = is_array($data['company'] ?? null) ? $data['company'] : [];

        return new IpIntelligenceResult(
            (string) ($data['ip'] ?? $fallbackIp),
            self::stringOrNull($asn['asn'] ?? $asn['number'] ?? null),
            self::stringOrNull($company['name'] ?? $asn['org'] ?? $asn['organization'] ?? $asn['name'] ?? null),
            (bool) ($data['is_vpn'] ?? false),
            (bool) ($data['is_proxy'] ?? false),
            (bool) ($data['is_tor'] ?? false),
            is_array($data['egress_service'] ?? null) || (bool) ($data['is_relay'] ?? false),
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

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
