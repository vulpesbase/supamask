<?php

namespace Supamask\Challenge;

/**
 * Applies security controls that are specific to the challenge document.
 *
 * The protected application's normal responses are not modified. The policy
 * is generated per challenge response so inline scripts receive a fresh CSP
 * nonce while the document remains self-contained.
 */
final class ChallengeSecurityPolicy
{
    /**
     * @return array{body: string, headers: array<string, string>}
     */
    public function protect(string $html, ?string $nonce = null): array
    {
        $nonce ??= $this->nonce();
        $html = $this->ensureMetadata($html);
        $html = $this->nonceInlineScripts($html, $nonce);

        return [
            'body' => $html,
            'headers' => [
                'Referrer-Policy' => 'no-referrer',
                'X-Robots-Tag' => 'noindex, nofollow',
                'Content-Security-Policy' => $this->contentSecurityPolicy($nonce),
            ],
        ];
    }

    private function ensureMetadata(string $html): string
    {
        if (!preg_match('/<meta\s+[^>]*name=["\']robots["\']/i', $html)) {
            $html = $this->injectHead($html, '<meta name="robots" content="noindex,nofollow">');
        }

        if (!preg_match('/<meta\s+[^>]*name=["\']referrer["\']/i', $html)) {
            $html = $this->injectHead($html, '<meta name="referrer" content="no-referrer">');
        }

        return $html;
    }

    private function injectHead(string $html, string $tag): string
    {
        if (stripos($html, '</head>') !== false) {
            $result = preg_replace('/<\/head>/i', $tag . '</head>', $html, 1);
            return is_string($result) ? $result : $html;
        }

        if (stripos($html, '<head') !== false) {
            $result = preg_replace('/(<head\b[^>]*>)/i', '$1' . $tag, $html, 1);
            return is_string($result) ? $result : $html;
        }

        if (stripos($html, '<html') !== false) {
            $result = preg_replace('/(<html\b[^>]*>)/i', '$1' . $tag, $html, 1);
            return is_string($result) ? $result : $html;
        }

        return $tag . $html;
    }

    private function nonceInlineScripts(string $html, string $nonce): string
    {
        $result = preg_replace_callback(
            '/<script\b([^>]*)>/i',
            static function (array $matches) use ($nonce): string {
                $attributes = $matches[1];

                // Replace an existing nonce so custom presentations cannot
                // accidentally use a stale value from another response.
                $attributes = preg_replace(
                    '/\snonce\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i',
                    '',
                    $attributes,
                ) ?? $attributes;

                return '<script' . $attributes . ' nonce="' . $nonce . '">';
            },
            $html,
        );

        return is_string($result) ? $result : $html;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'none'",
            "base-uri 'none'",
            "object-src 'none'",
            "frame-ancestors 'none'",
            "form-action 'self'",
            "connect-src 'self'",
            "img-src 'none'",
            "font-src 'none'",
            "media-src 'none'",
            "script-src 'nonce-" . $nonce . "'",
            "script-src-attr 'none'",
            "style-src 'unsafe-inline'",
        ]);
    }

    public function nonce(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }
}
