<?php

namespace Tests\Feature\Order;

use App\Domain\Order\OrderStateMachine;
use App\Models\CustomerAddress;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $customerToken;
    private string $partnerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LocationSeeder::class);
        $this->seed(CatalogSeeder::class);

        [$this->customerToken, $this->partnerToken, $this->acServiceId, $this->cleaningServiceId] = $this->setupMarketplace();
    }

    private function setupMarketplace(): array
    {
        $customer = $this->postJson('/api/v1/auth/register', [
            'name' => 'Customer', 'email' => 'c@c.test', 'password' => 'RahasiaKuat99',
        ]);
        $customerToken = $customer->json('data.token');

        $partner = $this->postJson('/api/v1/auth/register', [
            'name' => 'Partner', 'email' => 'p@p.test', 'password' => 'RahasiaKuat99',
        ]);
        $partnerToken = $partner->json('data.token');

        $this->withToken($partnerToken)->postJson('/api/v1/partner', [
            'type' => 'individual', 'display_name' => 'AC Tech', 'city' => 'Jakarta Selatan',
        ]);
        \App\Models\Partner::query()->update(['verification_state' => 'verified']);

        $catId = \App\Models\Category::where('slug', 'ac-electronics')->value('id');
        $this->withToken($partnerToken)->postJson('/api/v1/partner/services', [
            'category_id' => $catId,
            'title' => 'Cuci AC',
            'fulfillment_type' => 'per_unit',
            'delivery_mode' => 'onsite',
            'price_model' => 'per_unit',
            'base_price' => 90000,
            'unit_label' => 'unit',
            'duration_minutes' => 60,
        ]);
        $acServiceId = \App\Models\Service::first()->id;

        // Partner schedule: all days 08:00-16:00
        foreach (range(0, 6) as $day) {
            \App\Models\PartnerSchedule::create([
                'partner_id' => \App\Models\Partner::first()->id,
                'day_of_week' => $day,
                'start_time' => '08:00',
                'end_time' => '16:00',
            ]);
        }

        return [$customerToken, $partnerToken, $acServiceId, null];
    }

    private function createAddress(): int
    {
        $res = $this->withToken($this->customerToken)->postJson('/api/v1/addresses', [
            'label' => 'Rumah',
            'recipient_name' => 'Customer',
            'phone' => '08123456789',
            'address_line' => 'Jl. Testing No. 1',
        ]);

        return $res->json('data.address.id');
    }

    public function test_quote_endpoint(): void
    {
        $res = $this->withToken($this->customerToken)->postJson('/api/v1/orders/quote', [
            'service_id' => $this->acServiceId,
            'quantity' => 3,
        ]);

        $res->assertOk()->assertJsonPath('data.quote.total', 270000);
    }

    public function test_create_order_with_slot_and_pricing_snapshot(): void
    {
        $addressId = $this->createAddress();
        $scheduled = \Carbon\Carbon::parse('next monday 09:00')->toIso8601String();

        $res = $this->withToken($this->customerToken)->postJson('/api/v1/orders', [
            'service_id' => $this->acServiceId,
            'quantity' => 2,
            'scheduled_at' => $scheduled,
            'address_id' => $addressId,
            'customer_note' => 'Pakai tangga sendiri ada.',
        ]);

        $res->assertCreated()->assertJsonPath('data.order.status', 'pending_payment');
        $order = $res->json('data.order');

        // Frozen snapshot
        $this->assertSame(180000, $order['total']);
        $this->assertSame('Rumah', $order['address_snapshot']['label']);
        $this->assertCount(1, $order['items']);

        // Slot held
        $this->assertDatabaseHas('booking_slots', [
            'owner_id' => \App\Models\Partner::first()->id,
            'status' => 'held',
        ]);
    }

    public function test_double_booking_same_slot_rejected(): void
    {
        $addressId = $this->createAddress();
        $scheduled = \Carbon\Carbon::parse('next monday 09:00')->toIso8601String();

        $this->withToken($this->customerToken)->postJson('/api/v1/orders', [
            'service_id' => $this->acServiceId,
            'scheduled_at' => $scheduled,
            'address_id' => $addressId,
        ])->assertCreated();

        $second = $this->withToken($this->customerToken)->postJson('/api/v1/orders', [
            'service_id' => $this->acServiceId,
            'scheduled_at' => $scheduled,
            'address_id' => $addressId,
        ]);

        $second->assertStatus(409)->assertJsonPath('error.code', 'SLOT_TAKEN');
    }

    public function test_cancel_releases_slot(): void
    {
        $addressId = $this->createAddress();
        $order = $this->withToken($this->customerToken)->postJson('/api/v1/orders', [
            'service_id' => $this->acServiceId,
            'scheduled_at' => \Carbon\Carbon::parse('next monday 10:00')->toIso8601String(),
            'address_id' => $addressId,
        ])->json('data.order');

        $this->withToken($this->customerToken)->postJson("/api/v1/orders/{$order['id']}/cancel", [
            'reason' => 'Berubah rencana',
        ])->assertOk()->assertJsonPath('data.order.status', 'cancelled');

        $this->assertDatabaseMissing('booking_slots', ['id' => $order['slot_id']]);

        // Reusable now
        $this->withToken($this->customerToken)->postJson('/api/v1/orders', [
            'service_id' => $this->acServiceId,
            'scheduled_at' => \Carbon\Carbon::parse('next monday 10:00')->toIso8601String(),
            'address_id' => $addressId,
        ])->assertCreated();
    }

    public function test_illegal_transition_throws(): void
    {
        $order = \App\Models\Order::factory()->create(['status' => 'paid']);
        $this->expectException(\App\Domain\Common\Exceptions\StateTransitionException::class);

        app(OrderStateMachine::class)->transition($order, 'completed');
    }

    public function test_history_is_immutable_log(): void
    {
        $order = \App\Models\Order::factory()->create(['status' => 'pending_payment']);
        $sm = app(OrderStateMachine::class);

        $sm->transition($order, 'paid', null, 'test pay');
        $sm->transition($order, 'searching_provider');

        $this->assertDatabaseCount('order_status_history', 2);

        $first = \App\Models\OrderStatusHistory::query()->orderBy('id')->first();
        $this->assertSame('pending_payment', $first->from_status);
        $this->assertSame('paid', $first->to_status);
    }
}
