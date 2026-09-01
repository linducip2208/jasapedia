<?php

namespace Tests\Feature\Payment;

use App\Models\PaymentWebhookEvent;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $customerToken;
    private int $orderId;
    private string $orderCode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LocationSeeder::class);
        $this->seed(CatalogSeeder::class);

        $customer = $this->postJson('/api/v1/auth/register', [
            'name' => 'C', 'email' => 'c@c.test', 'password' => 'RahasiaKuat99',
        ]);
        $this->customerToken = $customer->json('data.token');

        $partner = $this->postJson('/api/v1/auth/register', [
            'name' => 'P', 'email' => 'p@p.test', 'password' => 'RahasiaKuat99',
        ]);
        $this->withToken($partner->json('data.token'))->postJson('/api/v1/partner', [
            'type' => 'individual', 'display_name' => 'Tech',
        ]);
        \App\Models\Partner::query()->update(['verification_state' => 'verified']);

        $catId = \App\Models\Category::where('slug', 'handyman')->value('id');
        $this->withToken($partner->json('data.token'))->postJson('/api/v1/partner/services', [
            'category_id' => $catId,
            'title' => 'Servis Ringan',
            'fulfillment_type' => 'appointment',
            'delivery_mode' => 'onsite',
            'price_model' => 'fixed',
            'base_price' => 150000,
            'duration_minutes' => 90,
        ]);
        $serviceId = \App\Models\Service::first()->id;

        // Partner schedule all week 08:00-17:00
        foreach (range(0, 6) as $day) {
            \App\Models\PartnerSchedule::create([
                'partner_id' => \App\Models\Partner::first()->id,
                'day_of_week' => $day,
                'start_time' => '08:00',
                'end_time' => '17:00',
            ]);
        }

        $order = $this->withToken($this->customerToken)->postJson('/api/v1/orders', [
            'service_id' => $serviceId,
            'scheduled_at' => \Carbon\Carbon::parse('next monday 13:00')->toIso8601String(),
        ]);
        $this->orderId = $order->json('data.order.id');
        $this->orderCode = $order->json('data.order.code');
    }

    public function test_intent_then_webhook_pays_order(): void
    {
        $intent = $this->withToken($this->customerToken)->postJson('/api/v1/payments/intent', [
            'order_id' => $this->orderId,
        ]);

        $intent->assertOk()->assertJsonPath('data.payment.status', 'pending');
        $ref = $intent->json('data.payment.gateway_ref');

        // Webhook pays
        $webhook = $this->postJson('/api/v1/payments/webhook/sandbox', [
            'order_code' => $this->orderCode,
            'gateway_ref' => $ref,
            'amount' => 150000,
            'status' => 'paid',
            'event_id' => 'EVT-001',
        ], ['X-Sandbox-Signature' => hash_hmac('sha256', $this->orderCode, 'sandbox-secret')]);

        $webhook->assertStatus(200);

        $this->assertDatabaseHas('orders', ['id' => $this->orderId, 'status' => 'searching_provider']);
        $this->assertNotNull(\App\Models\Order::find($this->orderId)->paid_at);
        $this->assertDatabaseHas('payment_transactions', ['gateway_ref' => $ref, 'status' => 'paid']);
    }

    public function test_duplicate_webhook_is_idempotent(): void
    {
        $intent = $this->withToken($this->customerToken)->postJson('/api/v1/payments/intent', ['order_id' => $this->orderId]);
        $ref = $intent->json('data.payment.gateway_ref');

        $headers = ['X-Sandbox-Signature' => hash_hmac('sha256', $this->orderCode, 'sandbox-secret')];
        $payload = ['order_code' => $this->orderCode, 'gateway_ref' => $ref, 'amount' => 150000, 'status' => 'paid', 'event_id' => 'EVT-DUP'];

        $this->postJson('/api/v1/payments/webhook/sandbox', $payload, $headers)->assertStatus(200);
        $this->postJson('/api/v1/payments/webhook/sandbox', $payload, $headers)->assertStatus(200);
        $this->postJson('/api/v1/payments/webhook/sandbox', $payload, $headers)->assertStatus(200);

        // Only ONE webhook event row and ONE paid transaction
        $this->assertDatabaseCount('payment_webhook_events', 1);
        $this->assertDatabaseHas('payment_transactions', ['gateway_ref' => $ref, 'status' => 'paid']);
        $this->assertDatabaseHas('orders', ['id' => $this->orderId, 'status' => 'searching_provider']);
    }

    public function test_bad_signature_rejected(): void
    {
        $intent = $this->withToken($this->customerToken)->postJson('/api/v1/payments/intent', ['order_id' => $this->orderId]);
        $ref = $intent->json('data.payment.gateway_ref');

        $this->postJson('/api/v1/payments/webhook/sandbox', [
            'order_code' => $this->orderCode, 'gateway_ref' => $ref, 'status' => 'paid', 'event_id' => 'EVT-X',
        ], ['X-Sandbox-Signature' => 'deadbeef'])->assertStatus(401);

        $this->assertDatabaseMissing('payment_webhook_events', ['event_id' => 'EVT-X']);
    }

    public function test_amount_mismatch_rejected(): void
    {
        $intent = $this->withToken($this->customerToken)->postJson('/api/v1/payments/intent', ['order_id' => $this->orderId]);
        $ref = $intent->json('data.payment.gateway_ref');

        $this->postJson('/api/v1/payments/webhook/sandbox', [
            'order_code' => $this->orderCode, 'gateway_ref' => $ref, 'amount' => 999, 'status' => 'paid', 'event_id' => 'EVT-Y',
        ], ['X-Sandbox-Signature' => hash_hmac('sha256', $this->orderCode, 'sandbox-secret')]);

        $this->assertDatabaseHas('payment_webhook_events', ['event_id' => 'EVT-Y', 'status' => 'failed']);
        $this->assertDatabaseHas('payment_transactions', ['gateway_ref' => $ref, 'status' => 'pending']);
    }

    public function test_sandbox_pay_endpoint_e2e(): void
    {
        $res = $this->postJson('/api/v1/payments/sandbox/pay', ['order_code' => $this->orderCode]);
        $res->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $this->orderId, 'status' => 'searching_provider']);
    }

    public function test_cannot_repay_settled_order(): void
    {
        $this->postJson('/api/v1/payments/sandbox/pay', ['order_code' => $this->orderCode])->assertOk();

        $this->withToken($this->customerToken)->postJson('/api/v1/payments/intent', ['order_id' => $this->orderId])
            ->assertStatus(409)->assertJsonPath('error.code', 'INVALID_ORDER_STATE');
    }
}
