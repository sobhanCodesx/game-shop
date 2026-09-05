<?php

namespace App\Support;

use JsonException;

final class Seo
{
    /**
     * Build the structured page data used by React and the server-rendered
     * fallback head used by crawlers that do not execute JavaScript.
     *
     * @param  array<string, mixed>  $data
     * @return array{seo: array<string, mixed>, head: list<string>}
     *
     * @throws JsonException
     */
    public static function page(array $data): array
    {
        $title = preg_replace('/\bplay\s*nexus\b/iu', 'پلی نکسوس', (string) $data['title'])
            ?? (string) $data['title'];

        $seo = [
            'title' => $title,
            'description' => (string) $data['description'],
            'canonical' => (string) $data['canonical'],
            'robots' => (string) ($data['robots'] ?? 'index, follow'),
            'type' => (string) ($data['type'] ?? 'website'),
            'siteName' => (string) ($data['siteName'] ?? config('seo.site_name')),
            'locale' => (string) ($data['locale'] ?? config('seo.locale')),
            'image' => (string) $data['image'],
            'imageAlt' => (string) ($data['imageAlt'] ?? $data['title']),
            'absoluteTitle' => (bool) ($data['absoluteTitle'] ?? true),
            'structuredData' => $data['structuredData'] ?? null,
        ];

        if (isset($data['heading'])) {
            $seo['heading'] = (string) $data['heading'];
        }
        if (isset($data['video']) && is_array($data['video'])) {
            $seo['video'] = $data['video'];
        }

        return [
            'seo' => $seo,
            'head' => self::head($seo),
        ];
    }

    /**
     * @param  array<string, mixed>  $seo
     * @return list<string>
     *
     * @throws JsonException
     */
    private static function head(array $seo): array
    {
        $tags = [
            self::element('title', 'title', (string) $seo['title']),
            self::meta('description', 'name', 'description', (string) $seo['description']),
            self::meta('robots', 'name', 'robots', (string) $seo['robots']),
            self::link('canonical', (string) $seo['canonical']),
            self::meta('og:type', 'property', 'og:type', (string) $seo['type']),
            self::meta('og:title', 'property', 'og:title', (string) $seo['title']),
            self::meta('og:description', 'property', 'og:description', (string) $seo['description']),
            self::meta('og:url', 'property', 'og:url', (string) $seo['canonical']),
            self::meta('og:site_name', 'property', 'og:site_name', (string) $seo['siteName']),
            self::meta('og:locale', 'property', 'og:locale', str_replace('-', '_', (string) $seo['locale'])),
            self::meta('og:image', 'property', 'og:image', (string) $seo['image']),
            self::meta('og:image:alt', 'property', 'og:image:alt', (string) $seo['imageAlt']),
            self::meta('twitter:card', 'name', 'twitter:card', 'summary_large_image'),
            self::meta('twitter:title', 'name', 'twitter:title', (string) $seo['title']),
            self::meta('twitter:description', 'name', 'twitter:description', (string) $seo['description']),
            self::meta('twitter:image', 'name', 'twitter:image', (string) $seo['image']),
            self::meta('twitter:image:alt', 'name', 'twitter:image:alt', (string) $seo['imageAlt']),
        ];

        if (is_array($seo['structuredData'])) {
            $json = json_encode(
                $seo['structuredData'],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
            );
            $tags[] = '<script data-inertia="structured-data" type="application/ld+json">'.$json.'</script>';
        }

        if (isset($seo['video']) && is_array($seo['video'])) {
            $tags[] = self::meta('og:video', 'property', 'og:video', (string) $seo['video']['url']);
            if (filled($seo['video']['type'] ?? null)) {
                $tags[] = self::meta('og:video:type', 'property', 'og:video:type', (string) $seo['video']['type']);
            }
            if (filled($seo['video']['duration'] ?? null)) {
                $tags[] = self::meta('og:video:duration', 'property', 'og:video:duration', (string) $seo['video']['duration']);
            }
        }

        return $tags;
    }

    private static function element(string $key, string $tag, string $content): string
    {
        return sprintf(
            '<%1$s data-inertia="%2$s">%3$s</%1$s>',
            $tag,
            self::escape($key),
            self::escape($content),
        );
    }

    private static function meta(string $key, string $attribute, string $name, string $content): string
    {
        return sprintf(
            '<meta data-inertia="%s" %s="%s" content="%s">',
            self::escape($key),
            $attribute,
            self::escape($name),
            self::escape($content),
        );
    }

    private static function link(string $key, string $href): string
    {
        return sprintf(
            '<link data-inertia="%s" rel="canonical" href="%s">',
            self::escape($key),
            self::escape($href),
        );
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
