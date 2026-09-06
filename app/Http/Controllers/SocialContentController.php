<?php

namespace App\Http\Controllers;

use App\Models\SocialComment;
use App\Models\SocialContent;
use App\Models\VideoPlaylist;
use App\Services\MediaStorage;
use App\Services\StorefrontDataService;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SocialContentController extends Controller
{
    public function show(Request $request, string $type, SocialContent $content, StorefrontDataService $data): Response
    {
        $expectedType = match ($type) {
            'posts' => 'post', 'videos' => 'video', 'shorts' => 'short', default => abort(404),
        };

        abort_unless($content->type === $expectedType && $content->status === 'published' && $content->published_at?->isPast(), 404);

        if ($content->type === 'video') {
            $viewed = $request->session()->get('viewed_videos', []);
            if (! in_array($content->id, $viewed, true)) {
                $content->increment('views');
                $request->session()->put('viewed_videos', [...array_slice($viewed, -199), $content->id]);
            }
        }

        $content->load([
            'game:id,name,slug,cover,background,developer,publisher',
            'game.playlists' => fn ($query) => $query->publiclyVisible()->whereNotNull('logo')->select(['id', 'game_id', 'logo', 'sort_order']),
            'user:id,name,avatar',
        ]);
        $reactionCounts = $content->reactions()->selectRaw('type, COUNT(*) as aggregate')->groupBy('type')->pluck('aggregate', 'type');
        $userReaction = $request->user()
            ? $content->reactions()->where('user_id', $request->user()->id)->value('type')
            : null;

        $comments = null;
        if ($content->type === 'video' && $content->allow_comments) {
            $commentQuery = SocialComment::query()->published()->whereBelongsTo($content, 'content')->whereNull('parent_id')
                ->with(['user:id,name,avatar', 'replies' => fn ($query) => $query->published()->with('user:id,name,avatar')->withCount('likedBy')])
                ->withCount('likedBy');

            $request->string('comment_sort')->toString() === 'newest'
                ? $commentQuery->latest()
                : $commentQuery->orderByDesc('liked_by_count')->latest();

            $likedCommentIds = $request->user()
                ? $request->user()->belongsToMany(SocialComment::class, 'social_comment_likes')->pluck('social_comments.id')->all()
                : [];
            $comments = $commentQuery->paginate(20, ['*'], 'comments_page')->withQueryString()
                ->through(fn (SocialComment $comment) => $this->commentData($comment, $request, $likedCommentIds));
        }

        $related = SocialContent::query()->published()->where('type', $content->type)->whereKeyNot($content->id)
            ->with('game:id,name,slug,cover')
            ->when($content->game_id, fn (Builder $query) => $query->orderByRaw('CASE WHEN game_id = ? THEN 0 ELSE 1 END', [$content->game_id]))
            ->latest('published_at')->limit(12)->get()->map(fn (SocialContent $item) => $data->content($item));

        $seo = $this->seo($content, $type);

        return Inertia::render('Content/Show', [
            ...$seo,
            'content' => [
                ...$data->content($content),
                'excerpt' => RichText::plainText($content->excerpt),
                'body' => RichText::sanitize($content->body),
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
            'playlist' => $this->playlistContext($request, $content, $data),
        ]);
    }

    private function seo(SocialContent $content, string $routeType): array
    {
        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $locale = (string) config('seo.locale', 'fa-IR');
        $canonical = route('content.show', ['type' => $routeType, 'content' => $content->slug]);
        $logo = url((string) config('seo.default_image', '/logo.png'));
        $thumbnail = MediaStorage::url($content->thumbnail);
        $thumbnail = $thumbnail ? url($thumbnail) : null;
        $videoUrl = MediaStorage::url($content->video_path);
        $videoUrl = $videoUrl ? url($videoUrl) : null;
        $summary = RichText::plainText($content->seo_description ?: $content->excerpt);
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
                ...($thumbnail ? ['thumbnailUrl' => [$thumbnail]] : []),
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

        $breadcrumbs = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'صفحه اصلی', 'item' => route('home')],
            ...($content->type === 'video' ? [[
                '@type' => 'ListItem',
                'position' => 2,
                'name' => 'ویدیوها',
                'item' => route('videos.index'),
            ]] : []),
            [
                '@type' => 'ListItem',
                'position' => $content->type === 'video' ? 3 : 2,
                'name' => $content->title,
                'item' => $canonical,
            ],
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
                        'itemListElement' => $breadcrumbs,
                    ],
                ],
            ],
        ]);
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
            'replies' => $comment->replies->map(fn (SocialComment $reply) => [
                'id' => $reply->id,
                'body' => $reply->body,
                'created_at' => $reply->created_at->toISOString(),
                'likes_count' => (int) $reply->liked_by_count,
                'is_liked' => in_array($reply->id, $likedIds, true),
                'can_delete' => (bool) ($request->user() && ($request->user()->is_admin || $request->user()->id === $reply->user_id)),
                'user' => [
                    'name' => $reply->user->name,
                    'avatar_url' => MediaStorage::url($reply->user->avatar),
                ],
            ])->values(),
        ];
    }

    private function playlistContext(Request $request, SocialContent $content, StorefrontDataService $data): ?array
    {
        $slug = $request->string('list')->toString();
        if ($content->type !== 'video') {
            return null;
        }

        $playlist = VideoPlaylist::query()->whereIn('visibility', ['public', 'unlisted'])
            ->when($slug !== '', fn ($query) => $query->where('slug', $slug))
            ->whereHas('videos', fn ($query) => $query->whereKey($content->id))
            ->with(['game:id,name,slug', 'videos' => fn ($query) => $query->published()->where('type', 'video')->with('game:id,name,slug,cover')])
            ->orderBy('sort_order')
            ->first();
        if (! $playlist) {
            return null;
        }

        return [
            'id' => $playlist->id,
            'title' => $playlist->title,
            'slug' => $playlist->slug,
            'channel_name' => $playlist->game->name,
            'url' => route('channels.playlists.show', ['game' => $playlist->game->slug, 'playlist' => $playlist->slug], false),
            'items' => $playlist->videos->map(fn (SocialContent $video) => $data->content($video))->values(),
            'current_id' => $content->id,
        ];
    }
}
