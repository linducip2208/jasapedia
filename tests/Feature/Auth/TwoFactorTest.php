<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    private function staffUser()
    {
        $user = \App\Models\User::factory()->create([
            'password' => Hash::make('StaffPass123'),
        ]);
        $user->roles()->attach(\App\Models\Role::where('name', 'FinanceManager')->value('id'));

        return $user;
    }

    public function test_full_2fa_lifecycle(): void
    {
        $user = $this->staffUser();

        // Login works without 2FA
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'StaffPass123']);
        $login->assertOk();
        $token = $login->json('data.token');

        // Start → secret + otpauth
        $start = $this->withToken($token)->postJson('/api/v1/auth/2fa/enable');
        $start->assertOk()->assertJsonStructure(['data' => ['secret', 'otpauth_url']]);
        $secret = $start->json('data.secret');

        // Confirm with valid TOTP
        $code = \App\Domain\Auth\Totp::at($secret, intdiv(time(), 30));
        $this->withToken($token)->postJson('/api/v1/auth/2fa/confirm', ['code' => $code])
            ->assertOk();

        $this->assertNotNull($user->fresh()->two_factor_enabled_at);

        // Login now requires code
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'StaffPass123'])
            ->assertStatus(401)
            ->assertJsonPath('error.code', '2FA_CHALLENGE');

        // Wrong code rejected
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'StaffPass123', 'code' => '000000',
        ])->assertStatus(401)->assertJsonPath('error.code', '2FA_CHALLENGE');

        // Valid code → in
        $code2 = \App\Domain\Auth\Totp::at($secret, intdiv(time(), 30));
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'StaffPass123', 'code' => $code2,
        ])->assertOk();

        // Disable
        $code3 = \App\Domain\Auth\Totp::at($secret, intdiv(time(), 30));
        $login2 = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'StaffPass123', 'code' => $code3,
        ]);
        $token2 = $login2->json('data.token');

        $code4 = \App\Domain\Auth\Totp::at($secret, intdiv(time(), 30) + 1);
        $this->withToken($token2)->postJson('/api/v1/auth/2fa/disable', ['code' => $code4])
            ->assertOk();

        $this->assertNull($user->fresh()->two_factor_enabled_at);

        // Login without code works again
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'StaffPass123'])
            ->assertOk();
    }

    public function test_totp_window_tolerance(): void
    {
        $secret = \App\Domain\Auth\Totp::generateSecret();
        $ts = 1000000000;
        $code = \App\Domain\Auth\Totp::at($secret, intdiv($ts, 30));

        $this->assertTrue(\App\Domain\Auth\Totp::verify($secret, $code, $ts));
        $this->assertTrue(\App\Domain\Auth\Totp::verify($secret, $code, $ts + 29));   // same window
        $this->assertTrue(\App\Domain\Auth\Totp::verify($secret, $code, $ts - 31));   // previous window
        $this->assertFalse(\App\Domain\Auth\Totp::verify($secret, $code, $ts - 91));  // too far
        $this->assertFalse(\App\Domain\Auth\Totp::verify($secret, 'abc123', $ts));
    }
}
