<?php

namespace Supamask\Challenge\Presentation;

/**
 * The seven supported reference compositions. Profiles combine approved copy
 * with one compatible structural family; templates own the corresponding UI.
 */
final class PresentationProfileCatalogue
{
    /** @var array<string, array{layout: string, title: string, heading: string, body: string, button: string, trust: string, eyebrow: string}> */
    private const PROFILES = [
        'branded-confirm' => ['layout' => 'branded', 'title' => 'Confirm you are human', 'heading' => 'Confirm you are human', 'body' => 'This quick check helps keep the site safe. It usually takes a few seconds.', 'button' => 'Verify', 'trust' => 'Privacy first', 'eyebrow' => 'Protected session'],
        'compact-icon-confirm' => ['layout' => 'compact-icon', 'title' => 'Confirm you are human', 'heading' => 'Confirm you are human', 'body' => 'Tap below to verify this browser session.', 'button' => 'Continue', 'trust' => 'Secure gate', 'eyebrow' => ''],
        'compact-secure' => ['layout' => 'compact', 'title' => 'Secure continue', 'heading' => 'Secure continue', 'body' => 'Please confirm to continue. Your connection stays encrypted.', 'button' => 'Next', 'trust' => 'TLS secured', 'eyebrow' => ''],
        'compact-quick' => ['layout' => 'compact', 'title' => 'Quick security check', 'heading' => 'Quick security check', 'body' => 'Tap below to verify this browser session.', 'button' => 'Continue', 'trust' => 'Encrypted session', 'eyebrow' => ''],
        'compact-almost' => ['layout' => 'compact', 'title' => 'Almost there', 'heading' => 'Almost there', 'body' => 'Please confirm to continue. Your connection stays encrypted.', 'button' => "I'm ready", 'trust' => 'Privacy first', 'eyebrow' => ''],
        'branded-one-more' => ['layout' => 'branded', 'title' => 'One more step', 'heading' => 'One more step', 'body' => 'We need a short verification before showing the page.', 'button' => 'Continue', 'trust' => 'Privacy first', 'eyebrow' => 'Browser check'],
        'branded-protected' => ['layout' => 'branded', 'title' => 'One more step', 'heading' => 'One more step', 'body' => 'This quick check helps keep the site safe. It usually takes a few seconds.', 'button' => 'Verify', 'trust' => 'TLS secured', 'eyebrow' => 'Protected session'],
        'extended-8' => ['layout' => 'extended-8', 'title' => 'Verify this session', 'heading' => 'Verify this session', 'body' => 'We need a short verification before showing the page.', 'button' => "I'm ready", 'trust' => 'Secure channel', 'eyebrow' => 'Protected session'],
        'extended-9' => ['layout' => 'extended-9', 'title' => 'Almost there', 'heading' => 'Almost there', 'body' => 'Please confirm to continue. Your connection stays encrypted.', 'button' => 'Start check', 'trust' => 'Secure channel', 'eyebrow' => 'Browser check'],
        'extended-10' => ['layout' => 'extended-10', 'title' => 'Just a moment', 'heading' => 'Just a moment', 'body' => 'Please confirm to continue. Your connection stays encrypted.', 'button' => 'Continue', 'trust' => 'Encrypted session', 'eyebrow' => 'Human verify'],
        'extended-11' => ['layout' => 'extended-11', 'title' => 'Just a moment', 'heading' => 'Just a moment', 'body' => 'We need a short verification before showing the page.', 'button' => 'Proceed', 'trust' => 'Browser bound', 'eyebrow' => 'Secure gate'],
        'extended-12' => ['layout' => 'extended-12', 'title' => 'Almost there', 'heading' => 'Almost there', 'body' => 'Confirm your browser to continue securely.', 'button' => 'Proceed', 'trust' => 'Browser bound', 'eyebrow' => 'Human verify'],
        'extended-13' => ['layout' => 'extended-13', 'title' => 'Just a moment', 'heading' => 'Just a moment', 'body' => 'This quick check helps keep the site safe. It usually takes a few seconds.', 'button' => 'Verify', 'trust' => 'Encrypted session', 'eyebrow' => 'Browser check'],
        'extended-14' => ['layout' => 'extended-14', 'title' => 'Confirm you are human', 'heading' => 'Confirm you are human', 'body' => 'This quick check helps keep the site safe. It usually takes a few seconds.', 'button' => 'Continue', 'trust' => 'Privacy first', 'eyebrow' => 'Session check'],
    ];

    /** @return array<string, array{layout: string, title: string, heading: string, body: string, button: string, trust: string, eyebrow: string}> */
    public static function all(): array { return self::PROFILES; }

    /** @return array{layout: string, title: string, heading: string, body: string, button: string, trust: string, eyebrow: string} */
    public static function get(string $name): array
    {
        if (!isset(self::PROFILES[$name])) {
            throw new \InvalidArgumentException("Unknown presentation profile: {$name}");
        }

        return self::PROFILES[$name];
    }

    public static function isProfile(string $name): bool { return isset(self::PROFILES[$name]); }

    /** @return array<int, string> */
    public static function names(): array { return array_keys(self::PROFILES); }
}
