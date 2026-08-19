<?php

namespace Supamask\Security\IpIntelligence;

interface IpIntelligenceProviderInterface
{
    public function lookup(string $ip): IpIntelligenceResult;
}
