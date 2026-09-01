<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Order\OrderService;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Order;
use App\Models\Service;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderService $orders)
    {
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'package_id' => ['nullable', 'integer'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer'],
            'emergency' => ['nullable', 'boolean'],
            'duration_minutes' => ['nullable', 'integer', 'min:15'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
        ]);

        $service = Service::findOrFail($data['service_id']);
        $quote = $this->orders->quote($service, \App\Domain\Pricing\PricingInput::fromArray($data));

        return $this->ok(['quote' => $quote->snapshot()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'package_id' => ['nullable', 'integer'],
            'addon_ids' => ['nullable', 'array'],
            'addon_ids.*' => ['integer'],
            'emergency' => ['nullable', 'boolean'],
            'duration_minutes' => ['nullable', 'integer', 'min:15'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array'],
        ]);

        $service = Service::findOrFail($data['service_id']);
        $order = $this->orders->createServiceOrder($request->user(), $service, $data);

        return $this->created(['order' => $order], 'Order created, awaiting payment.');
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->hasMany(Order::class)
            ->with('service:id,title,slug,price_model,base_price,unit_label', 'partner:id,display_name,slug,city,rating_avg', 'items')
            ->latest()
            ->paginate(20);

        return $this->paginated($orders);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = $request->user()->hasMany(Order::class)
            ->with('items', 'history', 'partner:id,display_name,slug,city,rating_avg,verification_state')
            ->findOrFail($id);

        return $this->ok(['order' => $order]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $order = $request->user()->hasMany(Order::class)->findOrFail($id);
        $order = $this->orders->cancel($order, $request->user(), $data['reason']);

        return $this->ok(['order' => $order], 'Order cancelled.');
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $order = $request->user()->hasMany(Order::class)->findOrFail($id);
        $order = $this->orders->confirmCompletion($order, $request->user(), $request->string('note')->toString());

        return $this->ok(['order' => $order], 'Order completed.');
    }

    /** Customer confirms partner check-in via OTP. */
    public function confirmCheckin(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['otp' => ['required', 'string', 'size:6']]);

        $order = $request->user()->hasMany(Order::class)->findOrFail($id);
        $order = app(\App\Domain\FieldService\FieldServiceService::class)->verifyCheckin($order, $data['otp'], $request->user());

        return $this->ok(['order' => $order], 'Provider checked in.');
    }

    /** Structured additional-charge decision (never chat text). */
    public function decideAdditionalCharge(Request $request, int $acrId): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected']]);

        $acr = \App\Models\AdditionalChargeRequest::findOrFail($acrId);
        $acr = app(\App\Domain\FieldService\FieldServiceService::class)->decideAdditionalCharge($acr, $request->user(), $data['decision']);

        return $this->ok(['additional_charge' => $acr], "Charge {$data['decision']}.");
    }
}
