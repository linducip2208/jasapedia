<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domain\Auth\AuthService;
use App\Domain\Auth\DomainException;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $auth)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', PasswordRule::min(8)],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $this->auth->register($data);

        $token = $user->createToken('register', $user->permissions());

        return $this->created([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'token' => $token->plainTextToken,
        ], 'Registration successful.');
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'code' => ['sometimes', 'nullable', 'string', 'size:6'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $this->auth->attemptLogin($data['email'], $data['password']);

        // Privileged accounts with 2FA enabled must present a valid TOTP code.
        if ($user->two_factor_enabled_at !== null) {
            $secret = decrypt($user->two_factor_secret);
            if (empty($data['code']) || ! \App\Domain\Auth\Totp::verify($secret, $data['code'])) {
                throw new DomainException(
                    $user->two_factor_enabled_at === null ? '' : 'Two-factor code required/invalid.',
                    '2FA_CHALLENGE',
                    401,
                );
            }
        }

        $token = $user->createToken($data['device_name'] ?? 'login', $user->permissions());

        return $this->ok([
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'is_staff' => $user->isStaff()],
            'token' => $token->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->ok(null, 'Logged out.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile', 'roles');

        return $this->ok([
            'user' => $user,
            'permissions' => $user->permissions(),
        ]);
    }

    public function updateMe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'locale' => ['sometimes', 'string', 'max:8'],
            'profile' => ['sometimes', 'array'],
            'profile.city' => ['sometimes', 'nullable', 'string', 'max:64'],
            'profile.bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'profile.gender' => ['sometimes', 'nullable', 'string', 'max:16'],
            'profile.birth_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $user = $request->user();
        $user->fill(array_intersect_key($data, array_flip(['name', 'phone', 'locale'])));
        $user->save();

        if (isset($data['profile'])) {
            $user->profile()->updateOrCreate(['user_id' => $user->id], $data['profile']);
        }

        return $this->ok(['user' => $user->fresh('profile')], 'Profile updated.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', PasswordRule::min(8)],
        ]);

        $this->auth->changePassword($request->user(), $data['current_password'], $data['password']);

        $request->user()->tokens()->whereNot('id', $request->user()->currentAccessToken()->id)->delete();

        return $this->ok(null, 'Password changed. Other sessions revoked.');
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::sendResetLink($request->only('email'));

        return $this->ok(null, 'If the email exists, a reset link was sent.');
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', PasswordRule::min(8)],
        ]);

        $status = Password::reset(
            $data,
            fn (User $user, string $password) => $user->forceFill(['password' => Hash::make($password), 'failed_login_attempts' => 0, 'locked_until' => null])->save(),
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new DomainException(__($status), 'RESET_FAILED', 422);
        }

        return $this->ok(null, 'Password has been reset.');
    }

    public function sessions(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->get(['id', 'name', 'last_used_at', 'created_at']);

        return $this->ok(['sessions' => $tokens]);
    }

    public function revokeSession(Request $request, int $id): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $id)->delete();

        if (! $deleted) {
            throw new DomainException('Session not found.', 'NOT_FOUND', 404);
        }

        return $this->ok(null, 'Session revoked.');
    }

    public function startTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = \App\Domain\Auth\Totp::generateSecret();

        $user->forceFill(['two_factor_secret' => encrypt($secret)])->save();

        return $this->ok([
            'secret' => $secret,
            'otpauth_url' => \App\Domain\Auth\Totp::otpauthUri($secret, $user->email),
        ], 'Scan with authenticator app, then confirm with a code.');
    }

    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $user = $request->user();

        if (! $user->two_factor_secret) {
            throw new DomainException('Two-factor not started.', '2FA_NOT_STARTED', 422);
        }

        $secret = decrypt($user->two_factor_secret);

        if (! \App\Domain\Auth\Totp::verify($secret, $data['code'])) {
            throw new DomainException('Invalid verification code.', '2FA_INVALID_CODE', 422);
        }

        $user->forceFill(['two_factor_enabled_at' => now()])->save();
        app(\App\Support\Audit\AuditLogger::class)->log('user.2fa_enabled', $user);

        return $this->ok(null, 'Two-factor enabled.');
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $user = $request->user();

        if ($user->two_factor_enabled_at === null) {
            throw new DomainException('Two-factor not enabled.', '2FA_NOT_ENABLED', 422);
        }

        $secret = decrypt($user->two_factor_secret);

        if (! \App\Domain\Auth\Totp::verify($secret, $data['code'])) {
            throw new DomainException('Invalid verification code.', '2FA_INVALID_CODE', 422);
        }

        $user->forceFill(['two_factor_enabled_at' => null, 'two_factor_secret' => null])->save();
        app(\App\Support\Audit\AuditLogger::class)->log('user.2fa_disabled', $user);

        return $this->ok(null, 'Two-factor disabled.');
    }
}
