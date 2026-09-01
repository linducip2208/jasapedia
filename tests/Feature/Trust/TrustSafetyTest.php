<?php

namespace Tests\Feature\Trust;

use App\Domain\Dispatch\DispatchService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trust & Safety E2E: review → dispute (refund via ledger) → warranty.
 * Builds on the home-service flow.
 */
class TrustSafetyTest extends TestCase
{
    use RefreshDatabase;

    private string $customerToken;
    private string $partnerToken;
    private int $orderId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\LocationSeeder::class);
        $this->seed(CatalogSeeder::class);
    }

    /** Complete a home-service order up to completed/settled. */
    private function completeOrder(bool $settle = true): void
    {
        $customer = $this->postJson('/api/v1/auth/register', [
            'name' => 'C', 'email' => 'c@test.id', 'password' => 'RahasiaKuat99',
        ]);
        $this->customerToken = $customer->json('data.token');

        $partner = $this->postJson('/api/v1/auth/register', [
            'name' => 'P', 'email' => 'p@test.id', 'password' => 'RahasiaKuat99',
        ]);
        $this->partnerToken = $partner->json('data.token');

        $this->withToken($this->partnerToken)->postJson('/api/v1/partner', [
            'type' => 'individual', 'display_name' => 'Tech', 'city' => 'Jakarta',
        ]);
        \App\Models\Partner::query()->update(['verification_state' => 'verified', 'rating_avg' => 4.5, 'completed_jobs' => 30]);

        foreach (range(0, 6) as $day) {
            \App\Models\PartnerSchedule::create([
                'partner_id' => \App\Models\Partner::first()->id,
                'day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '17:00',
            ]);
        }

        $catId = \App\Models\Category::where('slug', 'ac-electronics')->value('id');
        $this->withToken($this->partnerToken)->postJson('/api/v1/partner/services', [
            'category_id' => $catId, 'title' => 'Cuci AC',
            'fulfillment_type' => 'per_unit', 'delivery_mode' => 'onsite',
            'price_model' => 'per_unit', 'base_price' => 100000, 'unit_label' => 'unit',
            'duration_minutes' => 60, 'warranty_days' => 7,
        ]);
        $serviceId = \App\Models\Service::first()->id;

        $order = $this->withToken($this->customerToken)->postJson('/api/v1/orders', [
            'service_id' => $serviceId,
            'scheduled_at' => \Carbon\Carbon::parse('next monday 09:00')->toIso8601String(),
        ]);
        $this->orderId = $order->json('data.order.id');

        $this->postJson('/api/v1/payments/sandbox/pay', ['order_code' => $order->json('data.order.code')])->assertOk();

        // dispatch + accept + drive to completion
        app(DispatchService::class)->dispatch(\App\Models\Order::find($this->orderId));
        $assignmentId = \App\Models\Assignment::where('order_id', $this->orderId)->first()->id;
        $this->withToken($this->partnerToken)->postJson("/api/v1/field/assignments/{$assignmentId}/accept");
        $this->withToken($this->partnerToken)->postJson("/api/v1/field/orders/{$this->orderId}/on-the-way");
        $otp = $this->withToken($this->partnerToken)->postJson("/api/v1/field/orders/{$this->orderId}/arrived")->json('data.otp');
        $this->withToken($this->customerToken)->postJson("/api/v1/orders/{$this->orderId}/checkin", ['otp' => $otp]);
        $this->withToken($this->partnerToken)->postJson("/api/v1/field/orders/{$this->orderId}/start-work");
        $this->withToken($this->partnerToken)->postJson("/api/v1/field/orders/{$this->orderId}/evidence", [
            'stage' => 'after', 'file_path' => 'a/1.jpg',
        ]);
        $this->withToken($this->partnerToken)->postJson("/api/v1/field/orders/{$this->orderId}/submit-completion");
        $this->withToken($this->customerToken)->postJson("/api/v1/orders/{$this->orderId}/confirm");

        if ($settle) {
            $orderRow = \App\Models\Order::find($this->orderId);
            $settlement = app(\App\Domain\Finance\SettlementService::class)->createFor($orderRow);
            app(\App\Domain\Finance\SettlementService::class)->process($settlement);
        }
    }

    public function test_review_only_once_and_rating_recomputed(): void
    {
        $this->completeOrder(settle: false);

        $payload = [
            'overall' => 5,
            'dimension_ratings' => ['quality' => 5, 'punctuality' => 5, 'communication' => 4, 'professionalism' => 5, 'value' => 4],
            'comment' => 'Kerja rapi dan tepat waktu.',
        ];

        $res = $this->withToken($this->customerToken)->postJson("/api/v1/orders/{$this->orderId}/review", $payload);
        $res->assertCreated()->assertJsonPath('data.review.overall', 5);

        // Partner rating recomputed
        $partner = \App\Models\Partner::first();
        $this->assertSame(1, $partner->rating_count);
        $this->assertEquals(5.0, $partner->rating_avg);

        // Duplicate blocked
        $this->withToken($this->customerToken)->postJson("/api/v1/orders/{$this->orderId}/review", $payload)
            ->assertStatus(409)->assertJsonPath('error.code', 'ALREADY_REVIEWED');

        // Missing dimension blocked (per category config)
        $order2 = $this->completeSecondOrder();
        $this->withToken($this->customerToken)->postJson("/api/v1/orders/{$order2}/review", [
            'overall' => 4, 'dimension_ratings' => ['quality' => 4],
        ])->assertStatus(422);

        // Partner responds
        $reviewId = \App\Models\Review::first()->id;
        $this->withToken($this->partnerToken)->postJson("/api/v1/reviews/{$reviewId}/respond", [
            'response' => 'Terima kasih!',
        ])->assertOk();
    }

    public function test_dispute_full_refund_via_ledger(): void
    {
        $this->completeOrder(settle: true);

        // Open dispute (settled order can be disputed)
        $dispute = $this->withToken($this->customerToken)->postJson("/api/v1/disputes/orders/{$this->orderId}", [
            'reason' => 'AC tidak dingin setelah 2 hari',
            'description' => 'Sudah dibersihkan tapi tetap tidak dingin.',
        ]);
        $dispute->assertCreated()->assertJsonPath('data.dispute.status', 'opened');
        $disputeId = $dispute->json('data.dispute.id');

        $this->assertSame('disputed', \App\Models\Order::find($this->orderId)->status);

        // Evidence
        $this->withToken($this->customerToken)->postJson("/api/v1/disputes/{$disputeId}/evidence", [
            'kind' => 'photo', 'file_path' => 'disputes/1/thermometer.jpg', 'note' => 'Suhu 30C',
        ])->assertCreated();

        // Customer cannot resolve
        $this->withToken($this->customerToken)->postJson('/api/v1/admin/disputes', [
            'dispute_id' => $disputeId, 'resolution' => 'full_refund', 'note' => 'self-serve',
        ])->assertStatus(403);

        // Officer resolves full refund
        $officer = $this->officer();
        $this->actingAs($officer, 'sanctum')->postJson('/api/v1/admin/disputes', [
            'dispute_id' => $disputeId, 'resolution' => 'full_refund', 'note' => 'Klaim terbukti dari bukti suhu',
        ])->assertOk()->assertJsonPath('data.dispute.status', 'resolved');

        // Order refunded, ledger balanced
        $this->assertSame('refunded', \App\Models\Order::find($this->orderId)->status);
        $this->assertTrue(app(\App\Domain\Ledger\LedgerService::class)->ledgerIsBalanced());
        $this->assertDatabaseHas('audit_logs', ['action' => 'dispute.resolved']);
    }

    public function test_warranty_claim_window(): void
    {
        $this->completeOrder(settle: false);

        // In window (warranty 7 days)
        $claim = $this->withToken($this->customerToken)->postJson("/api/v1/orders/{$this->orderId}/warranty-claims", [
            'issue' => 'AC bocor lagi',
        ]);
        $claim->assertCreated()->assertJsonPath('data.warranty_claim.status', 'submitted');
    }

    private function completeSecondOrder(): int
    {
        // quick second completed order via factory path
        $order = \App\Models\Order::factory()->create([
            'user_id' => \App\Models\User::where('email', 'c@test.id')->first()->id,
            'partner_id' => \App\Models\Partner::first()->id,
            'service_id' => \App\Models\Service::first()->id,
            'status' => 'completed',
            'completed_at' => now(),
            'fulfillment_type' => 'per_unit',
        ]);

        return $order->id;
    }

    private function officer()
    {
        $officer = \App\Models\User::factory()->create(['email' => 'officer@test.id']);
        $officer->roles()->attach(\App\Models\Role::where('name', 'DisputeOfficer')->value('id'));

        return $officer;
    }
}
