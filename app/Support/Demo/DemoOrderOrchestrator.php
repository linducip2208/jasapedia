<?php

namespace App\Support\Demo;

use App\Domain\Finance\RefundService;
use App\Domain\Finance\SettlementService;
use App\Domain\Finance\WithdrawalService;
use App\Domain\Ledger\LedgerService;
use App\Domain\Order\OrderService;
use App\Domain\Payment\Contracts\GatewayEvent;
use App\Domain\Payment\PaymentService;
use App\Models\Order;
use App\Models\PayoutDestination;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Domain-driven order lifecycle for the demo's FINANCE-COMPLETE subset.
 * Every rupiah flows through existing services:
 *   OrderService → PaymentService (sandbox webhook) → OrderStateMachine
 *   → SettlementService (commission + balanced ledger) → RefundService /
 *   WithdrawalService. No direct balance insertion anywhere.
 */
final class DemoOrderOrchestrator
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PaymentService $payments,
        private readonly SettlementService $settlements,
        private readonly RefundService $refunds,
        private readonly WithdrawalService $withdrawals,
    ) {}

    /**
     * Full happy path: create → pay → field-service walk → complete → settle.
     * Returns the settled Order.
     */
    public function createPaidAndSettled(User $customer, Service $service, array $orderData, int $sequence): Order
    {
        $order = $this->orders->createServiceOrder($customer, $service, $orderData);

        // Domain services don't know about demo tagging — flag here so
        // --fresh-demo can remove these rows surgically.
        $order->forceFill(['is_demo' => true])->save();

        $this->payViaSandboxWebhook($order, $sequence);

        // handleWebhook mutates its own hydrated order instance — re-sync memory state
        $order = $order->fresh();

        // Walk the locked state machine to completion (searching_provider was auto-advanced by markPaid)
        $walk = ['offered', 'accepted', 'assigned', 'on_the_way', 'arrived', 'checked_in', 'working', 'awaiting_customer_confirmation'];

        foreach ($walk as $state) {
            $order->transition($state, null, 'Demo field-service progression');
        }

        $order->transition('completed', $customer, 'Customer confirmed completion');

        $settlement = $this->settlements->createFor($order);
        $this->settlements->process($settlement);

        return $order->fresh();
    }

    /**
     * Pay an existing order through the real sandbox gateway webhook path.
     * Creates a genuine PaymentTransaction then applies the paid event.
     */
    public function payViaSandboxWebhook(Order $order, int $sequence): void
    {
        $tx = $this->payments->initialize($order);

        // Event id must be unique FOREVER (webhook idempotency table survives
        // --fresh-demo wipes), so derive it from the unique order code.
        $event = new GatewayEvent(
            eventId: 'EVT-DEMO-'.$order->code.'-'.$sequence,
            type: 'payment.paid',
            orderCode: $order->code,
            gatewayRef: $tx->gateway_ref,
            status: 'paid',
            amountIdr: (int) $order->total,
            raw: [
                'order_code' => $order->code,
                'gateway_ref' => $tx->gateway_ref,
                'amount' => (int) $order->total,
                'status' => 'paid',
                'method' => 'sandbox_qris',
                'demo' => true,
            ],
        );

        $this->payments->handleWebhook('sandbox', $event);

        // Payment must actually land before state walking
        $nowStatus = $order->fresh()->status;
        if (! in_array($nowStatus, ['paid', 'searching_provider'], true)) {
            throw new \RuntimeException("Demo payment did not apply: order {$order->code} still {$nowStatus} (tx {$tx->fresh()->status}).");
        }
    }

    /** Full refund of a settled order via RefundService (balanced reversal). */
    public function refundFully(Order $order, User $actor, string $reason): void
    {
        $refund = $this->refunds->request($order, (int) $order->total, 'full', $reason, $actor);
        $this->refunds->approveAndExecute($refund, $actor);
    }

    /** Withdraw part of a partner's settled balance via WithdrawalService. */
    public function withdraw(PayoutDestination $destination, int $amount, User $requestedBy): void
    {
        $withdrawal = $this->withdrawals->request($destination->partner, $destination, $amount, $requestedBy);

        match (mt_rand(1, 4)) {
            1 => $this->withdrawals->transition($withdrawal, 'approved', $requestedBy), // stays pending pool
            2 => $this->withdrawals->transition(
                $this->withdrawals->transition($withdrawal, 'approved', $requestedBy),
                'processing',
                $requestedBy,
            ),
            3 => $this->withdrawals->transition(
                $this->withdrawals->transition(
                    $this->withdrawals->transition($withdrawal, 'approved', $requestedBy),
                    'processing',
                    $requestedBy,
                ),
                'completed',
                $requestedBy,
                'SBX-WD-'.strtoupper(bin2hex(random_bytes(4))),
            ),
            default => null, // leave as 'requested'
        };
    }

    /** Post-order financial summary assertions (fail fast if ledger corrupt). */
    public static function assertLedgerBalanced(LedgerService $ledger): void
    {
        if (! $ledger->ledgerIsBalanced()) {
            throw new \RuntimeException('Demo seeding produced an UNBALANCED ledger. Aborting.');
        }
    }

    /** Settled per-partner vendor_net map (for withdrawal simulation). */
    public static function settledBalanceByPartner(): array
    {
        return DB::table('settlements')
            ->where('status', 'completed')
            ->groupBy('partner_id')
            ->selectRaw('partner_id, SUM(vendor_net) as net')
            ->pluck('net', 'partner_id')
            ->all();
    }
}
