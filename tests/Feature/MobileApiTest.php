<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SocialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_customer_can_login_with_bearer_token_and_fetch_profile(): void
    {
        $user = User::factory()->create([
            'name' => 'Mobile Player',
            'email' => 'mobile@example.com',
            'email_verified_at' => now(),
            'status' => 'active',
            'password' => 'player1234',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'identifier' => 'mobile@example.com',
            'password' => 'player1234',
            'device_name' => 'Test Android',
        ]);

        $login->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'mobile@example.com');

        $token = (string) $login->json('access_token');
        $this->assertNotSame('', $token);

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('profile.id', $user->id)
            ->assertJsonPath('profile.name', 'Mobile Player');

        $this->assertDatabaseHas('mobile_access_tokens', [
            'user_id' => $user->id,
            'device_name' => 'Test Android',
        ]);
    }

    public function test_bearer_user_can_pass_current_password_validation(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status' => 'active',
            'password' => 'player1234',
        ]);
        $token = app(\App\Services\MobileApiTokenService::class)
            ->issue($user, 'Password test')['plain_text_token'];

        $this->withToken($token)
            ->putJson('/api/v1/me/password', [
                'current_password' => 'player1234',
                'password' => 'newplayer5678',
                'password_confirmation' => 'newplayer5678',
            ])
            ->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check(
            'newplayer5678',
            $user->fresh()->password,
        ));
    }

    public function test_mobile_api_rejects_protected_endpoint_without_token(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('code', 'mobile_unauthenticated');
    }

    public function test_mobile_cart_is_stateless_and_resolves_server_prices(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        $product = Product::factory()->create([
            'status' => 'published',
            'visibility' => 'public',
            'price' => 1_100_000,
            'discount_price' => 1_000_000,
            'stock' => 5,
            'reserved_stock' => 0,
        ]);
        $token = app(\App\Services\MobileApiTokenService::class)
            ->issue($user, 'Test device')['plain_text_token'];

        $this->withToken($token)
            ->postJson('/api/v1/cart/resolve', [
                'items' => [[
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'quantity' => 2,
                ]],
            ])
            ->assertOk()
            ->assertJsonPath('items.0.product_id', $product->id)
            ->assertJsonPath('items.0.quantity', 2)
            ->assertJsonPath('items.0.unit_price', 1_000_000)
            ->assertJsonPath('summary.subtotal', 2_000_000);
    }

    public function test_mobile_watch_progress_uses_bearer_auth(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $video = SocialContent::query()->create([
            'type' => 'video',
            'title' => 'Native playback test',
            'slug' => 'native-playback-test',
            'duration' => 100,
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $token = app(\App\Services\MobileApiTokenService::class)
            ->issue($user, 'Test device')['plain_text_token'];

        $this->withToken($token)
            ->putJson("/api/v1/watch-progress/{$video->id}", [
                'position_seconds' => 98,
                'duration_seconds' => 100,
            ])
            ->assertOk()
            ->assertJsonPath('progress.content_id', $video->id)
            ->assertJsonPath('progress.completed', true);

        $this->withToken($token)
            ->getJson('/api/v1/watch-progress')
            ->assertOk()
            ->assertJsonPath("progress.{$video->id}.position", 98);
    }


    public function test_mobile_relevance_profile_does_not_require_a_web_session(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $request = Request::create('/api/v1/home', 'GET');
        $request->setUserResolver(fn () => $user);

        $profile = app(\App\Services\UserGamingRelevanceService::class)
            ->profile($request, collect());

        $this->assertIsArray($profile);
        $this->assertArrayHasKey('game_scores', $profile);
        $this->assertArrayHasKey('confidence', $profile);
    }

    public function test_public_mobile_content_endpoint_returns_native_contract(): void
    {
        $content = SocialContent::query()->create([
            'type' => 'post',
            'feed_type' => 'news',
            'title' => 'Native API story',
            'slug' => 'native-api-story',
            'excerpt' => 'A mobile API story',
            'body' => '<p>Body</p>',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->getJson("/api/v1/contents/{$content->slug}")
            ->assertOk()
            ->assertJsonPath('content.id', $content->id)
            ->assertJsonPath('content.title', 'Native API story')
            ->assertJsonPath('content.feed_type', 'news');
    }

    public function test_mobile_stories_are_separate_from_shorts(): void
    {
        $story = SocialContent::query()->create([
            'type' => 'story',
            'media_type' => 'image',
            'title' => 'Story only',
            'slug' => 'story-only',
            'video_path' => 'stories/story.webp',
            'thumbnail' => 'stories/story.webp',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $short = SocialContent::query()->create([
            'type' => 'short',
            'media_type' => 'video',
            'title' => 'Short only',
            'slug' => 'short-only',
            'video_path' => 'shorts/short.mp4',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/v1/stories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $story->id)
            ->assertJsonMissing(['id' => $short->id]);

        $this->getJson('/api/v1/shorts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $short->id)
            ->assertJsonMissing(['id' => $story->id]);
    }

}
