<?php

namespace Supamask\Security\IpIntelligence;

final class IpIntelligenceResult
{
    public function __construct(
        private string $ip,
        private ?string $asn = null,
        private ?string $organization = null,
        private bool $isVpn = false,
        private bool $isProxy = false,
        private bool $isTor = false,
        private bool $isRelay = false,
    ) {
    }

    public function ip(): string { return $this->ip; }
    public function asn(): ?string { return $this->asn; }
    public function organization(): ?string { return $this->organization; }
    public function isVpn(): bool { return $this->isVpn; }
    public function isProxy(): bool { return $this->isProxy; }
    public function isTor(): bool { return $this->isTor; }
    public function isRelay(): bool { return $this->isRelay; }

    /** @return array{ip:string,asn:?string,organization:?string,is_vpn:bool,is_proxy:bool,is_tor:bool,is_relay:bool} */
    public function toArray(): array
    {
        return [
            'ip' => $this->ip,
            'asn' => $this->asn,
            'organization' => $this->organization,
            'is_vpn' => $this->isVpn,
            'is_proxy' => $this->isProxy,
            'is_tor' => $this->isTor,
            'is_relay' => $this->isRelay,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['ip'] ?? ''),
            isset($data['asn']) && $data['asn'] !== '' ? (string) $data['asn'] : null,
            isset($data['organization']) && $data['organization'] !== '' ? (string) $data['organization'] : null,
            (bool) ($data['is_vpn'] ?? false),
            (bool) ($data['is_proxy'] ?? false),
            (bool) ($data['is_tor'] ?? false),
            (bool) ($data['is_relay'] ?? false),
        );
    }
}
