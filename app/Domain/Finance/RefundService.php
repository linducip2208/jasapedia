<?php

namespace App\Domain\Finance;

use App\Domain\Auth\DomainException;
use App\Models\Order;
use App\Models\Refund;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function __construct(private readonly \App\Domain\Ledger\LedgerService $ledger)
    {
    }

    public function eligibleAmount(Order $order): int
    {
        $paid = (int) DB::table('payment_transactions')->where('order_id', $order->id)->where('status', 'paid')->sum('amount');

        $refunded = (int) Refund::where('order_id', $order->id)->whereIn('status', ['approved', 'processing', 'completed'])->sum('amount');

        return max(0, $paid - $refunded);
    }

    public function request(Order $order, int $amount, string $type, string $reason, $requestedBy): Refund
    {
        return DB::transaction(function () use ($order, $amount, $type, $reason, $requestedBy) {
            $eligible = $this->eligibleAmount($order);

            if ($amount <= 0 || $amount > $eligible) {
                throw new DomainException(
                    "Refund amount exceeds eligible paid balance (Rp".number_format($eligible, 0, ',', '.').").",
                    'REFUND_EXCEEDS_ELIGIBLE',
                    409,
                    ['eligible' => $eligible],
                );
            }

            if ($type === 'full' && $amount !== $eligible) {
                throw new DomainException('Full refund must equal eligible amount.', 'INVALID_FULL_REFUND', 422);
            }

            return Refund::create([
                'order_id' => $order->id,
                'amount' => $amount,
                'type' => $type,
                'reason' => $reason,
                'status' => 'requested',
                'requested_by' => $requestedBy->id ?? $requestedBy,
            ]);
        });
    }

    public function approveAndExecute(Refund $refund, $actor): Refund
    {
        return DB::transaction(function () use ($refund, $actor) {
            $refund = Refund::where('id', $refund->id)->lockForUpdate()->first();

            if ($refund->status === 'completed') {
                throw new DomainException('Refund already executed.', 'DOUBLE_REFUND', 409);
            }

            if (! in_array($refund->status, ['requested', 'approved'], true)) {
                throw new DomainException("Refund is {$refund->status}.", 'INVALID_STATE', 409);
            }

            // Re-check eligibility under lock (race with other refunds)
            $order = $refund->order;
            $refundedOthers = (int) Refund::where('order_id', $order->id)
                ->where('id', '!=', $refund->id)
                ->whereIn('status', ['approved', 'processing', 'completed'])
                ->sum('amount');
            $paid = (int) DB::table('payment_transactions')->where('order_id', $order->id)->where('status', 'paid')->sum('amount');

            if ($refund->amount + $refundedOthers > $paid) {
                throw new DomainException('Refund exceeds paid amount.', 'REFUND_EXCEEDS_ELIGIBLE', 409);
            }

            // Ledger: refund flows out of gateway clearing; vendor payable and
            // platform commission are reduced proportionally.
            $commission = $order->commission ? (int) $order->commission->amount : 0;
            $gross = (int) $order->total;

            if ($gross <= 0) {
                throw new DomainException('Order has no payable amount.', 'REFUND_NO_BASIS', 409);
            }

            $vendorShare = (int) round($refund->amount * (($gross - $commission) / $gross));
            $platformShare = $refund->amount - $vendorShare;

            $entries = [
                '1001' => ['credit' => $refund->amount, 'memo' => "Refund #{$refund->id}"],
            ];

            $debitSum = 0;
            if ($vendorShare > 0) {
                $entries['2101'] = ['debit' => $vendorShare, 'memo' => 'Refund vendor share'];
                $debitSum += $vendorShare;
            }
            if ($platformShare > 0) {
                $entries['4201'] = ['debit' => $platformShare, 'memo' => 'Refund commission share'];
                $debitSum += $platformShare;
            }
            if ($debitSum < $refund->amount) {
                // Balance against refunds payable (float account)
                $entries['2103'] = ['debit' => $refund->amount - $debitSum, 'memo' => 'Refund balance'];
            }

            $this->ledger->post('refund', 'refund', $refund->id, $entries, "Refund for {$order->code}: {$refund->reason}");

            $refund->update([
                'status' => 'completed',
                'decided_by' => $actor->id ?? $actor,
                'executed_at' => now(),
                'provider_ref' => 'SBX-REF-'.strtoupper(bin2hex(random_bytes(4))),
            ]);

            // Reflect on order status (refund path per doc 10)
            $refundedTotal = $refundedOthers + $refund->amount;
            $order->transition('refund_pending', null, 'Refund executing');

            if ($refundedTotal >= $paid) {
                $order->transition('refunded', null, 'Full refund executed');
            } else {
                $order->transition('partially_refunded', null, 'Partial refund executed');
            }

            return $refund->fresh();
        });
    }
}
