<?php

namespace Tests\Feature\Web;

use App\Models\Partner;
use App\Models\Service;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Customer storefront web UI — homepage, explore, service detail, provider page.
 */
class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LocationSeeder::class);
        $this->seed(CatalogSeeder::class);
    }

    private function makeVerifiedPartnerWithService(): array
    {
        $reg = $this->postJson('/api/v1/auth/register', [
            'name' => 'Partner Web', 'email' => 'pw@p.test', 'password' => 'RahasiaKuat99',
        ]);
        $token = $reg->json('data.token');

        $this->withToken($token)->postJson('/api/v1/partner', [
            'type' => 'individual', 'display_name' => 'Jasa Web Test', 'city' => 'Jakarta Selatan',
        ]);
        Partner::query()->update(['verification_state' => 'verified']);

        $categoryId = \App\Models\Category::where('slug', 'ac-electronics')->value('id');
        $this->withToken($token)->postJson('/api/v1/partner/services', [
            'category_id' => $categoryId,
            'title' => 'Cuci AC Split 1 PK Web',
            'description' => 'Cuci AC lengkap dengan pembersihan filter dan pengecekan freon.',
            'fulfillment_type' => 'per_unit',
            'delivery_mode' => 'onsite',
            'price_model' => 'per_unit',
            'base_price' => 150000,
            'unit_label' => 'unit',
            'emergency_capable' => true,
        ]);

        return [Partner::first(), Service::where('title', 'Cuci AC Split 1 PK Web')->first()];
    }

    public function test_home_page_renders_brand_and_sections(): void
    {
        $res = $this->get('/');

        $res->assertOk()
            ->assertSee('Semua Jasa, Satu Platform')
            ->assertSee('Jasa Populer')
            ->assertSee('Cara Kerja Jasapedia')
            ->assertSee('Jadi Penyedia');
    }

    public function test_home_shows_no_laravel_branding(): void
    {
        $res = $this->get('/');

        $res->assertOk()->assertDontSee('Laravel');
    }

    public function test_explore_lists_active_services(): void
    {
        $this->makeVerifiedPartnerWithService();

        $res = $this->get('/explore');

        $res->assertOk()->assertSee('Cuci AC Split 1 PK Web');
    }

    public function test_explore_keyword_filter_works(): void
    {
        $this->makeVerifiedPartnerWithService();

        $res = $this->get('/explore?q=nonexistent-xyz');

        $res->assertOk()->assertSee('Tidak ada jasa yang cocok');
    }

    public function test_service_detail_renders_purchase_panel(): void
    {
        [, $service] = $this->makeVerifiedPartnerWithService();

        $res = $this->get("/jasa/{$service->slug}");

        $res->assertOk()
            ->assertSee('Cuci AC Split 1 PK Web')
            ->assertSee('Beli Sekarang')
            ->assertSee('Dana ditahan sampai pekerjaan selesai')
            ->assertSee('Darurat 24/7');
    }

    public function test_service_detail_hides_inactive_service(): void
    {
        [, $service] = $this->makeVerifiedPartnerWithService();
        $service->update(['status' => 'paused']);

        $this->get("/jasa/{$service->slug}")->assertStatus(404);
    }

    public function test_provider_storefront_shows_services_and_level(): void
    {
        [$partner] = $this->makeVerifiedPartnerWithService();

        $res = $this->get("/penyedia/{$partner->slug}");

        $res->assertOk()
            ->assertSee('Jasa Web Test')
            ->assertSee('Verified Provider')
            ->assertSee('Cuci AC Split 1 PK Web');
    }

    public function test_provider_storefront_404_for_unverified(): void
    {
        [$partner] = $this->makeVerifiedPartnerWithService();
        Partner::where('id', $partner->id)->update(['verification_state' => 'unverified']);

        // Guest request — verify as a truly unauthenticated visitor.
        $this->postJson('/api/v1/auth/logout');

        $user = \App\Models\User::factory()->create(); // separate non-owner account
        $this->actingAs($user);
        $this->app['auth']->forgetUser();

        $response = $this->get("/penyedia/{$partner->slug}");
        $response->assertStatus(404);
    }

    public function test_search_suggest_endpoint_returns_json(): void
    {
        $this->makeVerifiedPartnerWithService();

        $res = $this->getJson('/search/suggest?q=cuci');

        $res->assertOk()->assertJsonStructure(['suggestions']);
    }
}
