<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_added_to_responses(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', [
                'email' => 'unknown@supersoft.id',
                'password' => 'invalid-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'unknown@supersoft.id',
            'password' => 'invalid-password',
        ])->assertTooManyRequests();
    }
}
