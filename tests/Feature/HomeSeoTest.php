<?php

namespace Tests\Feature;

use App\Models\HomeSetting;
use App\Models\HomeSlide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HomeSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_exposes_complete_server_rendered_and_inertia_seo(): void
    {
        HomeSetting::query()->create([
            'content' => [
                'seo_title' => 'فروشگاه گیمینگ تست | PlayNexus',
                'seo_description' => 'توضیحات واقعی و اختصاصی صفحه اصلی فروشگاه گیمینگ تست.',
            ],
        ]);
        HomeSlide::query()->create([
            'title' => 'بنر تست',
            'alt' => 'کنسول و تجهیزات گیمینگ در بنر اصلی',
            'desktop_image' => 'home/slides/seo-banner.jpg',
            'is_active' => true,
        ]);

        $response = $this->get('/?utm_source=campaign');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Home')
            ->where('seo.title', 'فروشگاه گیمینگ تست - پلی نکسوس')
            ->where('seo.description', 'توضیحات واقعی و اختصاصی صفحه اصلی فروشگاه گیمینگ تست.')
            ->where('seo.canonical', 'http://localhost')
            ->where('seo.robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1')
            ->where('seo.image', 'http://localhost/storage/home/slides/seo-banner.jpg')
            ->where('seo.imageAlt', 'کنسول و تجهیزات گیمینگ در بنر اصلی')
            ->where('seo.structuredData.@context', 'https://schema.org')
            ->where('seo.structuredData.@graph.0.@type', 'Organization')
            ->where('seo.structuredData.@graph.1.@type', 'WebSite')
            ->has('head', 18));

        $response->assertSee('<title data-inertia="title">فروشگاه گیمینگ تست - پلی نکسوس</title>', false);
        $response->assertSee('<link data-inertia="canonical" rel="canonical" href="http://localhost">', false);
        $response->assertSee('<meta data-inertia="og:image" property="og:image" content="http://localhost/storage/home/slides/seo-banner.jpg">', false);
        $response->assertSee('<script data-inertia="structured-data" type="application/ld+json">', false);
        $response->assertSee('<html class="dark" data-theme="dark" lang="fa-IR" dir="rtl">', false);

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertCount(1, $document->getElementsByTagName('title'));
        $this->assertCount(1, $xpath->query('//meta[@name="description"]'));
    }
}
