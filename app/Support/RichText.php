<?php

namespace App\Support;

final class RichText
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a><hr>';

    public static function sanitize(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = preg_replace('#<(script|style|iframe|object|embed|svg|math)\b[^>]*>.*?</\1>#is', '', $value);
        $html = strip_tags((string) $value, self::ALLOWED_TAGS);
        $html = preg_replace_callback('/<(\/)?([a-z0-9]+)\b([^>]*)>/i', function (array $match): string {
            $closing = $match[1] === '/';
            $tag = strtolower($match[2]);

            if ($closing) {
                return in_array($tag, ['br', 'hr'], true) ? '' : "</{$tag}>";
            }

            if ($tag !== 'a') {
                return "<{$tag}>";
            }

            if (! preg_match('/\bhref\s*=\s*(["\'])(.*?)\1/i', $match[3], $hrefMatch)) {
                return '<a>';
            }

            $href = trim(html_entity_decode($hrefMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (! preg_match('#^(https?://|/|\#|mailto:)#i', $href)) {
                return '<a>';
            }

            return '<a href="'.htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'" rel="noopener noreferrer">';
        }, $html);

        return trim((string) $html) ?: null;
    }

    public static function plainText(?string $value): ?string
    {
        $html = self::sanitize($value);

        return $html ? trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : null;
    }
}
