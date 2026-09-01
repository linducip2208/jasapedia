<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 46 — security hardening checks.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_security_headers_present(): void
    {
        $res = $this->getJson('/api/v1/health');

        $res->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_api_rate_limiting_active(): void
    {
        $this->postJson('/api/v1/auth/register', ['name' => 'RL', 'email' => 'rl@test.id', 'password' => 'RahasiaKuat99']);

        // auth limiter = 10/min per IP → hit login repeatedly with wrong creds
        $blocked = false;
        for ($i = 0; $i < 12; $i++) {
            $res = $this->postJson('/api/v1/auth/login', [
                'email' => 'rl@test.id', 'password' => 'wrong-'.uniqid(),
            ]);

            if ($res->status() === 429) {
                $blocked = true;
                break;
            }
        }

        $this->assertTrue($blocked, 'Rate limiter did not engage after burst');
    }

    public function test_error_envelope_never_leaks_internals(): void
    {
        $token = $this->postJson('/api/v1/auth/register', [
            'name' => 'V', 'email' => 'v@test.id', 'password' => 'RahasiaKuat99',
        ])->json('data.token');

        // Validation failure (this user has customer.order.create; service_id bogus → 422)
        $res = $this->withToken($token)->postJson('/api/v1/orders/quote', [
            'service_id' => 'not-an-integer',
        ]);
        $res->assertStatus(422);
        $body = $res->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('vendor/', $body);
        $this->assertStringNotContainsString('D:\\project', $body);
        $this->assertNotNull(json_decode($body, true)['error']['reference_id'] ?? null);
    }

    public function test_unauthenticated_money_endpoints_are_401(): void
    {
        $this->postJson('/api/v1/orders', ['service_id' => 1])->assertStatus(401);
        $this->postJson('/api/v1/payments/intent', ['order_id' => 1])->assertStatus(401);
        $this->getJson('/api/v1/orders')->assertStatus(401);
        $this->postJson('/api/v1/field/orders/1/on-the-way')->assertStatus(401);
    }

    public function test_admin_endpoints_require_permission(): void
    {
        $token = $this->postJson('/api/v1/auth/register', [
            'name' => 'Pleb', 'email' => 'pleb@test.id', 'password' => 'RahasiaKuat99',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/v1/admin/disputes', [
            'dispute_id' => 1, 'resolution' => 'full_refund', 'note' => 'x',
        ])->assertStatus(403);
    }
}
