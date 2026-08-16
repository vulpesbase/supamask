<?php

namespace Supamask\Challenge\Presentation;

use Supamask\Challenge\ChallengePresentationInterface;

/**
 * Bridge adapter that connects the new ChallengePresenter system
 * with the existing ChallengePresentationInterface contract.
 *
 * This allows ChallengeHandler to work unchanged while gaining access
 * to the polymorphic presentation subsystem.
 *
 * Maps the old interface method render(array) to the new presenter.
 *
 * Context array keys:
 * - id: Challenge UUID (12-char hex)
 * - token: Verification token (64-char hex)
 * - action: Form action URL
 * - title: (optional) Override content catalogue title
 * - heading: (optional) Override content catalogue heading
 * - message: (optional) Override content catalogue body
 * - button: (optional) Override content catalogue button label
 *
 * If optional overrides are provided, they take precedence over
 * content catalogue random selection (for testing/customization).
 */
final class PolymorphicChallengePresentation implements ChallengePresentationInterface
{
    private ChallengePresenter $presenter;
    public function __construct()
    {
        $this->presenter = new ChallengePresenter();
    }

    /**
     * Renders challenge HTML using the polymorphic presentation system.
     *
     * Implements ChallengePresentationInterface::render() for compatibility
     * with existing ChallengeHandler code.
     *
     * @param array<string, string> $context Challenge presentation context
     *
     * @return string Rendered HTML
     */
    public function render(array $context): string
    {
        $challengeId = $context['id'] ?? '';
        $token = $context['token'] ?? '';
        $action = $context['action'] ?? '';

        if ($challengeId === '' || $token === '' || $action === '') {
            throw new \InvalidArgumentException(
                'Challenge context must include: id, token, action'
            );
        }

        // If overrides are provided, use them (for testing/config-based customization)
        if ($this->hasOverrides($context)) {
            return $this->presenter->presentWithOverrides($challengeId, $token, $action, $context);
        }

        // Otherwise use the polymorphic presenter with random variant and copy
        return $this->presenter->present($challengeId, $token, $action);
    }

    /**
     * Returns presenter instance for configuration.
     */
    public function presenter(): ChallengePresenter
    {
        return $this->presenter;
    }

    /**
     * Checks if context contains override values.
     *
     * @param array<string, string> $context
     */
    private function hasOverrides(array $context): bool
    {
        return isset($context['title']) ||
               isset($context['heading']) ||
               isset($context['message']) ||
               isset($context['button']);
    }

}
