<?php

namespace Tests\Feature\Demo;

use App\Models\Category;
use App\Models\Partner;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\Demo\DemoDataSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Demo seeder integration test — runs with SMALL counts only (never 10k in CI).
 * Asserts structural integrity of the whole demo dataset (prompt §24).
 */
class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    private const COUNTS = [
        'services' => 210,
        'providers' => 21,
        'customers' => 40,
        'orders' => 30,
        'projects' => 12,
        'rfqs' => 12,
        'reviews' => 20,
        'corporates' => 3,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LocationSeeder::class);
        $this->seed(CatalogSeeder::class);

        mt_srand(20260901);
        app(DemoDataSeeder::class)->run(self::COUNTS);
    }

    public function test_services_equal_requested_count_exactly(): void
    {
        $this->assertSame(210, Service::where('is_demo', true)->count());
        $this->assertSame(210, Service::where('is_demo', true)->where('status', 'active')->count());
    }

    public function test_every_category_has_services(): void
    {
        $this->assertGreaterThan(0, Category::count());

        Category::query()->each(function (Category $category) {
            $count = Service::where('is_demo', true)->where('category_id', $category->id)->count();
            $this->assertGreaterThan(0, $count, "Category [{$category->name}] has no demo services.");
        });
    }

    public function test_no_duplicate_service_slugs(): void
    {
        $dupes = Service::where('is_demo', true)
            ->select('slug', DB::raw('COUNT(*) as c'))
            ->groupBy('slug')
            ->havingRaw('c > 1')
            ->count();

        $this->assertSame(0, $dupes);
    }

    public function test_no_invalid_category_or_partner_references(): void
    {
        $invalidCategory = Service::where('is_demo', true)
            ->whereNotIn('category_id', Category::select('id'))
            ->count();
        $this->assertSame(0, $invalidCategory);

        $invalidPartner = Service::where('is_demo', true)
            ->whereNotIn('partner_id', Partner::select('id'))
            ->count();
        $this->assertSame(0, $invalidPartner);
    }

    public function test_prices_are_positive_integers(): void
    {
        $bad = Service::where('is_demo', true)->where('base_price', '<', 50000)->count();
        $this->assertSame(0, $bad);

        $floats = Service::where('is_demo', true)->whereRaw('base_price != CAST(base_price AS SIGNED)')->count();
        $this->assertSame(0, $floats);
    }

    public function test_providers_have_valid_service_associations(): void
    {
        Service::where('is_demo', true)->select('partner_id')->distinct()
            ->each(fn ($row) => $this->assertTrue(Partner::where('id', $row->partner_id)->exists()));

        Partner::where('is_demo', true)->each(function (Partner $partner) {
            $this->assertTrue(User::where('id', $partner->user_id)->exists(), "Partner {$partner->id} has no user.");
        });
    }

    public function test_demo_emails_use_safe_test_domains(): void
    {
        $unsafe = User::where('is_demo', true)
            ->where(function ($q) {
                $q->where('email', 'not like', '%@example.test')
                    ->where('email', 'not like', '%@jasapedia.test');
            })
            ->count();

        $this->assertSame(0, $unsafe);
    }

    public function test_demo_password_is_hashed(): void
    {
        $user = User::where('is_demo', true)->first();
        $this->assertNotSame('password', $user->password);
        $this->assertStringStartsWith('$2y$', $user->password);
    }

    public function test_reviews_are_domain_eligible_and_within_bounds(): void
    {
        Review::where('is_demo', true)->each(function (Review $review) {
            $this->assertGreaterThanOrEqual(1, $review->overall);
            $this->assertLessThanOrEqual(5, $review->overall);

            $orderStatus = DB::table('orders')->where('id', $review->order_id)->value('status');
            $this->assertContains($orderStatus, ['completed', 'settled', 'closed']);

            // dimensions match the category config
            $dims = DB::table('services')
                ->join('categories', 'categories.id', '=', 'services.category_id')
                ->where('services.id', DB::table('orders')->where('id', $review->order_id)->value('service_id'))
                ->value('categories.config');
            $expected = json_decode((string) $dims, true)['review_dimensions'] ?? null;
            if ($expected) {
                foreach (array_keys($review->dimension_ratings ?? []) as $dim) {
                    $this->assertContains($dim, $expected);
                }
            }
        });
    }

    public function test_seeder_is_idempotent_guarded(): void
    {
        // Running again without fresh-demo must abort (command-level guard).
        $this->artisan('jasapedia:seed-demo', ['--fresh-demo' => false])
            ->expectsOutputToContain('Dataset demo sudah ada')
            ->assertExitCode(1);
    }

    public function test_command_fresh_demo_respects_counts(): void
    {
        // Full command path with small counts (fresh) — still exact services.
        $this->artisan('jasapedia:seed-demo', [
            '--services' => 210,
            '--providers' => 21,
            '--customers' => 40,
            '--orders' => 25,
            '--projects' => 10,
            '--rfqs' => 10,
            '--reviews' => 15,
            '--corporates' => 2,
            '--fresh-demo' => true,
        ])->assertSuccessful();

        $this->assertSame(210, Service::where('is_demo', true)->count());
    }
}
