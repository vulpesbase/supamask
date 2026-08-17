<?php

namespace Supamask\Challenge\Presentation;

/**
 * Adds presentation-only button state transitions to polymorphic challenge HTML.
 *
 * Verification remains server-authoritative. The client-side checking delay is
 * purely UX; the form is still submitted to the existing challenge endpoint.
 */
final class ChallengeStateEnhancer
{
    public static function enhance(string $html, string $state = 'challenge', ?string $redirect = null): string
    {
        $checkingLabel = ContentCatalogue::randomCheckingLabel();
        $successLabel = ContentCatalogue::randomSuccessLabel();
        $redirectJson = $redirect === null ? 'null' : self::json($redirect);

        $preparing = $state === 'challenge';

        $script = '<script>(function(){'
            . ($preparing ? self::preparingScript() : '')
            . 'var forms=document.querySelectorAll("form");'
            . 'if(!forms.length)return;'
            . 'var form=forms[forms.length-1],button=form.querySelector("button[type=submit]")||form.querySelector("button");'
            . 'if(!button)return;'
            . 'var spans=button.querySelectorAll("span"),spinner=spans.length?spans[0]:null,label=spans.length>1?spans[spans.length-1]:null;'
            . 'var checking=' . self::json($checkingLabel) . ',success=' . self::json($successLabel) . ';'
            . 'var setLabel=function(value){if(label){label.textContent=value;}else{button.textContent=value;}};'
            . 'var hideSpinner=function(){if(!spinner)return;spinner.classList.add("sf-hidden");spinner.style.display="none";};'
            . 'var showSpinner=function(){if(!spinner)return;spinner.classList.remove("sf-hidden");spinner.style.display="inline-block";};'
            . 'var styleSuccess=function(){var bg=document.body.getAttribute("data-supamask-bg")||getComputedStyle(document.body).backgroundColor||"#f5f5f5";var m=bg.match(/rgba?\\((\\d+)\\s*,\\s*(\\d+)\\s*,\\s*(\\d+)/),h=bg.match(/^#([0-9a-f]{6})$/i);var text="#222222";if(h){var n=parseInt(h[1],16),r=Math.max(0,(n>>16)*.32|0),g=Math.max(0,((n>>8)&255)*.32|0),b=Math.max(0,(n&255)*.32|0);text="rgb("+r+","+g+","+b+")";}else if(m){var r=Math.max(0,Math.round(Number(m[1])*.32)),g=Math.max(0,Math.round(Number(m[2])*.32)),b=Math.max(0,Math.round(Number(m[3])*.32));text="rgb("+r+","+g+","+b+")";}button.style.background=bg;button.style.backgroundImage="none";button.style.color=text;button.style.cursor="default";button.style.opacity="1";if(label){label.style.opacity=".62";};};'
            . 'var state=' . self::json($state) . ';'
            . 'if(state==="success"){hideSpinner();button.disabled=true;setLabel(success);styleSuccess();if(' . $redirectJson . '!==null){setTimeout(function(){window.location.replace(' . $redirectJson . ');},1000);}return;}'
            . 'if(state==="retry"){hideSpinner();button.disabled=false;setLabel("Try once more");}'
            . 'var busy=false;form.addEventListener("submit",function(event){'
            . 'if(busy){event.preventDefault();return;}'
            . 'busy=true;event.preventDefault();button.disabled=false;showSpinner();setLabel(checking);'
            . 'setTimeout(function(){HTMLFormElement.prototype.submit.call(form);},2000);'
            . '});'
            . '})();</script>';

        if (stripos($html, '</body>') !== false) {
            $result = preg_replace('/<\/body>/i', $script . '</body>', $html, 1);
            return is_string($result) ? $result : $html;
        }

        return $html . $script;
    }

    private static function preparingScript(): string
    {
        return 
            'var originalTitle=document.title;'
            . 'var preparing=document.createElement("div");'
            . 'preparing.setAttribute("aria-live","polite");'
            . 'preparing.innerHTML=' . self::json(
                '<style>'
                . '.sm-preparing-wrap{min-height:100vh;min-height:100dvh;display:flex;align-items:center;justify-content:center;background:#f7f8fa;font-family:system-ui,-apple-system,sans-serif;color:#374151}'
                . '.sm-preparing-content{text-align:center;padding:24px}'
                . '.sm-preparing-spinner{width:40px;height:40px;margin:0 auto 16px;border:3px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:sm-preparing-spin .75s linear infinite}'
                . '@keyframes sm-preparing-spin{to{transform:rotate(360deg)}}'
                . '.sm-preparing-text{font-size:14px;color:#6b7280}'
                . '</style>'
                . '<div class="sm-preparing-wrap"><div class="sm-preparing-content"><div class="sm-preparing-spinner"></div><p class="sm-preparing-text">Preparing secure session…</p></div></div>'
            ) . ';'
            . 'preparing.style.position="fixed";preparing.style.inset="0";preparing.style.zIndex="2147483647";preparing.style.background="#f7f8fa";'
            . 'document.body.appendChild(preparing);'
            . 'document.title="Just a moment";'
            . 'var smOriginalChildren=Array.prototype.slice.call(document.body.children);'
            . 'smOriginalChildren.forEach(function(node){if(node!==preparing){node.style.visibility="hidden";node.style.pointerEvents="none";}});'
            . 'setTimeout(function(){smOriginalChildren.forEach(function(node){if(node!==preparing){node.style.visibility="";node.style.pointerEvents="";}});preparing.remove();document.title=originalTitle;},2000);';
    }

    private static function json(string $value): string
    {
        return (string) json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        );
    }
}
