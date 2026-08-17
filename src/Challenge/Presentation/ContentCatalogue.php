<?php

namespace Supamask\Challenge\Presentation;

/**
 * Structured content catalogue for challenge presentations.
 *
 * Provides organized access to copy variants for titles, headings,
 * body text, CTA labels, and trust messages.
 *
 * The catalogue enables selecting from multiple options for each
 * presentation element without hard-coding them in templates.
 */
final class ContentCatalogue
{
    /**
     * Titles (page <title> and metadata)
     */
    private const TITLES = [
        'One more step',
        'Almost there',
        'Quick security check',
        'Secure continue',
        'Confirm you are human',
    ];

    /**
     * H1 headings (main heading on page)
     */
    private const HEADINGS = [
        'One more step',
        'Almost there',
        'Quick security check',
        'Secure continue',
        'Confirm you are human',
    ];

    /**
     * Body copy (explanatory text)
     */
    private const BODIES = [
        'This quick check helps keep the site safe. It usually takes a few seconds.',
        'Please confirm to continue. Your connection stays encrypted.',
        'Tap below to verify this browser session.',
        'We need a short verification before showing the page.',
        'Help us protect this site from unauthorized access.',
    ];

    /**
     * CTA button labels
     */
    private const BUTTON_LABELS = [
        'Verify',
        'Continue',
        'I\'m ready',
        'Next',
    ];

    /**
     * Trust/security footer messages
     */
    private const CHECKING_LABELS = [
        'Checking...',
        'Working...',
        'Validating...',
        'Confirming...',
        'Verifying...',
    ];

    /**
     * Button labels used after successful verification.
     */
    private const SUCCESS_LABELS = [
        'Success',
        'Verified',
    ];

    private const TRUST_FOOTERS = [
        'Privacy first',
        'TLS secured',
        'Encrypted session',
        'Secure gate',
        'Browser check',
    ];

    /**
     * Returns all available titles.
     *
     * @return array<int, string>
     */
    public static function allTitles(): array
    {
        return self::TITLES;
    }

    /**
     * Returns all available headings.
     *
     * @return array<int, string>
     */
    public static function allHeadings(): array
    {
        return self::HEADINGS;
    }

    /**
     * Returns all available body copy.
     *
     * @return array<int, string>
     */
    public static function allBodies(): array
    {
        return self::BODIES;
    }

    /**
     * Returns all available button labels.
     *
     * @return array<int, string>
     */
    public static function allButtonLabels(): array
    {
        return self::BUTTON_LABELS;
    }

    /**
     * Returns all available trust footers.
     *
     * @return array<int, string>
     */
    public static function allTrustFooters(): array
    {
        return self::TRUST_FOOTERS;
    }

    /**
     * Returns a random title from the catalogue.
     */
    public static function randomTitle(): string
    {
        return self::selectRandom(self::TITLES);
    }

    /**
     * Returns a random heading from the catalogue.
     */
    public static function randomHeading(): string
    {
        return self::selectRandom(self::HEADINGS);
    }

    /**
     * Returns a random body from the catalogue.
     */
    public static function randomBody(): string
    {
        return self::selectRandom(self::BODIES);
    }

    /**
     * Returns a random button label from the catalogue.
     */
    public static function randomButtonLabel(): string
    {
        return self::selectRandom(self::BUTTON_LABELS);
    }

    /**
     * Returns a random trust footer from the catalogue.
     */
    public static function randomTrustFooter(): string
    {
        return self::selectRandom(self::TRUST_FOOTERS);
    }

    /** @return array<int, string> */
    public static function allCheckingLabels(): array
    {
        return self::CHECKING_LABELS;
    }

    public static function randomCheckingLabel(): string
    {
        return self::selectRandom(self::CHECKING_LABELS);
    }

    /** @return array<int, string> */
    public static function allSuccessLabels(): array
    {
        return self::SUCCESS_LABELS;
    }

    public static function randomSuccessLabel(): string
    {
        return self::selectRandom(self::SUCCESS_LABELS);
    }

    /**
     * Selects a random element from an array using a cryptographically
     * secure random source.
     *
     * @param array<int, string> $items
     */
    private static function selectRandom(array $items): string
    {
        if (empty($items)) {
            return '';
        }

        $randomIndex = (int) (random_int(0, 2147483647) % count($items));

        return $items[$randomIndex];
    }
}
