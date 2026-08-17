<?php

namespace Supamask\Challenge\Presentation;

use Supamask\Challenge\ChallengePresentationInterface;
use RuntimeException;

/**
 * Renders the supplied HTML reference designs verbatim, with only the
 * functional values Supamask must inject (copy, reference code, token,
 * action) and render-scoped class/id randomization.
 */
final class HtmlChallengePresentation implements ChallengePresentationInterface
{
    private const TEMPLATE_MAP = [
        1 => '1.html',
        2 => '2.html',
        3 => '3.html',
        8 => '8.html',
        9 => '9.html',
        10 => '10.html',
        11 => '11.html',
        12 => '12.html',
        13 => '13.html',
        14 => '14.htm',
    ];

    public function render(array $context): string
    {
        foreach (['id', 'token', 'action'] as $key) {
            if (!isset($context[$key]) || !is_string($context[$key]) || $context[$key] === '') {
                throw new \InvalidArgumentException("Challenge context must include: {$key}");
            }
        }

        $template = $this->loadTemplate($this->selectTemplate());
        $template = $this->randomizeIdentifiers($template);

        $title = $context['title'] ?? ContentCatalogue::randomTitle();
        $heading = $context['heading'] ?? ContentCatalogue::randomHeading();
        $body = $context['message'] ?? ContentCatalogue::randomBody();
        $button = $context['button'] ?? ContentCatalogue::randomButtonLabel();
        $trust = $context['trust_footer'] ?? ContentCatalogue::randomTrustFooter();
        $ref = $context['referenceCode'] ?? ReferenceCodeGenerator::generate();

        $template = $this->replaceFirst($template, '/<title\b[^>]*>.*?<\/title>/is', '<title>' . $this->escape($title) . '</title>');
        $template = $this->replaceFirst($template, '/(<h1\b[^>]*>).*?(<\/h1>)/is', '$1' . $this->escape($heading) . '$2');
        $template = $this->replaceFirst($template, '/(<p\b[^>]*>).*?(<\/p>)/is', '$1' . $this->escape($body) . '$2');

        // Replace the visible button label while preserving the supplied button markup.
        $template = preg_replace(
            '/(<button\b[^>]*>.*?<span\b[^>]*>).*?(<\/span>\s*<\/button>)/is',
            '$1' . $this->escape($button) . '$2',
            $template,
            1
        ) ?? $template;

        // Replace only the trust text immediately preceding an 8-character ref span.
        $template = preg_replace(
            '/(<div\b[^>]*>)[^<]{1,80}([·|])(\s*<span\b[^>]*>)[A-Z0-9]{8}(<\/span>\s*<\/div>)/i',
            '$1' . $this->escape($trust) . ' $2$3' . $this->escape($ref) . '$4',
            $template,
            1
        ) ?? $template;

        // Turn the supplied CTA into the real Supamask POST operation without
        // changing its visual styling. A hidden token is submitted alongside it.
        $template = $this->wireButton($template, $context['action'], $context['token']);

        return $template;
    }

    private function selectTemplate(): int
    {
        $keys = array_keys(self::TEMPLATE_MAP);
        return $keys[(int) (random_int(0, PHP_INT_MAX) % count($keys))];
    }

    private function loadTemplate(int $number): string
    {
        $file = dirname(__DIR__, 3) . '/resources/challenge-ux/' . self::TEMPLATE_MAP[$number];

        if (!is_file($file)) {
            throw new RuntimeException('Supplied challenge template is missing: ' . self::TEMPLATE_MAP[$number]);
        }

        return (string) file_get_contents($file);
    }

    private function randomizeIdentifiers(string $html): string
    {
        $map = [];

                $html = preg_replace_callback(
            '/\b(class|id)=(?:(["\'])(.*?)\2|([^\s>]+))/i',
            static function (array $m) use (&$map): string {
                $quote = $m[2] ?? '';
                $value = $quote !== '' ? ($m[3] ?? '') : ($m[4] ?? '');
                $tokens = preg_split('/\s+/', trim($value)) ?: [];
                foreach ($tokens as &$token) {
                    if ($token === '') {
                        continue;
                    }
                    $map[$token] ??= self::identifier();
                    $token = $map[$token];
                }
                $value = implode(' ', $tokens);
                return $m[1] . '=' . ($quote !== '' ? $quote . $value . $quote : $value);
            },
            $html
        ) ?? $html;

        // Re-map every CSS .class and #id selector corresponding to a
        // class/id attribute. This preserves the supplied design exactly.
        foreach ($map as $old => $new) {
            $html = preg_replace('/(?<![A-Za-z0-9_-])\.' . preg_quote($old, '/') . '(?![A-Za-z0-9_-])/', '.' . $new, $html) ?? $html;
            $html = preg_replace('/(?<![A-Za-z0-9_-])#' . preg_quote($old, '/') . '(?![A-Za-z0-9_-])/', '#' . $new, $html) ?? $html;
        }

        return $html;
    }

    private static function identifier(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $value = $alphabet[random_int(0, 25)];
        for ($i = 0; $i < 15; $i++) {
            $value .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $value;
    }

    private function wireButton(string $html, string $action, string $token): string
    {
        $action = $this->escape($action);
        $token = $this->escape($token);

        $replacement = '<form method="post" action="' . $action . '" style="margin:0">'
            . '<input type="hidden" name="token" value="' . $token . '">'
            . '$0'
            . '</form>';

        return preg_replace('/<button\b[^>]*>.*?<\/button>/is', $replacement, $html, 1) ?? $html;
    }

    private function replaceFirst(string $subject, string $pattern, string $replacement): string
    {
        return preg_replace($pattern, $replacement, $subject, 1) ?? $subject;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
