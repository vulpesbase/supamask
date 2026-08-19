<?php

namespace Supamask\Security\IpIntelligence;

final class IpIntelligenceService implements IpIntelligenceProviderInterface
{
    public function __construct(
        private IpIntelligenceProviderInterface $provider,
        private bool $skipPrivate = true,
    ) {
    }

    public function lookup(string $ip): IpIntelligenceResult
    {
        if ($this->skipPrivate && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return new IpIntelligenceResult($ip);
        }

        return $this->provider->lookup($ip);
    }
}
