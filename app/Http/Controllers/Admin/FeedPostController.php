<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FeedPostRequest;
use App\Models\Game;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\SocialContentMedia;
use App\Services\MediaOptimizationService;
use App\Services\MediaStorage;
use App\Services\TemporaryUploadService;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FeedPostController extends Controller
{
    public function index(): Response
    {
        $posts = SocialContent::query()->where('type', 'post')->with(['game:id,name', 'media'])->latest('id')->paginate(12)->withQueryString();

        return Inertia::render('Admin/Feed/Index', ['posts' => [
            'data' => collect($posts->items())->map(fn (SocialContent $post) => [
                ...$post->only(['id', 'title', 'excerpt', 'status', 'feed_type', 'feed_badge', 'published_at']),
                'channel' => $post->game?->name,
                'media_count' => $post->media->count(),
                'cover_url' => MediaStorage::url($post->media->first()?->thumbnail ?: $post->media->first()?->path ?: $post->thumbnail),
                'edit_url' => route('admin.feed.edit', $post),
                'url' => $post->status === 'published' ? route('posts.show', $post->slug, false) : null,
            ]),
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'total' => $posts->total(),
        ]]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Feed/Form', ['post' => null, ...$this->options()]);
    }

    public function store(FeedPostRequest $request, MediaOptimizationService $optimizer, TemporaryUploadService $uploads): RedirectResponse
    {
        $post = new SocialContent([
            ...$this->fields($request),
            'user_id' => $request->user()->id,
            'type' => 'post',
            'slug' => $this->uniqueSlug($request->string('title')->toString()),
            'published_at' => $request->string('status')->toString() === 'published' ? now() : null,
        ]);

        DB::transaction(function () use ($post, $request, $optimizer, $uploads): void {
            $post->save();
            $this->syncMedia($post, $request->validated('media', []), $request->user()->id, $optimizer, $uploads);
        });

        return to_route('admin.feed.index')->with('success', 'پست فید منتشر شد.');
    }

    public function edit(SocialContent $post): Response
    {
        $this->ensurePost($post);
        $post->load('media');

        return Inertia::render('Admin/Feed/Form', [
            'post' => [
                ...$post->only(['id', 'title', 'excerpt', 'body', 'feed_type', 'feed_badge', 'game_id', 'related_product_id', 'related_content_id', 'status', 'allow_comments', 'notify_followers', 'seo_title', 'seo_description']),
                'media' => $post->media->map(fn (SocialContentMedia $media) => [
                    'id' => $media->id,
                    'type' => $media->type,
                    'url' => MediaStorage::url($media->path),
                    'preview_url' => MediaStorage::url($media->thumbnail ?: $media->path),
                    'alt' => $media->alt ?? '',
                ]),
            ],
            ...$this->options(),
        ]);
    }

    public function update(FeedPostRequest $request, SocialContent $post, MediaOptimizationService $optimizer, TemporaryUploadService $uploads): RedirectResponse
    {
        $this->ensurePost($post);
        DB::transaction(function () use ($post, $request, $optimizer, $uploads): void {
            $post->fill($this->fields($request));
            $post->published_at = $post->status === 'published' ? ($post->published_at ?? now()) : null;
            $post->save();
            $this->syncMedia($post, $request->validated('media', []), $request->user()->id, $optimizer, $uploads);
        });

        return to_route('admin.feed.index')->with('success', 'پست فید ویرایش شد.');
    }

    public function destroy(SocialContent $post): RedirectResponse
    {
        $this->ensurePost($post);
        $paths = $post->media()->get()->flatMap(fn (SocialContentMedia $media) => [$media->path, $media->thumbnail])->filter()->all();
        $post->delete();
        MediaStorage::disk()->delete($paths);

        return back()->with('success', 'پست فید حذف شد.');
    }

    private function fields(FeedPostRequest $request): array
    {
        $fields = $request->safe()->only(['title', 'feed_type', 'feed_badge', 'game_id', 'related_product_id', 'related_content_id', 'status', 'allow_comments', 'notify_followers', 'seo_title', 'seo_description']);
        $body = RichText::sanitize($request->string('body')->toString());
        $fields['body'] = $body ?: null;
        $fields['excerpt'] = Str::limit(RichText::plainText($body), 500, '…') ?: null;

        return $fields;
    }

    private function syncMedia(SocialContent $post, array $items, int $userId, MediaOptimizationService $optimizer, TemporaryUploadService $uploads): void
    {
        $keep = collect($items)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        $removed = $post->media()->when($keep, fn ($query) => $query->whereNotIn('id', $keep))->get();
        $removedPaths = $removed->flatMap(fn (SocialContentMedia $media) => [$media->path, $media->thumbnail])->filter()->all();
        $removed->each->delete();

        foreach ($items as $sort => $item) {
            if (! empty($item['id'])) {
                $post->media()->whereKey($item['id'])->update(['sort_order' => $sort, 'alt' => $item['alt'] ?: null]);

                continue;
            }

            $token = (string) ($item['upload_token'] ?? '');
            if ($token === '') {
                continue;
            }
            $file = $uploads->claim($userId, $token);
            try {
                $this->storeMedia($post, $file, $sort, (string) ($item['alt'] ?? ''), $optimizer);
            } finally {
                $uploads->forget($userId, $token);
            }
        }
        MediaStorage::disk()->delete($removedPaths);
    }

    private function storeMedia(SocialContent $post, UploadedFile $file, int $sort, string $alt, MediaOptimizationService $optimizer): void
    {
        $mime = (string) $file->getMimeType();
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4', 'video/webm', 'video/quicktime'], true), 422);
        $type = str_starts_with($mime, 'video/') ? 'video' : 'image';
        abort_if($type === 'image' && $file->getSize() > 8 * 1024 * 1024, 422, 'حجم هر تصویر باید حداکثر ۸ مگابایت باشد.');
        $dimensions = $type === 'image' ? @getimagesize($file->getRealPath()) : null;
        $stored = $optimizer->store($file, 'feed');
        $post->media()->create([
            'type' => $type,
            'path' => $stored['path'],
            'thumbnail' => null,
            'mime' => $mime,
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'duration' => null,
            'alt' => trim($alt) ?: $post->title,
            'sort_order' => $sort,
        ]);
    }

    private function options(): array
    {
        return [
            'games' => Game::query()->whereIn('status', ['active', 'published'])->orderBy('name')->get(['id', 'name']),
            'products' => Product::query()->publiclyVisible()->latest()->limit(300)->get(['id', 'title']),
            'videos' => SocialContent::query()->published()->where('type', 'video')->latest('published_at')->limit(300)->get(['id', 'title']),
        ];
    }

    private function ensurePost(SocialContent $post): void
    {
        abort_unless($post->type === 'post', 404);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'feed-post';
        $slug = $base;
        for ($counter = 2; SocialContent::query()->where('slug', $slug)->exists(); $counter++) {
            $slug = "{$base}-{$counter}";
        }

        return $slug;
    }
}
