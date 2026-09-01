<?php

namespace App\Domain\Dispatch;

use App\Domain\Auth\DomainException;
use App\Models\Assignment;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DispatchService
{
    public const OFFER_TTL_MINUTES = 30;

    /**
     * Score candidates for an order (doc 29). Transparent, rule-based.
     * @return array<int, array{partner: Partner, score: float, breakdown: array}>
     */
    public function candidates(Order $order, int $limit = 10): array
    {
        $service = $order->service;

        $partners = Partner::query()
            ->where('verification_state', 'verified')
            ->when($service?->category_id, fn ($q) => $q->whereHas('services', fn ($s) => $s
                ->where('category_id', $service->category_id)
                ->where('status', 'active')))
            ->get();

        $scored = [];
        foreach ($partners as $partner) {
            $breakdown = [
                'rating' => (float) $partner->rating_avg * 20,                              // max 100
                'completed' => min(50, $partner->completed_jobs),                           // max 50
                'online' => $partner->isOnline() ? 30 : 0,
                'response' => max(0, 30 - ((int) $partner->response_minutes / 10)),         // max 30
                'acceptance' => (float) $partner->acceptance_rate * 0.2,                    // max 20
                'workload' => 0, // current open assignments penalty added below
            ];

            $open = DB::table('assignments')
                ->where('partner_id', $partner->id)
                ->where('status', 'accepted')
                ->count();
            $breakdown['workload'] = max(0, 20 - $open * 5);

            $score = array_sum($breakdown);
            $scored[] = ['partner' => $partner, 'score' => round($score, 2), 'breakdown' => $breakdown];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /** Start dispatch: auto-direct if single high-score candidate, else broadcast. */
    public function dispatch(Order $order, ?User $actor = null, string $mode = 'auto'): Order
    {
        return DB::transaction(function () use ($order, $actor, $mode) {
            if ($order->status !== 'searching_provider') {
                throw new DomainException("Order is {$order->status}; dispatch requires searching_provider.", 'INVALID_STATE', 409);
            }

            $candidates = $this->candidates($order);

            if ($candidates === []) {
                return $order; // stays searching; ops can intervene
            }

            if ($mode === 'manual' && $actor) {
                // manual handled by assign()
                return $order;
            }

            if (count($candidates) === 1 || $mode === 'auto_direct') {
                $top = $candidates[0];

                Assignment::create([
                    'order_id' => $order->id,
                    'partner_id' => $top['partner']->id,
                    'mode' => 'auto_direct',
                    'status' => 'offered',
                    'expires_at' => now()->addMinutes(self::OFFER_TTL_MINUTES),
                    'score_breakdown' => $top['breakdown'],
                ]);

                $this->transition($order, 'offered', $actor, "Auto-assigned offer #{$top['partner']->id}");

                return $order;
            }

            // Broadcast to top N
            foreach (array_slice($candidates, 0, 5) as $candidate) {
                Assignment::create([
                    'order_id' => $order->id,
                    'partner_id' => $candidate['partner']->id,
                    'mode' => 'broadcast',
                    'status' => 'offered',
                    'expires_at' => now()->addMinutes(self::OFFER_TTL_MINUTES),
                    'score_breakdown' => $candidate['breakdown'],
                ]);
            }

            return $this->transition($order, 'offered', $actor, 'Broadcast offer');
        });
    }

    public function assign(Order $order, Partner $partner, ?User $actor = null, ?int $memberId = null, ?int $workerUserId = null): Assignment
    {
        return DB::transaction(function () use ($order, $partner, $actor, $memberId, $workerUserId) {
            if ($memberId !== null) {
                $member = PartnerMember::where('organization_id', $partner->organization?->id)->findOrFail($memberId);
                $workerUserId = $member->user_id;
            }

            // Cancel any open offers
            Assignment::where('order_id', $order->id)->where('status', 'offered')->update(['status' => 'expired']);

            $assignment = Assignment::create([
                'order_id' => $order->id,
                'partner_id' => $partner->id,
                'member_id' => $memberId,
                'worker_user_id' => $workerUserId,
                'mode' => $memberId !== null ? 'vendor_internal' : 'manual',
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            if (in_array($order->status, ['searching_provider', 'offered'], true)) {
                $this->transition($order, 'accepted', $actor, 'Assigned');
            }

            return $assignment;
        });
    }

    public function accept(Assignment $assignment, ?User $actor = null): Assignment
    {
        return DB::transaction(function () use ($assignment, $actor) {
            if ($assignment->status !== 'offered') {
                throw new DomainException('Offer already handled.', 'OFFER_HANDLED', 409);
            }

            if ($assignment->expires_at && $assignment->expires_at->isPast()) {
                $assignment->update(['status' => 'expired']);
                throw new DomainException('Offer expired.', 'OFFER_EXPIRED', 409);
            }

            // First-accept-wins at row level
            $affected = Assignment::where('id', $assignment->id)->where('status', 'offered')
                ->update(['status' => 'accepted', 'responded_at' => now()]);

            if ($affected === 0) {
                throw new DomainException('Offer already accepted by another party.', 'OFFER_HANDLED', 409);
            }

            // Lose sibling offers
            Assignment::where('order_id', $assignment->order_id)
                ->where('id', '!=', $assignment->id)
                ->where('status', 'offered')
                ->update(['status' => 'expired']);

            $order = $assignment->order;
            if ($order->status === 'offered') {
                $this->transition($order, 'accepted', $actor, "Partner {$assignment->partner_id} accepted");
            }

            return $assignment->fresh();
        });
    }

    public function reject(Assignment $assignment, ?User $actor = null, string $reason = ''): Assignment
    {
        $assignment->update(['status' => 'rejected', 'responded_at' => now()]);

        $order = $assignment->order;

        // Any offers left?
        $remaining = Assignment::where('order_id', $order->id)->where('status', 'offered')->exists();

        if (! $remaining && $order->status === 'offered') {
            $this->transition($order, 'searching_provider', $actor, 'All offers rejected');
        }

        return $assignment->fresh();
    }

    public function transition(Order $order, string $to, ?User $actor, string $reason): Order
    {
        return app(\App\Domain\Order\OrderStateMachine::class)->transition($order, $to, $actor, $reason);
    }
}
