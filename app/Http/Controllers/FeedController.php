<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVideoCommentRequest;
use App\Models\Game;
use App\Models\Product;
use App\Models\SocialComment;
use App\Models\SocialContent;
use App\Services\ContentViewService;
use App\Services\FeedPageDataService;
use App\Services\FeedService;
use App\Services\MediaStorage;
use App\Services\StorefrontDataService;
use App\Services\VideoCommunityService;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FeedController extends Controller
{
    public function index(Request $request, FeedService $feed): JsonResponse|Response
    {
        $tab = $request->string('tab')->toString() === 'following' ? 'following' : 'for-you';
        $items = $feed->paginate($request, $tab);

        if ($request->expectsJson()) {
            return response()->json($items);
        }

        $canonical = route('feed.index');
        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $locale = (string) config('seo.locale', 'fa-IR');
        $logo = url((string) config('seo.default_image', '/logo.png'));
        $description = "فید گیمینگ {$siteName}؛ تازه‌ترین خبرها، ویدیوها، نقدها و به‌روزرسانی کانال‌های بازی.";
        $firstItem = collect($items->items())->first();
        $firstMedia = data_get($firstItem, 'media.0');
        $socialImagePath = data_get($firstMedia, 'type') === 'image'
            ? data_get($firstMedia, 'url')
            : data_get($firstMedia, 'thumbnail');
        $socialImage = $socialImagePath ? url($socialImagePath) : $logo;
        $organizationId = route('home').'#organization';
        $itemListId = $canonical.'#item-list';
        $listItems = collect($items->items())->values()->map(fn (array $item, int $index) => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'item' => [
                '@type' => 'CreativeWork',
                '@id' => url($item['url']),
                'url' => url($item['url']),
                'name' => $item['title'],
                ...($item['created_at'] ? ['datePublished' => $item['created_at']] : []),
                ...(data_get($item, 'media.0.type') === 'image' && data_get($item, 'media.0.url')
                    ? ['image' => url(data_get($item, 'media.0.url'))]
                    : []),
                'author' => ['@id' => $organizationId],
            ],
        ])->all();

        return Inertia::render('Feed/Index', [
            ...Seo::page([
                'title' => "فید گیمینگ {$siteName}",
                'description' => $description,
                'canonical' => $canonical,
                'robots' => $tab === 'following'
                    ? 'noindex, follow'
                    : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
                'type' => 'website',
                'siteName' => $siteName,
                'locale' => $locale,
                'image' => $socialImage,
                'imageAlt' => data_get($firstMedia, 'alt') ?: "فید گیمینگ {$siteName}",
                'heading' => "فید گیمینگ {$siteName}",
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
                        [
                            '@type' => 'CollectionPage',
                            '@id' => $canonical.'#webpage',
                            'url' => $canonical,
                            'name' => "فید گیمینگ {$siteName}",
                            'description' => $description,
                            'inLanguage' => $locale,
                            'isPartOf' => ['@id' => route('home').'#website'],
                            'publisher' => ['@id' => $organizationId],
                            'mainEntity' => ['@id' => $itemListId],
                        ],
                        [
                            '@type' => 'ItemList',
                            '@id' => $itemListId,
                            'name' => "تازه‌ترین مطالب {$siteName}",
                            'numberOfItems' => count($listItems),
                            'itemListOrder' => 'https://schema.org/ItemListOrderDescending',
                            'itemListElement' => $listItems,
                        ],
                        [
                            '@type' => 'BreadcrumbList',
                            '@id' => $canonical.'#breadcrumb',
                            'itemListElement' => [
                                ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                                ['@type' => 'ListItem', 'position' => 2, 'name' => 'فید گیمینگ', 'item' => $canonical],
                            ],
                        ],
                    ],
                ],
            ]),
            'feed' => $items,
            'trendingGames' => $this->trendingGames(),
        ]);
    }

    public function show(
        Request $request,
        SocialContent $content,
        StorefrontDataService $storefront,
        ContentViewService $views,
        FeedPageDataService $page,
    ): RedirectResponse|Response {
        $this->ensureVisible($content);

        if ($content->type === 'video') {
            return redirect()->route('content.show', [
                'type' => 'videos',
                'content' => $content->slug,
            ], 301);
        }

        $views->record($request, $content);

        $pageData = $page->withLiveState(
            $page->get($content),
            $request->user(),
        );

        $productRelations = [
            'category:id,name',
            'type:id,title',
            'game:id,name,developer,publisher',
            'platforms:id,name',
            'attributeValues.attribute:id,name,slug',
            'coverMedia',
            'variants:id,product_id,status',
        ];

        return Inertia::render('Feed/Show', [
            ...$page->seo($pageData['seo_input']),
            'item' => $pageData['item'],
            'breadcrumbs' => $pageData['breadcrumbs'],
            'latestFeed' => $pageData['latestFeed'],
            'latestVideos' => $pageData['latestVideos'],
            'latestProducts' => Product::query()
                ->with($productRelations)
                ->publiclyVisible()
                ->latest()
                ->limit(4)
                ->get()
                ->map(fn (Product $product) => $storefront->product($product, $request->user()))
                ->values(),
        ]);
    }

    public function legacyShow(SocialContent $content): RedirectResponse
    {
        $this->ensureVisible($content);

        return $content->type === 'video'
            ? redirect()->route('content.show', ['type' => 'videos', 'content' => $content->slug], 301)
            : redirect()->route('posts.show', $content->slug, 301);
    }

    public function react(Request $request, SocialContent $content, VideoCommunityService $community): JsonResponse
    {
        $this->ensureVisible($content);
        $request->validate(['type' => ['required', 'in:like']]);
        $reaction = $community->toggleReaction($content, $request->user(), 'like');

        return response()->json(['liked' => $reaction === 'like']);
    }

    public function save(Request $request, SocialContent $content): JsonResponse
    {
        $this->ensureVisible($content);
        $user = $request->user();
        $saved = DB::transaction(function () use ($user, $content): bool {
            $exists = DB::table('social_content_saves')
                ->where('social_content_id', $content->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->exists();
            $exists
                ? $user->savedContent()->detach($content->id)
                : $user->savedContent()->attach($content->id);

            return ! $exists;
        });

        return response()->json(['saved' => $saved]);
    }

    public function comments(Request $request, SocialContent $content, VideoCommunityService $community): JsonResponse
    {
        $this->ensureVisible($content);
        abort_unless($content->allow_comments, 403);
        $query = SocialComment::query()->published()->whereBelongsTo($content, 'content')->whereNull('parent_id')
            ->with('user:id,name,avatar')
            ->withCount('likedBy');
        $request->string('sort')->toString() === 'newest'
            ? $query->latest()
            : $query->orderByDesc('liked_by_count')->latest();
        $likedIds = $request->user()
            ? $request->user()->belongsToMany(SocialComment::class, 'social_comment_likes')->pluck('social_comments.id')->all()
            : [];
        $comments = $query->paginate(30);
        $community->loadCommentReplies($content, $comments->getCollection());
        $comments->through(fn (SocialComment $comment) => $this->comment($comment, $request, $likedIds));

        return response()->json($comments);
    }

    public function storeComment(StoreVideoCommentRequest $request, SocialContent $content, VideoCommunityService $community): JsonResponse
    {
        $this->ensureVisible($content);
        abort_unless($content->allow_comments, 403);
        $data = $request->validated();
        $comment = $community->createComment($content, $request->user(), $data['body'], $data['parent_id'] ?? null);

        return response()->json(['comment_id' => $comment->id], 201);
    }

    public function trending(): JsonResponse
    {
        return response()->json($this->trendingGames());
    }

    private function trendingGames(): array
    {
        return Game::query()->whereIn('status', ['active', 'published'])
            ->withCount(['videos', 'subscribers'])
            ->orderByDesc('subscribers_count')->orderByDesc('videos_count')->limit(6)->get(['id', 'name', 'slug', 'cover'])
            ->map(fn (Game $game) => [
                'id' => $game->id,
                'name' => $game->name,
                'url' => route('channels.show', $game->slug, false),
                'image_url' => MediaStorage::url($game->cover),
                'followers' => $game->subscribers_count,
            ])->all();
    }

    private function ensureVisible(SocialContent $content): void
    {
        abort_unless(in_array($content->type, ['post', 'video'], true) && $content->status === 'published' && $content->published_at?->isPast(), 404);
    }

    private function comment(SocialComment $comment, Request $request, array $likedIds): array
    {
        $map = fn (SocialComment $row) => [
            'id' => $row->id,
            'body' => $row->body,
            'created_at' => $row->created_at->toISOString(),
            'likes_count' => (int) $row->liked_by_count,
            'is_liked' => in_array($row->id, $likedIds, true),
            'can_delete' => (bool) ($request->user() && ($request->user()->is_admin || $request->user()->id === $row->user_id)),
            'user' => ['name' => $row->user->name, 'avatar_url' => MediaStorage::url($row->user->avatar)],
        ];

        return [...$map($comment), 'replies' => $comment->replies
            ->map(fn (SocialComment $reply) => $this->comment($reply, $request, $likedIds))->values()];
    }
}
