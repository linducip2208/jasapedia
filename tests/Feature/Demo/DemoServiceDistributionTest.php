<?php

namespace Tests\Feature\Demo;

use App\Models\Category;
use App\Models\Service;
use App\Support\Demo\DemoDictionary;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\Demo\DemoDataSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies the distribution algorithm: normalized weights always sum EXACTLY
 * to the requested total, every category gets > 0 services, and realistic
 * Indonesian titles/prices per category (prompt §4, §5, §6, §10).
 */
class DemoServiceDistributionTest extends TestCase
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
            'orders' => 25,
            'projects' => 10,
            'rfqs' => 10,
            'reviews' => 15,
            'corporates' => 2,
        ]);
    }

    public function test_normalized_distribution_sums_exactly_to_total(): void
    {
        foreach ([210, 1000, 10000] as $total) {
            $dist = DemoDictionary::normalizedDistribution($total);
            $this->assertSame($total, array_sum($dist), "Distribution for {$total} does not sum exactly.");
            $this->assertCount(count(DemoDictionary::SERVICE_WEIGHTS), $dist);
            foreach ($dist as $slug => $count) {
                $this->assertGreaterThan(0, $count, "{$slug} would get 0 services.");
            }
        }
    }

    public function test_default_distribution_matches_10000_blueprint(): void
    {
        $dist = DemoDictionary::normalizedDistribution(10000);

        $this->assertSame(10000, array_sum($dist));

        // Equal weights ⇒ equal normalized counts.
        $this->assertSame($dist['technology-programming'], $dist['cleaning']);
        $this->assertSame($dist['cleaning'], $dist['ac-electronics']);

        // Proportional to weights within ±1 (largest-remainder rounding).
        $weightSum = array_sum(DemoDictionary::SERVICE_WEIGHTS);
        foreach (DemoDictionary::SERVICE_WEIGHTS as $slug => $weight) {
            $expectedFloor = (int) floor($weight * 10000 / $weightSum);
            $this->assertLessThanOrEqual(1, $dist[$slug] - $expectedFloor, "{$slug} deviates from proportional share.");
            $this->assertGreaterThanOrEqual(0, $dist[$slug] - $expectedFloor, "{$slug} deviates from proportional share.");
        }
    }

    public function test_all_21_categories_present_in_database(): void
    {
        $this->assertSame(21, Category::count());

        $missing = array_diff(
            array_keys(DemoDictionary::SERVICE_WEIGHTS),
            Category::pluck('slug')->all(),
        );

        $this->assertSame([], $missing, 'Missing categories: '.implode(', ', $missing));
    }

    public function test_titles_are_category_appropriate_not_lorem(): void
    {
        $forbidden = ['Lorem', 'Ipsum', 'Service 1234', 'Test Service', 'Best Service', 'Professional Service'];

        Service::where('is_demo', true)->get(['title', 'category_id'])->each(function (Service $service) use ($forbidden) {
            foreach ($forbidden as $needle) {
                $this->assertStringNotContainsStringIgnoringCase($needle, $service->title, "Generic title leaked: {$service->title}");
            }
        });

        // Sample: cleaning services should contain cleaning vocabulary
        $cleaningCat = Category::where('slug', 'cleaning')->first();
        $cleaningTitle = Service::where('category_id', $cleaningCat->id)->inRandomOrder()->first()->title;
        $this->assertMatchesRegularExpression(
            '/Cleaning|Cuci|Kamar|Sofa|Karpet|Kost|Ruko|Kantor/iu',
            $cleaningTitle,
            "Cleaning title not category-specific: {$cleaningTitle}",
        );

        $acCat = Category::where('slug', 'ac-electronics')->first();
        $acTitle = Service::where('category_id', $acCat->id)->inRandomOrder()->first()->title;
        $this->assertMatchesRegularExpression(
            '/AC|Freon|Kulkas|Cuci|Mesin|TV|Service|Service|Instalasi|Bongkar|Reparasi|Water Heater|Dispenser|Freezer|Inverter/iu',
            $acTitle,
            "AC title not category-specific: {$acTitle}",
        );

        $techCat = Category::where('slug', 'technology-programming')->first();
        $techTitle = Service::where('category_id', $techCat->id)->inRandomOrder()->first()->title;
        $this->assertMatchesRegularExpression(
            '/Website|Aplikasi|API|Laravel|Database|Payment|VPS|POS|CRM|Dashboard|Maintenance|Bug|Integrasi|Sistem|Pembuatan|Setup|Optimasi|Tokon? ?(Online)?|Deployment|Audit/iu',
            $techTitle,
            "Technology title not category-specific: {$techTitle}",
        );
    }

    public function test_titles_have_reasonable_variety(): void
    {
        $distinct = Service::where('is_demo', true)->distinct('title')->count('title');
        $total = Service::where('is_demo', true)->count();

        // Dictionary combination space must keep most titles unique even on small sets.
        $this->assertGreaterThan($total * 0.5, $distinct, 'Too many duplicate titles — generator too narrow.');
    }

    public function test_prices_within_category_ranges(): void
    {
        // Construction can reach 1 miliar; cleaning capped at 2.5 juta (prompt §10)
        $cleaningCat = Category::where('slug', 'cleaning')->first();
        $maxCleaning = Service::where('category_id', $cleaningCat->id)->max('base_price');
        $this->assertLessThanOrEqual(2500000, $maxCleaning);
        $this->assertGreaterThanOrEqual(50000, $maxCleaning);

        $educationCat = Category::where('slug', 'education')->first();
        $maxEdu = Service::where('category_id', $educationCat->id)->max('base_price');
        $this->assertLessThanOrEqual(5000000, $maxEdu);

        Service::where('is_demo', true)->each(function (Service $service) {
            $this->assertIsInt((int) $service->base_price);
            $this->assertGreaterThan(0, (int) $service->base_price);
        });
    }

    public function test_packages_and_addons_exist_for_services(): void
    {
        $servicesWithPackages = DB::table('service_packages')
            ->join('services', 'services.id', '=', 'service_packages.service_id')
            ->where('services.is_demo', true)
            ->distinct()
            ->count('service_packages.service_id');

        $this->assertGreaterThan(0, $servicesWithPackages);

        $packagesByService = DB::table('service_packages')
            ->join('services', 'services.id', '=', 'service_packages.service_id')
            ->where('services.is_demo', true)
            ->groupBy('service_packages.service_id')
            ->selectRaw('service_packages.service_id, COUNT(*) as c')
            ->pluck('c', 'service_packages.service_id');

        // Every service that HAS packages must have exactly the 3-tier set.
        $packagesByService->each(fn ($count) => $this->assertSame(3, $count));

        $this->assertGreaterThan(0, DB::table('service_addons')->join('services', 'services.id', '=', 'service_addons.service_id')->where('services.is_demo', true)->count());
    }

    public function test_service_media_covers_generated_locally(): void
    {
        $service = Service::where('is_demo', true)->whereNotNull('media')->inRandomOrder()->first();
        $media = $service->media;

        $this->assertArrayHasKey('cover', $media);
        $this->assertStringStartsWith('demo/', $media['cover']);
        $this->assertFileExists(public_path($media['cover']));
    }

    public function test_fulfillment_and_price_models_are_valid(): void
    {
        Service::where('is_demo', true)->each(function (Service $service) {
            $this->assertContains($service->status, Service::STATUSES);
            $this->assertContains($service->price_model, Service::PRICE_MODELS);
            $this->assertContains($service->delivery_mode, ['remote', 'onsite', 'hybrid', 'provider_location']);
        });
    }
}
