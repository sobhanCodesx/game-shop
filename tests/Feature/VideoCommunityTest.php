<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\SocialComment;
use App\Models\SocialContent;
use App\Models\User;
use App\Models\VideoPlaylist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VideoCommunityTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_authenticated_users_can_react_comment_and_subscribe(): void
    {
        [$game, $video] = $this->channelWithVideo();

        $this->postJson(route('videos.reaction', $video->slug), ['type' => 'like'])->assertUnauthorized();
        $this->postJson(route('videos.comments.store', $video->slug), ['body' => 'نظر'])->assertUnauthorized();
        $this->postJson(route('channels.subscription', $game->slug))->assertUnauthorized();
    }

    public function test_member_can_toggle_video_reactions_and_channel_subscription(): void
    {
        [$game, $video] = $this->channelWithVideo();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('videos.reaction', $video->slug), ['type' => 'like'])
            ->assertOk()->assertJsonPath('reaction', 'like');
        $this->assertDatabaseHas('social_content_reactions', ['social_content_id' => $video->id, 'user_id' => $user->id, 'type' => 'like']);

        $this->actingAs($user)->postJson(route('videos.reaction', $video->slug), ['type' => 'dislike'])
            ->assertOk()->assertJsonPath('reaction', 'dislike');
        $this->assertDatabaseCount('social_content_reactions', 1);

        $this->actingAs($user)->postJson(route('videos.reaction', $video->slug), ['type' => 'dislike'])
            ->assertOk()->assertJsonPath('reaction', null);
        $this->assertDatabaseCount('social_content_reactions', 0);

        $this->actingAs($user)->postJson(route('channels.subscription', $game->slug))
            ->assertOk()->assertJsonPath('subscribed', true);
        $this->assertDatabaseHas('game_subscriptions', ['game_id' => $game->id, 'user_id' => $user->id]);

        $this->actingAs($user)->postJson(route('channels.subscription', $game->slug))
            ->assertOk()->assertJsonPath('subscribed', false);
        $this->assertDatabaseMissing('game_subscriptions', ['game_id' => $game->id, 'user_id' => $user->id]);
    }

    public function test_member_can_comment_reply_like_and_delete_own_comment(): void
    {
        [, $video] = $this->channelWithVideo();
        $user = User::factory()->create();
        $other = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('videos.comments.store', $video->slug), ['body' => 'نظر اصلی'])
            ->assertCreated();
        $comment = SocialComment::query()->findOrFail($response->json('comment_id'));

        $replyResponse = $this->actingAs($other)->postJson(route('videos.comments.store', $video->slug), [
            'body' => 'پاسخ نظر', 'parent_id' => $comment->id,
        ])->assertCreated();
        $reply = SocialComment::query()->findOrFail($replyResponse->json('comment_id'));

        $this->actingAs($user)->postJson(route('videos.comments.store', $video->slug), [
            'body' => 'پاسخ سطح سوم', 'parent_id' => $reply->id,
        ])->assertStatus(422);

        $this->actingAs($user)->postJson(route('comments.like', $comment))->assertOk()->assertJsonPath('liked', true);
        $this->assertDatabaseHas('social_comment_likes', ['social_comment_id' => $comment->id, 'user_id' => $user->id]);

        $this->actingAs($other)->deleteJson(route('comments.destroy', $comment))->assertForbidden();
        $this->actingAs($user)->deleteJson(route('comments.destroy', $comment))->assertOk();
        $this->assertSoftDeleted($comment);
    }

    public function test_social_activity_reuses_database_notifications_with_real_content_urls(): void
    {
        [, $video] = $this->channelWithVideo();
        $owner = User::factory()->create();
        $commenter = User::factory()->create();
        $video->update(['user_id' => $owner->id]);

        $response = $this->actingAs($commenter)->postJson(route('videos.comments.store', $video->slug), [
            'body' => 'نظر تازه',
        ])->assertCreated();

        $notification = $owner->notifications()->firstOrFail();
        $this->assertSame('comment', $notification->data['activity']);
        $this->assertSame('/videos/'.$video->slug.'#comments', $notification->data['url']);
        $this->assertSame($response->json('comment_id'), $notification->data['comment_id']);

        $this->actingAs($commenter)->postJson(route('videos.reaction', $video->slug), ['type' => 'like'])
            ->assertOk();

        $this->assertSame(2, $owner->notifications()->count());
    }

    public function test_channel_and_playlist_expose_published_videos_and_watch_context(): void
    {
        [$game, $video] = $this->channelWithVideo();
        $playlist = VideoPlaylist::query()->create([
            'game_id' => $game->id, 'title' => 'شروع بازی', 'slug' => 'getting-started',
            'description' => '<h2>راهنمای شروع</h2><script>alert(1)</script><p>از اینجا شروع کنید.</p>',
            'visibility' => 'public',
        ]);
        $playlist->videos()->attach($video->id, ['position' => 0]);

        $this->get(route('channels.show', $game->slug))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Channels/Show')
            ->where('channel.name', $game->name)
            ->where('seo.canonical', route('channels.show', $game->slug))
            ->where('seo.robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1')
            ->where('seo.structuredData.@graph.0.@type', 'VideoGame')
            ->where('seo.structuredData.@graph.1.@type', 'BreadcrumbList')
            ->has('videos.data', 1)->has('playlists', 1));

        $this->get(route('channels.playlists.show', ['game' => $game->slug, 'playlist' => $playlist->slug]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Channels/Playlist')
            ->where('playlist.title', 'شروع بازی')
            ->where('playlist.description_html', '<h2>راهنمای شروع</h2><p>از اینجا شروع کنید.</p>')
            ->where('seo.canonical', route('collections.show', $playlist->slug))
            ->where('seo.structuredData.@graph.0.@type', 'CollectionPage')
            ->where('seo.structuredData.@graph.1.@type', 'ItemList')
            ->where('seo.structuredData.@graph.2.@type', 'BreadcrumbList')
            ->has('playlist.videos', 1));

        $this->get(route('collections.show', $playlist->slug))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Channels/Playlist')
            ->where('seo.canonical', route('collections.show', $playlist->slug))
            ->where('seo.robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1')
            ->has('playlist.videos', 1));

        $this->get(route('content.show', ['type' => 'videos', 'content' => $video->slug, 'list' => $playlist->slug]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Content/Show')->where('playlist.title', 'شروع بازی')->has('playlist.items', 1));
    }

    public function test_video_view_is_counted_once_per_session(): void
    {
        [, $video] = $this->channelWithVideo();
        $url = route('content.show', ['type' => 'videos', 'content' => $video->slug]);

        $this->get($url)->assertOk();
        $this->get($url)->assertOk();

        $this->assertSame(1, $video->refresh()->views);
    }

    public function test_admin_can_create_a_playlist_and_assign_a_video_to_it(): void
    {
        [$game, $video] = $this->channelWithVideo();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.video-playlists.store'), [
            'game_id' => $game->id,
            'title' => 'راهنمای شروع',
            'description' => '<h2>بازیکن تازه‌وارد</h2><script>alert(1)</script><p>ویدیوهای مناسب شروع بازی</p>',
            'visibility' => 'unlisted',
            'sort_order' => 1,
        ])->assertRedirect();

        $playlist = VideoPlaylist::query()->firstOrFail();
        $this->assertSame('<h2>بازیکن تازه‌وارد</h2><p>ویدیوهای مناسب شروع بازی</p>', $playlist->description);
        $this->actingAs($admin)->put(route('admin.videos.update', $video), [
            'title' => $video->title,
            'excerpt' => $video->excerpt,
            'status' => 'published',
            'featured' => false,
            'game_id' => $game->id,
            'playlist_ids' => [$playlist->id],
            'allow_comments' => false,
        ])->assertRedirect(route('admin.videos.index'));

        $this->assertDatabaseHas('playlist_video', [
            'video_playlist_id' => $playlist->id,
            'social_content_id' => $video->id,
            'position' => 0,
        ]);
        $this->assertFalse($video->refresh()->allow_comments);

        $this->get(route('channels.show', $game->slug))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('playlists', 0));
        $this->get(route('channels.playlists.show', ['game' => $game->slug, 'playlist' => $playlist->slug]))
            ->assertOk();
    }

    private function channelWithVideo(): array
    {
        $game = Game::factory()->create(['name' => 'Elden Ring', 'slug' => 'elden-ring', 'status' => 'active']);
        $video = SocialContent::query()->create([
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'راهنمای بازی',
            'slug' => 'game-guide',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'allow_comments' => true,
            'video_path' => 'videos/game-guide.mp4',
            'video_mime' => 'video/mp4',
        ]);

        return [$game, $video];
    }
}
