<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogPaginationSeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_unfiltered_shop_pagination_is_self_canonical_and_indexable(): void
    {
        Product::factory()->count(20)->create();

        $canonical = route('shop.index', ['page' => 2]);

        $this->get($canonical)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('seo.canonical', $canonical)
            ->where('seo.robots', 'index, follow, max-image-preview:large, max-snippet:-1')
            ->where('seo.title', 'فروشگاه بازی و محصولات گیمینگ PlayNexus؛ صفحه 2 - پلی نکسوس')
            ->where('seo.structuredData.@graph.0.url', $canonical)
            ->where('seo.structuredData.@graph.1.numberOfItems', 2));

        // Filtered/sorted combinations should not generate additional indexable URLs.
        $this->get(route('shop.index', ['page' => 2, 'sort' => 'price_asc']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('seo.canonical', route('shop.index'))
                ->where('seo.robots', 'noindex, follow'));
    }

    public function test_category_pagination_has_its_own_canonical_while_filters_stay_noindex(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        Product::factory()->count(20)->create(['category_id' => $category->id]);

        $canonical = route('categories.show', ['category' => $category->slug, 'page' => 2]);
        $this->get($canonical)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('seo.canonical', $canonical)
            ->where('seo.robots', 'index, follow, max-image-preview:large, max-snippet:-1')
            ->where('seo.structuredData.@graph.0.url', $canonical)
            ->where('seo.structuredData.@graph.1.numberOfItems', 2));

        $this->get(route('categories.show', ['category' => $category->slug, 'page' => 2, 'sort' => 'price_desc']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('seo.canonical', route('categories.show', $category->slug))
                ->where('seo.robots', 'noindex, follow'));
    }

    public function test_offers_and_exchange_pagination_preserve_crawlable_pages(): void
    {
        Product::factory()->count(20)->create([
            'trade_enabled' => true,
            'price' => 1000000,
            'discount_price' => 800000,
        ]);

        foreach (['offers.index', 'exchange-products.index'] as $routeName) {
            $canonical = route($routeName, ['page' => 2]);

            $this->get($canonical)->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('seo.canonical', $canonical)
                ->where('seo.robots', 'index, follow, max-image-preview:large, max-snippet:-1')
                ->where('seo.structuredData.@graph.0.url', $canonical));
        }
    }

    public function test_empty_paginated_catalog_does_not_claim_an_indexable_landing_page(): void
    {
        Product::factory()->create();

        $this->get(route('shop.index', ['page' => 99]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('seo.canonical', route('shop.index'))
                ->where('seo.robots', 'noindex, follow'));
    }
}
