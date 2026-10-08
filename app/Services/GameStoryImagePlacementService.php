<?php

namespace App\Services;

use App\Models\ContentAsset;
use App\Models\GameStory;
use App\Support\StoryRichText;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class GameStoryImagePlacementService
{
    /**
     * Insert an existing, story-owned MCP upload without re-writing the story.
     * Indices are 1-based direct top-level narrative blocks. Text anchors must
     * match exactly one block, avoiding accidental placement on repeated prose.
     */
    public function insert(array $arguments): array
    {
        $data = Validator::make($arguments, [
            'id' => ['required', 'integer', 'min:1'],
            'asset_id' => ['required', 'integer', 'min:1'],
            'position' => ['required', Rule::in(['start', 'end', 'before_block', 'after_block', 'before_text', 'after_text'])],
            'block_index' => ['required_if:position,before_block,after_block', 'nullable', 'integer', 'min:1'],
            'anchor_text' => ['required_if:position,before_text,after_text', 'nullable', 'string', 'min:3', 'max:500'],
            'alt' => ['required', 'string', 'min:3', 'max:180'],
            'caption' => ['nullable', 'string', 'max:250'],
            'expected_updated_at' => ['nullable', 'date'],
        ])->validate();

        return DB::transaction(function () use ($data): array {
            $story = GameStory::query()->whereKey($data['id'])->lockForUpdate()->firstOrFail();

            if (isset($data['expected_updated_at']) && $story->updated_at?->toISOString() !== $data['expected_updated_at']) {
                throw ValidationException::withMessages(['expected_updated_at' => 'Story changed after the last read. Read it again before inserting media.']);
            }

            $asset = ContentAsset::query()
                ->where('resource', 'game_story')
                ->where('resource_id', $story->id)
                ->where('slot', 'attachment')
                ->where('kind', 'image')
                ->whereKey($data['asset_id'])
                ->firstOrFail();

            if (! StoryRichText::imagePath($asset->path) || ! in_array($asset->mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                throw ValidationException::withMessages(['asset_id' => 'Invalid Game Story image attachment.']);
            }

            $imageUrl = MediaStorage::url($asset->path);
            if (! $imageUrl || ! MediaStorage::disk()->exists($asset->path)) {
                throw ValidationException::withMessages(['asset_id' => 'Image is not accessible in media storage.']);
            }

            $html = trim((string) $story->body);
            $document = new DOMDocument('1.0', 'UTF-8');
            $previous = libxml_use_internal_errors(true);
            try {
                $document->loadHTML(
                    '<?xml encoding="UTF-8"?><div id="pn-story-body">'.$html.'</div>',
                    LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
                );
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }

            $root = (new DOMXPath($document))->query('//*[@id="pn-story-body"]')->item(0);
            if (! $root instanceof DOMElement) {
                throw ValidationException::withMessages(['body' => 'Story body could not be parsed.']);
            }

            $blocks = [];
            foreach ($root->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'blockquote', 'figure'], true)) {
                    $blocks[] = $child;
                }
            }

            $placement = $data['position'];
            $target = null;
            if (in_array($placement, ['before_block', 'after_block'], true)) {
                $target = $blocks[((int) $data['block_index']) - 1] ?? null;
                if (! $target) {
                    throw ValidationException::withMessages(['block_index' => 'Block index is outside the Game Story content.']);
                }
            }
            if (in_array($placement, ['before_text', 'after_text'], true)) {
                $needle = $this->normalize($data['anchor_text']);
                $matches = array_values(array_filter($blocks, fn (DOMElement $block) =>
                    mb_strpos($this->normalize($block->textContent), $needle) !== false
                ));
                if (count($matches) !== 1) {
                    throw ValidationException::withMessages(['anchor_text' => 'Anchor must match exactly one narrative block. Use block_index when text is repeated.']);
                }
                $target = $matches[0];
            }

            $figure = $document->createElement('figure');
            $img = $document->createElement('img');
            $img->setAttribute('src', $imageUrl);
            $img->setAttribute('alt', trim($data['alt']));
            $img->setAttribute('loading', 'lazy');
            $img->setAttribute('decoding', 'async');
            $figure->appendChild($img);
            if (filled($data['caption'] ?? null)) {
                $caption = $document->createElement('figcaption');
                $caption->appendChild($document->createTextNode(trim($data['caption'])));
                $figure->appendChild($caption);
            }

            if ($placement === 'start') {
                $root->insertBefore($figure, $root->firstChild);
            } elseif ($placement === 'end') {
                $root->appendChild($figure);
            } elseif (str_starts_with($placement, 'before_')) {
                $root->insertBefore($figure, $target);
            } else {
                $root->insertBefore($figure, $target->nextSibling);
            }

            $updated = '';
            foreach ($root->childNodes as $child) {
                $updated .= $document->saveHTML($child);
            }
            $sanitized = StoryRichText::sanitize($updated);
            if (! $sanitized || ! str_contains($sanitized, 'src="'.htmlspecialchars($imageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"')) {
                throw ValidationException::withMessages(['body' => 'The uploaded image was rejected by the HTML sanitizer.']);
            }
            $story->body = $sanitized;
            $story->save();
            app(SitemapCacheService::class)->invalidate();

            return [
                'id' => $story->id,
                'story_url' => $story->public_url,
                'asset_id' => $asset->id,
                'image_url' => $imageUrl,
                'position' => $placement,
                'blocks_count' => count($blocks) + 1,
                'body' => $story->body,
                'updated_at' => $story->updated_at?->toISOString(),
            ];
        });
    }

    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
