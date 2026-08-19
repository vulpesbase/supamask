<?php

namespace Supamask\Challenge\Presentation;

/**
 * Generates render-scoped hidden honeypot content.
 */
final class HoneypotGenerator
{
    private const VALUE_LENGTH = 20;
    private const ATTRIBUTE_NAMES = ['data-x', 'data-k', 'data-r', 'data-q'];
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    public function generate(): HoneypotData
    {
        return new HoneypotData(
            value: $this->randomString(),
            attributeName: self::ATTRIBUTE_NAMES[random_int(0, count(self::ATTRIBUTE_NAMES) - 1)],
            attributeValue: $this->randomString(),
            childValue: $this->randomString(),
            id: $this->identifier(),
        );
    }

    private function identifier(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz';
        $remaining = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $bytes = random_bytes(16);
        $value = $alphabet[ord($bytes[0]) % strlen($alphabet)];
        for ($i = 1; $i < 16; $i++) {
            $value .= $remaining[ord($bytes[$i]) % strlen($remaining)];
        }
        return $value;
    }

    private function randomString(): string
    {
        $bytes = random_bytes(self::VALUE_LENGTH);
        $value = '';
        $alphabetLength = strlen(self::ALPHABET);

        for ($i = 0; $i < self::VALUE_LENGTH; $i++) {
            $value .= self::ALPHABET[ord($bytes[$i]) % $alphabetLength];
        }

        return $value;
    }
}
