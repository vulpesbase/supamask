<?php

namespace Supamask\Middleware;

use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Context;
use Supamask\Core\Decision;
use Supamask\Security\IpIntelligence\IpIntelligenceProviderException;
use Supamask\Security\IpIntelligence\IpIntelligenceProviderInterface;
use Supamask\Security\IpIntelligence\IpIntelligenceResult;

final class IpIntelligenceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private IpIntelligenceProviderInterface $provider,
        private bool $blockVpn = false,
        private bool $detectIsp = false,
        private array $ispExclusions = [],
        private bool $failClosed = false,
    ) {
    }

    public function handle(Context $context): Decision
    {
        $ip = $context->request()->ip();
        if ($ip === '' || (!$this->blockVpn && !$this->detectIsp)) {
            return Decision::ALLOW;
        }

        try {
            $result = $this->provider->lookup($ip);
        } catch (IpIntelligenceProviderException $e) {
            if ($this->failClosed) {
                $context->setDecisionReason('ip_intelligence_unavailable');
                return Decision::DENY;
            }
            return Decision::ALLOW;
        }

        $context->setIpIntelligence($result);

        if ($this->blockVpn && $result->isVpn()) {
            $context->setDecisionReason('blocked_vpn');
            return Decision::DENY;
        }

        if ($this->detectIsp && $this->matchesIspExclusion($result)) {
            $context->setDecisionReason('blocked_isp');
            return Decision::DENY;
        }

        return Decision::ALLOW;
    }

    private function matchesIspExclusion(IpIntelligenceResult $result): bool
    {
        $asn = strtoupper(trim((string) $result->asn()));
        $organization = $this->normalizeOrganization($result->organization());

        foreach ($this->ispExclusions as $entry) {
            if (!is_string($entry)) {
                continue;
            }

            $value = trim($entry);
            if ($value === '') {
                continue;
            }

            if (preg_match('/^AS\d+$/i', $value)) {
                if ($asn !== '' && strtoupper($value) === $asn) {
                    return true;
                }
                continue;
            }

            if ($organization !== '' && $organization === $this->normalizeOrganization($value)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeOrganization(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? $value;
        return trim($value);
    }
}
