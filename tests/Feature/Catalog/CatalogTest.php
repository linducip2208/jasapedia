<?php

namespace Tests\Feature\Catalog;

use Database\Seeders\CatalogSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private string $partnerToken;
    private int $partnerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LocationSeeder::class);
        $this->seed(CatalogSeeder::class);

        // Register + verified partner
        $reg = $this->postJson('/api/v1/auth/register', [
            'name' => 'Partner', 'email' => 'p@p.test', 'password' => 'RahasiaKuat99',
        ]);
        $this->partnerToken = $reg->json('data.token');

        $this->withToken($this->partnerToken)->postJson('/api/v1/partner', [
            'type' => 'individual', 'display_name' => 'Jasa AC Test', 'city' => 'Jakarta Selatan',
        ]);

        \App\Models\Partner::query()->update(['verification_state' => 'verified']);
        $this->partnerId = \App\Models\Partner::first()->id;
    }

    public function test_public_category_tree(): void
    {
        $res = $this->getJson('/api/v1/catalog/categories');
        $res->assertOk();
        $names = collect($res->json('data.categories.*.name'));
        $this->assertTrue($names->contains('Cleaning'));
        $this->assertTrue($names->contains('AC & Electronics'));
        $this->assertTrue($names->contains('Technology & Programming'));
        $this->assertCount(21, $names);
    }

    public function test_partner_creates_service_with_valid_price_model(): void
    {
        $categoryId = \App\Models\Category::where('slug', 'ac-electronics')->value('id');

        $res = $this->withToken($this->partnerToken)->postJson('/api/v1/partner/services', [
            'category_id' => $categoryId,
            'title' => 'Cuci AC Split 1 PK',
            'description' => 'Cuci AC lengkap dengan pembersihan filter.',
            'fulfillment_type' => 'per_unit',
            'delivery_mode' => 'onsite',
            'price_model' => 'per_unit',
            'base_price' => 90000,
            'unit_label' => 'unit',
            'min_quantity' => 1,
            'duration_minutes' => 60,
            'emergency_capable' => true,
            'emergency_surcharge' => 50000,
            'warranty_days' => 7,
            'addons' => [
                ['name' => 'Cuci Outdoor Unit', 'price' => 30000],
            ],
        ]);

        $res->assertCreated()->assertJsonPath('data.service.status', 'active');
        $this->assertCount(1, $res->json('data.service.addons'));
    }

    public function test_price_model_violation_rejected(): void
    {
        $categoryId = \App\Models\Category::where('slug', 'cleaning')->value('id');

        $this->withToken($this->partnerToken)->postJson('/api/v1/partner/services', [
            'category_id' => $categoryId,
            'title' => 'Cleaning jam per jam',
            'fulfillment_type' => 'hourly',
            'delivery_mode' => 'onsite',
            'price_model' => 'per_unit', // not allowed for hourly
            'base_price' => 50000,
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_PRICE_MODEL');
    }

    public function test_unverified_partner_cannot_publish(): void
    {
        \App\Models\Partner::query()->update(['verification_state' => 'submitted']);
        $categoryId = \App\Models\Category::where('slug', 'cleaning')->value('id');

        $this->withToken($this->partnerToken)->postJson('/api/v1/partner/services', [
            'category_id' => $categoryId,
            'title' => 'Bersih Rumah',
            'fulfillment_type' => 'hourly',
            'delivery_mode' => 'onsite',
            'price_model' => 'hourly',
            'base_price' => 30000,
        ])->assertStatus(403)->assertJsonPath('error.code', 'NOT_VERIFIED');
    }

    public function test_public_search_and_filters(): void
    {
        $categoryId = \App\Models\Category::where('slug', 'ac-electronics')->value('id');

        $this->withToken($this->partnerToken)->postJson('/api/v1/partner/services', [
            'category_id' => $categoryId,
            'title' => 'Cuci AC Split',
            'fulfillment_type' => 'per_unit',
            'delivery_mode' => 'onsite',
            'price_model' => 'per_unit',
            'base_price' => 90000,
        ]);

        $this->withToken($this->partnerToken)->postJson('/api/v1/partner/services', [
            'category_id' => $categoryId,
            'title' => 'Servis AC Berat',
            'fulfillment_type' => 'survey_required',
            'delivery_mode' => 'onsite',
            'price_model' => 'starting_from',
            'base_price' => 250000,
        ]);

        $search = $this->getJson('/api/v1/catalog/services?q=cuci+ac');
        $search->assertOk()->assertJsonCount(1, 'data');

        $filtered = $this->getJson('/api/v1/catalog/services?fulfillment_type=survey_required');
        $filtered->assertOk()->assertJsonCount(1, 'data');

        $byCat = $this->getJson('/api/v1/catalog/services?category=ac-electronics');
        $byCat->assertOk()->assertJsonCount(2, 'data');

        $price = $this->getJson('/api/v1/catalog/services?max_price=100000');
        $price->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_service_detail_shows_pricing_and_warranty(): void
    {
        $categoryId = \App\Models\Category::where('slug', 'ac-electronics')->value('id');
        $this->withToken($this->partnerToken)->postJson('/api/v1/partner/services', [
            'category_id' => $categoryId,
            'title' => 'Cuci AC Spesial',
            'fulfillment_type' => 'per_unit',
            'delivery_mode' => 'onsite',
            'price_model' => 'per_unit',
            'base_price' => 95000,
            'warranty_days' => 7,
        ]);
        $slug = \App\Models\Service::first()->slug;

        $res = $this->getJson("/api/v1/catalog/services/{$slug}");
        $res->assertOk()
            ->assertJsonPath('data.service.price_model', 'per_unit')
            ->assertJsonPath('data.service.warranty_days', 7);
    }

    public function test_locations_endpoint(): void
    {
        $provinces = $this->getJson('/api/v1/locations?type=province');
        $provinces->assertOk();
        $this->assertCount(15, $provinces->json('data.locations'));

        $cities = $this->getJson('/api/v1/locations?type=city&q=sura');
        $this->assertGreaterThanOrEqual(2, count($cities->json('data.locations')));
    }
}
