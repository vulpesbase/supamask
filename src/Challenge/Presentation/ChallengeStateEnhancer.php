<?php

namespace Supamask\Challenge\Presentation;

/**
 * Adds presentation-only button state transitions and the optional client-side
 * proof-of-work solver. Verification remains server-authoritative.
 */
final class ChallengeStateEnhancer
{
    public static function enhance(
        string $html,
        string $state = 'challenge',
        ?string $redirect = null,
        ?string $nonce = null,
        ?string $powNonce = null,
        ?int $powDifficulty = null,
    ): string {
        $checkingLabel = ContentCatalogue::randomCheckingLabel();
        $nonceAttribute = $nonce !== null ? ' nonce="' . htmlspecialchars($nonce, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';
        $successLabel = ContentCatalogue::randomSuccessLabel();
        $redirectJson = $redirect === null ? 'null' : self::json($redirect);
        $powEnabled = $powNonce !== null && $powDifficulty !== null;
        $powNonceJson = $powEnabled ? self::json($powNonce) : 'null';
        $powDifficultyJson = $powEnabled ? (string) $powDifficulty : 'null';

        $html = self::ensurePowField($html, $powEnabled);
        $preparing = $state === 'challenge';

        $preparingMarkup = $preparing ? self::preparingMarkup() : '';

        $script = '<script' . $nonceAttribute . '>(function(){'
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
            . 'var styleSuccess=function(){var bg=document.body.getAttribute("data-supamask-bg")||getComputedStyle(document.body).backgroundColor||"#f5f5f5";var m=bg.match(/rgba?\\((\\d+)\\s*,\\s*(\\d+)\\s*,\\s*(\\d+)/),h=bg.match(/^#([0-9a-f]{6})$/i),text="#222222";if(h){var n=parseInt(h[1],16),r=Math.max(0,(n>>16)*.32|0),g=Math.max(0,((n>>8)&255)*.32|0),b=Math.max(0,(n&255)*.32|0);text="rgb("+r+","+g+","+b+")";}else if(m){var r=Math.max(0,Math.round(Number(m[1])*.32)),g=Math.max(0,Math.round(Number(m[2])*.32)),b=Math.max(0,Math.round(Number(m[3])*.32));text="rgb("+r+","+g+","+b+")";}button.style.background=bg;button.style.backgroundImage="none";button.style.color=text;button.style.cursor="default";button.style.opacity="1";if(label){label.style.opacity=".62";}};'
            . 'var state=' . self::json($state) . ';'
            . 'if(state==="success"){hideSpinner();button.disabled=true;setLabel(success);styleSuccess();if(' . $redirectJson . '!==null){setTimeout(function(){window.location.replace(' . $redirectJson . ');},1000);}return;}'
            . 'if(state==="retry"){hideSpinner();button.disabled=false;setLabel("Try once more");}'
            . 'var busy=false;form.addEventListener("submit",function(event){'
            . 'if(busy){event.preventDefault();return;}'
            . 'busy=true;event.preventDefault();button.disabled=true;showSpinner();setLabel(checking);'
            . 'var submit=function(){HTMLFormElement.prototype.submit.call(form);};'
            . ($powEnabled ? 'var nonce=' . $powNonceJson . ',difficulty=' . $powDifficultyJson . ',tokenInput=form.querySelector("input[name=token]"),powInput=form.querySelector("input[name=pow_counter]");'
                . 'if(!window.crypto||!window.crypto.subtle||!window.TextEncoder||!tokenInput||!powInput){busy=false;button.disabled=false;hideSpinner();setLabel("Try once more");return;}'
                . 'var meets=function(bytes,bits){var full=Math.floor(bits/8),rem=bits%8,i;if(bits<=0)return true;for(i=0;i<full;i++){if(bytes[i]!==0)return false;}return rem===0||((bytes[full]>>(8-rem))===0);};'
                . 'var solve=function(){var encoder=new TextEncoder(),counter=0,batch=32,token=tokenInput.value;var next=function(){var jobs=[],start=counter,i;for(i=0;i<batch;i++){(function(value){var data=encoder.encode(nonce+":"+token+":"+value);jobs.push(crypto.subtle.digest("SHA-256",data).then(function(buffer){return {counter:value,bytes:new Uint8Array(buffer)};}));})(String(start+i));}counter+=batch;return Promise.all(jobs).then(function(results){for(var j=0;j<results.length;j++){if(meets(results[j].bytes,difficulty)){return results[j].counter;}}if(counter>10000000)throw new Error("proof-of-work search exhausted");return next();});};return next();};'
                . 'solve().then(function(value){powInput.value=value;submit();}).catch(function(){busy=false;button.disabled=false;hideSpinner();setLabel("Try once more");});'
                : 'setTimeout(submit,2000);')
            . '});'
            . '})();</script>';

        if (stripos($html, '</body>') !== false) {
            $result = preg_replace('/<\/body>/i', $preparingMarkup . $script . '</body>', $html, 1);
            return is_string($result) ? $result : $html;
        }

        return $html . $preparingMarkup . $script;
    }

    private static function ensurePowField(string $html, bool $enabled): string
    {
        if (!$enabled || stripos($html, 'name="pow_counter"') !== false || stripos($html, "name='pow_counter'") !== false) {
            return $html;
        }

        $field = '<input type="hidden" name="pow_counter" value="">';
        $result = preg_replace('/(<form\b[^>]*>)/i', '$1' . $field, $html, 1);

        return is_string($result) ? $result : $html;
    }

    private static function preparingMarkup(): string
    {
        return '<div id="sm-preparing-screen" class="sm-preparing-wrap" aria-live="polite">'
            . '<div class="sm-preparing-content"><div class="sm-preparing-spinner" aria-hidden="true"></div>'
            . '<p class="sm-preparing-text">Preparing secure session…</p></div></div>'
            . '<style>.sm-preparing-wrap{position:fixed;inset:0;z-index:2147483647;display:flex;align-items:center;justify-content:center;background:#f7f8fa;font-family:system-ui,-apple-system,sans-serif;color:#374151}.sm-preparing-content{text-align:center;padding:24px}.sm-preparing-spinner{width:40px;height:40px;margin:0 auto 16px;border:3px solid #e5e7eb;border-top-color:#3b82f6;border-radius:50%;animation:sm-preparing-spin .75s linear infinite}@keyframes sm-preparing-spin{to{transform:rotate(360deg)}}.sm-preparing-text{font-size:14px;color:#6b7280}</style>';
    }

    private static function preparingScript(): string
    {
        return 'var preparing=document.getElementById("sm-preparing-screen");'
            . 'if(preparing){setTimeout(function(){preparing.remove();},2000);}';
    }

    private static function json(string $value): string
    {
        return (string) json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        );
    }
}
