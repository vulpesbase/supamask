<?php

namespace Supamask\Challenge;

final class SessionVerification
{
    private const SESSION_KEY = '_supamask_verified_until';

    public function isVerified(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $until = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_int($until) || $until < time()) {
            unset($_SESSION[self::SESSION_KEY]);
            return false;
        }

        return true;
    }

    public function markVerified(int $ttl): void
    {
        if ($ttl <= 0) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION[self::SESSION_KEY] = time() + $ttl;
    }

    public function clear(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        unset($_SESSION[self::SESSION_KEY]);
    }
}
