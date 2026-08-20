<?php

namespace Supamask\Security\IpIntelligence;

use InvalidArgumentException;

final class IpIntelligenceProviderFactory
{
    /** @param array<string,mixed> $config */
    public static function create(array $config): IpIntelligenceProviderInterface
    {
        $provider = strtolower((string) ($config['provider'] ?? 'ipinfo'));

        return match ($provider) {
            'ipinfo' => new IpinfoProvider(
                (string) ($config['token'] ?? ''),
                (int) ($config['timeout'] ?? 2),
                (string) ($config['endpoint'] ?? 'https://api.ipinfo.io/lookup/'),
            ),
            'ipapi.is', 'ipapi_is', 'ipapiis' => new IpApiIsProvider(
                (string) ($config['token'] ?? ''),
                (int) ($config['timeout'] ?? 2),
                (string) ($config['endpoint'] ?? 'https://api.ipapi.is'),
            ),
            default => throw new InvalidArgumentException('Unsupported IP intelligence provider: ' . $provider),
        };
    }
}
