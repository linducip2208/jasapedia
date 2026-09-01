<?php

namespace Tests\Feature\FieldService;

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRITICAL E2E (§126): book → pay → dispatch → accept → field work →
 * additional charge → complete → customer confirm.
 */
class HomeServiceE2eTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LocationSeeder::class);
        $this->seed(CatalogSeeder::class);
    }

    private function seedMarketplace(): array
    {
        $customer = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ibu Sari', 'email' => 'sari@test.id', 'password' => 'RahasiaKuat99',
        ]);
        $customerToken = $customer->json('data.token');

        $partner = $this->postJson('/api/v1/auth/register', [
            'name' => 'Pak Anton', 'email' => 'anton@test.id', 'password' => 'RahasiaKuat99',
        ]);
        $partnerToken = $partner->json('data.token');

        $this->withToken($partnerToken)->postJson('/api/v1/partner', [
            'type' => 'individual', 'display_name' => 'Anton AC Service', 'city' => 'Jakarta Selatan',
        ]);
        \App\Models\Partner::query()->update([
            'verification_state' => 'verified',
            'rating_avg' => 4.8,
            'rating_count' => 42,
            'completed_jobs' => 120,
            'response_minutes' => 15,
        ]);

        foreach (range(0, 6) as $day) {
            \App\Models\PartnerSchedule::create([
                'partner_id' => \App\Models\Partner::first()->id,
                'day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '17:00',
            ]);
        }

        $catId = \App\Models\Category::where('slug', 'ac-electronics')->value('id');
        $this->withToken($partnerToken)->postJson('/api/v1/partner/services', [
            'category_id' => $catId,
            'title' => 'Cuci AC Split',
            'fulfillment_type' => 'per_unit',
            'delivery_mode' => 'onsite',
            'price_model' => 'per_unit',
            'base_price' => 90000,
            'unit_label' => 'unit',
            'duration_minutes' => 60,
            'emergency_capable' => true,
            'emergency_surcharge' => 40000,
        ]);
        $serviceId = \App\Models\Service::first()->id;

        $address = $this->withToken($customerToken)->postJson('/api/v1/addresses', [
            'label' => 'Rumah', 'recipient_name' => 'Sari', 'phone' => '0812345',
            'address_line' => 'Jl. Melati No. 9',
        ])->json('data.address.id');

        return [$customerToken, $partnerToken, $serviceId, $address];
    }

    public function test_full_home_service_journey(): void
    {
        [$customerToken, $partnerToken, $serviceId, $addressId] = $this->seedMarketplace();

        // 1. Book 2 units
        $order = $this->withToken($customerToken)->postJson('/api/v1/orders', [
            'service_id' => $serviceId,
            'quantity' => 2,
            'scheduled_at' => \Carbon\Carbon::parse('next monday 09:00')->toIso8601String(),
            'address_id' => $addressId,
            'customer_note' => '2 unit AC, satu di kamar satu di ruang tamu',
        ]);
        $order->assertCreated();
        $orderId = $order->json('data.order.id');
        $this->assertSame(180000, $order->json('data.order.total'));

        // 2. Pay (sandbox)
        $this->postJson('/api/v1/payments/sandbox/pay', [
            'order_code' => $order->json('data.order.code'),
        ])->assertOk();

        // 3. Auto dispatch → offered
        $orderRow = \App\Models\Order::find($orderId);
        app(\App\Domain\Dispatch\DispatchService::class)->dispatch($orderRow);
        $this->assertDatabaseHas('assignments', ['order_id' => $orderId, 'status' => 'offered', 'mode' => 'auto_direct']);

        // 4. Partner accepts offer
        $assignmentId = \App\Models\Assignment::where('order_id', $orderId)->first()->id;
        $this->withToken($partnerToken)->postJson("/api/v1/field/assignments/{$assignmentId}/accept")
            ->assertOk()->assertJsonPath('data.assignment.status', 'accepted');
        $this->assertSame('accepted', \App\Models\Order::find($orderId)->status);

        // 5. On the way
        $this->withToken($partnerToken)->postJson("/api/v1/field/orders/{$orderId}/on-the-way")
            ->assertOk()->assertJsonPath('data.order.status', 'on_the_way');

        // 6. Arrived → OTP issued
        $arrived = $this->withToken($partnerToken)->postJson("/api/v1/field/orders/{$orderId}/arrived", [
            'lat' => -6.2495, 'lng' => 106.7992,
        ])->assertOk();
        $otp = $arrived->json('data.otp');
        $this->assertSame('arrived', \App\Models\Order::find($orderId)->status);

        // 7. Check-in via OTP
        $this->withToken($customerToken)->postJson("/api/v1/orders/{$orderId}/checkin", ['otp' => $otp])
            ->assertOk()->assertJsonPath('data.order.status', 'checked_in');

        // Wrong OTP rejected for second use
        $this->withToken($customerToken)->postJson("/api/v1/orders/{$orderId}/checkin", ['otp' => $otp])
            ->assertStatus(422);

        // 8. Start work + before evidence
        $this->withToken($partnerToken)->postJson("/api/v1/field/orders/{$orderId}/start-work")
            ->assertOk()->assertJsonPath('data.order.status', 'working');

        $this->withToken($partnerToken)->postJson("/api/v1/field/orders/{$orderId}/evidence", [
            'stage' => 'before', 'file_path' => 'orders/before/1.jpg',
        ])->assertCreated();

        // 9. Additional charge: kapasitor rusak
        $acr = $this->withToken($partnerToken)->postJson("/api/v1/field/orders/{$orderId}/additional-charges", [
            'item' => 'Ganti kapasitor AC ruang tamu',
            'description' => 'Kapasitor bocor, perlu ganti baru.',
            'amount' => 150000,
            'evidence_path' => 'orders/acr/kapasitor.jpg',
        ])->assertCreated()->json('data.additional_charge');

        // 10. Submit blocked while ACR pending
        $this->withToken($partnerToken)->postJson("/api/v1/field/orders/{$orderId}/submit-completion")
            ->assertStatus(409)->assertJsonPath('error.code', 'ACR_PENDING');

        // 11. Customer approves charge (structured endpoint)
        $this->withToken($customerToken)->postJson("/api/v1/orders/{$acr['id']}/additional-charges/decide", [
            'decision' => 'approved',
        ])->assertOk()->assertJsonPath('data.additional_charge.status', 'approved');

        // Free text in chat later must NOT change amounts — no such endpoint exists.

        // 12. After evidence + submit
        $this->withToken($partnerToken)->postJson("/api/v1/field/orders/{$orderId}/evidence", [
            'stage' => 'after', 'file_path' => 'orders/after/1.jpg', 'note' => 'AC dingin kembali',
        ])->assertCreated();

        // Material used
        $this->withToken($partnerToken)->postJson("/api/v1/field/orders/{$orderId}/materials", [
            'name' => 'Kapasitor 35uF', 'qty' => 1, 'unit' => 'pcs', 'cost' => 45000, 'sell_price' => 150000,
        ])->assertCreated();

        $this->withToken($partnerToken)->postJson("/api/v1/field/orders/{$orderId}/submit-completion")
            ->assertOk()->assertJsonPath('data.order.status', 'awaiting_customer_confirmation');

        // 13. Customer confirms → completed
        $this->withToken($customerToken)->postJson("/api/v1/orders/{$orderId}/confirm", [
            'note' => 'Kerja rapi, terima kasih',
        ])->assertOk()->assertJsonPath('data.order.status', 'completed');

        // History integrity
        $statuses = \App\Models\OrderStatusHistory::where('order_id', $orderId)
            ->orderBy('id')->pluck('to_status')->all();
        $this->assertSame([
            'pending_payment', 'paid', 'searching_provider', 'offered', 'accepted',
            'on_the_way', 'arrived', 'checked_in', 'working',
            'awaiting_customer_confirmation', 'completed',
        ], $statuses);
    }

    public function test_non_worker_cannot_drive_field_state(): void
    {
        [$customerToken, $partnerToken, $serviceId, $addressId] = $this->seedMarketplace();

        $order = $this->withToken($customerToken)->postJson('/api/v1/orders', [
            'service_id' => $serviceId,
            'scheduled_at' => \Carbon\Carbon::parse('next monday 10:00')->toIso8601String(),
            'address_id' => $addressId,
        ])->json('data.order');

        // Customer cannot pretend to be the worker
        $this->withToken($customerToken)->postJson("/api/v1/field/orders/{$order['id']}/on-the-way")
            ->assertStatus(403);
    }
}
