<?php

namespace Supamask\Challenge\Presentation\Variants;

use Supamask\Challenge\Presentation\ChallengePresentationVariant;
use Supamask\Challenge\Presentation\ChallengeViewData;

/**
 * Seven additional reference-derived visual compositions (variants 8-14).
 *
 * These are presentation-only variants. They share the same challenge form,
 * token and state handling as the existing variants.
 */
final class ExtendedPresentation implements ChallengePresentationVariant
{
    public function __construct(private int $variant)
    {
        if ($variant < 8 || $variant > 14) {
            throw new \InvalidArgumentException('Extended presentation variant must be between 8 and 14.');
        }
    }

    public function render(ChallengeViewData $data): string
    {
        $e = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $title = $e($data->title());
        $heading = $e($data->heading());
        $body = $e($data->body());
        $button = $e($data->buttonLabel());
        $trust = $e($data->trustFooter());
        $ref = $e($data->referenceCode());
        $action = $e($data->action());
        $token = $e($data->verificationToken());
        $eyebrow = $e($data->eyebrow());
        $i = $data->identifiers();
        $rand = static fn (): string => bin2hex(random_bytes(6));
        $h1 = $rand(); $h2 = $rand(); $h3 = $rand();

        $presets = [
            8 => [
                'bg' => '#ecfdf5', 'font' => 'Manrope,ui-sans-serif,system-ui,sans-serif', 'color' => '#064e3b',
                'max' => '396px', 'card' => 'background:#fff;border:1px solid #a7f3d0;border-radius:26px;padding:27px 21px 23px;box-shadow:0 13px 39px rgba(5,100,70,.08)',
                'icon' => 'width:48px;height:48px;border-radius:14px;background:#059669;color:#fff;display:grid;place-items:center;margin-bottom:16px',
                'button' => 'border-radius:10px;padding:15px;background:#059669;color:#fff;font-size:14px;font-weight:700',
                'heading' => 'font-size:21px;font-weight:700;letter-spacing:-.03em;color:#064e3b;margin-bottom:8px',
                'body' => 'font-size:14px;line-height:1.55;color:#335f52;margin-bottom:22px',
                'footer' => 'margin-top:16px;font-size:11px;color:#047857;font-family:ui-monospace,monospace',
                'eyebrow' => 'font-size:12px;font-weight:600;color:#047857;margin-bottom:12px',
                'strip' => '',
            ],
            9 => [
                'bg' => '#dbeafe', 'font' => 'Outfit,ui-sans-serif,system-ui,sans-serif', 'color' => '#0c4a6e',
                'max' => '415px', 'card' => 'display:flex;background:#fff;border-radius:15px;overflow:hidden;border:1px solid #bae6fd;box-shadow:0 19px 42px rgba(14,80,140,.09)',
                'icon' => 'width:8px;flex-shrink:0;background:linear-gradient(180deg,#0369a1 0%,#7dd3fc 100%)',
                'button' => 'border-radius:11px;padding:16px;background:#0369a1;color:#fff;font-size:14px;font-weight:600',
                'heading' => 'font-size:21px;font-weight:700;letter-spacing:-.03em;color:#0c4a6e;margin-bottom:8px',
                'body' => 'font-size:14px;line-height:1.55;color:#365b70;margin-bottom:22px',
                'footer' => 'margin-top:14px;text-align:center;font-size:11px;color:#0369a1',
                'eyebrow' => 'display:flex;align-items:center;gap:7px;font-size:12px;font-weight:600;color:#0369a1;margin-bottom:12px',
                'strip' => 'width:10px;flex-shrink:0',
            ],
            10 => [
                'bg' => '#ddeef8', 'font' => 'Outfit,ui-sans-serif,system-ui,sans-serif', 'color' => '#0c1924',
                'max' => '410px', 'card' => 'display:flex;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #d5e4ef;box-shadow:0 18px 48px rgba(30,80,120,.11)',
                'icon' => 'width:10px;flex-shrink:0;background:linear-gradient(180deg,#0284c7 0%,#38bdf8 100%)',
                'button' => 'border-radius:8px;padding:16px;background:#0284c7;color:#fff;font-size:14px;font-weight:600',
                'heading' => 'font-size:21px;font-weight:700;letter-spacing:-.03em;color:#0c1924;margin-bottom:8px',
                'body' => 'font-size:14px;line-height:1.55;color:#40576a;margin-bottom:22px',
                'footer' => 'margin-top:14px;text-align:center;font-size:11px;color:#52738b',
                'eyebrow' => 'display:flex;align-items:center;gap:7px;font-size:12px;font-weight:600;color:#0284c7;margin-bottom:12px',
                'strip' => 'width:10px;flex-shrink:0',
            ],
            11 => [
                'bg' => '#e0e7ff', 'font' => 'Outfit,ui-sans-serif,system-ui,sans-serif', 'color' => '#1e3a5f',
                'max' => '420px', 'card' => 'display:flex;background:#fff;border-radius:18px;overflow:hidden;border:1px solid #c7d2fe;box-shadow:0 19px 49px rgba(37,99,235,.10)',
                'icon' => 'width:9px;flex-shrink:0;background:linear-gradient(180deg,#2563eb 0%,#818cf8 100%)',
                'button' => 'border-radius:12px;padding:16px;background:#2563eb;color:#fff;font-size:14px;font-weight:600',
                'heading' => 'font-size:21px;font-weight:700;letter-spacing:-.03em;color:#1e3a5f;margin-bottom:8px',
                'body' => 'font-size:14px;line-height:1.55;color:#475569;margin-bottom:22px',
                'footer' => 'margin-top:14px;text-align:center;font-size:11px;color:#64748b',
                'eyebrow' => 'display:flex;align-items:center;gap:7px;font-size:12px;font-weight:600;color:#1d4ed8;margin-bottom:12px',
                'strip' => 'width:9px;flex-shrink:0',
            ],
            12 => [
                'bg' => '#f0fdfa', 'font' => 'Manrope,ui-sans-serif,system-ui,sans-serif', 'color' => '#134e4a',
                'max' => '385px', 'card' => 'background:#fff;border:1px solid #99f6e4;border-radius:20px;padding:25px 22px 22px;box-shadow:0 12px 37px rgba(13,100,90,.09)',
                'icon' => 'width:48px;height:48px;border-radius:17px;background:#0d9488;color:#fff;display:grid;place-items:center;margin-bottom:16px',
                'button' => 'border-radius:14px;padding:15px;background:#0d9488;color:#fff;font-size:14px;font-weight:700',
                'heading' => 'font-size:21px;font-weight:700;letter-spacing:-.03em;color:#134e4a;margin-bottom:8px',
                'body' => 'font-size:14px;line-height:1.55;color:#365f5a;margin-bottom:22px',
                'footer' => 'margin-top:16px;font-size:11px;color:#0f766e;font-family:ui-monospace,monospace',
                'eyebrow' => 'display:flex;align-items:center;gap:7px;font-size:12px;font-weight:600;color:#0f766e;margin-bottom:12px',
                'strip' => '',
            ],
            13 => [
                'bg' => '#f0fdfa', 'font' => 'Manrope,ui-sans-serif,system-ui,sans-serif', 'color' => '#134e4a',
                'max' => '385px', 'card' => 'background:#fff;border:1px solid #99f6e4;border-radius:23px;padding:30px 21px 23px;box-shadow:0 14px 35px rgba(13,100,90,.09)',
                'icon' => 'width:48px;height:48px;border-radius:13px;background:#0d9488;color:#fff;display:grid;place-items:center;margin-bottom:16px',
                'button' => 'border-radius:13px;padding:13px;background:#0d9488;color:#fff;font-size:14px;font-weight:700',
                'heading' => 'font-size:21px;font-weight:700;letter-spacing:-.03em;color:#134e4a;margin-bottom:8px',
                'body' => 'font-size:14px;line-height:1.55;color:#365f5a;margin-bottom:22px',
                'footer' => 'margin-top:16px;font-size:11px;color:#0f766e;font-family:ui-monospace,monospace',
                'eyebrow' => 'display:flex;align-items:center;gap:7px;font-size:12px;font-weight:600;color:#0f766e;margin-bottom:12px',
                'strip' => '',
            ],
            14 => [
                'bg' => '#ddeef8', 'font' => 'Outfit,ui-sans-serif,system-ui,sans-serif', 'color' => '#0c1924',
                'max' => '414px', 'card' => 'display:flex;background:#fff;border-radius:14px;overflow:hidden;border:1px solid #d5e4ef;box-shadow:0 15px 41px rgba(30,80,120,.11)',
                'icon' => 'width:6px;flex-shrink:0;background:linear-gradient(180deg,#0284c7 0%,#38bdf8 100%)',
                'button' => 'border-radius:10px;padding:14px;background:#0284c7;color:#fff;font-size:14px;font-weight:600',
                'heading' => 'font-size:21px;font-weight:700;letter-spacing:-.03em;color:#0c1924;margin-bottom:8px',
                'body' => 'font-size:14px;line-height:1.55;color:#40576a;margin-bottom:22px',
                'footer' => 'margin-top:14px;text-align:center;font-size:11px;color:#52738b',
                'eyebrow' => 'display:flex;align-items:center;gap:7px;font-size:12px;font-weight:600;color:#0284c7;margin-bottom:12px',
                'strip' => 'width:6px;flex-shrink:0',
            ],
        ][$this->variant];

        $split = $this->variant >= 9 && $this->variant !== 12 && $this->variant !== 13;
        $iconSvg = '<svg class="'.$i->icon().'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><path d="M9 12l2 2 4-4"></path></svg>';

        $strip = $split ? '<div class="'.$i->divider().'" style="'.$presets['strip'].'"></div>' : '';
        $icon = $split ? '' : '<div class="'.$i->iconWrapper().'" style="'.$presets['icon'].'">'.$iconSvg.'</div>';
        $eyebrowIcon = $split ? '<span style="width:8px;height:8px;border-radius:50%;background:currentColor;display:inline-block"></span>' : '';

        return '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>'.$title.'</title><style>'
            .'*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}html,body{height:100%;width:100%}body{min-height:100dvh;font-family:'.$presets['font'].';color:'.$presets['color'].';overflow-x:hidden;background:'.$presets['bg'].'}.'.$i->container().'{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);width:calc(100% - 32px);max-width:'.$presets['max'].';z-index:2}.'.$i->card().'{'.$presets['card'].'}.'.$i->content().'{padding:24px 24px 22px;flex:1;min-width:0}.'.$i->heading().'{'.$presets['heading'].'}.'.$i->body().'{'.$presets['body'].'}.'.$i->button().'{width:100%;border:0;'.$presets['button'].';cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px}.'.$i->spinner().'{width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;animation:'.$i->spinner().' .68s linear infinite;flex-shrink:0}@keyframes '.$i->spinner().'{to{transform:rotate(360deg)}}.'.$i->footer().'{'.$presets['footer'].'}.'.$i->eyebrow().'{'.$presets['eyebrow'].'}.'.$i->honeypot().'{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none;overflow:hidden}.'.$i->icon().'{width:22px;height:22px;color:#fff}</style><style>.sf-hidden{display:none!important}</style><body data-supamask-bg="'.$presets['bg'].'"><div class="'.$i->container().'" id="'.$h1.'">'.$strip.'<div class="'.$i->card().'">'.$icon.'<div class="'.$i->content().'">'
            .'<div class="'.$i->eyebrow().'">'.$eyebrowIcon.$eyebrow.'</div><h1 class="'.$i->heading().'">'.$heading.'</h1><p class="'.$i->body().'">'.$body.'</p><form class="'.$i->form().'" method="post" action="'.$action.'"><input type="hidden" name="token" value="'.$token.'"><button type="submit" class="'.$i->button().'"><span class="'.$i->spinner().' sf-hidden" aria-hidden="true"></span><span>'.$button.'</span></button></form><div class="'.$i->footer().'">'.$trust.' · <span id="'.$h2.'">'.$ref.'</span></div></div></div><div class="'.$i->honeypot().'" id="'.$h3.'"></div></div></body></html>';
    }
}
