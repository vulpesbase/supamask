<?php

namespace Supamask\Challenge\Presentation;

/**
 * Presentation metadata for a single challenge render.
 *
 * Contains all data required for a presentation template to render
 * a challenge form, including selected copy, visual variant, and
 * presentation-specific metadata like reference code and render-scoped
 * CSS/HTML identifiers.
 *
 * This is strictly presentation data, not security/session state.
 * The reference code is for UI display only, not authentication.
 * Identifiers exist only to connect template markup and styling for one render.
 */
final class ChallengeViewData
{
    private HoneypotData $honeypot;

    public function __construct(
        private string $title,
        private string $heading,
        private string $body,
        private string $buttonLabel,
        private string $trustFooter,
        private string $referenceCode,
        private string $variant,
        private string $challengeId,
        private string $verificationToken,
        private string $action,
        private PresentationIdentifierSet $identifiers,
        private string $eyebrow = '',
        private string $profile = '',
        ?HoneypotData $honeypot = null,
    ) {
        $this->honeypot = $honeypot ?? (new HoneypotGenerator())->generate();
    }

    public function title(): string
    {
        return $this->title;
    }

    public function heading(): string
    {
        return $this->heading;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function buttonLabel(): string
    {
        return $this->buttonLabel;
    }

    public function trustFooter(): string
    {
        return $this->trustFooter;
    }

    public function referenceCode(): string
    {
        return $this->referenceCode;
    }

    public function variant(): string
    {
        return $this->variant;
    }

    public function challengeId(): string
    {
        return $this->challengeId;
    }

    public function verificationToken(): string
    {
        return $this->verificationToken;
    }

    public function action(): string
    {
        return $this->action;
    }

    public function identifiers(): PresentationIdentifierSet
    {
        return $this->identifiers;
    }

    public function eyebrow(): string
    {
        return $this->eyebrow;
    }

    public function profile(): string
    {
        return $this->profile;
    }

    public function honeypot(): HoneypotData
    {
        return $this->honeypot;
    }

    /**
     * Converts to associative array for backward compatibility with
     * ChallengePresentationInterface render() contracts.
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'heading' => $this->heading,
            'message' => $this->body,
            'body' => $this->body,
            'button' => $this->buttonLabel,
            'buttonLabel' => $this->buttonLabel,
            'trustFooter' => $this->trustFooter,
            'referenceCode' => $this->referenceCode,
            'variant' => $this->variant,
            'id' => $this->challengeId,
            'token' => $this->verificationToken,
            'action' => $this->action,
            'identifiers' => $this->identifiers->toArray(),
            'eyebrow' => $this->eyebrow,
            'profile' => $this->profile,
            'honeypot' => [
                'value' => $this->honeypot->value(),
                'attributeName' => $this->honeypot->attributeName(),
                'attributeValue' => $this->honeypot->attributeValue(),
                'childValue' => $this->honeypot->childValue(),
                'id' => $this->honeypot->id(),
            ],
        ];
    }
}
