<?php

namespace App\Domain\Payment;

use App\Domain\Auth\DomainException;
use App\Domain\Order\OrderService;
use App\Domain\Payment\Contracts\GatewayEvent;
use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly OrderService $orders,
    ) {
    }

    public function initialize(Order $order): PaymentTransaction
    {
        return DB::transaction(function () use ($order) {
            $existing = PaymentTransaction::where('order_id', $order->id)
                ->whereIn('status', ['created', 'pending'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $result = $this->gateway->createIntent($order->code, (int) $order->total);

            $tx = PaymentTransaction::create([
                'order_id' => $order->id,
                'gateway' => 'sandbox',
                'gateway_ref' => $result['ref'],
                'amount' => $order->total,
                'status' => 'pending',
                'meta' => ['payment_data' => $result['payment_data'] ?? null],
            ]);

            return $tx;
        });
    }

    /**
     * Idempotent webhook processing: unique(gateway, event_id) then process.
     * Duplicate events short-circuit without creating movements.
     */
    public function handleWebhook(string $gateway, GatewayEvent $event): PaymentWebhookEvent
    {
        $stored = null;

        try {
            $stored = PaymentWebhookEvent::create([
                'gateway' => $gateway,
                'event_id' => $event->eventId,
                'payload' => $event->raw,
                'status' => 'received',
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            // Idempotent replay — already processed/processing
            return PaymentWebhookEvent::where('gateway', $gateway)->where('event_id', $event->eventId)->first();
        }

        try {
            DB::transaction(function () use ($event) {
                $tx = PaymentTransaction::where('gateway_ref', $event->gatewayRef)->lockForUpdate()->first();

                if (! $tx) {
                    throw new DomainException('Payment transaction not found.', 'PAYMENT_NOT_FOUND', 404);
                }

                match ($event->status) {
                    'paid' => $this->applyPaid($tx, $event),
                    'failed' => $tx->update(['status' => 'failed']),
                    'expired' => $tx->update(['status' => 'expired']),
                    'cancelled' => $tx->update(['status' => 'cancelled']),
                    default => null,
                };

                // Amount tampering check
                if ($event->status === 'paid' && $event->amountIdr !== null && (int) $event->amountIdr !== (int) $tx->amount) {
                    throw new DomainException('Payment amount mismatch.', 'AMOUNT_MISMATCH', 409);
                }
            });

            $stored->update(['status' => 'processed', 'processed_at' => now()]);
        } catch (\Throwable $e) {
            $stored->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }

        return $stored;
    }

    private function applyPaid(PaymentTransaction $tx, GatewayEvent $event): void
    {
        // Guard: cannot settle twice
        if ($tx->status === 'paid') {
            return;
        }

        if (in_array($tx->status, ['failed', 'expired', 'cancelled'], true)) {
            throw new DomainException('Cannot pay a '.$tx->status.' transaction.', 'PAYMENT_STATE_CONFLICT', 409);
        }

        $order = $tx->order;

        $tx->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $event->raw['method'] ?? $tx->gateway,
        ]);

        $order->forceFill(['paid_at' => now()])->saveQuietly();
        $this->orders->markPaid($order);

        // Milestone funding: unlock milestone
        if ($order->type === Order::TYPE_MILESTONE_FUNDING) {
            app(\App\Domain\Deal\MilestoneService::class)->onFundingPaid($order->fresh());
        }
    }
}
