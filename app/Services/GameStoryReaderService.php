<?php

namespace App\Services;

/**
 * Creates permanent, server-renderable chapter targets without changing stored
 * manuscripts. The body is sanitized on save by StoryRichText.
 */
final class GameStoryReaderService
{
    /**
     * @return array{html: string, chapters: list<array{id: string, label: string, level: int}>, wordCount: int}
     */
    public function prepare(string $html): array
    {
        $chapters = [];
        $count = 0;
        $html = (string) preg_replace_callback(
            '~<(h[23])>(.*?)</\1>~isu',
            static function (array $matches) use (&$chapters, &$count): string {
                $label = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($matches[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($label === '') {
                    return $matches[0];
                }
                $count++;
                $id = 'chapter-'.$count;
                $chapters[] = ['id' => $id, 'label' => $label, 'level' => (int) substr($matches[1], 1)];

                return '<'.$matches[1].' id="'.$id.'">'.$matches[2].'</'.$matches[1].'>';
            },
            $html,
        );

        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $words = $text === '' ? [] : (preg_split('/\s+/u', $text) ?: []);

        return ['html' => $html, 'chapters' => $chapters, 'wordCount' => count($words)];
    }
}
