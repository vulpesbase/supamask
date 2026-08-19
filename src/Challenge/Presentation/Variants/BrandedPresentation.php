<?php

namespace Supamask\Challenge\Presentation\Variants;

use Supamask\Challenge\Presentation\ChallengePresentationVariant;
use Supamask\Challenge\Presentation\ChallengeViewData;
use Supamask\Challenge\Presentation\HoneypotRenderer;

/**
 * Branded reference-family presentation.
 *
 * Matches the supplied 440px card references with the dark top accent,
 * shield header, rounded rectangular CTA and split trust/reference footer.
 */
final class BrandedPresentation implements ChallengePresentationVariant
{
    public function render(ChallengeViewData $data): string
    {
        $escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $title = $escape($data->title());
        $heading = $escape($data->heading());
        $body = $escape($data->body());
        $button = $escape($data->buttonLabel());
        $eyebrow = $escape($data->eyebrow() !== '' ? $data->eyebrow() : 'Browser check');
        $trust = $escape($data->trustFooter());
        $reference = $escape($data->referenceCode());
        $action = $escape($data->action());
        $token = $escape($data->verificationToken());
        $ids = $data->identifiers();
        $honeypot = HoneypotRenderer::render($ids, $data->honeypot());


        return <<<HTML
<!DOCTYPE html> <html lang=en>
<meta charset=utf-8>
<meta name=viewport content="width=device-width,initial-scale=1">
<meta name=robots content=noindex,nofollow>
<title>{$title}</title>
<style>*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}html,body{height:100%;width:100%}body{min-height:100dvh;background:#f5f5f5;font-family:Inter,ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,sans-serif;color:#111111;overflow-x:hidden}.{$ids->container()}{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:calc(100% - 32px);max-width:440px;z-index:2}.{$ids->card()}{position:relative;z-index:1;background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;box-shadow:0 10px 40px rgba(15,23,42,.06);overflow:hidden}.{$ids->divider()}{height:3px;background:#111111}.{$ids->content()}{padding:28px 26px 22px}.{$ids->iconWrapper()}{display:flex;align-items:center;gap:12px;margin-bottom:18px}.{$ids->icon()}{width:42px;height:42px;border-radius:12px;background:#111111;display:flex;align-items:center;justify-content:center;flex-shrink:0}.{$ids->icon()} svg{width:20px;height:20px;color:#fff}.{$ids->eyebrow()}{font-size:11px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#555555}.{$ids->heading()}{font-size:22px;font-weight:700;letter-spacing:-.03em;line-height:1.25;margin-bottom:8px;color:#111111}.{$ids->body()}{font-size:14px;line-height:1.6;color:#333333;margin-bottom:22px}.{$ids->button()}{width:100%;border:0;border-radius:12px;padding:14px 16px;background:#111111;color:#fff;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;transition:transform .12s,filter .12s}.{$ids->button()}:hover{filter:brightness(1.15)}.{$ids->button()}:active{transform:scale(.985)}.{$ids->spinner()}{width:15px;height:15px;border-radius:50%;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;animation:{$ids->spinner()} .65s linear infinite}@keyframes {$ids->spinner()}{to{transform:rotate(360deg)}}.{$ids->footer()}{margin-top:16px;display:flex;justify-content:space-between;gap:12px;font-size:11px;color:#666666}.{$ids->honeypot()}{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;overflow:hidden}</style><meta name=referrer content=no-referrer><style>.sf-hidden{display:none!important}</style><body>
<div class="{$ids->container()}">
<div class="{$ids->card()}">
<div class="{$ids->divider()}"></div>
<div class="{$ids->content()}">
<div class="{$ids->iconWrapper()}">
<div class="{$ids->icon()}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg></div>
<span class="{$ids->eyebrow()}">{$eyebrow}</span>
</div>
<h1 class="{$ids->heading()}">{$heading}</h1>
<p class="{$ids->body()}">{$body}</p>
<form method="post" action="{$action}">
<input type="hidden" name="token" value="{$token}">
<button type="submit" class="{$ids->button()}"><span class="{$ids->spinner()} sf-hidden" aria-hidden="true"></span><span>{$button}</span></button>
</form>
<div class="{$ids->footer()}"><span>{$trust}</span><span>ref <span id="{$ids->reference()}">{$reference}</span></span></div>
</div>
</div>
</div>
{$honeypot}
</body></html>
HTML;
    }
}
