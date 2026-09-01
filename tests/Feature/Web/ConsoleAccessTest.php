<?php

namespace Tests\Feature\Web;

use App\Models\Partner;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Partner Center, Admin Console, Business — access control and dashboards.
 */
class ConsoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_partner_dashboard_redirects_non_partner_to_onboarding(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->get('/partner');

        $res->assertRedirect(route('web.partner.onboarding'));
    }

    public function test_partner_onboarding_registers_partner(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->post('/partner/onboarding', [
            'type' => 'individual',
            'display_name' => 'Jasa Komputer Kita',
            'city' => 'Depok',
            'skills' => ['servis-laptop', 'install-windows'],
        ]);

        $res->assertRedirect(route('web.partner.dashboard'));
        $this->assertDatabaseHas('partners', ['user_id' => $user->id, 'display_name' => 'Jasa Komputer Kita']);
        $this->assertDatabaseHas('partner_skills', ['name' => 'servis-laptop']);
    }

    public function test_repeat_onboarding_updates_existing_partner_without_duplicate(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/partner/onboarding', [
            'type' => 'individual', 'display_name' => 'Pertama',
        ]);

        $res = $this->actingAs($user)->post('/partner/onboarding', [
            'type' => 'individual', 'display_name' => 'Pertama Rebrand',
        ]);

        $res->assertRedirect();
        $this->assertDatabaseCount('partners', 1);
        $this->assertDatabaseHas('partners', ['user_id' => $user->id, 'display_name' => 'Pertama Rebrand']);
    }

    public function test_admin_console_forbids_regular_customer(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin')->assertStatus(403);
    }

    public function test_admin_console_forbids_guest(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_admin_dashboard_renders_for_staff(): void
    {
        $admin = User::factory()->create();
        $adminRole = \App\Models\Role::where('name', 'PlatformAdmin')->first();
        $admin->roles()->attach($adminRole->id);

        $res = $this->actingAs($admin)->get('/admin');

        $res->assertOk()->assertSee('Command Center')->assertSee('GMV');
    }

    public function test_admin_dashboard_forbidden_without_permission_even_if_staff_other_role(): void
    {
        // Staff role without admin.access permission
        $staff = User::factory()->create();
        $role = \App\Models\Role::create(['name' => 'SupportOnly', 'label' => 'Support Only', 'is_staff' => true]);
        $staff->roles()->attach($role->id);

        $this->actingAs($staff)->get('/admin')->assertStatus(403);
    }

    public function test_business_dashboard_renders_empty_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/business')->assertOk()->assertSee('Jasapedia Business');
    }

    public function test_business_dashboard_renders_for_org_member(): void
    {
        $user = User::factory()->create();
        $org = \App\Models\CorporateOrganization::create(['owner_user_id' => $user->id, 'name' => 'PT Maju Jaya']);

        $res = $this->actingAs($user)->get('/business/dashboard');

        $res->assertOk()->assertSee('PT Maju Jaya');
    }

    public function test_project_publish_and_owner_view(): void
    {
        $this->seed(\Database\Seeders\CatalogSeeder::class);
        $user = User::factory()->create();

        $res = $this->actingAs($user)->post('/proyek', [
            'category_id' => \App\Models\Category::first()->id,
            'title' => 'Landing page company profile',
            'description' => 'Butuh landing page modern dengan 5 section dan form kontak.',
            'budget_type' => 'range',
            'budget_min' => 2000000,
            'budget_max' => 5000000,
        ]);

        $res->assertRedirect();
        $project = Project::where('user_id', $user->id)->where('title', 'Landing page company profile')->first();
        $this->assertNotNull($project);

        $this->actingAs($user)->get('/proyek/'.$project->id)
            ->assertOk()
            ->assertSee('Landing page company profile')
            ->assertSee('Proposal (0)');
    }

    public function test_other_user_sees_own_proposals_only(): void
    {
        $this->seed(\Database\Seeders\CatalogSeeder::class);
        $owner = User::factory()->create();
        $project = Project::create([
            'code' => 'PRJ-TEST-0001',
            'user_id' => $owner->id, 'category_id' => \App\Models\Category::first()->id,
            'title' => 'Proyek Rahasia', 'description' => 'Detail proyek', 'budget_type' => 'fixed',
            'status' => 'receiving_proposals',
        ]);

        $visitor = User::factory()->create();
        $res = $this->actingAs($visitor)->get('/proyek/'.$project->id);

        $res->assertOk()->assertDontSee('Buat Kontrak');
    }
}
