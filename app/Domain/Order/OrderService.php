<?php

namespace App\Domain\Order;

use App\Domain\Availability\AvailabilityService;
use App\Domain\Auth\DomainException;
use App\Domain\Pricing\PricingCalculator;
use App\Domain\Pricing\PricingInput;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderService
{
    private const REQUIRES_SCHEDULE = ['appointment', 'per_unit', 'instant_booking'];

    public function __construct(
        private readonly PricingCalculator $pricing,
        private readonly AvailabilityService $availability,
        private readonly OrderStateMachine $states,
    ) {
    }

    public function quote(Service $service, PricingInput $input): \App\Domain\Pricing\PriceQuote
    {
        return $this->pricing->quote($service, $input);
    }

    public function createServiceOrder(User $customer, Service $service, array $data): Order
    {
        $needsSchedule = in_array($service->fulfillment_type, self::REQUIRES_SCHEDULE, true);

        if ($needsSchedule && empty($data['scheduled_at'])) {
            throw new DomainException('A schedule is required for this service.', 'SCHEDULE_REQUIRED', 422);
        }

        return DB::transaction(function () use ($customer, $service, $data, $needsSchedule) {
            $quote = $this->pricing->quote($service, PricingInput::fromArray($data));

            $address = null;
            if (! empty($data['address_id'])) {
                $address = CustomerAddress::where('user_id', $customer->id)->findOrFail($data['address_id']);
            }

            $order = Order::create([
                'user_id' => $customer->id,
                'partner_id' => $service->partner_id,
                'type' => Order::TYPE_SERVICE,
                'status' => 'draft',
                'service_id' => $service->id,
                'package_id' => $data['package_id'] ?? null,
                'fulfillment_type' => $service->fulfillment_type,
                'delivery_mode' => $service->delivery_mode,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'duration_minutes' => $service->duration_minutes,
                'address_id' => $address?->id,
                'address_snapshot' => $address ? [
                    'label' => $address->label,
                    'recipient_name' => $address->recipient_name,
                    'phone' => $address->phone,
                    'address_line' => $address->address_line,
                    'notes' => $address->notes,
                    'lat' => $address->lat,
                    'lng' => $address->lng,
                    'subdistrict_id' => $address->subdistrict_id,
                ] : null,
                'customer_note' => $data['customer_note'] ?? null,
                'attachments' => $data['attachments'] ?? null,
                'pricing_snapshot' => $quote->snapshot(),
                'subtotal' => $quote->subtotal->amount,
                'emergency_surcharge' => $quote->emergencySurcharge->amount,
                'total' => $quote->total->amount,
                'is_emergency' => $data['emergency'] ?? false,
            ]);

            foreach ($quote->lines as $line) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'type' => $line->type,
                    'name' => $line->name,
                    'qty' => $line->qty,
                    'unit_price' => $line->unitPrice,
                    'amount' => $line->amount(),
                    'ref_id' => $line->refId,
                    'unit_label' => $line->unitLabel,
                ]);
            }

            if ($needsSchedule) {
                $slotId = $this->availability->reserveSlot(
                    $service->partner()->first(),
                    $service,
                    $data['scheduled_at'],
                );
                $order->update(['slot_id' => $slotId]);
            }

            $this->states->transition($order, 'pending_payment', $customer, 'Checkout');

            return $order->load('items');
        });
    }

    public function cancel(Order $order, ?User $actor, string $reason): Order
    {
        return DB::transaction(function () use ($order, $actor, $reason) {
            $this->states->transition($order, 'cancelled', $actor, $reason);

            if ($order->slot_id) {
                $this->availability->releaseSlot($order->slot_id);
            }

            return $order;
        });
    }

    /** Customer confirms completion. */
    public function confirmCompletion(Order $order, ?User $actor, string $note = ''): Order
    {
        return $this->states->transition($order, 'completed', $actor, $note ?: 'Customer confirmed completion');
    }

    public function expireStalePendingPayments(int $minutes = 60): int
    {
        $expired = Order::where('status', 'pending_payment')
            ->where('created_at', '<', now()->subMinutes($minutes))
            ->get();
        $count = 0;

        foreach ($expired as $order) {
            DB::transaction(function () use ($order, &$count) {
                $this->states->transition($order, 'expired', null, 'Payment window elapsed');
                if ($order->slot_id) {
                    $this->availability->releaseSlot($order->slot_id);
                }
                $count++;
            });
        }

        return $count;
    }

    public function markPaid(Order $order, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $actor) {
            if ($order->slot_id) {
                DB::table('booking_slots')->where('id', $order->slot_id)->update(['status' => 'confirmed']);
            }

            $this->states->transition($order, 'paid', $actor, 'Payment received');

            // Auto-advance only service orders (milestone funding stays PAID until release)
            if ($order->type === Order::TYPE_SERVICE) {
                return $this->states->transition($order, 'searching_provider', null, 'Auto-dispatch started');
            }

            return $order;
        });
    }
}
