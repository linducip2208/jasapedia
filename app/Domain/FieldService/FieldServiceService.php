<?php

namespace App\Domain\FieldService;

use App\Domain\Auth\DomainException;
use App\Models\AdditionalChargeRequest;
use App\Models\Assignment;
use App\Models\Checkin;
use App\Models\Material;
use App\Models\Order;
use App\Models\ServiceEvidence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FieldServiceService
{
    public function __construct(private readonly \App\Domain\Dispatch\DispatchService $dispatch)
    {
    }

    public function onTheWay(Order $order, ?User $actor = null): Order
    {
        $this->assertWorker($order, $actor);
        $target = in_array($order->status, ['accepted', 'assigned'], true) ? 'on_the_way' : null;

        if ($target === null) {
            throw new DomainException("Cannot depart from {$order->status}.", 'INVALID_STATE', 409);
        }

        return $this->dispatch->transition($order, $target, $actor, 'Provider on the way');
    }

    public function arrived(Order $order, ?User $actor = null, ?float $lat = null, ?float $lng = null): array
    {
        $this->assertWorker($order, $actor);

        if ($order->status !== 'on_the_way') {
            throw new DomainException("Cannot arrive from {$order->status}.", 'INVALID_STATE', 409);
        }

        // OTP for arrival verification (customer confirms in person)
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::transaction(function () use ($order, $actor, $lat, $lng, $otp) {
            $this->dispatch->transition($order, 'arrived', $actor, 'Provider arrived');
            Checkin::create([
                'order_id' => $order->id,
                'user_id' => $actor->id,
                'type' => 'checkin',
                'lat' => $lat,
                'lng' => $lng,
                'otp_code' => $otp,
                'meta' => ['stage' => 'arrival_otp_issued'],
            ]);
        });

        return ['otp' => $otp]; // delivered to customer via notification in Phase 15
    }

    /** Customer-side or provider-side OTP verification completes check-in. */
    public function verifyCheckin(Order $order, string $otp, ?User $actor = null): Order
    {
        $checkin = Checkin::where('order_id', $order->id)
            ->where('type', 'checkin')
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $checkin || ! hash_equals((string) $checkin->otp_code, (string) $otp)) {
            throw new DomainException('Invalid OTP.', 'OTP_INVALID', 422);
        }

        $checkin->update(['verified_at' => now()]);
        $this->dispatch->transition($order, 'checked_in', $actor, 'OTP verified');

        return $order->fresh();
    }

    public function startWork(Order $order, ?User $actor = null): Order
    {
        $this->assertWorker($order, $actor);

        if ($order->status !== 'checked_in') {
            throw new DomainException("Cannot start work from {$order->status}.", 'INVALID_STATE', 409);
        }

        return $this->dispatch->transition($order, 'working', $actor, 'Work started');
    }

    public function uploadEvidence(Order $order, User $actor, string $stage, string $filePath, string $kind = 'photo', ?string $note = null): ServiceEvidence
    {
        $this->assertWorker($order, $actor);

        return ServiceEvidence::create([
            'order_id' => $order->id,
            'uploaded_by' => $actor->id,
            'stage' => $stage,
            'file_path' => $filePath,
            'kind' => $kind,
            'note' => $note,
        ]);
    }

    public function addMaterial(Order $order, User $actor, array $data): Material
    {
        $this->assertWorker($order, $actor);

        return Material::create([
            ...$data,
            'order_id' => $order->id,
            'partner_id' => $order->partner_id,
        ]);
    }

    public function requestAdditionalCharge(Order $order, User $actor, array $data): AdditionalChargeRequest
    {
        $this->assertWorker($order, $actor);

        if ($order->status !== 'working') {
            throw new DomainException('Additional charge only during working.', 'INVALID_STATE', 409);
        }

        return AdditionalChargeRequest::create([
            ...$data,
            'order_id' => $order->id,
            'created_by' => $actor->id,
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);
    }

    /** Structured commercial approval (doc 43) — never via free text. */
    public function decideAdditionalCharge(AdditionalChargeRequest $acr, User $customer, string $decision): AdditionalChargeRequest
    {
        if ($acr->order->user_id !== $customer->id) {
            throw new DomainException('Not your order.', 'FORBIDDEN', 403);
        }

        if ($acr->status !== 'pending') {
            throw new DomainException('Request already handled.', 'ALREADY_HANDLED', 409);
        }

        if ($acr->expires_at && $acr->expires_at->isPast()) {
            $acr->update(['status' => 'expired']);
            throw new DomainException('Request expired.', 'REQUEST_EXPIRED', 409);
        }

        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new DomainException('Invalid decision.', 'INVALID_DECISION', 422);
        }

        $acr->update(['status' => $decision, 'decided_by' => $customer->id, 'decided_at' => now()]);

        return $acr->fresh();
    }

    /** Submit for customer confirmation; requires after-evidence + pending ACRs resolved. */
    public function submitForConfirmation(Order $order, ?User $actor = null): Order
    {
        $this->assertWorker($order, $actor);

        if ($order->status !== 'working') {
            throw new DomainException("Cannot submit from {$order->status}.", 'INVALID_STATE', 409);
        }

        $pendingAcr = AdditionalChargeRequest::where('order_id', $order->id)->where('status', 'pending')->exists();
        if ($pendingAcr) {
            throw new DomainException('Pending additional charge must be resolved.', 'ACR_PENDING', 409);
        }

        $hasAfter = ServiceEvidence::where('order_id', $order->id)->where('stage', 'after')->exists();
        if (! $hasAfter) {
            throw new DomainException('After-evidence required before completion.', 'EVIDENCE_REQUIRED', 422);
        }

        return $this->dispatch->transition($order, 'awaiting_customer_confirmation', $actor, 'Provider submitted completion');
    }

    private function assertWorker(Order $order, ?User $actor): void
    {
        if (! $actor) {
            throw new DomainException('Authentication required.', 'UNAUTHENTICATED', 401);
        }

        $assignment = Assignment::where('order_id', $order->id)->where('status', 'accepted')->first();

        $isPartnerOwner = $order->partner && $order->partner->user_id === $actor->id;
        $isWorker = $assignment && ($assignment->worker_user_id === $actor->id || $order->partner?->user_id === $actor->id);

        if (! $isPartnerOwner && ! $isWorker) {
            throw new DomainException('Not the assigned worker for this order.', 'FORBIDDEN', 403);
        }
    }
}
