<?php

namespace Tests\Feature\Finance;

use App\Domain\Finance\ReconciliationService;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function paidOrder(): \App\Models\Order
    {
        $order = \App\Models\Order::factory()->create([
            'user_id' => User::factory()->create()->id,
            'partner_id' => Partner::create([
                'user_id' => User::factory()->create()->id, 'type' => 'individual',
                'display_name' => 'P', 'slug' => 'p-'.uniqid(), 'verification_state' => 'verified',
            ])->id,
            'status' => 'pending_payment',
            'total' => 100000,
            'fulfillment_type' => 'per_unit',
        ]);

        return $order;
    }

    public function test_detects_paid_order_stuck(): void
    {
        $order = $this->paidOrder();

        // Mark tx paid but leave order in pending_payment (simulated crash)
        \App\Models\PaymentTransaction::create([
            'order_id' => $order->id, 'gateway' => 'sandbox',
            'gateway_ref' => 'SBX-TEST', 'amount' => 100000, 'status' => 'paid', 'paid_at' => now(),
        ]);

        $result = app(ReconciliationService::class)->reconcilePayments(24);

        $types = array_column($result['discrepancies'], 'type');
        $this->assertContains('paid_order_stuck', $types);
    }

    public function test_detects_stale_pending_intent(): void
    {
        $order = $this->paidOrder();
        \App\Models\PaymentTransaction::create([
            'order_id' => $order->id, 'gateway' => 'sandbox',
            'gateway_ref' => 'SBX-STALE', 'amount' => 100000, 'status' => 'pending',
        ]);
        DB::table('payment_transactions')->where('order_id', $order->id)->update(['created_at' => now()->subHours(30)]);

        $result = app(ReconciliationService::class)->reconcilePayments(24);
        $types = array_column($result['discrepancies'], 'type');
        $this->assertContains('stale_pending_intent', $types);
    }

    public function test_settlement_without_posting_detected(): void
    {
        $partner = Partner::create([
            'user_id' => User::factory()->create()->id, 'type' => 'individual',
            'display_name' => 'P', 'slug' => 'p-'.uniqid(), 'verification_state' => 'verified',
        ]);
        $order = $this->paidOrder();
        $order->update(['partner_id' => $partner->id]);

        // completed settlement with NO ledger posting
        \App\Models\Settlement::create([
            'order_id' => $order->id, 'partner_id' => $partner->id,
            'gross' => 100000, 'commission' => 10000, 'vendor_net' => 90000,
            'status' => 'completed', 'processed_at' => now(),
        ]);

        $result = app(ReconciliationService::class)->reconcilePayouts();
        $types = array_column($result['discrepancies'], 'type');
        $this->assertContains('settlement_without_posting', $types);
    }

    public function test_clean_ledger_reports_no_payout_discrepancies(): void
    {
        $result = app(ReconciliationService::class)->reconcilePayouts();
        $this->assertCount(0, $result['discrepancies']);
    }
}
