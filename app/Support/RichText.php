<?php

namespace App\Support;

final class RichText
{
    private const ALLOWED_TAGS = '<p><br><strong><b><em><i><u><ul><ol><li>';

    public static function sanitize(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $value = preg_replace('#<(script|style|iframe|object|embed|svg|math)\b[^>]*>.*?</\1>#is', '', $value);
        $html = strip_tags((string) $value, self::ALLOWED_TAGS);
        $html = preg_replace('/<(p|br|strong|b|em|i|u|ul|ol|li)\b[^>]*>/i', '<$1>', $html);

        return trim((string) $html) ?: null;
    }

    public static function plainText(?string $value): ?string
    {
        $html = self::sanitize($value);

        return $html ? trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : null;
    }
}
