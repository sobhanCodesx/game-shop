<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\ProductMedia;
use App\Models\SocialComment;
use App\Models\SocialContent;
use App\Models\VideoPlaylist;
use App\Services\FeedService;
use App\Services\MediaStorage;
use App\Services\StorefrontDataService;
use App\Services\VideoCommunityService;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MobileContentController extends Controller
{
    public function feed(Request $request, FeedService $feed): JsonResponse
    {
        $tab = $request->string('tab')->toString() === 'following' ? 'following' : 'for-you';
        $perPage = max(1, min(30, $request->integer('per_page', 12)));

        return response()->json($feed->paginate($request, $tab, $perPage));
    }

    public function show(
        Request $request,
        SocialContent $content,
        StorefrontDataService $data,
        VideoCommunityService $community,
    ): JsonResponse {
        $this->ensureVisible($content);

        $content->load([
            'game:id,name,slug,cover,background,developer,publisher',
            'game.playlists' => fn ($query) => $query
                ->publiclyVisible()
                ->whereNotNull('logo')
                ->select(['id', 'game_id', 'logo', 'sort_order']),
            'user:id,name,avatar',
            'media',
        ]);

        $reactionCounts = $content->reactions()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $userReaction = $request->user()
            ? $content->reactions()->where('user_id', $request->user()->id)->value('type')
            : null;

        $isSaved = $request->user()
            ? $request->user()->savedContent()->whereKey($content->id)->exists()
            : false;

        $related = SocialContent::query()
            ->published()
            ->where('type', $content->type)
            ->whereKeyNot($content->id)
            ->with([
                'game:id,name,slug,cover',
                'game.playlists' => fn ($query) => $query
                    ->publiclyVisible()
                    ->whereNotNull('logo')
                    ->select(['id', 'game_id', 'logo', 'sort_order']),
                'media',
            ])
            ->when(
                $content->game_id,
                fn (Builder $query) => $query->orderByRaw('CASE WHEN game_id = ? THEN 0 ELSE 1 END', [$content->game_id]),
            )
            ->latest('published_at')
            ->limit(12)
            ->get()
            ->map(fn (SocialContent $item) => $data->content($item))
            ->values();

        $payload = [
            ...$data->content($content),
            'excerpt' => RichText::plainText($content->excerpt),
            'body' => RichText::sanitize($content->body),
            'feed_type' => $content->feed_type,
            'badge' => $content->feed_badge,
            'video_mime' => $content->video_mime,
            'allow_comments' => (bool) $content->allow_comments,
            'likes_count' => (int) ($reactionCounts['like'] ?? 0),
            'dislikes_count' => (int) ($reactionCounts['dislike'] ?? 0),
            'user_reaction' => $userReaction,
            'is_saved' => $isSaved,
            'comments_count' => $content->comments()->published()->count(),
        ];

        return response()->json([
            'content' => $payload,
            'channel' => $content->game ? [
                'id' => $content->game->id,
                'name' => $content->game->name,
                'slug' => $content->game->slug,
                'logo_url' => MediaStorage::url(
                    $content->game->playlists->first()?->logo ?: $content->game->cover,
                ),
                'avatar_url' => MediaStorage::url(
                    $content->game->playlists->first()?->logo ?: $content->game->cover,
                ),
                'cover_url' => MediaStorage::url($content->game->cover),
                'background_url' => MediaStorage::url($content->game->background),
                'subscribers_count' => $content->game->subscribers()->count(),
                'is_subscribed' => $request->user()
                    ? $content->game->subscribers()->whereKey($request->user()->id)->exists()
                    : false,
            ] : null,
            'playlist' => $this->playlistContext($request, $content, $data),
            'related' => $related,
        ]);
    }

    public function discover(Request $request, StorefrontDataService $data): JsonResponse
    {
        $perPage = max(1, min(30, $request->integer('per_page', 18)));

        $productMediaFeed = ProductMedia::query()
            ->whereHas('product', fn (Builder $query) => $query->publiclyVisible())
            ->select(['id', 'updated_at as sort_at'])
            ->selectRaw("'product_media' as kind");

        $contentFeed = SocialContent::query()
            ->published()
            ->where('type', '!=', 'short')
            ->where(fn (Builder $query) => $query
                ->whereNotNull('thumbnail')
                ->orWhereNotNull('video_path')
                ->orWhereHas('media'))
            ->select(['id', 'published_at as sort_at'])
            ->selectRaw("'content' as kind");

        $feed = DB::query()
            ->fromSub($productMediaFeed->unionAll($contentFeed), 'explore_feed')
            ->orderByDesc('sort_at')
            ->orderByDesc('id')
            ->simplePaginate($perPage)
            ->withQueryString();

        $rows = collect($feed->items());

        $media = ProductMedia::query()
            ->with(['product' => fn ($query) => $query->with($this->productRelations())])
            ->whereIn('id', $rows->where('kind', 'product_media')->pluck('id'))
            ->get()
            ->keyBy('id');

        $contentQuery = SocialContent::query()
            ->with([
                'game:id,name,slug,cover',
                'game.playlists' => fn ($query) => $query
                    ->publiclyVisible()
                    ->whereNotNull('logo')
                    ->select(['id', 'game_id', 'logo', 'sort_order']),
                'media',
            ])
            ->withCount([
                'reactions as likes_count' => fn (Builder $query) => $query->where('type', 'like'),
                'comments as comments_count' => fn (Builder $query) => $query->published(),
            ]);

        if ($request->user()) {
            $contentQuery->withExists([
                'reactions as is_liked' => fn (Builder $query) => $query
                    ->where('type', 'like')
                    ->where('user_id', $request->user()->id),
            ]);
        }

        $contents = $contentQuery
            ->whereIn('id', $rows->where('kind', 'content')->pluck('id'))
            ->get()
            ->keyBy('id');

        $feed->setCollection($rows->map(function (object $row) use ($media, $contents, $data, $request) {
            if ($row->kind === 'product_media' && $media->has($row->id)) {
                $item = $media[$row->id];

                return [
                    'key' => "product-media-{$row->id}",
                    'kind' => 'product_media',
                    'data' => [
                        ...$data->product($item->product, $request->user()),
                        'media_url' => MediaStorage::url($item->path),
                        'media_type' => $item->type,
                        'media_alt' => $item->alt ?: $item->product->title,
                    ],
                ];
            }

            if (! $contents->has($row->id)) {
                return null;
            }

            $item = $contents[$row->id];

            return [
                'key' => "content-{$row->id}",
                'kind' => 'content',
                'data' => [
                    ...$data->content($item),
                    'likes_count' => (int) $item->likes_count,
                    'comments_count' => (int) $item->comments_count,
                    'is_liked' => (bool) ($item->is_liked ?? false),
                    'allow_comments' => (bool) $item->allow_comments,
                ],
            ];
        })->filter()->values());

        return response()->json($feed);
    }

    public function videos(Request $request, StorefrontDataService $data): JsonResponse
    {
        return $this->contentIndex($request, $data, 'video');
    }

    public function shorts(Request $request, StorefrontDataService $data): JsonResponse
    {
        return $this->contentIndex($request, $data, 'short');
    }

    public function comments(
        Request $request,
        SocialContent $content,
        VideoCommunityService $community,
    ): JsonResponse {
        $this->ensureVisible($content);
        abort_unless($content->allow_comments, 403);

        $query = SocialComment::query()
            ->published()
            ->whereBelongsTo($content, 'content')
            ->whereNull('parent_id')
            ->with('user:id,name,avatar')
            ->withCount('likedBy');

        $request->string('sort')->toString() === 'newest'
            ? $query->latest()
            : $query->orderByDesc('liked_by_count')->latest();

        $likedIds = $request->user()
            ? $request->user()
                ->belongsToMany(SocialComment::class, 'social_comment_likes')
                ->pluck('social_comments.id')
                ->all()
            : [];

        $comments = $query
            ->paginate(max(1, min(50, $request->integer('per_page', 30))))
            ->withQueryString();

        $community->loadCommentReplies($content, $comments->getCollection());
        $comments->through(fn (SocialComment $comment) => $this->commentData($comment, $request, $likedIds));

        return response()->json($comments);
    }

    public function trending(): JsonResponse
    {
        $games = Game::query()
            ->whereIn('status', ['active', 'published'])
            ->withCount(['videos', 'subscribers'])
            ->orderByDesc('subscribers_count')
            ->orderByDesc('videos_count')
            ->limit(10)
            ->get(['id', 'name', 'slug', 'cover'])
            ->map(fn (Game $game) => [
                'id' => $game->id,
                'name' => $game->name,
                'slug' => $game->slug,
                'cover_url' => MediaStorage::url($game->cover),
                'followers' => (int) $game->subscribers_count,
                'videos_count' => (int) $game->videos_count,
            ])
            ->values();

        return response()->json(['games' => $games]);
    }

    public function recordView(Request $request, SocialContent $content): JsonResponse
    {
        $this->ensureVisible($content);

        $viewerKey = $request->user()
            ? 'user:'.$request->user()->id
            : 'guest:'.sha1((string) $request->ip().'|'.(string) $request->userAgent());

        $cacheKey = "mobile-content-view:{$content->id}:{$viewerKey}";
        if (Cache::add($cacheKey, true, now()->addHours(6))) {
            $content->increment('views');
        }

        return response()->json(['views' => (int) $content->fresh()->views]);
    }

    private function contentIndex(Request $request, StorefrontDataService $data, string $type): JsonResponse
    {
        $query = SocialContent::query()
            ->published()
            ->where('type', $type)
            ->with([
                'game:id,name,slug,cover',
                'game.playlists' => fn ($query) => $query
                    ->publiclyVisible()
                    ->whereNotNull('logo')
                    ->select(['id', 'game_id', 'logo', 'sort_order']),
                'media',
            ])
            ->withCount([
                'reactions as likes_count' => fn (Builder $query) => $query->where('type', 'like'),
                'reactions as dislikes_count' => fn (Builder $query) => $query->where('type', 'dislike'),
                'comments as comments_count' => fn (Builder $query) => $query->published(),
            ])
            ->latest('published_at');

        if ($request->user()) {
            $userId = $request->user()->id;

            $query
                ->withCount([
                    'reactions as viewer_like_count' => fn (Builder $query) => $query
                        ->where('type', 'like')
                        ->where('user_id', $userId),
                    'reactions as viewer_dislike_count' => fn (Builder $query) => $query
                        ->where('type', 'dislike')
                        ->where('user_id', $userId),
                    'savedBy as viewer_saved_count' => fn (Builder $query) => $query
                        ->where('users.id', $userId),
                ]);
        }

        $items = $query
            ->paginate(max(1, min(30, $request->integer('per_page', 18))))
            ->withQueryString()
            ->through(function (SocialContent $item) use ($data): array {
                $viewerReaction = ((int) ($item->viewer_like_count ?? 0)) > 0
                    ? 'like'
                    : (((int) ($item->viewer_dislike_count ?? 0)) > 0 ? 'dislike' : null);

                return [
                    ...$data->content($item),
                    'likes_count' => (int) ($item->likes_count ?? 0),
                    'dislikes_count' => (int) ($item->dislikes_count ?? 0),
                    'comments_count' => (int) ($item->comments_count ?? 0),
                    'user_reaction' => $viewerReaction,
                    'is_liked' => $viewerReaction === 'like',
                    'is_saved' => ((int) ($item->viewer_saved_count ?? 0)) > 0,
                ];
            });

        return response()->json($items);
    }

    private function ensureVisible(SocialContent $content): void
    {
        abort_unless(
            in_array($content->type, ['post', 'video', 'short'], true)
                && $content->status === 'published'
                && $content->published_at?->isPast(),
            404,
        );
    }

    private function commentData(SocialComment $comment, Request $request, array $likedIds): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'created_at' => $comment->created_at->toISOString(),
            'likes_count' => (int) $comment->liked_by_count,
            'is_liked' => in_array($comment->id, $likedIds, true),
            'can_delete' => (bool) (
                $request->user()
                && ($request->user()->is_admin || $request->user()->id === $comment->user_id)
            ),
            'user' => [
                'name' => $comment->user->name,
                'avatar_url' => MediaStorage::url($comment->user->avatar),
            ],
            'replies' => $comment->replies
                ->map(fn (SocialComment $reply) => $this->commentData($reply, $request, $likedIds))
                ->values(),
        ];
    }

    private function playlistContext(
        Request $request,
        SocialContent $content,
        StorefrontDataService $data,
    ): ?array {
        if ($content->type !== 'video') {
            return null;
        }

        $slug = $request->string('list')->toString();
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
                'videos' => fn ($query) => $query
                    ->published()
                    ->where('type', 'video')
                    ->with([
                        'game:id,name,slug,cover',
                        'game.playlists' => fn ($query) => $query
                            ->publiclyVisible()
                            ->whereNotNull('logo')
                            ->select(['id', 'game_id', 'logo', 'sort_order']),
                    ]),
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
            'is_public' => $playlist->visibility === 'public',
            'items' => $playlist->videos
                ->map(fn (SocialContent $video) => $data->content($video))
                ->values(),
            'current_id' => $content->id,
        ];
    }

    private function productRelations(): array
    {
        return [
            'category:id,name',
            'type:id,title',
            'game:id,name,developer,publisher',
            'platforms:id,name',
            'attributeValues.attribute:id,name,slug',
            'coverMedia',
            'variants:id,product_id,status',
        ];
    }
}
