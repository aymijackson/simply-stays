<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_failed_login_attempts_are_throttled(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        for ($i = 0; $i < 6; $i++) {
            $response = $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        // The 7th attempt within a minute should be rate limited, not just rejected for bad credentials.
        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_api_routes_are_throttled_per_the_configured_api_limiter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        for ($i = 0; $i < 60; $i++) {
            $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/user');
        }

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/user');

        $response->assertStatus(429);
    }
}
