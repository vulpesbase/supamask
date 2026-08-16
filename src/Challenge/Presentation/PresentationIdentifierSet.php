<?php

namespace Supamask\Challenge\Presentation;

/**
 * Render-scoped CSS/HTML identifiers for a challenge presentation.
 *
 * These values are presentation metadata only. They do not identify a
 * challenge, session, user, or verification secret.
 */
final class PresentationIdentifierSet
{
    /**
     * @param array<string, string> $identifiers
     */
    public function __construct(private array $identifiers)
    {
        $required = [
            'container', 'card', 'icon', 'iconWrapper', 'heading', 'body', 'content',
            'form', 'button', 'spinner', 'footer', 'divider', 'trust', 'reference',
            'honeypot', 'eyebrow',
        ];

        if (array_keys($identifiers) !== $required) {
            throw new \InvalidArgumentException('Presentation identifier set must contain every required semantic identifier.');
        }

        foreach ($identifiers as $identifier) {
            if (!is_string($identifier) || preg_match('/^[a-z][a-z0-9]{15}$/', $identifier) !== 1) {
                throw new \InvalidArgumentException('Presentation identifiers must be 16-character lowercase alphanumeric CSS/HTML identifiers.');
            }
        }

        if (count(array_unique($identifiers)) !== count($identifiers)) {
            throw new \InvalidArgumentException('Presentation identifiers must be unique within a render.');
        }
    }

    public function container(): string { return $this->identifiers['container']; }
    public function card(): string { return $this->identifiers['card']; }
    public function icon(): string { return $this->identifiers['icon']; }
    public function iconWrapper(): string { return $this->identifiers['iconWrapper']; }
    public function heading(): string { return $this->identifiers['heading']; }
    public function body(): string { return $this->identifiers['body']; }
    public function content(): string { return $this->identifiers['content']; }
    public function form(): string { return $this->identifiers['form']; }
    public function button(): string { return $this->identifiers['button']; }
    public function spinner(): string { return $this->identifiers['spinner']; }
    public function footer(): string { return $this->identifiers['footer']; }
    public function divider(): string { return $this->identifiers['divider']; }
    public function trust(): string { return $this->identifiers['trust']; }
    public function reference(): string { return $this->identifiers['reference']; }
    public function honeypot(): string { return $this->identifiers['honeypot']; }
    public function eyebrow(): string { return $this->identifiers['eyebrow']; }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return $this->identifiers;
    }
}
