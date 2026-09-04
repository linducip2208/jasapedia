<?php

namespace App\Domain\Deal;

use App\Domain\Auth\DomainException;
use App\Domain\Order\OrderStateMachine;
use App\Models\Order;
use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * RFQ (Phase 17) + Quotation (Phase 19) with versioned, immutable approvals (doc 36).
 */
class RfqService
{
    public function __construct(
        private readonly OrderStateMachine $states,
    ) {
    }
    public function create(User $buyer, array $data): Rfq
    {
        return Rfq::create([
            ...$data,
            'user_id' => $buyer->id,
            'code' => 'RFQ-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
            'status' => 'open',
        ]);
    }

    public function close(Rfq $rfq, User $buyer): Rfq
    {
        if ($rfq->user_id !== $buyer->id) {
            throw new DomainException('Not your RFQ.', 'FORBIDDEN', 403);
        }

        $rfq->update(['status' => 'closed']);

        return $rfq;
    }

    /**
     * Vendor submits a quotation (creates version 1).
     * Multiple vendors may quote; one row per vendor per RFQ.
     */
    public function submitQuotation(\App\Models\Partner $partner, array $data): Quotation
    {
        return DB::transaction(function () use ($partner, $data) {
            $rfq = Rfq::lockForUpdate()->findOrFail($data['rfq_id']);

            if ($rfq->status !== 'open') {
                throw new DomainException("RFQ is {$rfq->status}.", 'RFQ_NOT_OPEN', 409);
            }

            if ($rfq->deadline && $rfq->deadline->isPast()) {
                throw new DomainException('RFQ deadline passed.', 'DEADLINE_PASSED', 409);
            }

            // Public RFQs open to all; private only to invited
            if ($rfq->visibility === 'invited' && ! in_array($partner->id, $rfq->invited_partner_ids ?? [], true)) {
                throw new DomainException('Not invited to this RFQ.', 'NOT_INVITED', 403);
            }

            $existing = Quotation::where('rfq_id', $rfq->id)->where('partner_id', $partner->id)
                ->whereIn('status', ['draft', 'sent'])->first();

            if ($existing) {
                throw new DomainException('Quotation already submitted. Submit a new version instead.', 'QUOTATION_EXISTS', 409);
            }

            $lines = $data['line_items'];
            $subtotal = 0;
            foreach ($lines as $line) {
                $subtotal += (int) $line['qty'] * (int) $line['unit_price'];
            }

            $discount = (int) ($data['discount'] ?? 0);
            $tax = (int) ($data['tax'] ?? 0);
            $total = max(0, $subtotal - $discount + $tax);

            return Quotation::create([
                'code' => 'QUO-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'rfq_id' => $rfq->id,
                'partner_id' => $partner->id,
                'customer_id' => $rfq->user_id,
                'version' => 1,
                'line_items' => $lines,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'terms' => $data['terms'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'attachments' => $data['attachments'] ?? null,
                'status' => 'sent',
            ]);
        });
    }

    /**
     * Revision = NEW VERSION ROW. Approved quotations are never mutated (doc 36).
     */
    public function reviseQuotation(Quotation $quotation, \App\Models\Partner $partner, array $changes): Quotation
    {
        return DB::transaction(function () use ($quotation, $partner, $changes) {
            if ($quotation->partner_id !== $partner->id) {
                throw new DomainException('Not your quotation.', 'FORBIDDEN', 403);
            }

            if (in_array($quotation->status, ['approved', 'superseded'], true)) {
                throw new DomainException("Approved/superseded quotations cannot be revised.", 'IMMUTABLE', 409);
            }

            $lines = $changes['line_items'] ?? $quotation->line_items;
            $subtotal = 0;
            foreach ($lines as $line) {
                $subtotal += (int) $line['qty'] * (int) $line['unit_price'];
            }

            $discount = (int) ($changes['discount'] ?? $quotation->discount);
            $tax = (int) ($changes['tax'] ?? $quotation->tax);

            $version = Quotation::create([
                'code' => $quotation->code,
                'rfq_id' => $quotation->rfq_id,
                'order_id' => $quotation->order_id,
                'partner_id' => $quotation->partner_id,
                'customer_id' => $quotation->customer_id,
                'version' => $quotation->version + 1,
                'line_items' => $lines,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => max(0, $subtotal - $discount + $tax),
                'terms' => $changes['terms'] ?? $quotation->terms,
                'valid_until' => $changes['valid_until'] ?? $quotation->valid_until,
                'attachments' => $changes['attachments'] ?? $quotation->attachments,
                'status' => 'sent',
            ]);

            $quotation->update(['status' => 'superseded']);

            return $version;
        });
    }

    /** Buyer approves a quotation version — then it becomes immutable. */
    public function approveQuotation(Quotation $quotation, User $buyer): Quotation
    {
        return DB::transaction(function () use ($quotation, $buyer) {
            if ($quotation->customer_id !== $buyer->id) {
                throw new DomainException('Not your quotation.', 'FORBIDDEN', 403);
            }

            if ($quotation->status !== 'sent') {
                throw new DomainException("Quotation is {$quotation->status}.", 'INVALID_STATE', 409);
            }

            $quotation->update(['status' => 'approved', 'approved_by' => $buyer->id, 'decided_at' => now()]);

            // Supersede sibling versions + mark RFQ awarded
            Quotation::where('rfq_id', $quotation->rfq_id)
                ->where('id', '!=', $quotation->id)
                ->where('status', 'sent')
                ->update(['status' => 'superseded']);

            Rfq::where('id', $quotation->rfq_id)->update(['status' => 'awarded']);

            return $quotation->fresh();
        });
    }

    /**
     * Convert an approved quotation into a payable service order (survey→quotation→order path).
     * Idempotent: a quotation converts exactly once (order_id set under lock).
     */
    public function convertQuotationToOrder(Quotation $quotation, User $buyer): Order
    {
        return DB::transaction(function () use ($quotation, $buyer) {
            $quotation = Quotation::where('id', $quotation->id)->lockForUpdate()->firstOrFail();

            if ($quotation->customer_id !== $buyer->id) {
                throw new DomainException('Not your quotation.', 'FORBIDDEN', 403);
            }

            if ($quotation->status !== 'approved') {
                throw new DomainException("Only approved quotations can be ordered (current: {$quotation->status}).", 'INVALID_STATE', 409);
            }

            if ($quotation->valid_until && $quotation->valid_until->isPast()) {
                throw new DomainException('Quotation validity has passed.', 'QUOTATION_EXPIRED', 409);
            }

            if ($quotation->order_id) {
                return Order::findOrFail($quotation->order_id);
            }

            $order = Order::create([
                'user_id' => $buyer->id,
                'partner_id' => $quotation->partner_id,
                'type' => Order::TYPE_SERVICE,
                'status' => 'draft',
                'fulfillment_type' => 'rfq',
                'delivery_mode' => 'onsite',
                'pricing_snapshot' => [
                    'source' => 'quotation',
                    'quotation_id' => $quotation->id,
                    'quotation_code' => $quotation->code,
                    'version' => $quotation->version,
                    'rfq_id' => $quotation->rfq_id,
                    'currency' => 'IDR',
                ],
                'customer_note' => $quotation->terms,
                'subtotal' => $quotation->subtotal,
                'emergency_surcharge' => 0,
                'total' => $quotation->total,
                'meta' => ['quotation_id' => $quotation->id, 'rfq_id' => $quotation->rfq_id],
            ]);

            foreach ($quotation->line_items as $line) {
                $order->items()->create([
                    'type' => 'base',
                    'name' => (string) ($line['name'] ?? 'Item'),
                    'qty' => max(1, (int) ($line['qty'] ?? 1)),
                    'unit_price' => (int) ($line['unit_price'] ?? 0),
                    'amount' => (int) ($line['qty'] ?? 1) * (int) ($line['unit_price'] ?? 0),
                    'ref_id' => $quotation->id,
                ]);
            }

            $quotation->update(['order_id' => $order->id]);

            $this->states->transition($order, 'pending_payment', $buyer, 'Quotation converted to order');

            return $order->load('items');
        });
    }
}
