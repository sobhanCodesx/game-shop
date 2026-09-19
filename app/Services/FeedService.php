<?php

namespace App\Services;

use App\Models\Game;
use App\Models\SocialContent;
use App\Models\SocialContentMedia;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FeedService
{
    public const TYPES = ['post', 'news', 'article', 'video', 'clip', 'trailer', 'game_update', 'review', 'image'];

    public function paginate(Request $request, string $tab = 'for-you', int $perPage = 10): LengthAwarePaginator
    {
        $user = $request->user();
        $query = SocialContent::query()
            ->published()
            ->whereIn('type', ['post', 'video'])
            ->with([
                'game:id,name,slug,cover',
                'game.playlists' => fn ($query) => $query->publiclyVisible()->whereNotNull('logo')->select(['id', 'game_id', 'logo', 'sort_order']),
                'user:id,name,avatar',
                'media',
                'relatedProduct:id,title,slug,game_id,price,discount_price,status,visibility',
                'relatedProduct.coverMedia',
                'relatedContent:id,title,slug,type,thumbnail,video_path,video_mime,duration,status,published_at',
            ])
            ->withCount([
                'reactions as likes_count' => fn (Builder $query) => $query->where('type', 'like'),
                'comments as comments_count' => fn (Builder $query) => $query->published(),
            ]);

        if ($tab === 'following') {
            $gameIds = $user?->subscribedGames()->pluck('games.id') ?? collect();
            $query->whereIn('game_id', $gameIds);
        }

        $query->latest('published_at')->latest('id');

        $paginator = $query->paginate($perPage)->withQueryString();
        $ids = $paginator->getCollection()->pluck('id');
        $liked = $this->likedIds($user, $ids);
        $saved = $this->savedIds($user, $ids);
        $paginator->setCollection($paginator->getCollection()->map(
            fn (SocialContent $content) => $this->item($content, $liked, $saved),
        ));

        return $paginator;
    }

    public function single(SocialContent $content, ?User $user): array
    {
        $content->loadMissing([
            'game:id,name,slug,cover',
            'game.playlists' => fn ($query) => $query->publiclyVisible()->whereNotNull('logo')->select(['id', 'game_id', 'logo', 'sort_order']),
            'user:id,name,avatar', 'media',
            'relatedProduct:id,title,slug,game_id,price,discount_price,status,visibility',
            'relatedProduct.coverMedia',
            'relatedContent:id,title,slug,type,thumbnail,video_path,video_mime,duration,status,published_at',
        ])->loadCount([
            'reactions as likes_count' => fn (Builder $query) => $query->where('type', 'like'),
            'comments as comments_count' => fn (Builder $query) => $query->published(),
        ]);

        $ids = collect([$content->id]);

        return $this->item(
            $content,
            $this->likedIds($user, $ids),
            $this->savedIds($user, $ids),
        );
    }

    public function latest(Request $request, int $limit = 10): array
    {
        $contents = $this->feedQuery()->latest('published_at')->latest('id')->limit($limit)->get();

        return $this->mapItems($contents, $request->user());
    }

    public function latestPostsExcept(Request $request, int $exceptId, int $limit = 4): array
    {
        $contents = $this->feedQuery()
            ->where('type', 'post')
            ->whereKeyNot($exceptId)
            ->latest('published_at')
            ->latest('id')
            ->limit($limit)
            ->get();

        return $this->mapItems($contents, $request->user());
    }

    public function latestImportant(Request $request, int $limit = 8): array
    {
        $important = $this->feedQuery()
            ->where(fn (Builder $query) => $query
                ->where('featured', true)
                ->orWhereIn('feed_badge', ['breaking', 'news', 'trailer', 'update', 'review']))
            ->latest('published_at')->latest('id')->limit($limit)->get();

        if ($important->count() < $limit) {
            $fallback = $this->feedQuery()
                ->whereNotIn('id', $important->pluck('id'))
                ->latest('published_at')->latest('id')
                ->limit($limit - $important->count())->get();
            $important = $important->concat($fallback);
        }

        $contents = $important->sortByDesc(fn (SocialContent $content) => sprintf(
            '%s-%020d',
            $content->published_at?->format('Y-m-d H:i:s.u') ?? '',
            $content->id,
        ))->take($limit)->values();

        return $this->mapItems($contents, $request->user());
    }

    public function channel(Request $request, Game $game, int $limit = 8): array
    {
        $contents = $this->feedQuery()->whereBelongsTo($game)->latest('published_at')->latest('id')->limit($limit)->get();

        return $this->mapItems($contents, $request->user());
    }

    public function latestForGames(Request $request, array $gameIds, int $limit = 8): array
    {
        $ids = collect($gameIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $contents = $this->feedQuery()
            ->whereIn('game_id', $ids->all())
            ->latest('published_at')
            ->latest('id')
            ->limit($limit)
            ->get();

        return $this->mapItems($contents, $request->user());
    }

    private function item(SocialContent $content, array $liked, array $saved): array
    {
        $isVideo = $content->type === 'video';
        $media = $content->media->map(fn (SocialContentMedia $media) => [
            'id' => $media->id,
            'type' => $media->type,
            'url' => MediaStorage::url($media->path),
            'thumbnail' => MediaStorage::url($media->thumbnail),
            'width' => $media->width,
            'height' => $media->height,
            'duration' => $media->duration,
            'alt' => $media->alt ?: $content->title,
        ])->values();

        if ($media->isEmpty()) {
            $reference = $content->relatedContent;
            if ($reference?->video_path || ($content->type === 'video' && $content->video_path)) {
                $source = $reference ?: $content;
                $media->push([
                    'id' => -$source->id,
                    'type' => 'video',
                    'url' => MediaStorage::url($source->video_path),
                    'thumbnail' => MediaStorage::url($source->thumbnail),
                    'width' => null,
                    'height' => null,
                    'duration' => $source->duration,
                    'alt' => $source->title,
                ]);
            } elseif ($content->thumbnail) {
                $media->push([
                    'id' => -$content->id,
                    'type' => 'image',
                    'url' => MediaStorage::url($content->thumbnail),
                    'thumbnail' => null,
                    'width' => null,
                    'height' => null,
                    'duration' => null,
                    'alt' => $content->title,
                ]);
            }
        }

        $authorName = $content->game?->name ?? 'PlayNexus';
        $channelLogo = $content->game
            ? ($content->game->cover ?: $content->game->playlists->first()?->logo)
            : null;
        $authorAvatar = $channelLogo
            ? MediaStorage::url($channelLogo)
            : url((string) config('seo.default_image', '/logo.png'));

        return [
            'id' => $content->id,
            'type' => $content->type === 'video' && $content->feed_type === 'post'
                ? 'video'
                : (in_array($content->feed_type, self::TYPES, true) ? $content->feed_type : 'post'),
            'title' => $content->title,
            'body' => RichText::plainText($content->excerpt ?: $content->body),
            'body_html' => RichText::sanitize($content->body),
            'badge' => $content->feed_badge,
            'url' => $isVideo
                ? route('content.show', ['type' => 'videos', 'content' => $content->slug], false)
                : route('posts.show', $content->slug, false),
            'feed_slug' => $content->slug,
            'created_at' => $content->published_at?->toISOString(),
            'media' => $media,
            'author' => [
                'name' => $authorName,
                'avatar_url' => $authorAvatar,
                'url' => $content->game ? route('channels.show', $content->game->slug, false) : null,
            ],
            'likes_count' => (int) $content->likes_count,
            'comments_count' => (int) $content->comments_count,
            'is_liked' => in_array($content->id, $liked, true),
            'is_saved' => in_array($content->id, $saved, true),
            'allow_comments' => (bool) $content->allow_comments,
            'related_product' => $content->relatedProduct && $content->relatedProduct->status === 'published' && $content->relatedProduct->visibility === 'public' ? [
                'id' => $content->relatedProduct->id,
                'title' => $content->relatedProduct->title,
                'url' => route('products.show', $content->relatedProduct->slug, false),
                'image_url' => MediaStorage::url($content->relatedProduct->coverMedia?->path),
                'price' => $content->relatedProduct->discount_price ?: $content->relatedProduct->price,
            ] : null,
            'related_video' => $content->relatedContent && $content->relatedContent->status === 'published' ? [
                'id' => $content->relatedContent->id,
                'title' => $content->relatedContent->title,
                'url' => route('content.show', ['type' => $content->relatedContent->type === 'short' ? 'shorts' : 'videos', 'content' => $content->relatedContent->slug], false),
            ] : null,
        ];
    }

    private function feedQuery(): Builder
    {
        return SocialContent::query()
            ->published()
            ->whereIn('type', ['post', 'video'])
            ->with([
                'game:id,name,slug,cover',
                'game.playlists' => fn ($query) => $query->publiclyVisible()->whereNotNull('logo')->select(['id', 'game_id', 'logo', 'sort_order']),
                'user:id,name,avatar',
                'media',
                'relatedProduct:id,title,slug,game_id,price,discount_price,status,visibility',
                'relatedProduct.coverMedia',
                'relatedContent:id,title,slug,type,thumbnail,video_path,video_mime,duration,status,published_at',
            ])
            ->withCount([
                'reactions as likes_count' => fn (Builder $query) => $query->where('type', 'like'),
                'comments as comments_count' => fn (Builder $query) => $query->published(),
            ]);
    }

    private function mapItems($contents, ?User $user): array
    {
        $ids = $contents->pluck('id');
        $liked = $this->likedIds($user, $ids);
        $saved = $this->savedIds($user, $ids);

        return $contents->map(fn (SocialContent $content) => $this->item($content, $liked, $saved))->values()->all();
    }

    private function likedIds(?User $user, $ids): array
    {
        if (! $user || $ids->isEmpty()) {
            return [];
        }

        return $user->contentReactions()->whereIn('social_content_id', $ids)->where('type', 'like')
            ->pluck('social_content_id')->map(fn ($id) => (int) $id)->all();
    }

    private function savedIds(?User $user, $ids): array
    {
        if (! $user || $ids->isEmpty()) {
            return [];
        }

        return $user->savedContent()->whereIn('social_contents.id', $ids)
            ->pluck('social_contents.id')->map(fn ($id) => (int) $id)->all();
    }
}
