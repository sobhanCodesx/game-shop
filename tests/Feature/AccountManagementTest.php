<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_open_customer_dashboard(): void
    {
        $this->get(route('account.dashboard'))->assertRedirect(route('login'));
    }

    public function test_customer_can_open_dashboard_and_update_profile_with_avatar(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.dashboard'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Account/Dashboard')
            ->has('profile')
            ->has('addresses')
            ->has('accountNotifications')
            ->has('notifications.unread_count')
            ->has('notifications.latest')
            ->where('profileCompletion', 40));

        $this->actingAs($user)->patch(route('account.profile.update'), [
            'name' => 'گیمر حرفه‌ای', 'phone' => '09123456789', 'birth_date' => '2000-01-01',
            'avatar' => UploadedFile::fake()->image('avatar.webp'),
        ])->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('گیمر حرفه‌ای', $user->name);
        $this->assertSame('09123456789', $user->phone);
        Storage::disk('public')->assertExists($user->avatar);
    }

    public function test_customer_can_store_and_reset_home_experience_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('account.home-experience.update'), [
                'preference' => 'products',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('products', $user->fresh()->home_focus_preference);

        $this->actingAs($user)
            ->put(route('account.home-experience.update'), [
                'preference' => 'system',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->home_focus_preference);
    }

    public function test_customer_orders_are_filterable_and_paginated_in_dashboard(): void
    {
        $user = User::factory()->create();
        $attributes = ['user_id' => $user->id, 'shipping_address' => [], 'regular_subtotal' => 100_000, 'product_discount' => 0, 'subtotal' => 100_000, 'coupon_discount' => 0, 'delivery_fee' => 0, 'grand_total' => 100_000, 'wallet_used' => 0, 'payable_amount' => 100_000, 'cashback_percent' => 2, 'cashback_eligible_amount' => 100_000, 'cashback_amount' => 2_000];

        foreach (range(1, 10) as $index) {
            Order::query()->create([...$attributes, 'number' => "NP-DELIVERED-{$index}", 'status' => 'delivered']);
        }
        Order::query()->create([...$attributes, 'number' => 'NP-PENDING', 'status' => 'pending']);

        $this->actingAs($user)->get(route('account.dashboard', ['tab' => 'orders', 'status' => 'delivered']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Account/Dashboard')
                ->where('filters.tab', 'orders')
                ->where('filters.status', 'delivered')
                ->has('orders.data', 9)
                ->where('orders.total', 10)
                ->where('orders.last_page', 2)
                ->where('orderStatusCounts.delivered', 10)
                ->where('orderStatusCounts.pending', 1));
    }

    public function test_customer_can_manage_only_their_addresses(): void
    {
        $user = User::factory()->create();
        $payload = ['title' => 'خانه', 'recipient_name' => $user->name, 'phone' => '09123456789', 'province' => 'تهران', 'city' => 'تهران', 'postal_code' => '1234567890', 'address_line' => 'خیابان آزادی', 'plaque' => '۱۲', 'unit' => '۲', 'is_default' => false];

        $this->actingAs($user)->post(route('account.addresses.store'), $payload)->assertSessionHasNoErrors();
        $address = $user->addresses()->firstOrFail();
        $this->assertTrue($address->is_default);

        $otherAddress = User::factory()->create()->addresses()->create($payload);
        $this->actingAs($user)->delete(route('account.addresses.destroy', $otherAddress))->assertNotFound();
        $this->actingAs($user)->delete(route('account.addresses.destroy', $address))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('user_addresses', ['id' => $address->id]);
    }

    public function test_customer_can_change_password_with_current_password(): void
    {
        $user = User::factory()->create(['password' => 'oldplayer123']);
        $this->actingAs($user)->put(route('account.password.update'), ['current_password' => 'wrong', 'password' => 'newplayer123', 'password_confirmation' => 'newplayer123'])->assertSessionHasErrors('current_password');
        $this->actingAs($user)->put(route('account.password.update'), ['current_password' => 'oldplayer123', 'password' => 'newplayer123', 'password_confirmation' => 'newplayer123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('newplayer123', $user->fresh()->password));

        $this->post(route('logout'));
        $this->post(route('login.store'), ['identifier' => $user->email, 'password' => 'newplayer123'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_google_customer_can_set_their_first_password_without_a_current_password(): void
    {
        $user = User::factory()->create(['password' => null, 'google_id' => 'google-user']);

        $this->actingAs($user)->put(route('account.password.update'), [
            'password' => 'newplayer123',
            'password_confirmation' => 'newplayer123',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('newplayer123', $user->fresh()->password));

        $this->post(route('logout'));
        $this->post(route('login.store'), ['identifier' => $user->email, 'password' => 'newplayer123'])
            ->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_clicking_notification_marks_it_read_and_redirects_to_internal_destination(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'test',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode(['title' => 'سفارش', 'message' => 'جزئیات', 'url' => 'https://localhost/orders/42']),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->patch(route('account.notifications.mark-read', $id))->assertRedirect('/orders/42');
        $this->assertNotNull($user->notifications()->findOrFail($id)->read_at);
        $this->actingAs($other)->patch(route('account.notifications.mark-read', $id))->assertNotFound();
    }
}
