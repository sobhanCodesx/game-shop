<?php

namespace App\Services;

use App\Models\Product;
use App\Support\RichText;
use App\Support\Seo;
use Illuminate\Support\Str;

final class ProductPageDataService
{
    public function __construct(
        private readonly StorefrontPageCache $cache,
    ) {}

    public function get(Product $product): array
    {
        return $this->cache->remember(
            'product',
            $product->id,
            fn () => $this->build($product),
        );
    }

    public function seo(array $input, Product $product, array $pricing, bool $noindex): array
    {
        $availability = match ($product->availability) {
            'out_of_stock' => 'https://schema.org/OutOfStock',
            'preorder', 'coming_soon' => 'https://schema.org/PreOrder',
            'discontinued' => 'https://schema.org/Discontinued',
            default => 'https://schema.org/InStock',
        };

        $input['robots'] = $noindex
            ? 'noindex, follow'
            : 'index, follow, max-image-preview:large, max-snippet:-1';

        $graph = data_get($input, 'structuredData.@graph', []);
        foreach ($graph as $index => $entity) {
            if (($entity['@type'] ?? null) !== 'Product') {
                continue;
            }

            $graph[$index]['offers'] = [
                '@type' => 'Offer',
                'url' => $input['canonical'],
                'priceCurrency' => 'IRR',
                'price' => (int) $pricing['final_price'] * 10,
                'availability' => $availability,
                'itemCondition' => 'https://schema.org/NewCondition',
            ];
            break;
        }

        data_set($input, 'structuredData.@graph', $graph);

        return Seo::page($input);
    }

    private function build(Product $product): array
    {
        $product->loadMissing([
            'category:id,name,slug',
            'brand:id,name',
            'platforms:id,name',
            'attributeValues.attribute:id,name',
            'media',
        ]);

        $images = $product->media
            ->where('type', 'image')
            ->sortByDesc('is_primary')
            ->pluck('path')
            ->map(fn (string $path) => url(MediaStorage::url($path)))
            ->values();

        $image = $images->first() ?: url((string) config('seo.default_image', '/logo.png'));
        $canonical = route('products.show', $product->slug);
        $description = Str::limit(
            RichText::plainText($product->seo_description ?: $product->short_description ?: $product->description)
                ?: $product->title,
            160,
            '…',
        );

        return [
            'product' => [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'short_description' => RichText::plainText($product->short_description),
                'description' => RichText::sanitize($product->description),
                'release_date' => $product->release_date?->format('Y-m-d'),
                'category' => $product->category?->name,
                'brand' => $product->brand?->name,
                'platforms' => $product->platforms->pluck('name')->values()->all(),
                'attributes' => $product->attributeValues->map(fn ($value) => [
                    'name' => $value->attribute->name,
                    'value' => $value->value,
                ])->values()->all(),
                'media' => $product->media->map(fn ($media) => [
                    'id' => $media->id,
                    'type' => $media->type,
                    'url' => MediaStorage::url($media->path),
                    'alt' => $media->alt ?: $product->title,
                    'is_primary' => (bool) $media->is_primary,
                ])->values()->all(),
            ],
            'seo_input' => [
                'title' => $product->seo_title ?: $product->title,
                'description' => $description,
                'canonical' => $canonical,
                'robots' => 'index, follow, max-image-preview:large, max-snippet:-1',
                'type' => 'product',
                'image' => $image,
                'imageAlt' => $product->media->sortByDesc('is_primary')->first()?->alt ?: $product->title,
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'Product',
                            '@id' => $canonical.'#product',
                            'name' => $product->title,
                            'url' => $canonical,
                            'description' => $description,
                            'sku' => $product->sku,
                            'image' => $images->isNotEmpty() ? $images->all() : [$image],
                            ...($product->brand ? ['brand' => ['@type' => 'Brand', 'name' => $product->brand->name]] : []),
                        ],
                        [
                            '@type' => 'BreadcrumbList',
                            'itemListElement' => array_values(array_filter([
                                ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                                ['@type' => 'ListItem', 'position' => 2, 'name' => 'فروشگاه', 'item' => route('shop.index')],
                                $product->category ? [
                                    '@type' => 'ListItem',
                                    'position' => 3,
                                    'name' => $product->category->name,
                                    'item' => route('categories.show', $product->category->slug),
                                ] : null,
                                [
                                    '@type' => 'ListItem',
                                    'position' => $product->category ? 4 : 3,
                                    'name' => $product->title,
                                    'item' => $canonical,
                                ],
                            ])),
                        ],
                    ],
                ],
            ],
        ];
    }
}
