<?php

namespace Supamask\Challenge;

interface ChallengePresentationInterface
{
    /** @param array<string, string> $context */
    public function render(array $context): string;
}
