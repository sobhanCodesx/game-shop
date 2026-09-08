<?php

namespace Tests\Feature;

use App\Jobs\BroadcastContentPublished;
use App\Models\Game;
use App\Models\Product;
use App\Models\SocialContent;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\ContentPublishedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_content_notification_preferences_default_to_sms_and_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/account?tab=content-notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.tab', 'content-notifications')
                ->where('contentNotificationPreferences.sms_enabled', true)
                ->where('contentNotificationPreferences.email_enabled', false)
                ->where('contentNotificationPreferences.feed_enabled', false));

        $this->actingAs($user)
            ->put(route('account.content-notifications.update'), [
                'sms_enabled' => false,
                'email_enabled' => true,
                'feed_enabled' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('content_notification_preferences', [
            'user_id' => $user->id,
            'sms_enabled' => false,
            'email_enabled' => true,
            'feed_enabled' => true,
        ]);
    }

    public function test_published_content_is_sent_only_to_channel_subscribers_using_their_preferences(): void
    {
        Notification::fake();
        $game = Game::factory()->create();
        $subscriber = User::factory()->create(['phone' => '09121234567']);
        $defaultSubscriber = User::factory()->create(['phone' => '09123333333']);
        $outsider = User::factory()->create(['phone' => '09121111111']);
        $subscriber->subscribedGames()->attach($game);
        $defaultSubscriber->subscribedGames()->attach($game);
        $subscriber->contentNotificationPreference()->create([
            'sms_enabled' => false,
            'email_enabled' => true,
            'feed_enabled' => true,
        ]);
        $product = Product::withoutEvents(fn () => Product::factory()->create([
            'game_id' => $game->id,
            'status' => 'published',
            'visibility' => 'public',
        ]));

        (new BroadcastContentPublished('product', $product->id))->handle();

        Notification::assertSentTo(
            $subscriber,
            ContentPublishedNotification::class,
            fn (ContentPublishedNotification $notification, array $channels) => in_array('database', $channels, true)
                && in_array('mail', $channels, true)
                && ! in_array(SmsChannel::class, $channels, true),
        );
        Notification::assertNotSentTo($outsider, ContentPublishedNotification::class);
        Notification::assertSentTo(
            $defaultSubscriber,
            ContentPublishedNotification::class,
            fn (ContentPublishedNotification $notification, array $channels) => in_array('database', $channels, true)
                && in_array(SmsChannel::class, $channels, true)
                && ! in_array('mail', $channels, true),
        );
    }

    public function test_first_publication_of_products_and_videos_queues_one_broadcast(): void
    {
        Queue::fake();
        $game = Game::factory()->create();
        $author = User::factory()->create();

        $product = Product::factory()->create([
            'game_id' => $game->id,
            'status' => 'published',
            'visibility' => 'public',
        ]);
        $product->update(['title' => 'عنوان ویرایش‌شده']);

        SocialContent::query()->create([
            'user_id' => $author->id,
            'game_id' => $game->id,
            'type' => 'video',
            'title' => 'ویدیوی تازه',
            'slug' => 'fresh-video',
            'status' => 'published',
            'published_at' => now(),
        ]);

        Queue::assertPushed(
            BroadcastContentPublished::class,
            2,
        );
        Queue::assertPushed(
            BroadcastContentPublished::class,
            fn (BroadcastContentPublished $job) => $job->contentType === 'product' && $job->contentId === $product->id,
        );
        Queue::assertPushed(
            BroadcastContentPublished::class,
            fn (BroadcastContentPublished $job) => $job->contentType === 'social',
        );
    }
}
