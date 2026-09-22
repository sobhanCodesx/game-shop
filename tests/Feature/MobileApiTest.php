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

    public function test_optional_mobile_route_falls_back_to_guest_when_token_is_stale(): void
    {
        $this->withToken('stale-token-from-another-environment')
            ->getJson('/api/v1/stories')
            ->assertOk();
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
            ->assertJsonPath("progress.{$video->id}.position", 98)
            ->assertJsonPath("progress.{$video->id}.content.id", $video->id)
            ->assertJsonPath('items.0.content.id', $video->id);
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

    public function test_mobile_stories_use_the_same_short_records_as_storefront(): void
    {
        $short = SocialContent::query()->create([
            'type' => 'short',
            'media_type' => 'image',
            'title' => 'Wolverine Story',
            'slug' => 'wolverine-story',
            'video_path' => 'shorts/wolverine.webp',
            'thumbnail' => 'shorts/wolverine.webp',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->getJson('/api/v1/stories')
            ->assertOk()
            ->assertJsonPath('data.0.id', $short->id)
            ->assertJsonPath('data.0.title', 'Wolverine Story')
            ->assertJsonPath('data.0.media_type', 'image');

        $this->getJson('/api/v1/shorts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $short->id);
    }

    public function test_phone_password_login_requires_verified_phone_on_mobile_api(): void
    {
        $user = User::factory()->create([
            'phone' => '09121234567',
            'phone_verified_at' => null,
            'status' => 'active',
            'password' => 'player1234',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'identifier' => '09121234567',
            'password' => 'player1234',
            'device_name' => 'Android Phone',
        ])->assertStatus(409)
            ->assertJsonPath('code', 'verification_required')
            ->assertJsonPath('channel', 'mobile')
            ->assertJsonPath('identifier', '09121234567');

        $this->assertDatabaseCount('mobile_access_tokens', 0);
        $this->assertDatabaseHas('mobile_verification_codes', [
            'phone' => '09121234567',
            'purpose' => 'verify_mobile',
        ]);
    }

    public function test_mobile_passwordless_request_does_not_issue_code_for_unverified_phone(): void
    {
        $user = User::factory()->create([
            'phone' => '09122345678',
            'phone_verified_at' => null,
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/auth/passwordless/request', [
            'phone' => $user->phone,
        ])->assertOk();

        $this->assertDatabaseMissing('mobile_verification_codes', [
            'phone' => $user->phone,
            'purpose' => 'passwordless_login',
        ]);
    }

}
