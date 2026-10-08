<?php

namespace App\Support;

use App\Services\MediaStorage;

final class StoryRichText
{
    private const TAGS = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><blockquote><a><hr><figure><figcaption><img>';

    public static function imagePath(?string $value): ?string
    {
        $value = trim((string) $value);
        // MCP attachments are also eligible for inline placement.
        return preg_match('#^(game-stories/inline|content-assets/game_story)/[a-f0-9-]{36}\.(webp|png|jpg|jpeg|gif)$#i', $value)
            ? $value : null;
    }

    public static function sanitize(?string $input): ?string
    {
        if (blank($input)) {
            return null;
        }

        $input = preg_replace('#<(script|style|iframe|object|embed|svg|math|form|button|template)\b[^>]*>.*?</\1>#is', '', $input);
        $html = strip_tags((string) $input, self::TAGS);

        $html = preg_replace_callback('/<(\/)?([a-z0-9]+)\b([^>]*)>/i', function (array $match): string {
            $closing = $match[1] === '/';
            $tag = strtolower($match[2]);
            if ($closing) {
                return in_array($tag, ['img', 'hr', 'br'], true) ? '' : "</{$tag}>";
            }

            if ($tag === 'img') {
                $src = self::attribute($match[3], 'src');
        // MediaStorage::url('') returns null; generate the public prefix from a known directory.
                $base = MediaStorage::url('game-stories/inline/');
                $base = $base ? substr($base, 0, -strlen('game-stories/inline/')) : '';
                $path = $base !== '' && str_starts_with($src, $base) ? substr($src, strlen($base)) : '';
                if (! self::imagePath($path)) {
                    return '';
                }
                $alt = mb_substr(self::attribute($match[3], 'alt'), 0, 180);
                return '<img src="'.self::escape($base.$path).'" alt="'.self::escape($alt).'" loading="lazy" decoding="async">';
            }

            if ($tag === 'a') {
                $href = trim(html_entity_decode(self::attribute($match[3], 'href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (! preg_match('#^(https?://|/(?!/)|\#|mailto:)#i', $href) || preg_match('/[\x00-\x1f]/', $href)) {
                    return '<a>';
                }
                return '<a href="'.self::escape($href).'" rel="noopener noreferrer">';
            }

            return "<{$tag}>";
        }, $html);

        return trim((string) $html) ?: null;
    }

    private static function attribute(string $attributes, string $name): string
    {
        if (preg_match('/(?:^|\s)'.preg_quote($name, '/').'\s*=\s*(["\'])(.*?)\1/is', $attributes, $match)) {
            return html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return '';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

}
