<?php

namespace App\Domain\Finance;

use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;

/**
 * Reconciliation engine (Phase 27, doc 60):
 * detect differences between provider-reported state, internal records, ledger and payouts.
 */
class ReconciliationService
{
    /**
     * Payment reconciliation: paid transactions without processed webhook,
     * processed webhooks without paid transaction, stale pending intents.
     *
     * @return array{discrepancies: array, checked: int}
     */
    public function reconcilePayments(int $hours = 24): array
    {
        $since = now()->subHours($hours);
        $discrepancies = [];

        // 1. Webhook says paid but transaction not paid
        $stuckWebhooks = DB::table('payment_webhook_events as w')
            ->join('payment_transactions as t', function ($j) {
                $j->on('t.gateway', '=', 'w.gateway')
                    ->whereColumn(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(w.payload, '$.gateway_ref'))"), 't.gateway_ref');
            })
            ->where('w.status', 'processed')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(w.payload, '$.status')) = 'paid'")
            ->where('t.status', '!=', 'paid')
            ->where('w.created_at', '>=', $since)
            ->get(['t.id as tx_id', 't.order_id', 't.gateway_ref', 't.status']);

        foreach ($stuckWebhooks as $row) {
            $discrepancies[] = [
                'type' => 'webhook_paid_tx_not_paid',
                'severity' => 'high',
                'payment_transaction_id' => $row->tx_id,
                'order_id' => $row->order_id,
                'detail' => "Gateway ref {$row->gateway_ref} reported paid but tx is {$row->status}",
            ];
        }

        // 2. Pending intents older than window (likely abandoned or missed callback)
        $stalePending = DB::table('payment_transactions')
            ->where('status', 'pending')
            ->where('created_at', '<', $since)
            ->get(['id', 'order_id', 'gateway_ref']);

        foreach ($stalePending as $row) {
            $discrepancies[] = [
                'type' => 'stale_pending_intent',
                'severity' => 'medium',
                'payment_transaction_id' => $row->id,
                'order_id' => $row->order_id,
                'detail' => "Pending since before {$since}; needs provider status check {$row->gateway_ref}",
            ];
        }

        // 3. Paid transaction but order never advanced (order stuck)
        $stuckOrders = DB::table('payment_transactions as t')
            ->join('orders as o', 'o.id', '=', 't.order_id')
            ->where('t.status', 'paid')
            ->where('o.status', 'pending_payment')
            ->get(['t.id as tx_id', 'o.id as order_id']);

        foreach ($stuckOrders as $row) {
            $discrepancies[] = [
                'type' => 'paid_order_stuck',
                'severity' => 'high',
                'payment_transaction_id' => $row->tx_id,
                'order_id' => $row->order_id,
                'detail' => 'Payment marked paid but order still pending_payment',
            ];
        }

        return [
            'checked' => DB::table('payment_transactions')->where('created_at', '>=', $since)->count(),
            'discrepancies' => $discrepancies,
        ];
    }

    /**
     * Settlement/withdrawal reconciliation: completed settlements without
     * ledger posting, withdrawals completed without ledger movement.
     */
    public function reconcilePayouts(): array
    {
        $discrepancies = [];

        // Settlement completed but no order_payment ledger posting
        $unposted = DB::table('settlements as s')
            ->where('s.status', 'completed')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('ledger_transactions as lt')
                    ->whereColumn('lt.reference_id', 's.order_id')
                    ->where('lt.reference_type', 'order')
                    ->where('lt.group', 'order_payment');
            })
            ->get(['s.id', 's.order_id']);

        foreach ($unposted as $row) {
            $discrepancies[] = [
                'type' => 'settlement_without_posting',
                'severity' => 'high',
                'settlement_id' => $row->id,
                'order_id' => $row->order_id,
                'detail' => 'Settlement completed but ledger posting missing',
            ];
        }

        // Withdrawal completed but no ledger posting
        $unpostedW = DB::table('withdrawals as w')
            ->where('w.status', 'completed')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('ledger_transactions as lt')
                    ->whereColumn('lt.reference_id', 'w.id')
                    ->where('lt.reference_type', 'withdrawal')
                    ->where('lt.group', 'withdrawal');
            })
            ->get(['w.id']);

        foreach ($unpostedW as $row) {
            $discrepancies[] = [
                'type' => 'withdrawal_without_posting',
                'severity' => 'high',
                'withdrawal_id' => $row->id,
                'detail' => 'Withdrawal completed but ledger posting missing',
            ];
        }

        // Global ledger invariant check
        $sums = DB::table('ledger_entries')
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        if ((int) $sums->d !== (int) $sums->c) {
            $discrepancies[] = [
                'type' => 'ledger_unbalanced',
                'severity' => 'critical',
                'detail' => "Global debit {$sums->d} != credit {$sums->c}",
            ];
        }

        return ['discrepancies' => $discrepancies];
    }
}
