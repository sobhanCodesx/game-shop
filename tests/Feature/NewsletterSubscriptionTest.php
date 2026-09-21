<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_subscribe_to_newsletter(): void
    {
        $this->from('/')
            ->post(route('newsletter.store'), [
                'email' => 'Player@Example.COM ',
            ])
            ->assertRedirect('/')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('newsletter_subscriptions', [
            'email' => 'player@example.com',
        ]);
    }

    public function test_newsletter_subscription_is_idempotent_for_same_email(): void
    {
        $this->post(route('newsletter.store'), [
            'email' => 'player@example.com',
        ])->assertSessionHasNoErrors();

        $this->post(route('newsletter.store'), [
            'email' => 'PLAYER@example.com',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, NewsletterSubscription::query()->count());
    }

    public function test_newsletter_rejects_invalid_email(): void
    {
        $this->from('/')
            ->post(route('newsletter.store'), [
                'email' => 'not-an-email',
            ])
            ->assertRedirect('/')
            ->assertSessionHasErrors('email');
    }
}
