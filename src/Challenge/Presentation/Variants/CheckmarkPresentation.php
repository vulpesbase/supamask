<?php

namespace Supamask\Challenge\Presentation\Variants;

use Supamask\Challenge\Presentation\ChallengePresentationVariant;
use Supamask\Challenge\Presentation\ChallengeViewData;

/**
 * Checkmark presentation variant.
 *
 * Visual design: Circular/verification-oriented layout.
 * Emphasizes completion and verification through step-based presentation.
 */
final class CheckmarkPresentation implements ChallengePresentationVariant
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
        $iconWrapper = $identifiers->iconWrapper();
        $icon = $identifiers->icon();
        $headingClass = $identifiers->heading();
        $bodyClass = $identifiers->body();
        $form = $identifiers->form();
        $buttonClass = $identifiers->button();
        $divider = $identifiers->divider();
        $trustClass = $identifiers->trust();
        $reference = $identifiers->reference();

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
            background: linear-gradient(to bottom, #ffffff 0%, #f8f9fa 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            min-height: 100vh;
        }
        .{$container} {
            max-width: 380px;
            width: 100%;
            text-align: center;
        }
        .{$iconWrapper} {
            margin-bottom: 32px;
        }
        .{$icon} {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 80px;
            height: 80px;
            background: #edf2f7;
            border-radius: 50%;
            font-size: 48px;
            border: 2px solid #e2e8f0;
        }
        .{$headingClass} {
            font-size: 26px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 12px;
        }
        .{$bodyClass} {
            font-size: 15px;
            color: #4a5568;
            line-height: 1.7;
            margin-bottom: 36px;
        }
        .{$form} {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 32px;
        }
        .{$buttonClass} {
            background: #1a365d;
            color: white;
            border: none;
            padding: 13px 28px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(26, 54, 93, 0.2);
        }
        .{$buttonClass}:hover {
            background: #0d1f3c;
            box-shadow: 0 4px 12px rgba(26, 54, 93, 0.3);
        }
        .{$buttonClass}:active {
            transform: scale(0.98);
        }
        .{$buttonClass}:focus {
            outline: 2px solid #1a365d;
            outline-offset: 2px;
        }
        .{$divider} {
            height: 1px;
            background: #e2e8f0;
            margin: 0 0 24px 0;
        }
        .{$trustClass} {
            font-size: 13px;
            color: #718096;
            margin-bottom: 8px;
        }
        .{$reference} {
            font-size: 12px;
            color: #a0aec0;
            font-family: "Courier New", monospace;
            letter-spacing: 1.5px;
            word-spacing: 2px;
        }
        @media (max-width: 480px) {
            .{$container} {
                padding: 20px;
            }
            .{$icon} {
                width: 72px;
                height: 72px;
                font-size: 40px;
            }
            .{$headingClass} {
                font-size: 22px;
            }
            .{$bodyClass} {
                font-size: 14px;
                margin-bottom: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="{$container}">
        <div class="{$iconWrapper}">
            <div class="{$icon}">✓</div>
        </div>
        <h1 class="{$headingClass}">{$heading}</h1>
        <p class="{$bodyClass}">{$body}</p>
        <form method="post" action="{$action}" class="{$form}">
            <input type="hidden" name="token" value="{$token}">
            <button type="submit" class="{$buttonClass}">{$button}</button>
        </form>
        <div class="{$divider}"></div>
        <div class="{$trustClass}">{$trust}</div>
        <div class="{$reference}">{$refCode}</div>
    </div>
</body>
</html>
HTML;
    }
}
