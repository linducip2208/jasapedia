<?php

namespace App\Domain\Trust;

use App\Domain\Auth\DomainException;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DisputeService
{
    public function open(Order $order, User $opener, string $reason, string $description): Dispute
    {
        return DB::transaction(function () use ($order, $opener, $reason, $description) {
            if (Dispute::where('order_id', $order->id)->whereIn('status', ['opened', 'evidence_collection', 'counter_response', 'mediation', 'decision'])->exists()) {
                throw new DomainException('An active dispute already exists for this order.', 'DISPUTE_EXISTS', 409);
            }

            $isActive = $order->isActive() || in_array($order->status, ['completed', 'settled', 'settlement_pending'], true);
            if (! $isActive) {
                throw new DomainException("Order in {$order->status} cannot be disputed.", 'INVALID_STATE', 409);
            }

            // Snapshot active status for restore-on-resolve
            $order->update(['active_status_snapshot' => $order->status]);
            app(\App\Domain\Order\OrderStateMachine::class)->transition($order, 'disputed', $opener, "Dispute: {$reason}");

            return Dispute::create([
                'code' => 'DSP-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'order_id' => $order->id,
                'opened_by' => $opener->id,
                'reason' => $reason,
                'description' => $description,
                'status' => 'opened',
            ]);
        });
    }

    public function addEvidence(Dispute $dispute, User $user, array $data): DisputeEvidence
    {
        $participant = $dispute->order->user_id === $user->id
            || $dispute->order->partner?->user_id === $user->id
            || $user->can('dispute.manage');

        if (! $participant) {
            throw new DomainException('Not a dispute party.', 'FORBIDDEN', 403);
        }

        if ($dispute->status === 'resolved') {
            throw new DomainException('Dispute already resolved.', 'INVALID_STATE', 409);
        }

        if (in_array($dispute->status, ['opened', 'evidence_collection'], true)) {
            $dispute->update(['status' => 'evidence_collection']);
        }

        return DisputeEvidence::create([
            'dispute_id' => $dispute->id,
            'uploaded_by' => $user->id,
            'kind' => $data['kind'],
            'file_path' => $data['file_path'] ?? null,
            'ref_type' => $data['ref_type'] ?? null,
            'ref_id' => $data['ref_id'] ?? null,
            'note' => $data['note'] ?? null,
        ]);
    }

    /**
     * Officer resolution (permission-gated). Outcomes per doc 67.
     * Financial outcomes route through RefundService; audit logged.
     */
    public function resolve(Dispute $dispute, User $officer, string $resolution, ?int $amount, string $note): Dispute
    {
        return DB::transaction(function () use ($dispute, $officer, $resolution, $amount, $note) {
            if (! $officer->can('dispute.resolve')) {
                throw new DomainException('Missing dispute.resolve permission.', 'FORBIDDEN', 403);
            }

            if (in_array($dispute->status, ['resolved', 'closed'], true)) {
                throw new DomainException('Dispute already resolved.', 'INVALID_STATE', 409);
            }

            if (! in_array($resolution, ['release_payment', 'partial_refund', 'full_refund', 'rework', 'service_credit', 'claim_rejected'], true)) {
                throw new DomainException('Invalid resolution.', 'INVALID_RESOLUTION', 422);
            }

            $order = $dispute->order;

            // Financial outcomes execute deterministic refunds
            if (in_array($resolution, ['partial_refund', 'full_refund'], true)) {
                $refundService = app(\App\Domain\Finance\RefundService::class);
                $eligible = $refundService->eligibleAmount($order);

                if ($eligible <= 0) {
                    throw new DomainException('No eligible paid amount to refund.', 'REFUND_NO_BASIS', 409);
                }

                $refundAmount = $resolution === 'full_refund' ? $eligible : ($amount ?? 0);
                if ($refundAmount <= 0 || $refundAmount > $eligible) {
                    throw new DomainException('Invalid refund amount.', 'REFUND_EXCEEDS_ELIGIBLE', 422);
                }

                $refund = $refundService->request($order, $refundAmount, $resolution === 'full_refund' ? 'full' : 'partial', "Dispute {$dispute->code}", $officer);
                $refundService->approveAndExecute($refund, $officer);
            } elseif ($resolution === 'release_payment') {
                // restore order toward settlement pipeline
                if ($order->active_status_snapshot) {
                    app(\App\Domain\Order\OrderStateMachine::class)->transition($order, $order->active_status_snapshot, $officer, 'Dispute resolved: release');
                    $order->update(['active_status_snapshot' => null]);
                }
            }

            $dispute->update([
                'status' => 'resolved',
                'resolution' => $resolution,
                'resolution_amount' => $amount,
                'resolution_note' => $note,
                'resolved_by' => $officer->id,
                'resolved_at' => now(),
            ]);

            app(\App\Support\Audit\AuditLogger::class)->log(
                'dispute.resolved',
                $dispute,
                ['status' => $dispute->getOriginal('status')],
                ['resolution' => $resolution, 'amount' => $amount],
                $note,
                null,
                $officer,
            );

            return $dispute->fresh();
        });
    }
}
