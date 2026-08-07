<?php

namespace Supamask\Security;

class IpMatcher
{
    public function matches(string $ip, string $rule): bool
    {
        if (str_contains($rule, '/')) {
            return $this->matchesCidr($ip, $rule);
        }

        return $ip === $rule;
    }

    private function matchesCidr(string $ip, string $cidr): bool
    {
        [$network, $prefix] = explode('/', $cidr, 2);

        $ipBinary = inet_pton($ip);
        $networkBinary = inet_pton($network);

        if ($ipBinary === false || $networkBinary === false) {
            return false;
        }

        if (strlen($ipBinary) !== strlen($networkBinary)) {
            return false;
        }

        $prefix = (int) $prefix;
        $totalBits = strlen($ipBinary) * 8;

        if ($prefix < 0 || $prefix > $totalBits) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if (
            $fullBytes > 0 &&
            substr($ipBinary, 0, $fullBytes) !==
            substr($networkBinary, 0, $fullBytes)
        ) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainingBits) & 0xFF;

        return (
            ord($ipBinary[$fullBytes]) & $mask
        ) === (
            ord($networkBinary[$fullBytes]) & $mask
        );
    }
}