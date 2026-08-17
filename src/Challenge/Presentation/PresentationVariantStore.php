<?php

namespace Supamask\Challenge\Presentation;

/**
 * Keeps the selected polymorphic presentation variant stable for one
 * challenge lifecycle.
 *
 * This is presentation state only. It is keyed by challenge ID and is never
 * used for authentication, authorization, or verification.
 */
final class PresentationVariantStore
{
    private const SESSION_KEY = '_supamask_presentation_variants';

    public function get(string $challengeId): ?string
    {
        $this->ensureSession();

        $value = $_SESSION[self::SESSION_KEY][$challengeId] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function put(string $challengeId, string $variant): void
    {
        $this->ensureSession();

        $_SESSION[self::SESSION_KEY][$challengeId] = $variant;
    }

    public function forget(string $challengeId): void
    {
        $this->ensureSession();

        unset($_SESSION[self::SESSION_KEY][$challengeId]);
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
