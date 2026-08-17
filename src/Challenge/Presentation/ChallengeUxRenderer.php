<?php

namespace Supamask\Challenge\Presentation;

/**
 * Renders the supplied UX state documents without inventing replacement UI.
 *
 * Preparing, checking, success and retry are presentation-only states.
 * Server-side challenge verification and consumption remain authoritative.
 */
final class ChallengeUxRenderer
{
    public static function preparing(string $challengeHtml): string
    {
        $preparing = self::load('Preparing secure session.html');
        $script = '<script>(function(){var next=' . self::json($challengeHtml) . ';setTimeout(function(){document.open();document.write(next);document.close();},2000);}());</script>';

        return self::injectBeforeEnd($preparing, $script);
    }

    public static function success(): string
    {
        return self::load('Success.htm');
    }

    public static function checking(string $successHtml, string $destination): string
    {
        $checking = self::load('Checking.html');
        $success = self::injectBeforeEnd(
            $successHtml,
            '<script>setTimeout(function(){location.replace(' . self::json($destination) . ');},1000);</script>'
        );

        $script = '<script>(function(){var success=' . self::json($success) . ';setTimeout(function(){document.open();document.write(success);document.close();},2000);}());</script>';

        return self::injectBeforeEnd($checking, $script);
    }

    public static function tryOnceMore(string $challengePath): string
    {
        $retry = self::load('Try once more.html');
        $script = '<script>(function(){var b=document.querySelector("button");if(b){b.addEventListener("click",function(){location.replace('
            . self::json($challengePath)
            . ');});}}());</script>';

        return self::injectBeforeEnd($retry, $script);
    }

    private static function load(string $name): string
    {
        $file = dirname(__DIR__, 3) . '/resources/challenge-ux/' . $name;
        return (string) file_get_contents($file);
    }

    private static function injectBeforeEnd(string $html, string $script): string
    {
        if (stripos($html, '</body>') !== false) {
            return (string) preg_replace('/<\/body>/i', $script . '</body>', $html, 1);
        }

        return $html . $script;
    }

    private static function json(string $value): string
    {
        return (string) json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        );
    }
}
