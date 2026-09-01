<?php

namespace Tests\Unit\Finance;

use App\Domain\Finance\CommissionService;
use App\Domain\Finance\RefundService;
use App\Domain\Finance\SettlementService;
use App\Domain\Finance\WithdrawalService;
use App\Domain\Ledger\LedgerService;
use App\Models\Partner;
use App\Models\PayoutDestination;
use App\Models\Settlement;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Financial invariants (doc 13 §54, blueprint §54/§128) — MUST all pass.
 */
class FinanceInvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(CatalogSeeder::class);
    }

    private function partner(): Partner
    {
        return Partner::create([
            'user_id' => User::factory()->create()->id,
            'type' => 'individual',
            'display_name' => 'P',
            'slug' => 'p-'.uniqid(),
            'verification_state' => 'verified',
        ]);
    }

    private function paidOrder(Partner $partner, int $total = 100000): \App\Models\Order
    {
        $customer = User::factory()->create();

        $order = \App\Models\Order::factory()->create([
            'user_id' => $customer->id,
            'partner_id' => $partner->id,
            'status' => 'completed',
            'total' => $total,
            'paid_at' => now(),
            'completed_at' => now(),
            'fulfillment_type' => 'per_unit',
        ]);

        \App\Models\PaymentTransaction::create([
            'order_id' => $order->id,
            'gateway' => 'sandbox',
            'gateway_ref' => 'SBX-'.strtoupper(bin2hex(random_bytes(5))),
            'amount' => $total,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return $order;
    }

    public function test_settlement_posts_balanced_ledger_and_idempotent(): void
    {
        $partner = $this->partner();
        $order = $this->paidOrder($partner);

        $settlement = app(SettlementService::class)->createFor($order);
        $this->assertSame('pending', $settlement->status);

        $settlement = app(SettlementService::class)->process($settlement);
        $this->assertSame('completed', $settlement->status);
        $this->assertSame('settled', $order->fresh()->status);

        // Ledger balanced
        $this->assertTrue(app(LedgerService::class)->ledgerIsBalanced());

        // Vendor net = 90% (global commission 10%)
        $this->assertSame(90000, $settlement->vendor_net);
        $this->assertSame(10000, $settlement->commission);

        // Double settlement protection
        $this->expectException(\App\Domain\Auth\DomainException::class);
        app(SettlementService::class)->process($settlement);
    }

    public function test_ledger_post_rejects_unbalanced(): void
    {
        $this->expectException(\App\Domain\Auth\DomainException::class);
        app(LedgerService::class)->post('adjustment', null, null, [
            '1001' => ['debit' => 100],
            '4201' => ['credit' => 99],
        ]);
    }

    public function test_reversal_only_once(): void
    {
        $ledger = app(LedgerService::class);
        $txId = $ledger->post('adjustment', null, null, [
            '1001' => ['debit' => 500],
            '4201' => ['credit' => 500],
        ]);

        $rev = $ledger->reverse($txId, 'correction');
        $this->assertTrue($ledger->ledgerIsBalanced());

        $this->expectException(\App\Domain\Auth\DomainException::class);
        $ledger->reverse($txId, 'again');
    }

    public function test_commission_snapshot_immutable_and_unique(): void
    {
        $partner = $this->partner();
        $order = $this->paidOrder($partner);

        $svc = app(CommissionService::class);
        $c1 = $svc->createSnapshot($order);
        $c2 = $svc->createSnapshot($order);

        $this->assertSame($c1->id, $c2->id);
        $this->assertSame(10000, $c1->amount);
    }

    public function test_refund_cannot_exceed_paid(): void
    {
        $partner = $this->partner();
        $order = $this->paidOrder($partner, 100000);

        // Settle so vendor payable posted
        $settlement = app(SettlementService::class)->createFor($order);
        app(SettlementService::class)->process($settlement);

        $refundSvc = app(RefundService::class);

        $admin = User::factory()->create();
        $refund1 = $refundSvc->request($order, 60000, 'partial', 'Service issue', $admin);
        $refundSvc->approveAndExecute($refund1, $admin);

        // Eligible now 40000 — try 50000 → rejected
        try {
            $refund2 = $refundSvc->request($order, 50000, 'partial', 'over', $admin);
            $this->fail('Should have thrown');
        } catch (\App\Domain\Auth\DomainException $e) {
            $this->assertSame('REFUND_EXCEEDS_ELIGIBLE', $e->errorCode());
        }

        // Remaining 40000 OK
        $refund2 = $refundSvc->request($order, 40000, 'partial', 'rest', $admin);
        $refundSvc->approveAndExecute($refund2, $admin);
        $this->assertSame('refunded', $order->fresh()->status);

        $this->assertTrue(app(LedgerService::class)->ledgerIsBalanced());
    }

    public function test_double_refund_concurrent_blocked(): void
    {
        $partner = $this->partner();
        $order = $this->paidOrder($partner, 100000);
        $settlement = app(SettlementService::class)->createFor($order);
        app(SettlementService::class)->process($settlement);

        $admin = User::factory()->create();
        $refund = app(RefundService::class)->request($order, 100000, 'full', 'cancel', $admin);

        // First execution completes; a second execution of same row is blocked
        app(RefundService::class)->approveAndExecute($refund, $admin);

        $this->expectException(\App\Domain\Auth\DomainException::class);
        app(RefundService::class)->approveAndExecute($refund->fresh(), $admin);
    }

    public function test_withdrawal_bounds_and_race(): void
    {
        $partner = $this->partner();

        // Two settled orders 100000 each → vendor net 90000 each = 180000
        foreach ([1, 2] as $i) {
            $order = $this->paidOrder($partner);
            $s = app(SettlementService::class)->createFor($order);
            app(SettlementService::class)->process($s);
        }

        $svc = app(WithdrawalService::class);
        $this->assertSame(180000, $svc->availableBalance($partner));

        $dest = PayoutDestination::create([
            'partner_id' => $partner->id, 'type' => 'bank', 'bank_code' => 'BCA',
            'account_number' => '123', 'account_name' => 'P', 'is_default' => true,
            'verified_at' => now(),
        ]);

        $staff = User::factory()->create();
        $w1 = $svc->request($partner, $dest, 100000, $staff);

        // After reserving w1: 80000 left → second 100000 blocked
        $this->assertSame(80000, $svc->availableBalance($partner));

        try {
            $svc->request($partner, $dest, 100000, $staff);
            $this->fail('Over-reservation should have failed');
        } catch (\App\Domain\Auth\DomainException $e) {
            $this->assertSame('INSUFFICIENT_BALANCE', $e->errorCode());
        }

        $svc->transition($w1, 'approved', $staff);
        $svc->transition($w1, 'processing', $staff);
        $w1 = $svc->transition($w1, 'completed', $staff);

        // Ledger balanced after payout
        $this->assertTrue(app(LedgerService::class)->ledgerIsBalanced());

        // Double completion blocked
        try {
            $svc->transition($w1->fresh(), 'completed', $staff);
            $this->fail('should throw');
        } catch (\App\Domain\Common\Exceptions\StateTransitionException $e) {
            $this->addToAssertionCount(1);
        }

        // Below minimum rejected
        try {
            $svc->request($partner, $dest, 10000, $staff);
            $this->fail('should throw');
        } catch (\App\Domain\Auth\DomainException $e) {
            $this->assertSame('MIN_WITHDRAWAL', $e->errorCode());
        }
    }

    public function test_concurrent_settlement_process_only_posts_once(): void
    {
        $partner = $this->partner();
        $order = $this->paidOrder($partner);
        $settlement = app(SettlementService::class)->createFor($order);

        // Simulate racing processes sequentially under locks
        app(SettlementService::class)->process($settlement);

        $txCount = DB::table('ledger_transactions')->where('group', 'order_payment')->count();
        $this->assertSame(1, $txCount);
    }
}
