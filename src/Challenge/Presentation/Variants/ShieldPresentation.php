<?php

namespace Supamask\Challenge\Presentation\Variants;

use Supamask\Challenge\Presentation\ChallengePresentationVariant;
use Supamask\Challenge\Presentation\ChallengeViewData;

/**
 * Shield presentation variant.
 *
 * Visual design: Card/boxed layout with shield-style header.
 * Emphasizes security and trust through structured, formal presentation.
 */
final class ShieldPresentation implements ChallengePresentationVariant
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
        $icon = $identifiers->icon();
        $headingClass = $identifiers->heading();
        $bodyClass = $identifiers->body();
        $form = $identifiers->form();
        $buttonClass = $identifiers->button();
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .{$container} {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 420px;
            width: 100%;
            padding: 60px 40px 40px;
            text-align: center;
        }
        .{$icon} {
            font-size: 64px;
            margin-bottom: 24px;
        }
        .{$headingClass} {
            font-size: 24px;
            font-weight: 600;
            color: #1a202c;
            margin-bottom: 12px;
        }
        .{$bodyClass} {
            font-size: 15px;
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 32px;
        }
        .{$form} {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .{$buttonClass} {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .{$buttonClass}:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .{$buttonClass}:active {
            transform: translateY(0);
        }
        .{$buttonClass}:focus {
            outline: 2px solid #667eea;
            outline-offset: 2px;
        }
        .{$trustClass} {
            font-size: 13px;
            color: #718096;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
        }
        .{$reference} {
            font-size: 12px;
            color: #a0aec0;
            font-family: "Courier New", monospace;
            letter-spacing: 2px;
            margin-top: 12px;
        }
        @media (max-width: 480px) {
            .{$container} {
                padding: 40px 24px 24px;
            }
            .{$icon} {
                font-size: 48px;
            }
            .{$headingClass} {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="{$container}">
        <div class="{$icon}">🛡️</div>
        <h1 class="{$headingClass}">{$heading}</h1>
        <p class="{$bodyClass}">{$body}</p>
        <form method="post" action="{$action}" class="{$form}">
            <input type="hidden" name="token" value="{$token}">
            <button type="submit" class="{$buttonClass}">{$button}</button>
        </form>
        <div class="{$trustClass}">
            {$trust}
            <div class="{$reference}">{$refCode}</div>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
