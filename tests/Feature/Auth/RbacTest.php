<?php

namespace Tests\Feature\Auth;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $role)
    {
        $user = \App\Models\User::factory()->create();
        $user->roles()->attach(\App\Models\Role::where('name', $role)->value('id'));

        return $user;
    }

    public function test_customer_cannot_use_finance_permission(): void
    {
        $customer = $this->userWithRole('Customer');

        $this->assertTrue($customer->can('customer.order.create'));
        $this->assertFalse($customer->can('finance.refund.approve'));
    }

    public function test_finance_manager_has_refund_and_settlement(): void
    {
        $fm = $this->userWithRole('FinanceManager');

        $this->assertTrue($fm->can('finance.refund.approve'));
        $this->assertTrue($fm->can('finance.settlement.execute'));
        $this->assertFalse($fm->can('dispute.resolve'));
    }

    public function test_vendor_roles_are_scoped_to_vendor_permissions(): void
    {
        $worker = $this->userWithRole('VendorWorker');

        $this->assertTrue($worker->can('partner.order.complete'));
        $this->assertFalse($worker->can('vendor.member.manage'));
        $this->assertFalse($worker->can('partner.withdrawal.request'));
    }

    public function test_superadmin_has_everything(): void
    {
        $admin = $this->userWithRole('SuperAdmin');

        foreach (array_keys(\App\Domain\Authorization\PermissionRegistrar::catalog()) as $perm) {
            $this->assertTrue($admin->can($perm), "SuperAdmin missing {$perm}");
        }
    }

    public function test_permission_middleware_blocks_unauthorized(): void
    {
        $customer = $this->userWithRole('Customer');
        $admin = $this->userWithRole('SuperAdmin');

        $probe = new class extends \App\Http\Controllers\Api\V1\Controller
        {
            use \App\Support\Http\ApiResponse;

            public function index(\Illuminate\Http\Request $request)
            {
                return $this->ok(['granted' => true]);
            }
        };

        \Illuminate\Support\Facades\Route::get('/api/v1/_probe', [$probe::class, 'index'])
            ->middleware(['auth:sanctum', 'permission:finance.refund.approve']);

        $this->actingAs($customer, 'sanctum')->getJson('/api/v1/_probe')->assertStatus(403);
        $this->actingAs($admin, 'sanctum')->getJson('/api/v1/_probe')->assertOk()->assertJsonPath('data.granted', true);
    }
}
