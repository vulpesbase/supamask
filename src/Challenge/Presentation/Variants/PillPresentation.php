<?php

namespace Supamask\Challenge\Presentation\Variants;

use Supamask\Challenge\Presentation\ChallengePresentationVariant;
use Supamask\Challenge\Presentation\ChallengeViewData;
use Supamask\Challenge\Presentation\HoneypotRenderer;

/**
 * Pill presentation variant.
 *
 * Visual design: Rounded/pill-shaped CTA-focused layout.
 * Emphasizes action through minimalist, modern presentation.
 */
final class PillPresentation implements ChallengePresentationVariant
{
    public function render(ChallengeViewData $data): string
    {
        $escape = static fn (mixed $value): string =>
            htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $title = $escape($data->title());
        $heading = $escape($data->heading());
        $body = $escape($data->body());
        $button = $escape($data->buttonLabel());
        $action = $escape($data->action());
        $token = $escape($data->verificationToken());
        $refCode = $escape($data->referenceCode());
        $trust = $escape($data->trustFooter());
        $identifiers = $data->identifiers();
        $container = $identifiers->container();
        $headingClass = $identifiers->heading();
        $content = $identifiers->content();
        $bodyClass = $identifiers->body();
        $form = $identifiers->form();
        $buttonClass = $identifiers->button();
        $footer = $identifiers->footer();
        $trustClass = $identifiers->trust();
        $reference = $identifiers->reference();
        $honeypot = HoneypotRenderer::render($identifiers, $data->honeypot());

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex,nofollow">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-store">
    <title>{$title}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f7fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .{$container} {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 32px;
            max-width: 360px;
            width: 100%;
        }
        .{$headingClass} {
            font-size: 28px;
            font-weight: 700;
            color: #1a202c;
            text-align: center;
        }
        .{$content} {
            text-align: center;
        }
        .{$bodyClass} {
            font-size: 15px;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 8px;
        }
        .{$form} {
            width: 100%;
        }
        .{$buttonClass} {
            width: 100%;
            background: #2d3748;
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(45, 55, 72, 0.15);
        }
        .{$buttonClass}:hover {
            background: #1a202c;
            box-shadow: 0 8px 20px rgba(45, 55, 72, 0.3);
            transform: translateY(-2px);
        }
        .{$buttonClass}:active {
            transform: translateY(0);
        }
        .{$buttonClass}:focus {
            outline: 2px solid #2d3748;
            outline-offset: 2px;
        }
        .{$footer} {
            text-align: center;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }
        .{$trustClass} {
            font-size: 14px;
            color: #718096;
            font-weight: 500;
        }
        .{$reference} {
            font-size: 12px;
            color: #a0aec0;
            font-family: "Courier New", monospace;
            letter-spacing: 2px;
            margin-top: 12px;
        }
        @media (max-width: 480px) {
            .{$headingClass} {
                font-size: 24px;
            }
            .{$bodyClass} {
                font-size: 14px;
            }
            .{$buttonClass} {
                padding: 12px 24px;
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="{$container}">
        <h1 class="{$headingClass}">{$heading}</h1>
        <div class="{$content}">
            <p class="{$bodyClass}">{$body}</p>
        </div>
        <form method="post" action="{$action}" class="{$form}">
            <input type="hidden" name="token" value="{$token}">
            <button type="submit" class="{$buttonClass}">{$button}</button>
        </form>
        <div class="{$footer}">
            <div class="{$trustClass}">{$trust}</div>
            <div class="{$reference}">{$refCode}</div>
        </div>
    </div>
{$honeypot}
</body>
</html>
HTML;
    }
}
