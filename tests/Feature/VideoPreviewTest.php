<?php

namespace Tests\Feature;

use App\Models\SocialContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_video_exposes_preview_metadata(): void
    {
        $video = SocialContent::query()->create([
            'type' => 'video',
            'title' => 'Preview video',
            'slug' => 'preview-video',
            'video_path' => 'videos/preview.mp4',
            'duration' => 90,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/video-previews/{$video->slug}")
            ->assertOk()
            ->assertJsonPath('id', $video->id)
            ->assertJsonPath('duration', 90);

        $this->assertStringContainsString(
            'preview.mp4',
            (string) $response->json('video_url'),
        );
    }

    public function test_unpublished_content_cannot_be_previewed(): void
    {
        $video = SocialContent::query()->create([
            'type' => 'video',
            'title' => 'Draft preview',
            'slug' => 'draft-preview',
            'video_path' => 'videos/draft.mp4',
            'status' => 'draft',
            'published_at' => null,
        ]);

        $this->getJson("/video-previews/{$video->slug}")->assertNotFound();
    }
}
