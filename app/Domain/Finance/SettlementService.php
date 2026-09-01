<?php

namespace App\Domain\Finance;

use App\Domain\Auth\DomainException;
use App\Models\AdditionalChargeRequest;
use App\Models\Commission;
use App\Models\Order;
use App\Models\Settlement;
use Illuminate\Support\Facades\DB;

class SettlementService
{
    public const HOLD_DAYS_DEFAULT = 0;

    public function __construct(
        private readonly CommissionService $commissions,
        private readonly \App\Domain\Ledger\LedgerService $ledger,
    ) {
    }

    /** Called when an order reaches completed. Idempotent by unique order_id. */
    public function createFor(Order $order): Settlement
    {
        return DB::transaction(function () use ($order) {
            $existing = Settlement::where('order_id', $order->id)->first();
            if ($existing) {
                return $existing;
            }

            $commission = $this->commissions->createSnapshot($order);

            $additional = (int) AdditionalChargeRequest::where('order_id', $order->id)
                ->where('status', 'approved')
                ->sum('amount');

            $settings = app(\App\Support\Settings\Settings::class);
            $holdDays = (int) $settings->get('finance.settlement.hold_days', self::HOLD_DAYS_DEFAULT);

            $settlement = Settlement::create([
                'order_id' => $order->id,
                'partner_id' => $order->partner_id,
                'gross' => (int) $order->total + $additional,
                'commission' => $commission->amount,
                'additional_amount' => $additional,
                'vendor_net' => (int) $order->total + $additional - $commission->amount,
                'status' => 'pending',
                'eligible_at' => now()->addDays($holdDays),
            ]);

            if ($order->status === 'completed') {
                $order->transition('settlement_pending', null, 'Settlement created');
            }

            return $settlement;
        });
    }

    /** Money becomes withdrawable: payable posted to partner's payable account. */
    public function process(Settlement $settlement): Settlement
    {
        return DB::transaction(function () use ($settlement) {
            $settlement = Settlement::where('id', $settlement->id)->lockForUpdate()->first();

            if ($settlement->status === 'completed') {
                throw new DomainException('Settlement already completed.', 'DOUBLE_SETTLEMENT', 409);
            }

            if (in_array($settlement->status, ['failed', 'held'], true)) {
                throw new DomainException("Settlement is {$settlement->status}.", 'INVALID_STATE', 409);
            }

            $now = now();
            if ($settlement->eligible_at && $settlement->eligible_at->gt($now)) {
                $settlement->update(['status' => 'eligible']);

                return $settlement;
            }

            $order = $settlement->order;

            // Post payment economics ONCE (guard by unique group+reference lookup)
            $alreadyPosted = DB::table('ledger_transactions')
                ->where('group', 'order_payment')
                ->where('reference_type', 'order')
                ->where('reference_id', $order->id)
                ->exists();

            if (! $alreadyPosted) {
                $this->ledger->post(
                    'order_payment',
                    'order',
                    $order->id,
                    [
                        '1001' => ['debit' => $settlement->gross, 'memo' => "Payment for {$order->code}"],
                        '2101' => ['credit' => $settlement->vendor_net, 'memo' => "Vendor payable {$order->code}"],
                        '4201' => ['credit' => $settlement->commission, 'memo' => "Commission {$order->code}"],
                    ],
                    "Order payment {$order->code}",
                );
            }

            $settlement->update(['status' => 'completed', 'processed_at' => $now]);

            $order->transition('settled', null, 'Settlement completed');

            return $settlement->fresh();
        });
    }
}
