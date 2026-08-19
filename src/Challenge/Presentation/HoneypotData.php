<?php

namespace Supamask\Challenge\Presentation;

/**
 * Render-scoped metadata for the hidden challenge honeypot.
 *
 * These values are presentation-only. They are not authentication secrets,
 * session identifiers, or verification tokens.
 */
final class HoneypotData
{
    public function __construct(
        private string $value,
        private string $attributeName,
        private string $attributeValue,
        private string $childValue,
        private string $id,
    ) {
    }

    public function value(): string { return $this->value; }
    public function attributeName(): string { return $this->attributeName; }
    public function attributeValue(): string { return $this->attributeValue; }
    public function childValue(): string { return $this->childValue; }
    public function id(): string { return $this->id; }
}
