<?php

namespace Supamask\Challenge\Presentation;

/**
 * Same-URL client-side UX states using the supplied HTML files as canonical
 * visual templates. Timers are presentation-only; backend verification stays
 * authoritative.
 */
final class ChallengeUxStateRenderer
{
    public static function preparing(string $challengeHtml): string
    {
        $preparing = self::template('preparing.html');
        $encoded = self::json($challengeHtml);
        $script = '<script>setTimeout(function(){document.open();document.write('.$encoded.');document.close();},2000);</script>';
        return self::appendBeforeEnd($preparing, $script);
    }

    /**
     * Adds the visual state machine to an otherwise exact challenge document.
     * Normal form submission remains the no-JavaScript fallback.
     */
    public static function enhanceChallenge(
        string $challengeHtml,
        string $action,
        string $token,
        string $destination,
    ): string {
        $checking = self::template('checking.html');
        $success = self::template('success.html');
        $retry = self::template('try-once-more.html');

        $checkingJson = self::json($checking);
        $successJson = self::json(self::withRedirect($success, $destination));
        $retryHtml = self::wireButton($retry, $action, $token);
        $retryJson = self::json($retryHtml);
        $actionJson = self::json($action);
        $tokenJson = self::json($token);

        $script = '<script>(function(){'
            .'var forms=document.querySelectorAll("form");'
            .'if(!forms.length)return;'
            .'forms.forEach(function(form){form.addEventListener("submit",function(event){'
            .'event.preventDefault();'
            .'var body=new URLSearchParams(new FormData(form)).toString();'
            .'var action='.$actionJson.';'
            .'document.open();document.write('.$checkingJson.');document.close();'
            .'setTimeout(function(){fetch(action,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:body,credentials:"same-origin",redirect:"follow"})'
            .'.then(function(response){if(!response.ok&&!response.redirected)throw new Error("verification failed");document.open();document.write('.$successJson.');document.close();})'
            .'.catch(function(){document.open();document.write('.$retryJson.');document.close();});'
            .'},2000);'
            .'});});'
            .'})();</script>';

        return self::appendBeforeEnd($challengeHtml, $script);
    }

    public static function checking(string $successHtml, string $destination): string
    {
        return self::withRedirect(self::template('checking.html'), $destination);
    }

    public static function tryAgain(string $action, string $token): string
    {
        return self::wireButton(self::template('try-once-more.html'), $action, $token);
    }

    public static function success(string $destination): string
    {
        return self::withRedirect(self::template('success.html'), $destination);
    }

    private static function withRedirect(string $html, string $destination): string
    {
        return self::appendBeforeEnd(
            $html,
            '<script>setTimeout(function(){location.replace('.self::json($destination).');},800);</script>'
        );
    }

    private static function template(string $name): string
    {
        $path = __DIR__.'/Templates/'.$name;
        $html = file_get_contents($path);
        if ($html === false) {
            throw new \RuntimeException('Unable to load challenge UX template: '.$name);
        }
        return $html;
    }

    private static function wireButton(string $html, string $action, string $token): string
    {
        $action = htmlspecialchars($action, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $token = htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $result = preg_replace_callback(
            "#(<button\\b)([^>]*?)\\btype=([\"']?)button\\3([^>]*)>(.*?)</button>#is",
            static fn(array $m): string => '<form method="post" action="'.$action.'" style="display:contents"><input type="hidden" name="token" value="'.$token.'">'.$m[1].$m[2].'type="submit"'.$m[4].'>'.$m[5].'</button></form>',
            $html,
            1
        );
        return is_string($result) ? $result : $html;
    }

    private static function appendBeforeEnd(string $html, string $content): string
    {
        $pos = stripos($html, '</body>');
        if ($pos === false) {
            return $html.$content;
        }
        return substr($html, 0, $pos).$content.substr($html, $pos);
    }

    private static function json(string $value): string
    {
        return (string) json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    }
}
