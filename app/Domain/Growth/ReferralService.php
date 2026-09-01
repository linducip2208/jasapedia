<?php

namespace App\Domain\Growth;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Str;

class ReferralService
{
    public function code(User $user): string
    {
        return 'REF-'.strtoupper(substr(md5($user->id.'jasapedia'), 0, 8));
    }

    /** Attach inviter to a new user by code. */
    public function attach(User $invitee, string $referralCode, string $audience = 'customer'): ?Referral
    {
        $expected = self::code($invitee);
        if ($referralCode === $expected) {
            return null; // self-referral
        }

        // find owner of code deterministically
        $referrer = User::query()->get()->first(fn (User $u) => $this->code($u) === $referralCode);

        if (! $referrer || $referrer->id === $invitee->id) {
            return null;
        }

        return Referral::firstOrCreate(
            ['invitee_id' => $invitee->id],
            [
                'referrer_id' => $referrer->id,
                'referral_code' => $referralCode,
                'audience' => $audience,
                'status' => 'invited',
            ],
        );
    }

    /** Qualify when invitee completes first paid order. */
    public function qualify(User $invitee): void
    {
        $referral = Referral::where('invitee_id', $invitee->id)->where('status', 'invited')->first();

        if (! $referral) {
            return;
        }

        $settings = app(\App\Support\Settings\Settings::class);
        $reward = (int) $settings->get("growth.referral.reward.{$referral->audience}", 25000);

        // Fraud signals: same device/IP burst handled by Trust&Safety; simple check here
        if ($invitee->created_at->gt(now()->subMinutes(5))) {
            $referral->update(['status' => 'flagged']);

            return;
        }

        $referral->update(['status' => 'qualified', 'qualified_at' => now(), 'reward_amount' => $reward]);
    }
}
