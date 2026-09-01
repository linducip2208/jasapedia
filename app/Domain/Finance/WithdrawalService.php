<?php

namespace App\Domain\Finance;

use App\Domain\Auth\DomainException;
use App\Models\Partner;
use App\Models\PayoutDestination;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;

class WithdrawalService
{
    public const MIN_WITHDRAWAL = 50000;
    public const FEE_FLAT = 0; // configurable

    public function __construct(private readonly \App\Domain\Ledger\LedgerService $ledger)
    {
    }

    /** Available balance = settled vendor net − all reserved/completed withdrawals. */
    public function availableBalance(Partner $partner): int
    {
        $settled = (int) DB::table('settlements')->where('partner_id', $partner->id)->where('status', 'completed')->sum('vendor_net');
        $reserved = (int) DB::table('withdrawals')
            ->where('partner_id', $partner->id)
            ->whereIn('status', ['requested', 'under_review', 'approved', 'processing', 'completed'])
            ->sum('amount');

        return $settled - $reserved;
    }

    public function request(Partner $partner, PayoutDestination $destination, int $amount, $requestedBy): Withdrawal
    {
        if ($amount < self::MIN_WITHDRAWAL) {
            throw new DomainException('Minimum withdrawal is Rp'.number_format(self::MIN_WITHDRAWAL, 0, ',', '.').'.', 'MIN_WITHDRAWAL', 422);
        }

        if (! $destination->verified_at) {
            throw new DomainException('Payout destination must be verified.', 'DESTINATION_UNVERIFIED', 422);
        }

        return DB::transaction(function () use ($partner, $destination, $amount, $requestedBy) {
            // Race-safe: lock partner row (serialize withdrawal requests per partner)
            DB::table('partners')->where('id', $partner->id)->lockForUpdate()->first();

            $available = $this->availableBalance($partner);

            if ($amount > $available) {
                throw new DomainException('Insufficient available balance.', 'INSUFFICIENT_BALANCE', 409, ['available' => $available]);
            }

            $fee = self::FEE_FLAT;

            return Withdrawal::create([
                'partner_id' => $partner->id,
                'payout_destination_id' => $destination->id,
                'amount' => $amount,
                'fee' => $fee,
                'net' => $amount - $fee,
                'status' => 'requested',
                'requested_by' => $requestedBy->id ?? $requestedBy,
            ]);
        });
    }

    public function transition(Withdrawal $withdrawal, string $to, $actor = null, ?string $providerRef = null, ?string $failureReason = null): Withdrawal
    {
        $allowed = [
            'requested' => ['under_review', 'approved', 'rejected', 'cancelled'],
            'under_review' => ['approved', 'rejected', 'cancelled'],
            'approved' => ['processing', 'cancelled'],
            'processing' => ['completed', 'failed'],
            'completed' => [],
            'failed' => [],
            'rejected' => [],
            'cancelled' => [],
        ];

        if (! in_array($to, $allowed[$withdrawal->status] ?? [], true)) {
            throw new \App\Domain\Common\Exceptions\StateTransitionException($withdrawal->status, $to, 'withdrawal');
        }

        return DB::transaction(function () use ($withdrawal, $to, $actor, $providerRef, $failureReason) {
            // Money leaves the pool exactly once: on completed
            if ($to === 'completed') {
                $alreadyCompleted = Withdrawal::where('id', $withdrawal->id)->lockForUpdate()->value('status') === 'completed';
                if ($alreadyCompleted) {
                    throw new DomainException('Withdrawal already completed.', 'DOUBLE_WITHDRAWAL', 409);
                }

                $this->ledger->post(
                    'withdrawal',
                    'withdrawal',
                    $withdrawal->id,
                    [
                        '2101' => ['debit' => $withdrawal->amount, 'memo' => "Withdrawal #{$withdrawal->id}"],
                        '1002' => ['credit' => $withdrawal->net],
                        '4903' => ['credit' => $withdrawal->fee],
                    ],
                    "Partner withdrawal #{$withdrawal->id}",
                );
            }

            $withdrawal->update([
                'status' => $to,
                'reviewed_by' => $actor?->id,
                'provider_ref' => $providerRef ?? $withdrawal->provider_ref,
                'failure_reason' => $failureReason,
                'processed_at' => in_array($to, ['completed', 'failed'], true) ? now() : $withdrawal->processed_at,
            ]);

            return $withdrawal->fresh();
        });
    }
}
