<?php

use Supamask\Challenge\ChallengePresentationInterface;

final class CustomChallengePresentation implements ChallengePresentationInterface
{
    public function render(array $context): string
    {
        $escape = static fn (mixed $value): string =>
            htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return sprintf(
            '<!doctype html><html><head><title>%s</title></head><body><h1>%s</h1><form method="post" action="%s"><input type="hidden" name="token" value="%s"><button>%s</button></form></body></html>',
            $escape($context['title']),
            $escape($context['heading']),
            $escape($context['action']),
            $escape($context['token']),
            $escape($context['button']),
        );
    }
}
