<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Game;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\Studio;
use App\Models\VideoPlaylist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use XMLWriter;

class SitemapController extends Controller
{
    private const TYPES = ['static', 'products', 'categories', 'feed', 'content', 'channels', 'studios', 'playlists'];

    public function index(): Response
    {
        $writer = $this->writer('sitemapindex');

        foreach (self::TYPES as $type) {
            $writer->startElement('sitemap');
            $writer->writeElement('loc', route('sitemap.show', $type));
            if ($lastModified = $this->lastModified($type)) {
                $writer->writeElement('lastmod', $lastModified);
            }
            $writer->endElement();
        }

        return $this->response($writer);
    }

    public function show(string $type): Response
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $writer = $this->writer('urlset');
        foreach ($this->urls($type) as $entry) {
            $writer->startElement('url');
            $writer->writeElement('loc', $entry['loc']);
            if (isset($entry['lastmod'])) {
                $writer->writeElement('lastmod', $entry['lastmod']);
            }
            $writer->endElement();
        }

        return $this->response($writer);
    }

    /** @return iterable<array{loc: string, lastmod?: string}> */
    private function urls(string $type): iterable
    {
        if ($type === 'static') {
            foreach (['home', 'shop.index', 'exchange-products.index', 'discover', 'offers.index', 'videos.index', 'studios.index'] as $routeName) {
                yield ['loc' => route($routeName)];
            }

            return;
        }

        if ($type === 'products') {
            foreach (Product::query()->publiclyVisible()->orderBy('id')->cursor() as $product) {
                yield $this->entry(route('products.show', $product->slug), $product->updated_at);
            }

            return;
        }

        if ($type === 'categories') {
            foreach (Category::query()->where('status', 'active')->orderBy('id')->cursor() as $category) {
                yield $this->entry(route('categories.show', $category->slug), $category->updated_at);
            }

            return;
        }

        if ($type === 'feed') {
            yield ['loc' => route('feed.index')];
            foreach (SocialContent::query()->published()->where('type', 'post')->orderBy('id')->cursor() as $content) {
                yield $this->entry(route('feed.show', $content->slug), $content->updated_at);
            }

            return;
        }

        if ($type === 'content') {
            foreach (SocialContent::query()->published()->whereIn('type', ['video', 'short'])->orderBy('id')->cursor() as $content) {
                $plural = match ($content->type) {
                    'video' => 'videos', 'short' => 'shorts', default => 'posts',
                };
                yield $this->entry(route('content.show', [$plural, $content->slug]), $content->updated_at);
            }

            return;
        }

        if ($type === 'channels') {
            foreach (Game::query()->whereIn('status', ['active', 'published'])
                ->whereHas('videos', fn (Builder $query) => $query->published())
                ->orderBy('id')->cursor() as $game) {
                yield $this->entry(route('channels.show', $game->slug), $game->updated_at);
            }

            return;
        }

        if ($type === 'studios') {
            foreach (Studio::query()->where('status', 'active')->orderBy('id')->cursor() as $studio) {
                yield $this->entry(route('studios.show', $studio->slug), $studio->updated_at);
            }

            return;
        }

        foreach (VideoPlaylist::query()->where('visibility', 'public')
            ->whereHas('game', fn (Builder $query) => $query->whereIn('status', ['active', 'published']))
            ->whereHas('videos', fn (Builder $query) => $query->published()->where('type', 'video'))
            ->with('game:id,slug')->orderBy('id')->cursor() as $playlist) {
            yield $this->entry(route('channels.playlists.show', [$playlist->game->slug, $playlist->slug]), $playlist->updated_at);
        }
    }

    private function lastModified(string $type): ?string
    {
        $value = match ($type) {
            'products' => Product::query()->publiclyVisible()->max('updated_at'),
            'categories' => Category::query()->where('status', 'active')->max('updated_at'),
            'feed' => SocialContent::query()->published()->where('type', 'post')->max('updated_at'),
            'content' => SocialContent::query()->published()->whereIn('type', ['video', 'short'])->max('updated_at'),
            'channels' => Game::query()->whereIn('status', ['active', 'published'])->whereHas('videos', fn (Builder $query) => $query->published())->max('updated_at'),
            'studios' => Studio::query()->where('status', 'active')->max('updated_at'),
            'playlists' => VideoPlaylist::query()->where('visibility', 'public')->max('updated_at'),
            default => null,
        };

        return $value ? date(DATE_ATOM, strtotime($value)) : null;
    }

    private function entry(string $url, mixed $updatedAt): array
    {
        return array_filter([
            'loc' => $url,
            'lastmod' => $updatedAt?->toAtomString(),
        ]);
    }

    private function writer(string $root): XMLWriter
    {
        $writer = new XMLWriter;
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement($root);
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        return $writer;
    }

    private function response(XMLWriter $writer): Response
    {
        $writer->endElement();
        $writer->endDocument();

        return response($writer->outputMemory(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex, follow',
        ]);
    }
}
