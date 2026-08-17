<?php

namespace Supamask\Challenge\Presentation;

/**
 * Orchestrates challenge presentation selection and data generation.
 *
 * Responsible for:
 * - Selecting a presentation variant
 * - Selecting copy from the content catalogue
 * - Generating per-render reference code
 * - Generating per-render CSS/HTML identifiers
 * - Composing ChallengeViewData
 * - Delegating to variant for rendering
 *
 * Does not modify challenge logic, session state, or verification semantics.
 */
final class ChallengePresenter
{
    /**
     * Available presentation variants.
     * Maps variant name to variant implementation class.
     *
     * @var array<string, ChallengePresentationVariant>
     */
    private array $variants = [];

    /**
     * Configured list of enabled variants.
     * If empty, all registered variants are enabled.
     *
     * @var array<int, string>
     */
    private array $enabledVariants = [];

    /** @var array<int, string> */
    private array $defaultVariants = [];

    private PresentationIdentifierGenerator $identifierGenerator;
    private PresentationVariantStore $variantStore;

    public function __construct(?PresentationIdentifierGenerator $identifierGenerator = null, ?PresentationVariantStore $variantStore = null)
    {
        $this->identifierGenerator = $identifierGenerator ?? new PresentationIdentifierGenerator();
        $this->variantStore = $variantStore ?? new PresentationVariantStore();
        $compact = new Variants\CompactPresentation();
        $branded = new Variants\BrandedPresentation();

        foreach (PresentationProfileCatalogue::all() as $name => $profile) {
            $this->registerVariant($name, $profile['layout'] === 'branded' ? $branded : $compact);
        }
        $this->defaultVariants = PresentationProfileCatalogue::names();

        // Legacy names remain available to explicitly configured callers.
        $this->registerVariant('shield', new Variants\ShieldPresentation());
        $this->registerVariant('pill', new Variants\PillPresentation());
        $this->registerVariant('checkmark', new Variants\CheckmarkPresentation());
        for ($variant = 8; $variant <= 14; $variant++) {
            $this->registerVariant('extended-' . $variant, new Variants\ExtendedPresentation($variant));
        }
    }

    /**
     * Registers a presentation variant.
     *
     * @param string $name Unique identifier for the variant (e.g., 'shield')
     */
    public function registerVariant(string $name, ChallengePresentationVariant $variant): void
    {
        if ($name === '' || preg_match('#[^a-z0-9_-]#', $name)) {
            throw new \InvalidArgumentException(
                "Variant name must be non-empty and contain only lowercase letters, digits, dash, and underscore."
            );
        }

        $this->variants[$name] = $variant;
    }

    /**
     * Sets which variants are enabled.
     *
     * If not called, all registered variants are enabled.
     *
     * @param array<int, string> $enabled List of variant names to enable
     */
    public function setEnabledVariants(array $enabled): void
    {
        $this->enabledVariants = array_values(array_filter($enabled, 'is_string'));
    }

    /**
     * Returns the names of all enabled variants.
     *
     * @return array<int, string>
     */
    public function enabledVariants(): array
    {
        if (empty($this->enabledVariants)) {
            return $this->defaultVariants;
        }

        return $this->enabledVariants;
    }

    /**
     * Creates and renders a challenge presentation.
     *
     * Selects a variant, generates content, creates presentation data,
     * and delegates rendering to the variant.
     *
     * @param string $challengeId    Challenge UUID (12-char hex)
     * @param string $verificationToken Challenge verification token (64-char hex)
     * @param string $action         Form action URL (challenge endpoint)
     *
     * @return string HTML content
     */
    public function present(
        string $challengeId,
        string $verificationToken,
        string $action
    ): string {
        $variant = $this->variantForChallenge($challengeId);

        return $this->variants[$variant]->render($this->viewData($variant, $challengeId, $verificationToken, $action));
    }

    /** @param array<string, string> $overrides */
    public function presentWithOverrides(string $challengeId, string $verificationToken, string $action, array $overrides): string
    {
        $variant = $this->variantForChallenge($challengeId);

        return $this->variants[$variant]->render($this->viewData($variant, $challengeId, $verificationToken, $action, $overrides));
    }

    /** @param array<string, string> $overrides */
    private function viewData(string $variant, string $challengeId, string $verificationToken, string $action, array $overrides = []): ChallengeViewData
    {
        $profile = PresentationProfileCatalogue::isProfile($variant)
            ? PresentationProfileCatalogue::get($variant)
            : null;

        return new ChallengeViewData(
            title: $overrides['title'] ?? ContentCatalogue::randomTitle(),
            heading: $overrides['heading'] ?? ContentCatalogue::randomHeading(),
            body: $overrides['message'] ?? ContentCatalogue::randomBody(),
            buttonLabel: $overrides['button'] ?? ContentCatalogue::randomButtonLabel(),
            trustFooter: $overrides['trust_footer'] ?? ContentCatalogue::randomTrustFooter(),
            referenceCode: ReferenceCodeGenerator::generate(),
            variant: $profile ? $profile['layout'] : $variant,
            challengeId: $challengeId,
            verificationToken: $verificationToken,
            action: $action,
            identifiers: $this->identifierGenerator->generate(),
            eyebrow: $profile['eyebrow'] ?? '',
            profile: $variant,
        );
    }

    /**
     * Returns the presentation variant assigned to this challenge.
     *
     * The first render selects it randomly. Every subsequent render for the
     * same challenge reuses that exact variant so challenge -> checking ->
     * success/retry never swaps the visual design.
     */
    private function variantForChallenge(string $challengeId): string
    {
        $existing = $this->variantStore->get($challengeId);

        if ($existing !== null && in_array($existing, $this->enabledVariants(), true)) {
            return $existing;
        }

        $variant = $this->selectVariant();
        $this->variantStore->put($challengeId, $variant);

        return $variant;
    }

    /**
     * Releases presentation state after the challenge lifecycle reaches its
     * terminal success presentation.
     */
    public function forgetVariant(string $challengeId): void
    {
        $this->variantStore->forget($challengeId);
    }

    /**
     * Selects a variant for a new challenge.
     *
     * Uses random selection from enabled variants.
     */
    private function selectVariant(): string
    {
        $enabled = $this->enabledVariants();

        if (empty($enabled)) {
            throw new \RuntimeException('No enabled presentation variants available.');
        }

        if (count($enabled) === 1) {
            return $enabled[0];
        }

        $index = (int) (random_int(0, 2147483647) % count($enabled));

        return $enabled[$index];
    }
}
