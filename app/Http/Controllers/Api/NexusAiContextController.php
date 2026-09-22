<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GraphQL\PlayNexusGraphService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class NexusAiContextController extends Controller
{
    public function __invoke(Request $request, PlayNexusGraphService $graph): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:800'],
        ]);

        $question = trim($data['question']);
        $terms = $this->terms($question);

        if ($terms === []) {
            return response()->json(['context' => '', 'terms' => []]);
        }

        $key = 'nexus-ai:context:'.sha1(implode('|', $terms));

        $context = Cache::remember($key, now()->addMinute(), function () use ($graph, $terms): string {
            $chunks = [];

            foreach ($terms as $term) {
                try {
                    $result = $graph->execute($this->query(), ['q' => $term]);
                    $data = is_array($result['data'] ?? null) ? $result['data'] : [];
                    $formatted = $this->format($term, $data);

                    if ($formatted !== '') {
                        $chunks[] = $formatted;
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            return Str::limit(implode("\n\n", array_unique($chunks)), 6000, '');
        });

        return response()->json([
            'context' => $context,
            'terms' => $terms,
        ]);
    }

    private function terms(string $question): array
    {
        $normalized = preg_replace('/[\x{200c}\s]+/u', ' ', trim($question)) ?: $question;
        $terms = [];

        if (preg_match_all('/[A-Za-z0-9][A-Za-z0-9:+\'’.-]*(?:\s+[A-Za-z0-9][A-Za-z0-9:+\'’.-]*){0,3}/u', $normalized, $matches)) {
            foreach ($matches[0] as $match) {
                $value = trim($match);
                if (mb_strlen($value) >= 3) {
                    $terms[] = $value;
                }
            }
        }

        $stop = [
            'بازی', 'گیم', 'برای', 'درباره', 'راجع', 'راجب', 'چیه', 'چیست', 'چی',
            'کدوم', 'کدام', 'میشه', 'می‌شه', 'بگو', 'آخرین', 'جدید', 'ویدیو', 'فید',
            'سایت', 'پلی', 'نکسوس', 'playnexus',
        ];

        foreach (preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($normalized)) ?: [] as $token) {
            $token = trim($token);
            if (mb_strlen($token) < 3 || in_array($token, $stop, true)) {
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
      products(first: 3, status: "published", visibility: "public") {
        nodes { title price discountPrice availability url }
      }
      content(first: 5, status: "published", orderBy: "published_at") {
        nodes { type title excerpt publishedAt url }
      }
      collections(first: 3, visibility: "public") {
        nodes { title description videoCount url }
      }
      events(first: 4, status: "active", minImportance: 50) {
        nodes { type title summary importanceScore sourceName detectedAt }
      }
    }
  }
  publishedGames: games(search: $q, status: "published", first: 4) {
    nodes {
      name slug description developer publisher releaseDate url
      studio { name }
      platforms { name }
      products(first: 3, status: "published", visibility: "public") {
        nodes { title price discountPrice availability url }
      }
      content(first: 5, status: "published", orderBy: "published_at") {
        nodes { type title excerpt publishedAt url }
      }
      collections(first: 3, visibility: "public") {
        nodes { title description videoCount url }
      }
      events(first: 4, status: "active", minImportance: 50) {
        nodes { type title summary importanceScore sourceName detectedAt }
      }
    }
  }
  studios(search: $q, status: "active", first: 3) {
    nodes { name description website url gameCount collectionCount }
  }
  products(search: $q, status: "published", visibility: "public", first: 4) {
    nodes { title shortDescription price discountPrice availability condition url game { name } brand { name } platforms { name } }
  }
  contents(search: $q, status: "published", first: 5, orderBy: "published_at") {
    nodes { type title excerpt publishedAt url game { name } }
  }
  collections(search: $q, visibility: "public", first: 3) {
    nodes { title description videoCount url game { name } studio { name } }
  }
  radar(search: $q, first: 4) {
    nodes {
      title status releaseDate developer publisher gameUrl
      xbox { available platforms }
      playstation { available platforms }
    }
  }
}
GRAPHQL;
    }

    private function format(string $term, array $data): string
    {
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

        $hasResults = collect($payload)
            ->except('query')
            ->contains(fn ($items) => is_array($items) && $items !== []);

        if (! $hasResults) {
            return '';
        }

        return 'PLAYNEXUS LIVE CONTEXT: '.json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
    }
}
