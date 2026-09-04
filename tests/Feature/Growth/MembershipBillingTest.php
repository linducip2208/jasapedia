<?php

namespace Tests\Feature\Growth;

use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Membership billing cycle (Phase 37 completion):
 * subscribe → invoice order (TYPE_MEMBERSHIP) → paid via sandbox pipeline →
 * active membership window; renew extends; cancel + expiry close the window.
 */
class MembershipBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private MembershipPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(CatalogSeeder::class);

        $this->user = User::factory()->create();
        $this->plan = MembershipPlan::create([
            'name' => 'jasapedia_plus_test',
            'audience' => 'customer',
            'price_monthly' => 49000,
            'price_yearly' => 499000,
            'benefits' => ['discount' => 5, 'priority_dispatch' => true],
            'is_active' => true,
        ]);
    }

    private function register(): string
    {
        $res = $this->postJson('/api/v1/auth/register', [
            'name' => 'Member Test',
            'email' => 'member-'.uniqid().'@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        return $res->json('data.token');
    }

    public function test_subscribe_creates_paid_invoice_and_active_membership(): void
    {
        $token = $this->register();

        $res = $this->withToken($token)->postJson('/api/v1/membership/subscribe', [
            'plan_id' => $this->plan->id,
            'cycle' => 'monthly',
        ]);

        $res->assertCreated();
        $order = Order::findOrFail($res->json('data.order.id'));
        $this->assertSame(Order::TYPE_MEMBERSHIP, $order->type);
        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(49000, (int) $order->total);

        // Outside production the sandbox pipeline auto-pays the invoice
        $membership = Membership::where('member_id', $this->user->id)->first();
        $this->assertNull($membership, 'Membership must belong to the subscribing user, not another one.');

        $mine = Membership::where('member_id', $order->user_id)->where('status', 'active')->first();
        $this->assertNotNull($mine, 'Paid invoice must activate membership.');
        $this->assertTrue($mine->starts_at->isToday() || $mine->starts_at->lessThan(now()));
        $this->assertTrue($mine->ends_at->greaterThan(now()));
    }

    public function test_membership_orders_skip_settlement_pipeline(): void
    {
        $token = $this->register();

        $res = $this->withToken($token)->postJson('/api/v1/membership/subscribe', [
            'plan_id' => $this->plan->id,
            'cycle' => 'monthly',
        ]);
        $res->assertCreated();

        $orderId = $res->json('data.order.id');

        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('commissions')->where('order_id', $orderId)->count());
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('settlements')->where('order_id', $orderId)->count());
    }

    public function test_yearly_cycle_extends_twelve_months(): void
    {
        $token = $this->register();

        $res = $this->withToken($token)->postJson('/api/v1/membership/subscribe', [
            'plan_id' => $this->plan->id,
            'cycle' => 'yearly',
        ]);
        $res->assertCreated();

        $mine = Membership::where('member_id', $res->json('data.order.user_id'))->where('status', 'active')->first();
        $this->assertNotNull($mine);
        $this->assertTrue((float) $mine->starts_at->diffInDays($mine->ends_at) >= 360);
    }

    public function test_renew_extends_from_existing_window(): void
    {
        $token = $this->register();

        $this->withToken($token)->postJson('/api/v1/membership/subscribe', [
            'plan_id' => $this->plan->id,
            'cycle' => 'monthly',
        ]);

        $first = Membership::where('member_type', User::class)->orderBy('id')->first();

        $res = $this->withToken($token)->postJson('/api/v1/membership/renew', [
            'plan_id' => $this->plan->id,
            'cycle' => 'monthly',
        ]);
        $res->assertCreated();

        $rows = Membership::where('member_type', User::class)->where('status', 'active')->orderBy('id')->get();
        $this->assertGreaterThanOrEqual(1, $rows->count());

        $latest = $rows->last();
        $this->assertTrue(
            abs($latest->starts_at->getTimestamp() - $first->ends_at->getTimestamp()) <= 1,
            'Renewal must start from the existing window end ('.$latest->starts_at.' vs '.$first->ends_at.')',
        );
    }

    public function test_cancel_and_expiry_close_membership(): void
    {
        $token = $this->register();

        $this->withToken($token)->postJson('/api/v1/membership/subscribe', [
            'plan_id' => $this->plan->id,
            'cycle' => 'monthly',
        ]);

        $res = $this->withToken($token)->postJson('/api/v1/membership/cancel');
        $res->assertOk();
        $this->assertTrue($res->json('data.cancelled'));

        $this->assertSame(0, Membership::where('member_type', User::class)->where('status', 'active')->count());

        // Expiry path: force a window into the past, run the sweeper
        $membership = Membership::create([
            'plan_id' => $this->plan->id,
            'member_type' => User::class,
            'member_id' => $this->user->id,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(),
            'status' => 'active',
        ]);

        app(\App\Domain\Growth\MembershipService::class)->expireOverdue();

        $this->assertSame('expired', $membership->fresh()->status);
    }

    public function test_inactive_plan_and_invalid_cycle_rejected(): void
    {
        $token = $this->register();

        $this->plan->update(['is_active' => false]);

        $this->withToken($token)->postJson('/api/v1/membership/subscribe', [
            'plan_id' => $this->plan->id,
            'cycle' => 'monthly',
        ])->assertStatus(409);

        $this->plan->update(['is_active' => true]);

        $this->withToken($token)->postJson('/api/v1/membership/subscribe', [
            'plan_id' => $this->plan->id,
            'cycle' => 'weekly',
        ])->assertStatus(422);
    }
}
