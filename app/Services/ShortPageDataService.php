<?php

namespace App\Services;

use App\Models\Game;
use App\Models\SocialContent;
use App\Models\User;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ShortPageDataService
{
    public function __construct(
        private readonly StorefrontDataService $data,
        private readonly StorefrontPageCache $cache,
    ) {}

    public function get(SocialContent $content, ?User $user): array
    {
        $payload = $this->cache->remember(
            'short',
            $content->id,
            fn () => $this->build($content),
        );

        return $this->withLiveState($payload, $user);
    }

    private function build(SocialContent $content): array
    {
        $content->load([
            'game:id,name,slug,cover,background,developer,publisher',
            'game.playlists' => fn ($query) => $query
                ->publiclyVisible()
                ->whereNotNull('logo')
                ->select(['id', 'game_id', 'logo', 'sort_order']),
            'media',
        ]);

        $related = SocialContent::query()
            ->published()
            ->where('type', 'short')
            ->whereKeyNot($content->id)
            ->with(['game:id,name,slug,cover', 'media'])
            ->when(
                $content->game_id,
                fn (Builder $query) => $query->orderByRaw('CASE WHEN game_id = ? THEN 0 ELSE 1 END', [$content->game_id]),
            )
            ->latest('published_at')
            ->limit(12)
            ->get()
            ->map(fn (SocialContent $item) => $this->staticContent($this->data->content($item)))
            ->values()
            ->all();

        $excerpt = RichText::plainText($content->excerpt);
        if (! $excerpt) {
            $excerpt = $this->fallbackDescription($content);
        }

        $contentData = [
            ...$this->staticContent($this->data->content($content)),
            'excerpt' => $excerpt,
            'body' => RichText::sanitize($content->body),
            'video_mime' => $content->video_mime,
            'media_type' => $content->media_type,
            'allow_comments' => (bool) $content->allow_comments,
            'dislikes_count' => 0,
            'user_reaction' => null,
        ];

        $breadcrumbs = [
            [
                'name' => 'صفحه اصلی',
                'url' => route('home', absolute: false),
                'current' => false,
            ],
            [
                'name' => $content->title,
                'url' => route('content.show', ['type' => 'shorts', 'content' => $content->slug], false),
                'current' => true,
            ],
        ];

        return [
            'content' => $contentData,
            'channel' => $content->game ? [
                'id' => $content->game->id,
                'name' => $content->game->name,
                'slug' => $content->game->slug,
                'url' => route('channels.show', $content->game->slug, false),
                'avatar_url' => MediaStorage::url(
                    $content->game->cover ?: $content->game->playlists->first()?->logo,
                ),
                'subscribers_count' => 0,
                'is_subscribed' => false,
            ] : null,
            'comments' => null,
            'related' => $related,
            'playlist' => null,
            'breadcrumbs' => $breadcrumbs,
            'seo_input' => $this->seoInput($content, $breadcrumbs),
        ];
    }

    private function withLiveState(array $payload, ?User $user): array
    {
        $contentId = (int) data_get($payload, 'content.id', 0);

        $live = SocialContent::query()
            ->whereKey($contentId)
            ->withCount([
                'reactions as likes_count' => fn (Builder $query) => $query->where('type', 'like'),
                'reactions as dislikes_count' => fn (Builder $query) => $query->where('type', 'dislike'),
                'comments as comments_count' => fn (Builder $query) => $query->published(),
            ])
            ->first(['id', 'views']);

        $payload['content']['views'] = (int) ($live?->views ?? 0);
        $payload['content']['likes_count'] = (int) ($live?->likes_count ?? 0);
        $payload['content']['dislikes_count'] = (int) ($live?->dislikes_count ?? 0);
        $payload['content']['comments_count'] = (int) ($live?->comments_count ?? 0);
        $payload['content']['user_reaction'] = $user
            ? DB::table('social_content_reactions')
                ->where('social_content_id', $contentId)
                ->where('user_id', $user->id)
                ->value('type')
            : null;
        $payload['content']['is_liked'] = $payload['content']['user_reaction'] === 'like';

        if (is_array($payload['channel'] ?? null)) {
            $gameId = (int) ($payload['channel']['id'] ?? 0);
            $game = Game::query()->find($gameId);

            if ($game) {
                $payload['channel']['subscribers_count'] = $game->subscribers()->count();
                $payload['channel']['is_subscribed'] = (bool) ($user
                    ? $game->subscribers()->whereKey($user->id)->exists()
                    : false);
            }
        }

        $relatedIds = collect($payload['related'] ?? [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($relatedIds->isNotEmpty()) {
            $views = SocialContent::query()->whereKey($relatedIds)->pluck('views', 'id');
            $payload['related'] = collect($payload['related'])
                ->map(function (array $item) use ($views): array {
                    $id = (int) ($item['id'] ?? 0);

                    return [
                        ...$item,
                        'views' => (int) ($views[$id] ?? 0),
                    ];
                })
                ->values()
                ->all();
        }

        $payload = [
            ...$this->seo($payload['seo_input'], (int) ($payload['content']['views'] ?? 0)),
            ...collect($payload)->except('seo_input')->all(),
        ];

        return $payload;
    }

    private function staticContent(array $content): array
    {
        return [
            ...$content,
            'views' => 0,
            'likes_count' => 0,
            'comments_count' => 0,
            'is_liked' => false,
        ];
    }

    private function seo(array $input, int $views): array
    {
        $graph = data_get($input, 'structuredData.@graph', []);

        foreach ($graph as $index => $entity) {
            if (($entity['@type'] ?? null) === 'VideoObject') {
                $graph[$index]['interactionStatistic']['userInteractionCount'] = $views;
                break;
            }
        }

        data_set($input, 'structuredData.@graph', $graph);

        return Seo::page($input);
    }

    private function seoInput(SocialContent $content, array $breadcrumbs): array
    {
        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $locale = (string) config('seo.locale', 'fa-IR');
        $canonical = route('content.show', ['type' => 'shorts', 'content' => $content->slug]);
        $logo = url((string) config('seo.default_image', '/logo.png'));
        $isImage = $content->media_type === 'image';

        $primaryVideoMedia = $content->media->first(fn ($media) => $media->type === 'video');
        $primaryImageMedia = $content->media->first(fn ($media) => $media->type === 'image');

        $imagePath = $content->thumbnail
            ?: ($isImage ? $content->video_path : null)
            ?: $primaryVideoMedia?->thumbnail
            ?: $primaryImageMedia?->path;
        $imageUrl = MediaStorage::url($imagePath);
        $imageUrl = $imageUrl ? url($imageUrl) : null;

        $videoPath = $isImage ? null : ($content->video_path ?: $primaryVideoMedia?->path);
        $videoUrl = MediaStorage::url($videoPath);
        $videoUrl = $videoUrl ? url($videoUrl) : null;

        $summary = RichText::plainText($content->seo_description ?: $content->excerpt ?: $content->body)
            ?: $this->fallbackDescription($content);
        $description = Str::limit($summary, 160, '…');
        $title = filled($content->seo_title)
            ? trim($content->seo_title)
            : Str::limit("{$content->title} | {$siteName}", 60, '…');
        $organizationId = route('home').'#organization';

        $entity = $isImage
            ? [
                '@type' => 'Article',
                '@id' => $canonical.'#story',
                'headline' => $content->title,
                'description' => $description,
                'url' => $canonical,
                'datePublished' => $content->published_at?->toISOString(),
                'inLanguage' => $locale,
                'publisher' => ['@id' => $organizationId],
                ...($imageUrl ? ['image' => [$imageUrl]] : []),
                ...($content->game ? ['about' => ['@type' => 'VideoGame', 'name' => $content->game->name]] : []),
            ]
            : [
                '@type' => 'VideoObject',
                '@id' => $canonical.'#video',
                'name' => $content->title,
                'description' => $description,
                'url' => $canonical,
                'uploadDate' => $content->published_at?->toISOString(),
                'inLanguage' => $locale,
                'publisher' => ['@id' => $organizationId],
                'interactionStatistic' => [
                    '@type' => 'InteractionCounter',
                    'interactionType' => ['@type' => 'WatchAction'],
                    'userInteractionCount' => 0,
                ],
                ...($imageUrl ? ['thumbnailUrl' => [$imageUrl]] : []),
                ...($videoUrl ? ['contentUrl' => $videoUrl] : []),
                ...($content->duration ? ['duration' => $this->isoDuration((int) $content->duration)] : []),
                ...($content->game ? ['about' => ['@type' => 'VideoGame', 'name' => $content->game->name]] : []),
            ];

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'type' => $isImage ? 'article' : 'video.other',
            'siteName' => $siteName,
            'locale' => $locale,
            'image' => $imageUrl ?: $logo,
            'imageAlt' => $imageUrl ? $content->title : "لوگوی {$siteName}",
            ...($videoUrl ? ['video' => [
                'url' => $videoUrl,
                'type' => $content->video_mime,
                'duration' => $content->duration,
            ]] : []),
            'structuredData' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Organization',
                        '@id' => $organizationId,
                        'name' => $siteName,
                        'url' => route('home'),
                        'logo' => ['@type' => 'ImageObject', 'url' => $logo],
                    ],
                    $entity,
                    [
                        '@type' => 'BreadcrumbList',
                        '@id' => $canonical.'#breadcrumb',
                        'itemListElement' => collect($breadcrumbs)->map(fn (array $crumb, int $index) => [
                            '@type' => 'ListItem',
                            'position' => $index + 1,
                            'name' => $crumb['name'],
                            'item' => url($crumb['url']),
                        ])->all(),
                    ],
                ],
            ],
        ];
    }

    private function fallbackDescription(SocialContent $content): string
    {
        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $kind = $content->media_type === 'image' ? 'استوری تصویری' : 'ویدیوی کوتاه';
        $parts = ["«{$content->title}» یک {$kind} گیمینگ در {$siteName} است."];

        if ($content->game) {
            $parts[] = "این محتوا به بازی {$content->game->name} مرتبط است و می‌توانید محتوای بیشتر این بازی را در کانال آن دنبال کنید.";
        } else {
            $parts[] = "این محتوا بخشی از استوری‌ها و محتوای کوتاه پلی نکسوس برای دنبال‌کردن دنیای گیمینگ است.";
        }

        if ($content->media_type !== 'image' && $content->duration && $content->duration > 0) {
            $parts[] = "مدت این ویدیو {$content->duration} ثانیه است.";
        }

        return implode(' ', $parts);
    }

    private function isoDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return 'PT'.($hours ? "{$hours}H" : '').($minutes ? "{$minutes}M" : '')."{$remainingSeconds}S";
    }
}
