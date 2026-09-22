<?php

namespace App\Http\Controllers;

use App\Models\SocialComment;
use App\Models\SocialContent;
use App\Models\VideoPlaylist;
use App\Services\ContentViewService;
use App\Services\MediaStorage;
use App\Services\ShortPageDataService;
use App\Services\StorefrontDataService;
use App\Services\VideoCommunityService;
use App\Services\VideoPageDataService;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SocialContentController extends Controller
{
    public function show(Request $request, string $type, SocialContent $content, StorefrontDataService $data, VideoCommunityService $community, ContentViewService $views, ShortPageDataService $shortPage, VideoPageDataService $videoPage): Response
    {
        $expectedType = match ($type) {
            'posts' => 'post', 'videos' => 'video', 'shorts' => 'short', default => abort(404),
        };

        abort_unless($content->type === $expectedType && $content->status === 'published' && $content->published_at?->isPast(), 404);

        if (in_array($content->type, ['video', 'short'], true)) {
            $views->record($request, $content);
        }

        if ($content->type === 'short') {
            return Inertia::render('Content/ShortShow', $shortPage->get($content, $request->user()));
        }

        if ($content->type === 'video') {
            return $this->videoResponse($request, $content, $community, $videoPage);
        }

        $content->load([
            'game:id,name,slug,cover,background,developer,publisher',
            'game.playlists' => fn ($query) => $query->publiclyVisible()->whereNotNull('logo')->select(['id', 'game_id', 'logo', 'sort_order']),
            'user:id,name,avatar',
            'media',
        ]);
        $reactionCounts = $content->reactions()->selectRaw('type, COUNT(*) as aggregate')->groupBy('type')->pluck('aggregate', 'type');
        $userReaction = $request->user()
            ? $content->reactions()->where('user_id', $request->user()->id)->value('type')
            : null;

        $comments = null;
        if ($content->type === 'video' && $content->allow_comments) {
            $commentQuery = SocialComment::query()->published()->whereBelongsTo($content, 'content')->whereNull('parent_id')
                ->with('user:id,name,avatar')
                ->withCount('likedBy');

            $request->string('comment_sort')->toString() === 'newest'
                ? $commentQuery->latest()
                : $commentQuery->orderByDesc('liked_by_count')->latest();

            $likedCommentIds = $request->user()
                ? $request->user()->belongsToMany(SocialComment::class, 'social_comment_likes')->pluck('social_comments.id')->all()
                : [];
            $comments = $commentQuery->paginate(20, ['*'], 'comments_page')->withQueryString();
            $community->loadCommentReplies($content, $comments->getCollection());
            $comments->through(fn (SocialComment $comment) => $this->commentData($comment, $request, $likedCommentIds));
        }

        $related = SocialContent::query()->published()->where('type', $content->type)->whereKeyNot($content->id)
            ->with(['game:id,name,slug,cover', 'media'])
            ->when($content->game_id, fn (Builder $query) => $query->orderByRaw('CASE WHEN game_id = ? THEN 0 ELSE 1 END', [$content->game_id]))
            ->latest('published_at')->limit(12)->get()->map(fn (SocialContent $item) => $data->content($item));

        $playlist = $this->playlistContext($request, $content, $data);
        $breadcrumbs = $this->breadcrumbs($content, $type, $playlist);
        $seo = $this->seo($content, $type, $breadcrumbs);
        $excerpt = RichText::plainText($content->excerpt);

        if (! $excerpt && $content->type === 'short') {
            $excerpt = $this->shortFallbackDescription($content);
        }

        return Inertia::render($content->type === 'short' ? 'Content/ShortShow' : 'Content/Show', [
            ...$seo,
            'content' => [
                ...$data->content($content),
                'excerpt' => $excerpt,
                'body' => RichText::sanitize($content->body),
                'video_mime' => $content->video_mime,
                'allow_comments' => $content->allow_comments,
                'likes_count' => (int) ($reactionCounts['like'] ?? 0),
                'dislikes_count' => (int) ($reactionCounts['dislike'] ?? 0),
                'user_reaction' => $userReaction,
                'comments_count' => $content->comments()->published()->count(),
            ],
            'channel' => $content->game ? [
                'id' => $content->game->id,
                'name' => $content->game->name,
                'slug' => $content->game->slug,
                'url' => route('channels.show', $content->game->slug, false),
                'avatar_url' => MediaStorage::url($content->game->cover ?: $content->game->playlists->first()?->logo),
                'subscribers_count' => $content->game->subscribers()->count(),
                'is_subscribed' => $request->user()
                    ? $content->game->subscribers()->whereKey($request->user()->id)->exists()
                    : false,
            ] : null,
            'comments' => $comments,
            'related' => $related,
            'playlist' => $playlist,
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    private function videoResponse(
        Request $request,
        SocialContent $content,
        VideoCommunityService $community,
        VideoPageDataService $videoPage,
    ): Response {
        $pageData = $videoPage->withLiveCardMetrics(
            $videoPage->get($content, $request->string('list')->toString()),
        );

        $reactionCounts = $content->reactions()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $userReaction = $request->user()
            ? $content->reactions()->where('user_id', $request->user()->id)->value('type')
            : null;

        $comments = null;
        if ($content->allow_comments) {
            $commentQuery = SocialComment::query()
                ->published()
                ->whereBelongsTo($content, 'content')
                ->whereNull('parent_id')
                ->with('user:id,name,avatar')
                ->withCount('likedBy');

            $request->string('comment_sort')->toString() === 'newest'
                ? $commentQuery->latest()
                : $commentQuery->orderByDesc('liked_by_count')->latest();

            $likedCommentIds = $request->user()
                ? $request->user()->belongsToMany(SocialComment::class, 'social_comment_likes')->pluck('social_comments.id')->all()
                : [];

            $comments = $commentQuery->paginate(20, ['*'], 'comments_page')->withQueryString();
            $community->loadCommentReplies($content, $comments->getCollection());
            $comments->through(fn (SocialComment $comment) => $this->commentData($comment, $request, $likedCommentIds));
        }

        $channel = $pageData['channel'];
        if (is_array($channel) && $content->game_id) {
            $game = $content->game()->withCount('subscribers')->first(['games.id']);
            $channel = [
                ...$channel,
                'subscribers_count' => (int) ($game?->subscribers_count ?? 0),
                'is_subscribed' => (bool) ($request->user() && $game
                    ? $game->subscribers()->whereKey($request->user()->id)->exists()
                    : false),
            ];
        }

        $views = (int) SocialContent::query()->whereKey($content->id)->value('views');
        $seo = $videoPage->seo($pageData['seo_input'], $views);

        return Inertia::render('Content/Show', [
            ...$seo,
            'content' => [
                ...$pageData['content'],
                'views' => $views,
                'allow_comments' => (bool) $content->allow_comments,
                'likes_count' => (int) ($reactionCounts['like'] ?? 0),
                'dislikes_count' => (int) ($reactionCounts['dislike'] ?? 0),
                'user_reaction' => $userReaction,
                'is_liked' => $userReaction === 'like',
                'comments_count' => $content->comments()->published()->count(),
            ],
            'channel' => $channel,
            'comments' => $comments,
            'related' => $pageData['related'],
            'playlist' => $pageData['playlist'],
            'breadcrumbs' => $pageData['breadcrumbs'],
        ]);
    }

    /** @param array<int, array{name: string, url: string, current: bool}> $breadcrumbs */
    private function seo(SocialContent $content, string $routeType, array $breadcrumbs): array
    {
        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $locale = (string) config('seo.locale', 'fa-IR');
        $canonical = route('content.show', ['type' => $routeType, 'content' => $content->slug]);
        $logo = url((string) config('seo.default_image', '/logo.png'));
        $primaryVideoMedia = $content->media->first(fn ($media) => $media->type === 'video');
        $primaryImageMedia = $content->media->first(fn ($media) => $media->type === 'image');
        $thumbnailPath = $content->thumbnail ?: $primaryVideoMedia?->thumbnail ?: $primaryImageMedia?->path;
        $videoPath = $content->video_path ?: $primaryVideoMedia?->path;
        $thumbnail = MediaStorage::url($thumbnailPath);
        $thumbnail = $thumbnail ? url($thumbnail) : null;
        $videoUrl = MediaStorage::url($videoPath);
        $videoUrl = $videoUrl ? url($videoUrl) : null;
        $summary = RichText::plainText($content->seo_description ?: $content->excerpt ?: $content->body);

        if (! $summary && $content->type === 'short') {
            $summary = $this->shortFallbackDescription($content);
        }

        $description = $summary
            ? Str::limit($summary, 160, '…')
            : Str::limit("تماشای {$content->title}، ویدیوها و محتوای تازه دنیای گیمینگ در {$siteName}.", 160, '…');
        $title = filled($content->seo_title)
            ? trim($content->seo_title)
            : Str::limit("{$content->title} | {$siteName}", 60, '…');
        $isVideo = in_array($content->type, ['video', 'short'], true);
        $organizationId = route('home').'#organization';

        $entity = $isVideo
            ? [
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
                    'userInteractionCount' => (int) $content->views,
                ],
                ...($thumbnail ? ['thumbnailUrl' => [$thumbnail]] : ($content->type === 'video' ? ['thumbnailUrl' => [$logo]] : [])),
                ...($videoUrl ? ['contentUrl' => $videoUrl] : []),
                ...($content->duration ? ['duration' => $this->isoDuration((int) $content->duration)] : []),
                ...($content->game ? ['about' => ['@type' => 'VideoGame', 'name' => $content->game->name]] : []),
            ]
            : [
                '@type' => 'Article',
                '@id' => $canonical.'#article',
                'headline' => $content->title,
                'description' => $description,
                'url' => $canonical,
                'datePublished' => $content->published_at?->toISOString(),
                'inLanguage' => $locale,
                'publisher' => ['@id' => $organizationId],
                ...($thumbnail ? ['image' => [$thumbnail]] : []),
            ];

        return Seo::page([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
            'type' => $isVideo ? 'video.other' : 'article',
            'siteName' => $siteName,
            'locale' => $locale,
            'image' => $thumbnail ?: $logo,
            'imageAlt' => $thumbnail ? "تصویر بندانگشتی {$content->title}" : "لوگوی {$siteName}",
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
        ]);
    }

    private function shortFallbackDescription(SocialContent $content): string
    {
        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $parts = ["«{$content->title}» یک ویدیوی کوتاه گیمینگ در {$siteName} است."];

        if ($content->game) {
            $parts[] = "این شورت به بازی {$content->game->name} مرتبط است و می‌توانید محتوای بیشتر این بازی را در کانال آن دنبال کنید.";
        } else {
            $parts[] = "این شورت بخشی از محتوای کوتاه پلی نکسوس برای دنبال‌کردن لحظه‌ها، بازی‌ها و موضوعات دنیای گیمینگ است.";
        }

        if ($content->duration && $content->duration > 0) {
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

    private function commentData(SocialComment $comment, Request $request, array $likedIds): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'created_at' => $comment->created_at->toISOString(),
            'likes_count' => (int) $comment->liked_by_count,
            'is_liked' => in_array($comment->id, $likedIds, true),
            'can_delete' => (bool) ($request->user() && ($request->user()->is_admin || $request->user()->id === $comment->user_id)),
            'user' => [
                'name' => $comment->user->name,
                'avatar_url' => MediaStorage::url($comment->user->avatar),
            ],
            'replies' => $comment->replies->map(fn (SocialComment $reply) => $this->commentData($reply, $request, $likedIds))->values(),
        ];
    }

    private function playlistContext(Request $request, SocialContent $content, StorefrontDataService $data): ?array
    {
        $slug = $request->string('list')->toString();
        if ($content->type !== 'video') {
            return null;
        }

        $playlist = VideoPlaylist::query()
            ->when(
                $slug !== '',
                fn ($query) => $query->where('slug', $slug)->whereIn('visibility', ['public', 'unlisted']),
                fn ($query) => $query->publiclyVisible(),
            )
            ->whereHas('videos', fn ($query) => $query->whereKey($content->id))
            ->with([
                'game:id,name,slug',
                'studio:id,name,slug',
                'videos' => fn ($query) => $query->published()->where('type', 'video')->with('game:id,name,slug,cover'),
            ])
            ->orderBy('sort_order')
            ->first();
        if (! $playlist) {
            return null;
        }

        return [
            'id' => $playlist->id,
            'title' => $playlist->title,
            'slug' => $playlist->slug,
            'channel_name' => $playlist->game?->name ?? $playlist->studio?->name ?? 'PlayNexus',
            'url' => $playlist->game
                ? route('channels.playlists.show', ['game' => $playlist->game->slug, 'playlist' => $playlist->slug], false)
                : route('collections.show', $playlist->slug, false),
            'is_public' => $playlist->visibility === 'public',
            'items' => $playlist->videos->map(fn (SocialContent $video) => $data->content($video))->values(),
            'current_id' => $content->id,
        ];
    }

    /** @return array<int, array{name: string, url: string, current: bool}> */
    private function breadcrumbs(SocialContent $content, string $routeType, ?array $playlist): array
    {
        $items = [[
            'name' => 'صفحه اصلی',
            'url' => route('home', absolute: false),
            'current' => false,
        ]];

        if ($content->type === 'video' && $content->game) {
            $items[] = [
                'name' => $content->game->name,
                'url' => route('channels.show', $content->game->slug, false),
                'current' => false,
            ];
        }

        if ($content->type === 'video' && ($playlist['is_public'] ?? false)) {
            $items[] = [
                'name' => $playlist['title'],
                'url' => $playlist['url'],
                'current' => false,
            ];
        }

        $items[] = [
            'name' => $content->title,
            'url' => route('content.show', ['type' => $routeType, 'content' => $content->slug], false),
            'current' => true,
        ];

        return $items;
    }
}
