<?php

namespace App\Domain\Finance;

use App\Domain\Auth\DomainException;
use App\Models\Commission;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Commission engine (doc 56). Snapshot is IMMUTABLE per order.
 * Rules resolved: global → category → vendor (later phases extend).
 */
class CommissionService
{
    public function rateFor(Order $order): array
    {
        $settings = app(\App\Support\Settings\Settings::class);

        $globalPercent = (float) $settings->get('finance.commission.global_percent', 10);
        $categoryPercent = $settings->get("finance.commission.category.{$order->service?->category_id}");
        $vendorPercent = $settings->get("finance.commission.vendor.{$order->partner_id}");
        $flatFee = (int) $settings->get('finance.service_fee_flat', 0);

        $percent = $vendorPercent ?? $categoryPercent ?? $globalPercent;

        return [
            'percent' => (float) $percent,
            'flat_fee' => $flatFee,
            'scope' => $vendorPercent !== null ? 'vendor' : ($categoryPercent !== null ? 'category' : 'global'),
        ];
    }

    public function createSnapshot(Order $order): Commission
    {
        return DB::transaction(function () use ($order) {
            $existing = Commission::where('order_id', $order->id)->first();
            if ($existing) {
                return $existing;
            }

            $rule = $this->rateFor($order);
            $basis = (int) $order->total;
            $amount = (int) round($basis * $rule['percent'] / 100) + $rule['flat_fee'];

            return Commission::create([
                'order_id' => $order->id,
                'partner_id' => $order->partner_id,
                'basis_amount' => $basis,
                'rate_percent' => $rule['percent'],
                'amount' => $amount,
                'snapshot' => $rule,
            ]);
        });
    }
}
