<?php

namespace Tests\Feature;

use App\Services\NexusAiSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusAiPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dedicated_nexus_ai_page_is_indexable_when_enabled(): void
    {
        app(NexusAiSettings::class)->update([
            'nexus_ai_enabled' => true,
            'nexus_ai_page_enabled' => true,
        ]);

        $this->get('/nexus-ai')
            ->assertOk()
            ->assertSee('Nexus AI')
            ->assertSee('index, follow', false);

        $this->get('/sitemaps/static.xml')
            ->assertOk()
            ->assertSee(route('nexus-ai.index'), false);
    }

    public function test_dedicated_nexus_ai_page_disappears_when_disabled(): void
    {
        $settings = app(NexusAiSettings::class);

        $settings->update([
            'nexus_ai_enabled' => true,
            'nexus_ai_page_enabled' => true,
        ]);

        $this->get('/sitemaps/static.xml')
            ->assertOk()
            ->assertSee(route('nexus-ai.index'), false);

        $settings->update([
            'nexus_ai_enabled' => true,
            'nexus_ai_page_enabled' => false,
        ]);

        $this->get('/nexus-ai')->assertNotFound();

        $this->get('/sitemaps/static.xml')
            ->assertOk()
            ->assertDontSee(route('nexus-ai.index'), false);
    }
}
