<?php

namespace Supamask\Challenge\Presentation\Variants;

use Supamask\Challenge\Presentation\ChallengePresentationVariant;
use Supamask\Challenge\Presentation\ChallengeViewData;
use Supamask\Challenge\Presentation\HoneypotRenderer;

/**
 * Compact reference-family presentation.
 *
 * Matches the supplied minimalist 420px card references. The optional
 * compact-icon profile adds a small neutral icon treatment without changing
 * the functional challenge contract.
 */
final class CompactPresentation implements ChallengePresentationVariant
{
    public function render(ChallengeViewData $data): string
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $title = $escape($data->title());
        $heading = $escape($data->heading());
        $body = $escape($data->body());
        $button = $escape($data->buttonLabel());
        $action = $escape($data->action());
        $token = $escape($data->verificationToken());
        $trust = $escape($data->trustFooter());
        $reference = $escape($data->referenceCode());
        $ids = $data->identifiers();
        $honeypot = HoneypotRenderer::render($ids, $data->honeypot());
        $showIcon = $data->profile() === 'compact-icon-confirm';

        if ($showIcon) {
            return <<<HTML
<!DOCTYPE html> <html lang=en>
<meta charset=utf-8>
<meta name=viewport content="width=device-width,initial-scale=1">
<meta name=robots content=noindex,nofollow>
<title>{$title}</title>
<style>*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}html,body{height:100%;width:100%}body{min-height:100dvh;background:#f5f5f5;font-family:ui-sans-serif,system-ui,sans-serif;overflow-x:hidden}.{$ids->container()}{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:calc(100% - 32px);max-width:390px;z-index:2}.{$ids->card()}{background:#ffffff;border:1px solid #e5e7eb;border-radius:20px;padding:28px 24px;box-shadow:0 8px 30px rgba(0,0,0,.06)}.{$ids->iconWrapper()}{width:48px;height:48px;border-radius:14px;background:#111111;color:#fff;display:grid;place-items:center;margin-bottom:16px}.{$ids->icon()}{width:22px;height:22px}.{$ids->heading()}{font-size:22px;font-weight:700;color:#111111;margin-bottom:8px;letter-spacing:-.03em}.{$ids->body()}{font-size:14px;color:#333333;line-height:1.55;margin-bottom:22px}.{$ids->button()}{width:100%;border:0;border-radius:12px;padding:13px;background:#111111;color:#fff;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}.{$ids->spinner()}{width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;animation:{$ids->spinner()} .62s linear infinite}@keyframes {$ids->spinner()}{to{transform:rotate(360deg)}}.{$ids->footer()}{margin-top:16px;font-size:11px;color:#666666;font-family:ui-monospace,monospace}.{$ids->honeypot()}{height:0;overflow:hidden}</style><meta name=referrer content=no-referrer><style>.sf-hidden{display:none!important}</style><body>
<div class="{$ids->container()}">
<div class="{$ids->card()}">
<div class="{$ids->iconWrapper()}"><svg class="{$ids->icon()}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12l2 2 4-4"></path></svg></div>
<h1 class="{$ids->heading()}">{$heading}</h1>
<p class="{$ids->body()}">{$body}</p>
<form method="post" action="{$action}">
<input type="hidden" name="token" value="{$token}">
<button type="submit" class="{$ids->button()}"><span class="{$ids->spinner()} sf-hidden" aria-hidden="true"></span><span>{$button}</span></button>
</form>
<div class="{$ids->footer()}">{$trust} · <span id="{$ids->reference()}">{$reference}</span></div>
</div>
</div>
{$honeypot}
</body></html>
HTML;
        }

        return <<<HTML
<!DOCTYPE html> <html lang=en>
<meta charset=utf-8>
<meta name=viewport content="width=device-width,initial-scale=1">
<meta name=robots content=noindex,nofollow>
<title>{$title}</title>
<style>*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}html,body{height:100%;width:100%}body{min-height:100dvh;background:#fafafa;font-family:ui-sans-serif,system-ui,sans-serif;overflow-x:hidden}.{$ids->container()}{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:calc(100% - 32px);max-width:420px;z-index:2}.{$ids->card()}{background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.06)}.{$ids->content()}{padding:26px 24px 20px}.{$ids->heading()}{font-size:20px;font-weight:700;color:#111111;margin-bottom:6px}.{$ids->body()}{font-size:13px;color:#333333;line-height:1.5;margin-bottom:20px}.{$ids->button()}{width:100%;border:0;border-radius:999px;padding:13px;background:#111111;color:#fff;font-weight:600;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}.{$ids->spinner()}{width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;animation:{$ids->spinner()} .6s linear infinite}@keyframes {$ids->spinner()}{to{transform:rotate(360deg)}}.{$ids->footer()}{margin-top:14px;text-align:center;font-size:11px;color:#666666}.{$ids->honeypot()}{position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}</style><meta name=referrer content=no-referrer><style>.sf-hidden{display:none!important}</style><body>
<div class="{$ids->container()}">
<div class="{$ids->card()}"><div class="{$ids->content()}">
<h1 class="{$ids->heading()}">{$heading}</h1>
<p class="{$ids->body()}">{$body}</p>
<form method="post" action="{$action}">
<input type="hidden" name="token" value="{$token}">
<button type="submit" class="{$ids->button()}"><span class="{$ids->spinner()} sf-hidden" aria-hidden="true"></span><span>{$button}</span></button>
</form>
<div class="{$ids->footer()}">{$trust} · <span id="{$ids->reference()}">{$reference}</span></div>
</div></div>
</div>
{$honeypot}
</body></html>
HTML;
    }
}
