<?php

namespace Tests\Feature\Admin;

use App\Models\SocialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShortManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_edit_prioritize_and_delete_an_image_story(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/shorts', [
            'title' => 'استوری پیشنهاد ویژه',
            'media' => UploadedFile::fake()->image('story.jpg', 1080, 1920),
            'status' => 'published',
            'sort_order' => 2,
            'link_url' => '/products',
        ])->assertRedirect('/admin/shorts');

        $short = SocialContent::firstOrFail();
        $this->assertSame('short', $short->type);
        $this->assertSame('image', $short->media_type);
        $this->assertSame('/products', $short->link_url);
        Storage::disk('public')->assertExists($short->video_path);

        $this->actingAs($admin)->put("/admin/shorts/{$short->id}", [
            'title' => 'استوری اول',
            'status' => 'published',
            'sort_order' => 0,
            'link_url' => 'https://example.com/story',
        ])->assertRedirect('/admin/shorts');
        $this->assertDatabaseHas('social_contents', ['id' => $short->id, 'title' => 'استوری اول', 'sort_order' => 0]);

        $this->actingAs($admin)->delete("/admin/shorts/{$short->id}")->assertRedirect();
        $this->assertDatabaseMissing('social_contents', ['id' => $short->id]);
        Storage::disk('public')->assertMissing($short->video_path);
    }

    public function test_published_shorts_are_shared_as_stories_but_excluded_from_explore(): void
    {
        SocialContent::create([
            'type' => 'short', 'media_type' => 'image', 'title' => 'استوری', 'slug' => 'story',
            'video_path' => 'shorts/story.webp', 'thumbnail' => 'shorts/story.webp',
            'status' => 'published', 'published_at' => now()->subMinute(), 'sort_order' => 1,
        ]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('storefront.stories', 1)
            ->where('storefront.stories.0.title', 'استوری'));
        $this->get('/discover')->assertInertia(fn (Assert $page) => $page
            ->has('feed.data', 0));
    }

    public function test_published_legacy_short_without_media_does_not_break_inertia_pages(): void
    {
        SocialContent::create([
            'type' => 'short', 'title' => 'شورت ناقص قدیمی', 'slug' => 'legacy-short',
            'status' => 'published', 'published_at' => now()->subMinute(),
        ]);

        $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page->has('storefront.stories', 0));
        $this->actingAs(User::factory()->create(['is_admin' => true]))->get('/admin/shorts')->assertOk();
    }
}
