<?php

namespace Supamask\Challenge\Presentation;

/**
 * Generates 8-character alphanumeric reference codes for challenge presentations.
 *
 * Reference codes are:
 * - Exactly 8 characters
 * - Alphanumeric (A-Z, 0-9)
 * - Regenerated for each presentation
 * - Displayed as presentation metadata only
 *
 * Reference codes are NOT used for:
 * - Authentication
 * - Session identification
 * - Challenge verification
 * - Authorization
 *
 * They serve only as user-visible reference for support/debugging purposes.
 */
final class ReferenceCodeGenerator
{
    private const LENGTH = 8;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    private const ALPHABET_LENGTH = 36; // 26 letters + 10 digits

    /**
     * Generates a new 8-character reference code.
     *
     * Uses random_bytes() for cryptographic strength (even though this
     * is not a security-sensitive token, we prefer secure generators).
     */
    public static function generate(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $randomByte = ord(random_bytes(1));
            $index = $randomByte % self::ALPHABET_LENGTH;
            $code .= self::ALPHABET[$index];
        }

        return $code;
    }
}
