<?php

namespace Tests\Unit\Pricing;

use App\Domain\Pricing\PricingCalculator;
use App\Domain\Pricing\PricingInput;
use App\Models\Category;
use App\Models\Partner;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\ServicePackage;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Service $perUnit;
    private Service $hourly;
    private Service $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);

        $partner = Partner::create([
            'user_id' => \App\Models\User::factory()->create()->id,
            'type' => 'individual',
            'display_name' => 'Test',
            'slug' => 'test-'.uniqid(),
            'verification_state' => 'verified',
        ]);

        $catAc = Category::where('slug', 'ac-electronics')->first();
        $catCleaning = Category::where('slug', 'cleaning')->first();

        $this->perUnit = Service::create([
            'partner_id' => $partner->id, 'category_id' => $catAc->id,
            'title' => 'Cuci AC', 'slug' => 'cuci-ac-'.uniqid(),
            'fulfillment_type' => 'per_unit', 'delivery_mode' => 'onsite',
            'price_model' => 'per_unit', 'base_price' => 90000, 'unit_label' => 'unit',
            'duration_minutes' => 60, 'status' => 'active',
            'emergency_capable' => true, 'emergency_surcharge' => 50000,
        ]);

        $this->hourly = Service::create([
            'partner_id' => $partner->id, 'category_id' => $catCleaning->id,
            'title' => 'Cleaning', 'slug' => 'cleaning-'.uniqid(),
            'fulfillment_type' => 'hourly', 'delivery_mode' => 'onsite',
            'price_model' => 'hourly', 'base_price' => 30000,
            'duration_minutes' => 240, 'status' => 'active',
        ]);

        $this->package = Service::create([
            'partner_id' => $partner->id, 'category_id' => $catAc->id,
            'title' => 'Bundling AC', 'slug' => 'bundling-'.uniqid(),
            'fulfillment_type' => 'fixed_package', 'delivery_mode' => 'onsite',
            'price_model' => 'package', 'base_price' => 0, 'status' => 'active',
        ]);
        ServicePackage::create(['service_id' => $this->package->id, 'name' => 'Paket Cuci+Ganti Filter', 'price' => 250000]);
    }

    public function test_per_unit_quantity(): void
    {
        $quote = app(PricingCalculator::class)->quote($this->perUnit, new PricingInput(quantity: 3));

        $this->assertSame(270000, $quote->total->amount);
        $this->assertSame(3, $quote->lines[0]->qty);
    }

    public function test_hourly_ceil_rounding(): void
    {
        $quote = app(PricingCalculator::class)->quote($this->hourly, new PricingInput(durationMinutes: 300));

        // 300 min → 5 hours × 30000
        $this->assertSame(150000, $quote->total->amount);
    }

    public function test_package_selection(): void
    {
        $quote = app(PricingCalculator::class)->quote($this->package, new PricingInput(packageId: ServicePackage::first()->id));

        $this->assertSame(250000, $quote->total->amount);
    }

    public function test_package_required_for_package_service(): void
    {
        $this->expectException(\App\Domain\Auth\DomainException::class);
        app(PricingCalculator::class)->quote($this->package, new PricingInput());
    }

    public function test_addons_and_emergency(): void
    {
        $addon = ServiceAddon::create([
            'service_id' => $this->perUnit->id, 'name' => 'Outdoor', 'price' => 30000, 'is_active' => true,
        ]);

        $quote = app(PricingCalculator::class)->quote(
            $this->perUnit,
            new PricingInput(quantity: 2, addonIds: [$addon->id], emergency: true),
        );

        // 2×90000 + 30000 + 50000 = 260000
        $this->assertSame(260000, $quote->total->amount);
        $this->assertSame(50000, $quote->emergencySurcharge->amount);
    }

    public function test_snapshot_is_frozen_array(): void
    {
        $quote = app(PricingCalculator::class)->quote($this->perUnit, new PricingInput(quantity: 1));
        $snap = $quote->snapshot();

        $this->assertSame(90000, $snap['total']);
        $this->assertSame('IDR', $snap['currency']);
        $this->assertCount(1, $snap['lines']);
    }

    public function test_unknown_addon_rejected(): void
    {
        $this->expectException(\App\Domain\Auth\DomainException::class);
        app(PricingCalculator::class)->quote($this->perUnit, new PricingInput(addonIds: [999]));
    }
}
