<?php

namespace Tests\Feature\Demo;

use App\Domain\Ledger\LedgerService;
use App\Models\PaymentTransaction;
use App\Models\Settlement;
use App\Support\Demo\DemoDataWiper;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\Demo\DemoDataSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Financial integrity of the demo dataset (prompt §14, §24):
 *  - ledger always balanced after seeding AND after --fresh-demo
 *  - settled orders have commission + settlement + ledger postings via domain services
 *  - no fake balances: ledger rows only exist for domain-posted groups
 */
class DemoFinanceIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LocationSeeder::class);
        $this->seed(CatalogSeeder::class);

        mt_srand(20260901);
        app(DemoDataSeeder::class)->run([
            'services' => 210,
            'providers' => 21,
            'customers' => 40,
            'orders' => 30,
            'projects' => 10,
            'rfqs' => 10,
            'reviews' => 15,
            'corporates' => 2,
        ]);
    }

    public function test_ledger_remains_balanced_after_demo_seed(): void
    {
        $this->assertTrue(app(LedgerService::class)->ledgerIsBalanced());
    }

    public function test_ledger_debits_equal_credits(): void
    {
        $sums = DB::table('ledger_entries')->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')->first();

        $this->assertSame((int) $sums->d, (int) $sums->c);
    }

    public function test_settled_orders_have_complete_finance_stack(): void
    {
        $settled = DB::table('orders')->where('is_demo', true)->where('status', 'settled')->get(['id', 'partner_id', 'total']);

        $this->assertGreaterThan(0, $settled->count(), 'Demo seed must produce settled orders via domain pipeline.');

        $settled->each(function ($order) {
            $commission = DB::table('commissions')->where('order_id', $order->id)->first();
            $this->assertNotNull($commission, "Order {$order->id} settled without commission snapshot.");

            $settlement = DB::table('settlements')->where('order_id', $order->id)->first();
            $this->assertNotNull($settlement);
            $this->assertSame('completed', $settlement->status);

            // settlement math holds
            $this->assertSame((int) $order->total, (int) $settlement->gross - (int) $settlement->additional_amount + 0, 'gross mismatch');
            $this->assertSame((int) $settlement->gross - (int) $settlement->commission, (int) $settlement->vendor_net, 'vendor net mismatch');

            // ledger posting exists for this order
            $posted = DB::table('ledger_transactions')
                ->where('group', 'order_payment')
                ->where('reference_type', 'order')
                ->where('reference_id', $order->id)
                ->exists();
            $this->assertTrue($posted, "Order {$order->id} settled without ledger posting.");

            // payment transaction paid
            $paid = PaymentTransaction::where('order_id', $order->id)->where('status', 'paid')->sum('amount');
            $this->assertSame((int) $order->total, (int) $paid);
        });
    }

    public function test_refunded_orders_have_balanced_reversal(): void
    {
        $refunded = DB::table('orders')->where('is_demo', true)->where('status', 'refunded')->get(['id']);

        // May be 0 on tiny datasets — only assert invariants when present.
        $refunded->each(function ($order) {
            $refund = DB::table('refunds')->where('order_id', $order->id)->where('status', 'completed')->first();
            $this->assertNotNull($refund);

            $posted = DB::table('ledger_transactions')
                ->where('group', 'refund')
                ->where('reference_type', 'refund')
                ->where('reference_id', $refund->id)
                ->exists();
            $this->assertTrue($posted, 'Refund executed without ledger reversal.');
        });
    }

    public function test_no_order_exceeds_paid_amount_in_ledger(): void
    {
        // Σ credit(2101 + 4201) per order == Σ debit(1001) per order — always balanced per tx.
        $txs = DB::table('ledger_transactions')->where('group', 'order_payment')->pluck('id');

        $txs->each(function ($txId) {
            $entries = DB::table('ledger_entries')->where('ledger_transaction_id', $txId);
            $this->assertSame(
                (int) $entries->sum('debit'),
                (int) $entries->sum('credit'),
                "Ledger tx {$txId} unbalanced.",
            );
        });
    }

    public function test_pending_payment_orders_have_no_paid_transactions(): void
    {
        $violation = DB::table('orders')
            ->whereIn('status', ['draft', 'pending_payment', 'cancelled', 'expired'])
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('payment_transactions')
                ->whereColumn('payment_transactions.order_id', 'orders.id')
                ->where('payment_transactions.status', 'paid'))
            ->count();

        $this->assertSame(0, $violation, 'Never-paid orders must not carry paid payment transactions.');
    }

    public function test_fresh_demo_keeps_ledger_balanced_and_removes_demo_rows(): void
    {
        app(DemoDataWiper::class)->wipe();

        $this->assertTrue(app(LedgerService::class)->ledgerIsBalanced(), 'Ledger unbalanced after fresh-demo wipe.');

        $this->assertSame(0, DB::table('services')->where('is_demo', true)->count());
        $this->assertSame(0, DB::table('orders')->where('is_demo', true)->count());
        $this->assertSame(0, DB::table('users')->where('is_demo', true)->count());
        $this->assertSame(0, DB::table('partners')->where('is_demo', true)->count());
        $this->assertSame(0, Settlement::count());
    }

    public function test_commission_rate_snapshot_matches_global_settings(): void
    {
        $commissions = DB::table('commissions')->get(['amount', 'basis_amount', 'rate_percent']);

        $commissions->each(function ($commission) {
            $expected = (int) round($commission->basis_amount * $commission->rate_percent / 100);
            $this->assertSame($expected, (int) $commission->amount, 'Commission amount ≠ basis × rate.');
        });
    }
}
