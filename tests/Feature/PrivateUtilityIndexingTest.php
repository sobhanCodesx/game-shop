<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivateUtilityIndexingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_public_account_utility_pages_send_noindex_header(): void
    {
        foreach (['/login', '/register', '/verify-email', '/forgot-password'] as $path) {
            $this->get($path)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    public function test_login_redirect_parameter_does_not_create_indexable_url(): void
    {
        $this->get('/login?redirect=%2Fvideos%2Fexample')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_home_page_remains_indexable(): void
    {
        $this->get('/')->assertDontSee('X-Robots-Tag: noindex', false);
        $this->assertNull($this->get('/')->headers->get('X-Robots-Tag'));
    }
}
