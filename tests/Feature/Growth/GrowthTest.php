<?php

namespace Tests\Feature\Growth;

use App\Domain\Growth\PromotionService;
use App\Domain\Growth\RecurringService;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\RecurringOccurrence;
use App\Models\RecurringSchedule;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GrowthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(CatalogSeeder::class);
    }

    private function makeService(): Service
    {
        $partner = \App\Models\Partner::create([
            'user_id' => User::factory()->create()->id, 'type' => 'individual',
            'display_name' => 'P', 'slug' => 'p-'.uniqid(), 'verification_state' => 'verified',
        ]);

        return Service::create([
            'partner_id' => $partner->id,
            'category_id' => \App\Models\Category::first()->id,
            'title' => 'Servis T', 'slug' => 'servis-t-'.uniqid(),
            'fulfillment_type' => 'appointment', 'delivery_mode' => 'onsite',
            'price_model' => 'fixed', 'base_price' => 100000, 'status' => 'active',
        ]);
    }

    private function makeOrder(User $user, int $subtotal = 200000): Order
    {
        $service = Service::first() ?? $this->makeService();

        return Order::factory()->create([
            'user_id' => $user->id,
            'partner_id' => $service->partner_id,
            'service_id' => $service->id,
            'status' => 'pending_payment',
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'fulfillment_type' => 'per_unit',
        ]);
    }

    public function test_voucher_percent_discount_with_cap(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 200000);

        $promo = Promotion::create([
            'name' => 'Diskon 10%', 'code' => 'HEMAT10', 'type' => 'discount',
            'value' => 10, 'value_unit' => 'percent', 'max_discount' => 15000,
            'min_spend' => 100000, 'funding' => 'platform', 'per_user_limit' => 1,
            'status' => 'active',
        ]);

        $svc = app(PromotionService::class);
        $discount = $svc->apply($promo, $user, $order);

        // 10% of 200000 = 20000 → capped at 15000
        $this->assertSame(15000, $discount);
        $this->assertSame(185000, $order->fresh()->total);

        // Second application same order+user → user limit reached
        $order2 = $this->makeOrder($user);
        try {
            $svc->apply($promo, $user, $order2);
            $this->fail('should throw');
        } catch (\App\Domain\Auth\DomainException $e) {
            $this->assertSame('USER_LIMIT', $e->errorCode());
        }
    }

    public function test_voucher_min_spend_and_expiry(): void
    {
        $user = User::factory()->create();
        $order = $this->makeOrder($user, 50000);

        $promo = Promotion::create([
            'name' => 'Min spend', 'code' => 'MIN', 'type' => 'discount',
            'value' => 5000, 'value_unit' => 'idr', 'min_spend' => 100000,
            'status' => 'active',
        ]);

        try {
            app(PromotionService::class)->validate($promo, $user, $order);
            $this->fail('should throw');
        } catch (\App\Domain\Auth\DomainException $e) {
            $this->assertSame('MIN_SPEND', $e->errorCode());
        }

        $promo->update(['ends_at' => now()->subDay()]);
        try {
            app(PromotionService::class)->validate($promo, $user, $order);
            $this->fail('should throw');
        } catch (\App\Domain\Auth\DomainException $e) {
            $this->assertSame('PROMO_EXPIRED', $e->errorCode());
        }
    }

    public function test_recurring_generation_idempotent_and_materializes(): void
    {
        $user = User::factory()->create();
        $service = $this->makeService();
        $service->update(['fulfillment_type' => 'appointment', 'duration_minutes' => 60]);
        $partner = $service->partner;

        foreach (range(0, 6) as $day) {
            \App\Models\PartnerSchedule::create(['partner_id' => $partner->id, 'day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '17:00']);
        }

        $schedule = RecurringSchedule::create([
            'user_id' => $user->id, 'service_id' => $service->id,
            'frequency' => 'weekly', 'day_of_week' => 1,
            'starts_on' => \Carbon\Carbon::today()->toDateString(), 'status' => 'active',
            'preferred_time' => '09:00',
        ]);

        $svc = app(RecurringService::class);

        // generate twice — idempotent
        $first = $svc->generateOccurrences();
        $second = $svc->generateOccurrences();
        $this->assertSame(0, $second);
        $this->assertGreaterThan(0, $first);

        $count = RecurringOccurrence::where('schedule_id', $schedule->id)->count();
        $this->assertSame($first, $count);

        // materialize upcoming (within 2 days → today if Monday, else none; generate ensures at least next Monday)
        $materialized = $svc->materializeDueOccurrences();

        // every materialized occurrence has an order
        RecurringOccurrence::where('status', 'ordered')->get()->each(function ($occ) {
            $this->assertNotNull($occ->order_id);
            $this->assertSame('pending_payment', Order::find($occ->order_id)->status);
        });
    }

    public function test_referral_code_deterministic_and_self_referral_blocked(): void
    {
        $svc = app(\App\Domain\Growth\ReferralService::class);
        $u1 = User::factory()->create();

        $code = $svc->code($u1);
        $this->assertSame($code, $svc->code($u1));

        $u2 = User::factory()->create();
        $result = $svc->attach($u2, $svc->code($u2)); // self
        $this->assertNull($result);

        $ref = $svc->attach($u2, $code);
        $this->assertNotNull($ref);
        $this->assertSame('invited', $ref->status);
    }
}
