<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_register_login_me_and_password_change(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'Budi Santoso',
            'email' => 'budi@example.test',
            'password' => 'RahasiaKuat99',
            'phone' => '081234567890',
        ]);

        $register->assertCreated()->assertJsonPath('data.user.email', 'budi@example.test');
        $token = $register->json('data.token');

        $me = $this->withToken($token)->getJson('/api/v1/auth/me');
        $me->assertOk()
            ->assertJsonPath('data.user.email', 'budi@example.test')
            ->assertJsonFragment(['customer.order.create']);

        // Duplicate email rejected
        $this->postJson('/api/v1/auth/register', [
            'name' => 'X', 'email' => 'budi@example.test', 'password' => 'RahasiaKuat99',
        ])->assertStatus(422)->assertJsonPath('error.code', 'VALIDATION_FAILED');

        // Wrong password
        $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@example.test', 'password' => 'wrongpass',
        ])->assertStatus(401)->assertJsonPath('error.code', 'INVALID_CREDENTIALS');

        // Login ok
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'budi@example.test', 'password' => 'RahasiaKuat99',
        ]);
        $login->assertOk()->assertJsonPath('data.user.email', 'budi@example.test');
        $token2 = $login->json('data.token');

        // Change password revokes other sessions
        $this->withToken($token2)->putJson('/api/v1/auth/password', [
            'current_password' => 'RahasiaKuat99',
            'password' => 'RahasiaBaru88',
        ])->assertOk();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->withToken($token2)->getJson('/api/v1/auth/me')->assertOk();

        // Logout kills current token
        $this->withToken($token2)->postJson('/api/v1/auth/logout')->assertOk();
        $this->withToken($token2)->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_health_endpoint_reports_db(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('data.status', 'ok');
    }
}
