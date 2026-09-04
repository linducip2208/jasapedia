<?php

namespace App\Domain\Growth;

use App\Domain\Auth\DomainException;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Membership billing cycle (Phase 37 completion):
 * subscribe/renew create a TYPE_MEMBERSHIP invoice order; activation happens
 * when the invoice is PAID through the normal payment pipeline (webhook or
 * sandbox pay) — membership orders never reach settlement/commission.
 */
class MembershipService
{
    public const CYCLES = ['monthly', 'yearly'];

    public function subscribe(User $user, MembershipPlan $plan, string $cycle = 'monthly'): Order
    {
        if (! in_array($cycle, self::CYCLES, true)) {
            throw new DomainException('Invalid billing cycle.', 'INVALID_CYCLE', 422);
        }

        if (! $plan->is_active) {
            throw new DomainException('Plan is not available.', 'PLAN_INACTIVE', 409);
        }

        $amount = (int) ($cycle === 'yearly' ? $plan->price_yearly : $plan->price_monthly);

        if ($cycle === 'yearly' && $plan->price_yearly === null) {
            throw new DomainException('Plan has no yearly price.', 'CYCLE_NOT_AVAILABLE', 422);
        }

        return DB::transaction(function () use ($user, $plan, $cycle, $amount) {
            $order = Order::create([
                'user_id' => $user->id,
                'type' => Order::TYPE_MEMBERSHIP,
                'status' => 'draft',
                'fulfillment_type' => 'instant_booking',
                'delivery_mode' => 'remote',
                'pricing_snapshot' => [
                    'source' => 'membership',
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'cycle' => $cycle,
                    'currency' => 'IDR',
                ],
                'subtotal' => $amount,
                'emergency_surcharge' => 0,
                'total' => $amount,
                'meta' => ['membership_plan_id' => $plan->id, 'cycle' => $cycle],
            ]);

            $order->items()->create([
                'type' => 'base',
                'name' => "Membership {$plan->name} ({$cycle})",
                'qty' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
                'ref_id' => $plan->id,
            ]);

            $order->transition('pending_payment', $user, 'Membership invoice created');

            // Sandbox auto-pay outside production so the cycle completes in one call
            if (! app()->environment('production')) {
                $tx = app(\App\Domain\Payment\PaymentService::class)->initialize($order);
                $request = new \Illuminate\Http\Request([
                    'order_code' => $order->code,
                    'gateway_ref' => $tx->gateway_ref,
                    'amount' => $amount,
                    'status' => 'paid',
                    'event_id' => 'EVT-'.strtoupper(bin2hex(random_bytes(6))),
                    'method' => 'sandbox_qris',
                ]);
                $request->headers->set('X-Sandbox-Signature', hash_hmac('sha256', $order->code, (string) config('services.payments.sandbox_secret', 'sandbox-secret')));

                $event = app(\App\Domain\Payment\Gateways\SandboxGateway::class)->verifyWebhook($request);

                app(\App\Domain\Payment\PaymentService::class)->handleWebhook('sandbox', $event);
            }

            return $order->fresh('items');
        });
    }

    /** Idempotent: called by PaymentService after a membership invoice is paid. */
    public function onInvoicePaid(Order $order): void
    {
        $planId = $order->meta['membership_plan_id'] ?? null;
        $cycle = $order->meta['cycle'] ?? 'monthly';

        if (! $planId) {
            return;
        }

        DB::transaction(function () use ($order, $planId, $cycle) {
            $plan = MembershipPlan::lockForUpdate()->findOrFail($planId);

            $latest = Membership::where('member_type', User::class)
                ->where('member_id', $order->user_id)
                ->where('status', '!=', 'cancelled')
                ->orderByDesc('ends_at')
                ->lockForUpdate()
                ->first();

            if ($latest && $latest->plan_id !== $plan->id) {
                // Plan switch: end the other plan's future window at activation
                Membership::where('member_type', User::class)
                    ->where('member_id', $order->user_id)
                    ->where('plan_id', '!=', $plan->id)
                    ->where('status', 'active')
                    ->update(['status' => 'cancelled']);
                $startsAt = now();
            } else {
                // Same-plan renewal extends from the existing window end
                $startsAt = $latest && $latest->ends_at->isFuture() ? $latest->ends_at : now();
            }
            $endsAt = $cycle === 'yearly'
                ? $startsAt->copy()->addYear()
                : $startsAt->copy()->addMonth();

            Membership::create([
                'plan_id' => $plan->id,
                'member_type' => User::class,
                'member_id' => $order->user_id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => 'active',
            ]);
        });
    }

    /** Ends active memberships whose window elapsed (scheduled daily). */
    public function expireOverdue(): int
    {
        return Membership::where('status', 'active')
            ->where('ends_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
