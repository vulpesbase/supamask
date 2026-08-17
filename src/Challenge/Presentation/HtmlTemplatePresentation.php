<?php

namespace Supamask\Challenge\Presentation;

/**
 * Renders one of the supplied SingleFile HTML references without redesigning
 * its visual structure. Only functional values and render-scoped identifiers
 * are replaced.
 */
final class HtmlTemplatePresentation implements ChallengePresentationVariant
{
    public function __construct(private string $templatePath)
    {
    }

    public function render(ChallengeViewData $data): string
    {
        $html = file_get_contents($this->templatePath);
        if ($html === false) {
            throw new \RuntimeException('Unable to load presentation template.');
        }

        $html = $this->randomizeIdentifiers($html);
        $html = $this->replaceTagContent($html, 'title', $this->escape($data->title()));
        $html = $this->replaceTagContent($html, 'h1', $this->escape($data->heading()));
        $html = $this->replaceFirstParagraph($html, $this->escape($data->body()));
        $html = $this->replaceButtonLabel($html, $this->escape($data->buttonLabel()));
        $html = $this->replaceReferenceFooter($html, $this->escape($data->trustFooter()), $this->escape($data->referenceCode()));
        $html = $this->wireChallengeForm($html, $this->escape($data->action()), $this->escape($data->verificationToken()));

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function replaceTagContent(string $html, string $tag, string $value): string
    {
        $result = preg_replace(
            '#(<'.preg_quote($tag, '#').'\b[^>]*>).*?(</'.preg_quote($tag, '#').'>)#is',
            '$1'.$value.'$2',
            $html,
            1
        );
        return is_string($result) ? $result : $html;
    }

    private function replaceFirstParagraph(string $html, string $value): string
    {
        return $this->replaceTagContent($html, 'p', $value);
    }

    private function replaceButtonLabel(string $html, string $value): string
    {
        $result = preg_replace_callback(
            '#(<button\b[^>]*>)(.*?)(</button>)#is',
            static function (array $m) use ($value): string {
                $inner = $m[2];
                $spanPattern = '#(<span\b[^>]*>).*?(</span>)#is';
                preg_match_all($spanPattern, $inner, $spans, PREG_OFFSET_CAPTURE);
                if (count($spans[0]) >= 2) {
                    $last = $spans[0][count($spans[0]) - 1];
                    $start = $last[1];
                    $chunk = $last[0];
                    $chunk = preg_replace($spanPattern, '$1'.$value.'$2', $chunk, 1);
                    if (is_string($chunk)) {
                        $inner = substr($inner, 0, $start).$chunk.substr($inner, $start + strlen($last[0]));
                    }
                } else {
                    $inner = $value;
                }
                return $m[1].$inner.$m[3];
            },
            $html,
            1
        );
        return is_string($result) ? $result : $html;
    }

    private function replaceReferenceFooter(string $html, string $trust, string $reference): string
    {
        // Supplied references use a small footer containing a visible trust
        // phrase and an 8-character ref. Preserve its typography and layout.
        $result = preg_replace_callback(
            '#(<div\b[^>]*>)(\s*)([^<]{3,40})(\s*[·•]\s*<span\b[^>]*>).*?(</span>)(\s*</div>)#is',
            static function (array $m) use ($trust, $reference): string {
                return $m[1].$m[2].$trust.$m[4].$reference.$m[5].$m[6];
            },
            $html,
            1
        );

        if (is_string($result) && $result !== $html) {
            return $result;
        }

        // Fallback for split trust/ref layouts such as "Privacy first" + ref.
        $result = preg_replace_callback(
            '#(<div\b[^>]*>)(\s*<span>)[^<]*(</span>\s*<span[^>]*>ref\s*<span\b[^>]*>).*?(</span>)(\s*</span>\s*</div>)#is',
            static fn(array $m): string => $m[1].$m[2].$trust.$m[3].$reference.$m[4].$m[5],
            $html,
            1
        );

        return is_string($result) ? $result : $html;
    }

    private function wireChallengeForm(string $html, string $action, string $token): string
    {
        $result = preg_replace_callback(
            "#(<button\\b)([^>]*?)\\btype=([\"\']?)button\\3([^>]*)>(.*?)</button>#is",
            static function (array $m) use ($action, $token): string {
                $attrs = $m[1].$m[2].'type="submit"'.$m[4];
                $button = $attrs.'>'.$m[5].'</button>';
                return '<form method="post" action="'.$action.'" style="display:contents">'
                    .'<input type="hidden" name="token" value="'.$token.'">'
                    .$button.'</form>';
            },
            $html,
            1
        );
        return is_string($result) ? $result : $html;
    }

    private function randomizeIdentifiers(string $html): string
    {
        $map = [];

        $html = preg_replace_callback(
            '#\bclass=(?:(["\'])(.*?)\1|([^\s>]+))#is',
            static function (array $m) use (&$map): string {
                $value = $m[2] !== '' ? $m[2] : $m[3];
                $tokens = preg_split('/\s+/', trim($value)) ?: [];
                $new = [];
                foreach ($tokens as $token) {
                    if ($token === '') continue;
                    $map[$token] ??= self::identifier();
                    $new[] = $map[$token];
                }
                return 'class="'.implode(' ', $new).'"';
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '#\bid=(?:(["\'])(.*?)\1|([^\s>]+))#is',
            static function (array $m) use (&$map): string {
                $value = $m[2] !== '' ? $m[2] : $m[3];
                $map[$value] ??= self::identifier();
                return 'id="'.$map[$value].'"';
            },
            $html
        ) ?? $html;

        foreach ($map as $old => $new) {
            $html = str_replace('.'.$old, '.'.$new, $html);
        }

        return $html;
    }

    private static function identifier(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $value = $alphabet[random_int(0, 25)];
        for ($i = 1; $i < 16; $i++) {
            $value .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $value;
    }
}
