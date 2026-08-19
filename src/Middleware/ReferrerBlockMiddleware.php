<?php

namespace Supamask\Middleware;

use Supamask\Contracts\MiddlewareInterface;
use Supamask\Core\Context;
use Supamask\Core\Decision;

/**
 * Blocks requests whose browser-supplied Referer hostname matches a configured
 * host or one of its subdomains.
 *
 * Referer is advisory/client-controlled data and is never treated as identity.
 */
final class ReferrerBlockMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $blockedHosts;

    /**
     * @param array<int,mixed> $blocklist
     */
    public function __construct(
        private bool $enabled,
        array $blocklist = [],
        private bool $blockMissingReferrer = false,
    ) {
        $this->blockedHosts = [];
        foreach ($blocklist as $entry) {
            if (!is_string($entry)) {
                continue;
            }

            $host = self::normalizeHost($entry);
            if ($host !== null) {
                $this->blockedHosts[$host] = true;
            }
        }
    }

    public function handle(Context $context): Decision
    {
        if (!$this->enabled) {
            return Decision::ALLOW;
        }

        $referer = $context->request()->referrer();

        if ($referer === null || trim($referer) === '') {
            if ($this->blockMissingReferrer) {
                $context->setDecisionReason('blocked_missing_referrer');
                return Decision::DENY;
            }
            return Decision::ALLOW;
        }

        $host = self::normalizeHost($referer);
        if ($host === null) {
            // A malformed client-controlled Referer is not a missing Referer.
            // It is simply not a positive blocklist match.
            return Decision::ALLOW;
        }

        foreach ($this->blockedHosts as $blockedHost => $_) {
            if ($host === $blockedHost || str_ends_with($host, '.' . $blockedHost)) {
                $context->setDecisionReason('blocked_referrer');
                return Decision::DENY;
            }
        }

        return Decision::ALLOW;
    }

    /**
     * Normalize a Referer URL or configured hostname to a DNS hostname.
     * IDNs are converted to ASCII when ext-intl is available.
     */
    public static function normalizeHost(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        // A blocklist entry is a hostname, while an incoming value is normally
        // a full URL. parse_url handles the latter without substring matching.
        if (str_contains($value, '://')) {
            $host = parse_url($value, PHP_URL_HOST);
        } else {
            // Treat bare blocklist entries as hostnames. Do not accept paths,
            // credentials, ports, or arbitrary URL fragments as hostnames.
            $host = parse_url('http://' . $value, PHP_URL_HOST);
            $rebuilt = parse_url('http://' . $value);
            if (!is_array($rebuilt) || !isset($rebuilt['host']) || (isset($rebuilt['path']) && $rebuilt['path'] !== '')) {
                return null;
            }
        }

        if (!is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower(rtrim($host, '.'));
        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            // Referrer blocking is hostname-based. IP literals are intentionally
            // not treated as domain entries.
            return null;
        }

        if (function_exists('idn_to_ascii')) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($ascii) && $ascii !== '') {
                $host = strtolower(rtrim($ascii, '.'));
            }
        }

        if (strlen($host) > 253 || str_starts_with($host, '.') || str_ends_with($host, '..')) {
            return null;
        }

        return $host;
    }
}
