<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Domain\FieldService\FieldServiceService;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\AdditionalChargeRequest;
use App\Models\Order;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Partner field-service endpoints (Phase 12/13).
 * All state transitions re-validated server-side.
 */
class FieldServiceController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly FieldServiceService $field)
    {
    }

    public function accept(Request $request, int $assignmentId): JsonResponse
    {
        $assignment = \App\Models\Assignment::findOrFail($assignmentId);
        $this->assertPartnerAccess($request, $assignment);

        $assignment = app(\App\Domain\Dispatch\DispatchService::class)->accept($assignment, $request->user());

        return $this->ok(['assignment' => $assignment], 'Offer accepted.');
    }

    public function reject(Request $request, int $assignmentId): JsonResponse
    {
        $assignment = \App\Models\Assignment::findOrFail($assignmentId);
        $this->assertPartnerAccess($request, $assignment);

        $assignment = app(\App\Domain\Dispatch\DispatchService::class)->reject($assignment, $request->user(), $request->string('reason')->toString());

        return $this->ok(['assignment' => $assignment], 'Offer rejected.');
    }

    public function myOffers(Request $request): JsonResponse
    {
        $partnerIds = \App\Models\Partner::where('user_id', $request->user()->id)->pluck('id');
        $offers = \App\Models\Assignment::whereIn('partner_id', $partnerIds)
            ->where('status', 'offered')
            ->with('order:id,code,type,status,fulfillment_type,scheduled_at,total,address_snapshot')
            ->get();

        return $this->ok(['offers' => $offers]);
    }

    public function onTheWay(Request $request, int $orderId): JsonResponse
    {
        $order = $this->orderForPartner($request, $orderId);

        return $this->ok(['order' => $this->field->onTheWay($order, $request->user())], 'On the way.');
    }

    public function arrived(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
        ]);

        $order = $this->orderForPartner($request, $orderId);
        $result = $this->field->arrived($order, $request->user(), $data['lat'] ?? null, $data['lng'] ?? null);

        return $this->ok(['otp' => $result['otp']], 'Arrived. Share OTP with customer.');
    }

    public function verifyCheckin(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate(['otp' => ['required', 'string', 'size:6']]);
        $order = $this->orderForPartner($request, $orderId);
        $order = $this->field->verifyCheckin($order, $data['otp'], $request->user());

        return $this->ok(['order' => $order], 'Checked in.');
    }

    public function startWork(Request $request, int $orderId): JsonResponse
    {
        $order = $this->orderForPartner($request, $orderId);

        return $this->ok(['order' => $this->field->startWork($order, $request->user())], 'Work started.');
    }

    public function uploadEvidence(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'in:before,after,rework'],
            'file_path' => ['required', 'string', 'max:512'],
            'kind' => ['nullable', 'in:photo,video,file'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $order = $this->orderForPartner($request, $orderId);
        $evidence = $this->field->uploadEvidence($order, $request->user(), $data['stage'], $data['file_path'], $data['kind'] ?? 'photo', $data['note'] ?? null);

        return $this->created(['evidence' => $evidence]);
    }

    public function addMaterial(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'sku' => ['nullable', 'string', 'max:64'],
            'qty' => ['required', 'integer', 'min:1'],
            'unit' => ['nullable', 'string', 'max:24'],
            'cost' => ['nullable', 'integer', 'min:0'],
            'sell_price' => ['required', 'integer', 'min:0'],
        ]);

        $order = $this->orderForPartner($request, $orderId);
        $material = $this->field->addMaterial($order, $request->user(), $data);

        return $this->created(['material' => $material]);
    }

    public function requestAdditionalCharge(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'item' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'integer', 'min:1'],
            'evidence_path' => ['nullable', 'string', 'max:512'],
        ]);

        $order = $this->orderForPartner($request, $orderId);
        $acr = $this->field->requestAdditionalCharge($order, $request->user(), $data);

        return $this->created(['additional_charge' => $acr], 'Awaiting customer approval.');
    }

    public function submitCompletion(Request $request, int $orderId): JsonResponse
    {
        $order = $this->orderForPartner($request, $orderId);
        $order = $this->field->submitForConfirmation($order, $request->user());

        return $this->ok(['order' => $order], 'Submitted for customer confirmation.');
    }

    private function orderForPartner(Request $request, int $orderId): Order
    {
        $partnerIds = \App\Models\Partner::where('user_id', $request->user()->id)->pluck('id');
        $memberOrgs = \App\Models\PartnerMember::where('user_id', $request->user()->id)->pluck('organization_id');

        return Order::where(function ($q) use ($partnerIds, $memberOrgs) {
            $q->whereIn('partner_id', $partnerIds)->orWhereIn('organization_id', $memberOrgs);
        })->findOrFail($orderId);
    }

    private function assertPartnerAccess(Request $request, \App\Models\Assignment $assignment): void
    {
        $partner = \App\Models\Partner::find($assignment->partner_id);
        $member = \App\Models\PartnerMember::where('user_id', $request->user()->id)
            ->where('organization_id', $partner?->organization?->id ?? 0)
            ->exists();

        if ($partner?->user_id !== $request->user()->id && ! $member) {
            abort(403, 'Not your offer.');
        }
    }
}
