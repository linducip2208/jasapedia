<?php

namespace App\Domain\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'status' => 'active',
            ]);

            $user->profile()->create([]);
            $user->roles()->attach(
                \App\Models\Role::query()->where('name', 'Customer')->value('id')
            );

            event(new \Illuminate\Auth\Events\Registered($user));

            return $user;
        });
    }

    public function attemptLogin(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            if ($user) {
                $user->increment('failed_login_attempts');
                if ($user->failed_login_attempts >= 5) {
                    $user->forceFill(['locked_until' => now()->addMinutes(15)])->save();
                }
            }

            throw new DomainException('Invalid credentials.', 'INVALID_CREDENTIALS', 401);
        }

        if (! $user->isActive()) {
            throw new DomainException(
                $user->status !== 'active' ? 'Account suspended.' : 'Account temporarily locked. Try again later.',
                $user->status !== 'active' ? 'ACCOUNT_SUSPENDED' : 'ACCOUNT_LOCKED',
                403,
            );
        }

        $user->forceFill(['failed_login_attempts' => 0, 'locked_until' => null])->save();

        return $user;
    }

    public function changePassword(User $user, string $current, string $new): void
    {
        if (! Hash::check($current, $user->password)) {
            throw new DomainException('Current password is incorrect.', 'INVALID_CREDENTIALS', 422);
        }

        $user->forceFill(['password' => $password = Hash::make($new)])->save();
    }

    public function lockUser(User $user, ?string $reason = null): void
    {
        $user->forceFill(['status' => 'suspended'])->save();
        $user->tokens()->delete();

        app(\App\Support\Audit\AuditLogger::class)->log('user.suspended', $user, null, ['status' => 'suspended'], $reason);
    }
}
