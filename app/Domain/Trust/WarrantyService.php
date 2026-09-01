<?php

namespace App\Domain\Trust;

use App\Domain\Auth\DomainException;
use App\Models\WarrantyClaim;
use Illuminate\Support\Str;

class WarrantyService
{
    public function claim(\App\Models\Order $order, \App\Models\User $customer, string $issue, array $evidence = []): WarrantyClaim
    {
        if ($order->user_id !== $customer->id) {
            throw new DomainException("Not your order.", "FORBIDDEN", 403);
        }

        if (! in_array($order->status, ["completed", "settled", "closed"], true)) {
            throw new DomainException("Warranty applies to completed orders.", "NOT_WARRANTABLE", 409);
        }

        $warrantyDays = $order->service?->warranty_days ?? 0;
        if ($warrantyDays <= 0) {
            throw new DomainException("This service has no warranty.", "NO_WARRANTY", 409);
        }

        $completedAt = $order->completed_at ?? $order->updated_at;
        if ($completedAt->copy()->addDays($warrantyDays)->isPast()) {
            throw new DomainException("Warranty period expired.", "WARRANTY_EXPIRED", 409);
        }

        return WarrantyClaim::create([
            "code" => "WCL-".now()->format("ymd")."-".strtoupper(Str::random(5)),
            "order_id" => $order->id,
            "claimed_by" => $customer->id,
            "issue" => $issue,
            "evidence" => $evidence,
            "status" => "submitted",
        ]);
    }

    public function resolve(WarrantyClaim $claim, \App\Models\User $staff, string $outcome, string $note): WarrantyClaim
    {
        if (! in_array($outcome, ["rework", "refund", "service_credit", "rejected"], true)) {
            throw new DomainException("Invalid outcome.", "INVALID_OUTCOME", 422);
        }

        if (in_array($claim->status, ["resolved", "rejected"], true)) {
            throw new DomainException("Claim already resolved.", "INVALID_STATE", 409);
        }

        $claim->update([
            "status" => $outcome === "rejected" ? "rejected" : "resolved",
            "outcome" => $outcome,
            "resolution_note" => $note,
            "resolved_by" => $staff->id,
            "resolved_at" => now(),
        ]);

        app(\App\Support\Audit\AuditLogger::class)->log("warranty.resolved", $claim, null, ["outcome" => $outcome], $note, null, $staff);

        return $claim->fresh();
    }
}
