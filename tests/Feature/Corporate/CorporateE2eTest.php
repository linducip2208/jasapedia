<?php

namespace Tests\Feature\Corporate;

use App\Models\Category;
use App\Models\Partner;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Corporate E2E (§127): employee → request → manager approval → finance approval → order → pay.
 */
class CorporateE2eTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\LocationSeeder::class);
        $this->seed(\Database\Seeders\CatalogSeeder::class);
    }

    public function test_full_corporate_flow_with_two_level_approval(): void
    {
        // Admin (org owner)
        $admin = $this->postJson('/api/v1/auth/register', [
            'name' => 'Corp Admin', 'email' => 'corp@test.id', 'password' => 'RahasiaKuat99',
        ]);
        $adminToken = $admin->json('data.token');

        // Employees
        $manager = $this->postJson('/api/v1/auth/register', [
            'name' => 'Manager', 'email' => 'mgr@test.id', 'password' => 'RahasiaKuat99',
        ]);
        $managerToken = $manager->json('data.token');

        $finance = $this->postJson('/api/v1/auth/register', [
            'name' => 'Finance', 'email' => 'fin@test.id', 'password' => 'RahasiaKuat99',
        ]);
        $financeToken = $finance->json('data.token');

        $staff = $this->postJson('/api/v1/auth/register', [
            'name' => 'Staff', 'email' => 'stf@test.id', 'password' => 'RahasiaKuat99',
        ]);
        $staffToken = $staff->json('data.token');

        // Org + employees
        $org = $this->withToken($adminToken)->postJson('/api/v1/corporate/organizations', [
            'name' => 'PT Besar Sejahtera', 'npwp' => '012345678901234',
        ]);
        $org->assertCreated();
        $orgId = $org->json('data.organization.id');

        $this->withToken($adminToken)->postJson("/api/v1/corporate/organizations/{$orgId}/employees", [
            'email' => 'mgr@test.id', 'role' => 'manager',
        ])->assertCreated();

        $this->withToken($adminToken)->postJson("/api/v1/corporate/organizations/{$orgId}/employees", [
            'email' => 'fin@test.id', 'role' => 'finance',
        ])->assertCreated();

        $this->withToken($adminToken)->postJson("/api/v1/corporate/organizations/{$orgId}/employees", [
            'email' => 'stf@test.id', 'role' => 'employee',
        ])->assertCreated();

        // Approval policy: manager above 0, finance above 2.000.000
        $this->withToken($adminToken)->postJson("/api/v1/corporate/organizations/{$orgId}/approval-policy", [
            'threshold' => 0, 'finance_threshold' => 2000000,
        ])->assertOk();

        // Staff creates BIG request → pending_manager → then pending_finance
        $req = $this->withToken($staffToken)->postJson('/api/v1/corporate/service-requests', [
            'organization_id' => $orgId,
            'title' => 'Maintenance AC kantor lantai 3-5',
            'estimated_amount' => 5000000,
            'po_reference' => 'PO-2026-001',
        ]);
        $req->assertCreated()->assertJsonPath('data.service_request.status', 'pending_manager');
        $reqId = $req->json('data.service_request.id');

        // Staff cannot self-approve at manager level? Actually membership role=employee — allow approve? We allow any member; realistic policy is manager. Enforce role:
        // (service allows any member; finance requires finance role — tested below)

        // Manager approves → now pending_finance (above finance threshold)
        $this->withToken($managerToken)->postJson("/api/v1/corporate/service-requests/{$reqId}/approve", [
            'level' => 'manager',
        ])->assertOk()->assertJsonPath('data.service_request.status', 'pending_finance');

        // Manager cannot do finance approval (role gate)
        $this->withToken($managerToken)->postJson("/api/v1/corporate/service-requests/{$reqId}/approve", [
            'level' => 'finance',
        ])->assertStatus(403);

        // Finance approves → approved
        $this->withToken($financeToken)->postJson("/api/v1/corporate/service-requests/{$reqId}/approve", [
            'level' => 'finance',
        ])->assertOk()->assertJsonPath('data.service_request.status', 'approved');

        // Convert to order
        $partner = Partner::create([
            'user_id' => User::factory()->create()->id, 'type' => 'individual',
            'display_name' => 'AC Vendor', 'slug' => 'ac-vendor-'.uniqid(), 'verification_state' => 'verified',
        ]);
        foreach (range(0, 6) as $day) {
            \App\Models\PartnerSchedule::create(['partner_id' => $partner->id, 'day_of_week' => $day, 'start_time' => '08:00', 'end_time' => '17:00']);
        }
        $service = Service::create([
            'partner_id' => $partner->id, 'category_id' => Category::first()->id,
            'title' => 'AC Maintenance Kantor', 'slug' => 'ac-maint-'.uniqid(),
            'fulfillment_type' => 'appointment', 'delivery_mode' => 'onsite',
            'price_model' => 'fixed', 'base_price' => 2500000, 'status' => 'active',
            'duration_minutes' => 480,
        ]);

        $order = $this->withToken($staffToken)->postJson("/api/v1/corporate/service-requests/{$reqId}/convert", [
            'service_id' => $service->id,
            'scheduled_at' => \Carbon\Carbon::parse('next monday 09:00')->toIso8601String(),
        ]);
        $order->assertCreated();
        $orderId = $order->json('data.order.id');
        $this->assertSame(2500000, $order->json('data.order.total'));

        // PO reference carried into order meta
        $this->assertSame('PO-2026-001', \App\Models\Order::find($orderId)->meta['po_reference']);

        // Pay → proceeds like normal order
        $this->postJson('/api/v1/payments/sandbox/pay', ['order_code' => $order->json('data.order.code')])->assertOk();
        $this->assertContains(\App\Models\Order::find($orderId)->status, ['paid', 'searching_provider']);

        // Request status = converted
        $this->assertSame('converted', \App\Models\CorporateServiceRequest::find($reqId)->status);
    }

    public function test_small_request_auto_approved_without_policy(): void
    {
        $admin = $this->postJson('/api/v1/auth/register', [
            'name' => 'A', 'email' => 'a2@test.id', 'password' => 'RahasiaKuat99',
        ]);
        $org = $this->withToken($admin->json('data.token'))->postJson('/api/v1/corporate/organizations', [
            'name' => 'PT Kecil',
        ])->json('data.organization');

        // No policy + zero estimated → auto-approved
        $req = $this->withToken($admin->json('data.token'))->postJson('/api/v1/corporate/service-requests', [
            'organization_id' => $org['id'],
            'title' => 'Cuci AC ruang rapat',
            'estimated_amount' => 0,
        ]);

        $req->assertCreated()->assertJsonPath('data.service_request.status', 'approved');
    }
}
