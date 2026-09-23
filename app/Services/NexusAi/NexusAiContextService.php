<?php

namespace App\Services\NexusAi;

use App\Services\GraphQL\PlayNexusGraphService;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class NexusAiContextService
{
    private const MAX_CONTEXT_CHARS = 6000;

    public function __construct(private readonly PlayNexusGraphService $graph)
    {
    }

    public function build(string $question): array
    {
        $terms = $this->terms($question);
        if ($terms === []) {
            return ['context' => '', 'terms' => []];
        }

        $cacheTerms = $terms;
        sort($cacheTerms, SORT_NATURAL | SORT_FLAG_CASE);
        $key = 'nexus-ai:context:'.sha1(implode('|', $cacheTerms));

        $context = Cache::remember($key, now()->addMinute(), function () use ($terms): string {
            $chunks = [];
            $usedChars = 0;

            foreach ($terms as $term) {
                try {
                    $result = $this->graph->execute($this->query(), ['q' => $term]);
                    $data = is_array($result['data'] ?? null) ? $result['data'] : [];
                    $remaining = self::MAX_CONTEXT_CHARS - $usedChars - ($chunks === [] ? 0 : 2);
                    $formatted = $this->format($term, $data, max(0, $remaining));

                    if ($formatted !== '' && ! in_array($formatted, $chunks, true)) {
                        $chunks[] = $formatted;
                        $usedChars += mb_strlen($formatted) + (count($chunks) > 1 ? 2 : 0);
                    }

                    if ($usedChars >= self::MAX_CONTEXT_CHARS) {
                        break;
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            return implode("\n\n", $chunks);
        });

        return ['context' => $context, 'terms' => $terms];
    }

    private function terms(string $question): array
    {
        $normalized = preg_replace('/[\x{200c}\s]+/u', ' ', trim($question)) ?: $question;
        $terms = [];

        if (preg_match_all('/[A-Za-z0-9][A-Za-z0-9:+\'’.-]*/u', $normalized, $matches)) {
            $latinStop = [
                'a', 'an', 'and', 'are', 'for', 'from', 'game', 'games', 'in', 'is', 'of',
                'on', 'or', 'the', 'to', 'vs', 'with', 'xbox', 'playstation', 'ps4', 'ps5',
                'pc', 'dlc', 'fps', 'vrr', 'rt',
            ];
            $sequence = [];

            foreach ($matches[0] as $match) {
                $value = trim($match);
                $lower = mb_strtolower($value);

                if (mb_strlen($value) < 2 || in_array($lower, $latinStop, true)) {
                    if ($sequence !== []) {
                        $terms[] = implode(' ', array_slice($sequence, 0, 4));
                        $sequence = [];
                    }
                    continue;
                }

                $sequence[] = $value;
                if (count($sequence) === 4) {
                    $terms[] = implode(' ', $sequence);
                    $sequence = [];
                }
            }

            if ($sequence !== []) {
                $terms[] = implode(' ', $sequence);
            }
        }

        $stop = [
            'بازی', 'گیم', 'برای', 'درباره', 'راجع', 'راجب', 'چیه', 'چیست', 'چی',
            'کدوم', 'کدام', 'میشه', 'می‌شه', 'بگو', 'آخرین', 'جدید', 'ویدیو', 'فید',
            'سایت', 'پلی', 'نکسوس', 'playnexus', 'بهتره', 'بهتر', 'ارزش', 'خرید',
            'جهان', 'باز', 'هست', 'هستش', 'است', 'روی', 'بدون', 'اسپویل', 'پیشنهاد',
            'پیشنهادش', 'کن', 'کنم', 'کردن', 'نسخه', 'آپدیت', 'تغییر', 'تغییرات',
            'چطور', 'چطوره', 'اجرا', 'مقایسه', 'بین', 'مثل', 'شبیه', 'ساعت', 'وقت',
        ];

        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($normalized)) ?: [] as $token) {
            $token = trim($token);
            if (
                mb_strlen($token) < 3
                || preg_match('/^[a-z0-9]+$/i', $token)
                || in_array($token, $stop, true)
            ) {
                continue;
            }
            $terms[] = $token;
        }

        return array_slice(array_values(array_unique($terms)), 0, 2);
    }

    private function query(): string
    {
        return <<<'GRAPHQL'
query NexusAiContext($q: String!) {
  activeGames: games(search: $q, status: "active", first: 4) {
    nodes {
      name slug description developer publisher releaseDate url
      studio { name }
      platforms { name }
      products(first: 3, status: "published", visibility: "public") { nodes { title price discountPrice availability url } }
      content(first: 5, status: "published", orderBy: "published_at") { nodes { type title excerpt publishedAt url } }
      collections(first: 3, visibility: "public") { nodes { title description videoCount url } }
      events(first: 4, status: "active", minImportance: 50) { nodes { type title summary importanceScore sourceName detectedAt } }
    }
  }
  publishedGames: games(search: $q, status: "published", first: 4) {
    nodes {
      name slug description developer publisher releaseDate url
      studio { name }
      platforms { name }
      products(first: 3, status: "published", visibility: "public") { nodes { title price discountPrice availability url } }
      content(first: 5, status: "published", orderBy: "published_at") { nodes { type title excerpt publishedAt url } }
      collections(first: 3, visibility: "public") { nodes { title description videoCount url } }
      events(first: 4, status: "active", minImportance: 50) { nodes { type title summary importanceScore sourceName detectedAt } }
    }
  }
  studios(search: $q, status: "active", first: 3) { nodes { name description website url gameCount collectionCount } }
  products(search: $q, status: "published", visibility: "public", first: 4) { nodes { title shortDescription price discountPrice availability condition url game { name } brand { name } platforms { name } } }
  contents(search: $q, status: "published", first: 5, orderBy: "published_at") { nodes { type title excerpt publishedAt url game { name } } }
  collections(search: $q, visibility: "public", first: 3) { nodes { title description videoCount url game { name } studio { name } } }
  radar(search: $q, first: 4) { nodes { title status releaseDate developer publisher gameUrl xbox { available platforms } playstation { available platforms } } }
}
GRAPHQL;
    }

    private function format(string $term, array $data, int $maxChars): string
    {
        if ($maxChars <= 0) {
            return '';
        }

        $payload = [
            'query' => $term,
            'games' => array_values(array_unique([
                ...($data['activeGames']['nodes'] ?? []),
                ...($data['publishedGames']['nodes'] ?? []),
            ], SORT_REGULAR)),
            'studios' => $data['studios']['nodes'] ?? [],
            'products' => $data['products']['nodes'] ?? [],
            'content' => $data['contents']['nodes'] ?? [],
            'collections' => $data['collections']['nodes'] ?? [],
            'radar' => $data['radar']['nodes'] ?? [],
        ];

        $hasResults = collect($payload)->except('query')->contains(
            fn ($items) => is_array($items) && $items !== [],
        );
        if (! $hasResults) {
            return '';
        }

        $prefix = 'PLAYNEXUS LIVE CONTEXT: ';
        $priority = ['radar', 'collections', 'content', 'products', 'studios', 'games'];

        while (true) {
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (is_string($encoded) && mb_strlen($prefix.$encoded) <= $maxChars) {
                return $prefix.$encoded;
            }

            $trimmed = false;
            foreach ($priority as $key) {
                if (isset($payload[$key]) && is_array($payload[$key]) && $payload[$key] !== []) {
                    array_pop($payload[$key]);
                    $trimmed = true;
                    break;
                }
            }

            if (! $trimmed) {
                return '';
            }
        }
    }
}
