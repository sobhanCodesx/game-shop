<?php

namespace Tests\Unit;

use App\Models\SocialContent;
use App\Models\User;
use App\Notifications\Channels\ExpoPushChannel;
use App\Notifications\ContentPublishedNotification;
use PHPUnit\Framework\TestCase;

class ContentPublishedNotificationTest extends TestCase
{
    public function test_content_notification_includes_expo_push_when_feed_notifications_are_enabled(): void
    {
        $user = new User();
        $user->setRelation('contentNotificationPreference', null);

        $content = new SocialContent();
        $content->forceFill([
            'id' => 10,
            'type' => 'post',
            'title' => 'Test feed',
            'slug' => 'test-feed',
            'game_id' => null,
        ]);

        $channels = (new ContentPublishedNotification($content))->via($user);

        $this->assertContains('database', $channels);
        $this->assertContains(ExpoPushChannel::class, $channels);
    }
}
