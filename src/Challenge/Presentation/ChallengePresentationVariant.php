<?php

namespace Supamask\Challenge\Presentation;

/**
 * Interface for a presentation variant template.
 *
 * Each variant is responsible only for rendering the ChallengeViewData
 * into HTML. No business logic, variant selection, or content rotation
 * should happen in templates.
 */
interface ChallengePresentationVariant
{
    /**
     * Renders the challenge presentation HTML.
     *
     * @return string HTML content (properly escaped)
     */
    public function render(ChallengeViewData $data): string;
}
