<?php

namespace Supamask\Challenge\Presentation;

/**
 * Generates random, render-scoped identifiers for presentation markup.
 *
 * It deliberately has no dependency on challenge, session, or verification
 * state. The identifiers are safe to use as either CSS classes or HTML IDs.
 */
final class PresentationIdentifierGenerator
{
    private const LENGTH = 16;
    private const FIRST_CHARACTER_ALPHABET = 'abcdefghijklmnopqrstuvwxyz';
    private const REMAINING_CHARACTER_ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    public function generate(): PresentationIdentifierSet
    {
        $names = [
            'container', 'card', 'icon', 'iconWrapper', 'heading', 'body', 'content',
            'form', 'button', 'spinner', 'footer', 'divider', 'trust', 'reference',
            'honeypot', 'eyebrow',
        ];
        $identifiers = [];

        foreach ($names as $name) {
            do {
                $identifier = $this->generateIdentifier();
            } while (in_array($identifier, $identifiers, true));

            $identifiers[$name] = $identifier;
        }

        return new PresentationIdentifierSet($identifiers);
    }

    private function generateIdentifier(): string
    {
        $identifier = self::FIRST_CHARACTER_ALPHABET[random_int(0, strlen(self::FIRST_CHARACTER_ALPHABET) - 1)];

        for ($position = 1; $position < self::LENGTH; $position++) {
            $identifier .= self::REMAINING_CHARACTER_ALPHABET[
                random_int(0, strlen(self::REMAINING_CHARACTER_ALPHABET) - 1)
            ];
        }

        return $identifier;
    }
}
