<?php

namespace Tests\Feature;

use App\Models\SocialContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoverViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_explore_view_is_counted_once_per_session(): void
    {
        $content = SocialContent::query()->create([
            'type' => 'post',
            'title' => 'Explore test',
            'slug' => 'explore-test',
            'views' => 0,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->post(route('discover.views.store', ['content' => $content->slug]))
            ->assertOk()
            ->assertJson(['views' => 1]);

        $this->post(route('discover.content.view', $content))
            ->assertOk()
            ->assertJson(['views' => 1]);

        $this->assertDatabaseHas('social_contents', ['id' => $content->id, 'views' => 1]);
    }
}
