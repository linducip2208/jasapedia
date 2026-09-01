<?php

namespace App\Domain\Order;

use App\Domain\Common\Exceptions\StateTransitionException;
use App\Models\Order;
use App\Models\User;

/**
 * Order state machine — locked to docs/10-ORDER-STATE-MACHINE.md.
 * Unknown transitions throw; every change writes immutable history.
 */
final class OrderStateMachine
{
    /** @var array<string, string[]> */
    public const TRANSITIONS = [
        'draft' => ['pending_payment', 'cancelled'],
        'pending_payment' => ['paid', 'failed', 'expired', 'cancelled'],
        'paid' => ['searching_provider', 'settlement_pending', 'refund_pending'],
        'searching_provider' => ['offered', 'cancelled', 'refund_pending'],
        'offered' => ['accepted', 'searching_provider', 'expired'],
        'accepted' => ['assigned', 'on_the_way', 'cancelled', 'disputed'],
        'assigned' => ['on_the_way', 'cancelled', 'disputed'],
        'on_the_way' => ['arrived', 'cancelled', 'disputed'],
        'arrived' => ['checked_in'],
        'checked_in' => ['working'],
        'working' => ['awaiting_customer_confirmation', 'rework_required'],
        'awaiting_customer_confirmation' => ['completed', 'disputed', 'rework_required'],
        'completed' => ['settlement_pending', 'disputed'],
        'settlement_pending' => ['settled', 'disputed', 'refund_pending'],
        'settled' => ['closed', 'disputed', 'refund_pending'],
        'rework_required' => ['working', 'disputed', 'cancelled'],
        'refunded' => [],
        'partially_refunded' => ['refunded', 'refund_pending'],
        'refund_pending' => ['partially_refunded', 'refunded', 'settlement_pending'],
        'failed' => [],
        'expired' => [],
        'cancelled' => [],
        'closed' => [],
        'disputed' => ['refund_pending', 'settlement_pending'], // restore-from-snapshot handled by dispute module
    ];

    public function allowedNext(Order $order): array
    {
        return self::TRANSITIONS[$order->status] ?? [];
    }

    public function transition(Order $order, string $to, ?User $actor = null, ?string $reason = null, array $metadata = []): Order
    {
        $from = $order->status;

        if (! in_array($to, $this->allowedNext($order), true)) {
            throw new StateTransitionException($from, $to, 'order');
        }

        $order->update(['status' => $to]);

        // Milestone-style timestamps
        match ($to) {
            'completed' => $order->forceFill(['completed_at' => now()])->saveQuietly(),
            'cancelled' => $order->forceFill(['cancelled_at' => now(), 'cancelled_by' => $actor?->id, 'cancel_reason' => $reason])->saveQuietly(),
            'settled' => $order->forceFill(['settled_at' => now()])->saveQuietly(),
            default => null,
        };

        \DB::table('order_status_history')->insert([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'actor_type' => $actor ? 'user' : 'system',
            'reason' => $reason,
            'metadata' => $metadata !== [] ? json_encode($metadata) : null,
            'created_at' => now(),
        ]);

        return $order;
    }
}
