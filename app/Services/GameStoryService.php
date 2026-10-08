<?php

namespace App\Services;

use App\Models\GameStory;
use App\Support\StoryRichText;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use RuntimeException;

class GameStoryService
{
    public function rules(): array
    {
        return [
            'game_id' => ['required', 'integer', Rule::exists('games', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:160'],
            'kind' => ['required', Rule::in(GameStory::KINDS)],
            'subtitle' => ['nullable', 'string', 'max:230'],
            'summary' => ['nullable', 'string', 'max:600'],
            'body' => ['nullable', 'string', 'max:200000'],
            'cover_path' => ['nullable', 'string', 'max:255'],
            'source_url' => ['nullable', 'url:http,https', 'max:1000'],
            'contains_spoilers' => ['sometimes', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:60'],
            'seo_description' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function save(array $input, ?GameStory $story = null, ?int $authorId = null): GameStory
    {
        $data = Validator::make($input, $this->rules())->validate();
        $path = $data['cover_path'] ?? null;
        if ($path && ! StoryRichText::imagePath($path)) {
            throw ValidationException::withMessages(['cover_path' => 'تصویر جلد باید از کتابخانه مدیای Game Story باشد.']);
        }
        $body = StoryRichText::sanitize($data['body'] ?? null);
        if ($story?->status === 'published' && mb_strlen(trim(strip_tags((string) $body))) < 120) {
            throw ValidationException::withMessages(['body' => 'برای حفظ انتشار، متن کتابچه باید حداقل ۱۲۰ کاراکتر داشته باشد.']);
        }
        $summary = trim((string) ($data['summary'] ?? ''));
        if ($summary === '') {
            $summary = Str::limit(trim(html_entity_decode(strip_tags((string) $body), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 280, '…');
        }
        $attributes = [
            ...$data,
            'title' => trim($data['title']),
            'summary' => $summary ?: null,
            'body' => $body,
        ];
        if (! $story) {
            $story = new GameStory();
            $story->slug = $this->uniqueSlug($attributes['title']);
            $story->status = 'draft';
            $story->user_id = $authorId;
        }

        $story->fill($attributes);
        $story->save();
        app(SitemapCacheService::class)->invalidate();

        return $story->fresh(['game']);
    }

    public function setState(GameStory $story, string $state): GameStory
    {
        if (! in_array($state, ['draft', 'published'], true)) {
            throw ValidationException::withMessages(['state' => 'وضعیت انتشار نامعتبر است.']);
        }
        if ($state === 'published') {
            if (! in_array($story->game?->status, ['active', 'published'], true)) {
                throw ValidationException::withMessages(['game_id' => 'برای انتشار، کانال بازی باید فعال باشد.']);
            }
            $plain = trim(strip_tags((string) $story->body));
            if (mb_strlen($plain) < 120) {
                throw ValidationException::withMessages(['body' => 'قبل از انتشار یک روایت واقعی (حداقل ۱۲۰ کاراکتر) بنویسید.']);
            }
        }
        $story->status = $state;
        $story->published_at = $state === 'published' ? ($story->published_at ?? now()) : null;
        $story->save();
        app(SitemapCacheService::class)->invalidate();
        return $story->fresh(['game']);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'game-story';
        $base = mb_substr($base, 0, 155);
        for ($i = 1; $i < 1000; $i++) {
            $slug = $i === 1 ? $base : "{$base}-{$i}";
            if (! GameStory::query()->where('slug', $slug)->exists()) {
                return $slug;
            }
        }
        throw new RuntimeException('Could not allocate a unique Game Story slug.');
    }

    public function serialize(GameStory $story): array
    {
        $story->loadMissing('game:id,name,slug,status,cover,background');
        return [
            ...$story->card(),
            'body' => $story->body,
            'status' => $story->status,
            'game_id' => $story->game_id,
            'cover_path' => $story->cover_path,
            'source_url' => $story->source_url,
            'seo_title' => $story->seo_title,
            'seo_description' => $story->seo_description,
            'updated_at' => $story->updated_at?->toISOString(),
        ];
    }
}
