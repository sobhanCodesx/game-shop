<?php

namespace Tests\Feature;

use App\Models\SocialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoWatchProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_store_and_read_video_progress(): void
    {
        $user = User::factory()->create();
        $video = SocialContent::query()->create([
            'type' => 'video',
            'title' => 'Progress test video',
            'slug' => 'progress-test-video',
            'duration' => 120,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->postJson("/watch-progress/{$video->id}", [
                'position_seconds' => 47,
                'duration_seconds' => 120,
            ])
            ->assertOk()
            ->assertJsonPath('progress.contentId', $video->id)
            ->assertJsonPath('progress.position', 47)
            ->assertJsonPath('progress.completed', false);

        $this->assertDatabaseHas('video_watch_progress', [
            'user_id' => $user->id,
            'social_content_id' => $video->id,
            'position_seconds' => 47,
            'duration_seconds' => 120,
            'completed' => false,
        ]);

        $this->actingAs($user)
            ->getJson('/watch-progress')
            ->assertOk()
            ->assertJsonPath("progress.{$video->id}.position", 47)
            ->assertJsonPath("progress.{$video->id}.duration", 120);
    }

    public function test_near_end_is_marked_completed_and_guest_cannot_use_server_history(): void
    {
        $user = User::factory()->create();
        $video = SocialContent::query()->create([
            'type' => 'video',
            'title' => 'Completed progress test',
            'slug' => 'completed-progress-test',
            'duration' => 100,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->actingAs($user)
            ->postJson("/watch-progress/{$video->id}", [
                'position_seconds' => 98,
                'duration_seconds' => 100,
            ])
            ->assertOk()
            ->assertJsonPath('progress.completed', true);

        auth()->logout();
        $this->getJson('/watch-progress')->assertUnauthorized();
    }
}
