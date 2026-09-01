<?php

namespace App\Domain\Growth;

use App\Domain\Auth\DomainException;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\User;
use App\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;

/**
 * Promotion engine (doc 76/77): eligibility, controls, funding split, anti-abuse.
 */
class PromotionService
{
    public function validate(Promotion $promo, User $user, Order $order): int
    {
        if ($promo->status !== 'active') {
            throw new DomainException('Promo not active.', 'PROMO_INACTIVE', 409);
        }

        $now = now();
        if ($promo->starts_at && $promo->starts_at->gt($now)) {
            throw new DomainException('Promo not started.', 'PROMO_NOT_STARTED', 409);
        }
        if ($promo->ends_at && $promo->ends_at->lt($now)) {
            throw new DomainException('Promo expired.', 'PROMO_EXPIRED', 409);
        }

        if ((int) $order->subtotal < (int) $promo->min_spend) {
            throw new DomainException('Minimum spend not met.', 'MIN_SPEND', 422, ['min_spend' => $promo->min_spend]);
        }

        if ($promo->category_id && $order->service && (int) $order->service->category_id !== (int) $promo->category_id) {
            throw new DomainException('Promo not valid for this category.', 'CATEGORY_MISMATCH', 422);
        }

        if ($promo->city && $order->partner && strcasecmp((string) $order->partner->city, $promo->city) !== 0) {
            throw new DomainException('Promo not valid in this city.', 'CITY_MISMATCH', 422);
        }

        // First order only
        if ($promo->first_order_only && Order::where('user_id', $user->id)->whereIn('status', ['paid', 'completed', 'settled', 'closed'])->exists()) {
            throw new DomainException('Promo for first order only.', 'FIRST_ORDER_ONLY', 409);
        }

        // Global usage limit
        $usedTotal = VoucherRedemption::where('promotion_id', $promo->id)->count();
        if ($promo->usage_limit !== null && $usedTotal >= $promo->usage_limit) {
            throw new DomainException('Promo quota exhausted.', 'QUOTA_EXHAUSTED', 409);
        }

        // Per-user limit (count prior *consumed* redemptions: paid+)
        $usedByUser = VoucherRedemption::where('promotion_id', $promo->id)
            ->where('user_id', $user->id)
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['paid', 'completed', 'settled', 'closed', 'pending_payment', 'searching_provider', 'working']))
            ->count();

        if ($usedByUser >= $promo->per_user_limit) {
            throw new DomainException('Per-user limit reached.', 'USER_LIMIT', 409);
        }

        // Compute discount
        $discount = $promo->value_unit === 'percent'
            ? (int) round((int) $order->subtotal * $promo->value / 100)
            : (int) $promo->value;

        if ($promo->max_discount) {
            $discount = min($discount, (int) $promo->max_discount);
        }

        return min($discount, (int) $order->subtotal);
    }

    /** Apply: record redemption; order.total reduced. Ledger subsidy posted at settlement. */
    public function apply(Promotion $promo, User $user, Order $order): int
    {
        return DB::transaction(function () use ($promo, $user, $order) {
            $discount = $this->validate($promo, $user, $order);

            VoucherRedemption::create([
                'promotion_id' => $promo->id,
                'user_id' => $user->id,
                'order_id' => $order->id,
                'discount_amount' => $discount,
            ]);

            $newTotal = max(0, (int) $order->total - $discount);
            $order->forceFill(['total' => $newTotal])->save();

            return $discount;
        });
    }
}
