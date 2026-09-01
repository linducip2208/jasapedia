<?php

namespace App\Domain\Deal;

use App\Domain\Auth\DomainException;
use App\Models\Milestone;
use App\Models\MilestoneDeliverable;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Milestone engine (doc §38). Funding = milestone_funding order;
 * release = ledger movement to vendor payable.
 */
class MilestoneService
{
    public function __construct(
        private readonly \App\Domain\Order\OrderService $orders,
        private readonly \App\Domain\Finance\SettlementService $settlements,
    ) {
    }

    public function fund(Milestone $milestone, User $customer, string $gateway = 'sandbox'): Order
    {
        return DB::transaction(function () use ($milestone, $customer) {
            if (! in_array($milestone->status, ['ready', 'draft'], true)) {
                throw new DomainException("Milestone is {$milestone->status}.", 'INVALID_STATE', 409);
            }

            $contract = $milestone->contract;

            if ($contract->customer_id !== $customer->id) {
                throw new DomainException('Not your contract.', 'FORBIDDEN', 403);
            }

            // Create a funding order (type milestone_funding)
            $order = Order::create([
                'user_id' => $customer->id,
                'partner_id' => $contract->partner_id,
                'type' => Order::TYPE_MILESTONE_FUNDING,
                'status' => 'pending_payment',
                'fulfillment_type' => 'milestone_project',
                'delivery_mode' => 'remote',
                'pricing_snapshot' => ['milestone_id' => $milestone->id, 'total' => $milestone->amount, 'currency' => 'IDR'],
                'subtotal' => $milestone->amount,
                'total' => $milestone->amount,
                'meta' => ['milestone_id' => $milestone->id, 'contract_id' => $contract->id],
            ]);

            $order->items()->create([
                'type' => 'base',
                'name' => "Funding: {$milestone->title}",
                'qty' => 1,
                'unit_price' => $milestone->amount,
                'amount' => $milestone->amount,
                'ref_id' => $milestone->id,
            ]);

            $milestone->update(['order_id' => $order->id, 'status' => 'ready']);

            return $order;
        });
    }

    /** Called after funding order is paid. */
    public function onFundingPaid(Order $order): void
    {
        $milestoneId = $order->meta['milestone_id'] ?? null;

        if ($milestoneId) {
            Milestone::where('id', $milestoneId)->update(['status' => 'funded']);
        }
    }

    public function start(Milestone $milestone, User $partnerUser): Milestone
    {
        $this->assertPartner($milestone, $partnerUser);

        if ($milestone->status !== 'funded') {
            throw new DomainException("Milestone is {$milestone->status}.", 'INVALID_STATE', 409);
        }

        $milestone->update(['status' => 'in_progress']);

        return $milestone;
    }

    public function submit(Milestone $milestone, User $partnerUser, array $deliverables, ?string $note = null): Milestone
    {
        $this->assertPartner($milestone, $partnerUser);

        return DB::transaction(function () use ($milestone, $partnerUser, $deliverables, $note) {
            if (! in_array($milestone->status, ['funded', 'in_progress', 'resubmitted', 'revision_requested'], true)) {
                throw new DomainException("Milestone is {$milestone->status}.", 'INVALID_STATE', 409);
            }

            $revision = MilestoneDeliverable::where('milestone_id', $milestone->id)->max('revision') ?? 0;

            foreach ($deliverables as $file) {
                MilestoneDeliverable::create([
                    'milestone_id' => $milestone->id,
                    'uploaded_by' => $partnerUser->id,
                    'file_path' => $file['file_path'],
                    'kind' => $file['kind'] ?? 'file',
                    'note' => $note,
                    'revision' => $revision + 1,
                ]);
            }

            $milestone->update([
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            return $milestone->fresh();
        });
    }

    /** Customer revision request → partner resubmits. */
    public function requestRevision(Milestone $milestone, User $customer, string $note): Milestone
    {
        if ($milestone->contract->customer_id !== $customer->id) {
            throw new DomainException('Not your contract.', 'FORBIDDEN', 403);
        }

        if ($milestone->status !== 'submitted') {
            throw new DomainException("Milestone is {$milestone->status}.", 'INVALID_STATE', 409);
        }

        $milestone->update(['status' => 'revision_requested']);

        return $milestone->fresh();
    }

    /** Customer approval — idempotent and race-safe (unique-ish guard via status lock). */
    public function approve(Milestone $milestone, User $customer): Milestone
    {
        return DB::transaction(function () use ($milestone, $customer) {
            $milestone = Milestone::where('id', $milestone->id)->lockForUpdate()->first();

            if ($milestone->contract->customer_id !== $customer->id) {
                throw new DomainException('Not your contract.', 'FORBIDDEN', 403);
            }

            if (! in_array($milestone->status, ['submitted', 'resubmitted'], true)) {
                throw new DomainException("Milestone is {$milestone->status}.", 'INVALID_STATE', 409);
            }

            $milestone->update(['status' => 'approved', 'approved_at' => now()]);

            return $milestone;
        });
    }

    /** Release funds: commission snapshot + settlement for the funding order. */
    public function release(Milestone $milestone): void
    {
        DB::transaction(function () use ($milestone) {
            $milestone = Milestone::where('id', $milestone->id)->lockForUpdate()->first();

            if ($milestone->status !== 'approved') {
                throw new DomainException("Milestone is {$milestone->status}.", 'INVALID_STATE', 409);
            }

            $order = Order::findOrFail($milestone->order_id);

            if ($order->status !== 'paid') {
                throw new DomainException('Funding order not in paid state.', 'INVALID_STATE', 409);
            }

            // Complete funding order → settlement pipeline (commission + payable)
            $order->transition('settlement_pending', null, 'Milestone approved → release');
            $settlement = $this->settlements->createFor($order);
            $this->settlements->process($settlement);

            $milestone->update(['status' => 'released', 'released_at' => now()]);
        });
    }

    private function assertPartner(Milestone $milestone, User $user): void
    {
        if ($milestone->contract->partner->user_id !== $user->id) {
            throw new DomainException('Not the contracted partner.', 'FORBIDDEN', 403);
        }
    }
}
