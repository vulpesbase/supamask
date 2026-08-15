<?php

namespace Supamask\Challenge;

final class DefaultChallengePresentation implements ChallengePresentationInterface
{
    public function render(array $context): string
    {
        $escape = static fn (mixed $value): string =>
            htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $title = $escape($context['title'] ?? 'Security verification');
        $heading = $escape($context['heading'] ?? 'Security verification');
        $message = $escape($context['message'] ?? 'Please confirm to continue to the requested page.');
        $button = $escape($context['button'] ?? 'Continue');
        $action = $escape($context['action'] ?? '/');
        $token = $escape($context['token'] ?? '');

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex,nofollow">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-store">
    <title>{$title}</title>
</head>
<body>
    <main>
        <h1>{$heading}</h1>
        <p>{$message}</p>
        <form method="post" action="{$action}">
            <input type="hidden" name="token" value="{$token}">
            <button type="submit">{$button}</button>
        </form>
    </main>
</body>
</html>
HTML;
    }
}
