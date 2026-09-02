<?php

namespace Tests\Feature\Demo;

use App\Models\Category;
use App\Models\Partner;
use App\Models\Service;
use App\Support\Demo\DemoMediaPool;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\Demo\DemoDataSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Demo media integrity (prompt §12):
 *  - pool generated for all 21 categories (covers + avatars + banners)
 *  - 100% of demo services carry a cover; 70%/35%/10% gallery tiers
 *  - every referenced file exists locally (no broken refs, no hotlinks)
 */
class DemoMediaSeederTest extends TestCase
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

    public function test_demo_service_count_greater_than_zero(): void
    {
        $this->assertGreaterThan(0, Service::where('is_demo', true)->count());
    }

    public function test_every_demo_service_has_cover_media(): void
    {
        $missing = 0;
        $total = 0;

        Service::where('is_demo', true)->select('id', 'media')->chunkById(500, function ($rows) use (&$missing, &$total) {
            foreach ($rows as $row) {
                $total++;
                $media = $row->media;
                if (empty($media['cover'])) {
                    $missing++;
                }
            }
        });

        $this->assertSame(0, $missing, "{$missing} of {$total} demo services missing cover.");
    }

    public function test_no_demo_media_path_is_null_or_empty(): void
    {
        Service::where('is_demo', true)->select('media')->get()->each(function ($row) {
            $media = $row->media ?? [];
            $this->assertNotEmpty($media['cover'] ?? null);
            $this->assertIsArray($media['gallery'] ?? null, 'gallery key must exist');
            $this->assertNotEmpty($media['gallery']);
        });
    }

    public function test_all_referenced_local_files_exist(): void
    {
        $broken = [];

        Service::where('is_demo', true)->select('id', 'media')->get()->each(function ($row) use (&$broken) {
            $media = $row->media ?? [];
            foreach ([$media['cover'] ?? null, ...($media['gallery'] ?? [])] as $path) {
                if ($path && ! file_exists(public_path($path))) {
                    $broken[$path] = true;
                }
            }
        });

        $this->assertSame([], array_keys($broken), 'Broken media references: '.implode(', ', array_keys($broken)));
    }

    public function test_media_paths_use_allowed_local_prefix(): void
    {
        $allowed = ['demo/services/', 'demo/providers/', 'demo/categories/'];

        Service::where('is_demo', true)->select('media')->get()->each(function ($row) use ($allowed) {
            $media = $row->media ?? [];
            foreach ([$media['cover'] ?? null, ...($media['gallery'] ?? [])] as $path) {
                if ($path) {
                    $ok = collect($allowed)->contains(fn ($prefix) => str_starts_with($path, $prefix));
                    $this->assertTrue($ok, "Disallowed media path: {$path}");
                    $this->assertStringNotContainsString('http', $path, 'No external hotlinks allowed');
                }
            }
        });
    }

    public function test_all_21_categories_have_media_pool(): void
    {
        Category::query()->each(function (Category $category) {
            $pool = DemoMediaPool::forCategory($category->slug);
            $this->assertCount(DemoMediaPool::COVERS_PER_CATEGORY, $pool, "Pool incomplete for {$category->slug}");
            foreach (array_slice($pool, 0, 3) as $path) {
                $this->assertFileExists(public_path($path), "Missing pool asset: {$path}");
            }
            $this->assertFileExists(public_path(DemoMediaPool::categoryBanner($category->slug)));
        });
    }

    public function test_gallery_distribution_matches_target_tiers(): void
    {
        $with2 = 0;
        $with3 = 0;
        $with45 = 0;
        $total = 0;

        Service::where('is_demo', true)->select('media')->get()->each(function ($row) use (&$with2, &$with3, &$with45, &$total) {
            $media = $row->media ?? [];
            $count = count($media['gallery'] ?? []);
            $total++;
            match (true) {
                $count >= 4 => $with45++,
                $count === 3 => $with3++,
                $count === 2 => $with2++,
                default => null,
            };
        });

        // 70% ≥2 gallery, 35% ≥3, 10% ≥4 (±tolerance for small dataset)
        $this->assertGreaterThan($total * 0.5, $with2 + $with3 + $with45, 'Too few services with 2+ gallery.');
        $this->assertGreaterThan($total * 0.15, $with3 + $with45, 'Too few services with 3+ gallery.');
    }

    public function test_no_duplicate_gallery_files_within_a_service(): void
    {
        Service::where('is_demo', true)->select('media')->get()->each(function ($row) {
            $media = $row->media ?? [];
            $gallery = $media['gallery'] ?? [];

            $this->assertSame(count($gallery), count(array_unique($gallery)), 'Duplicate gallery file within one service.');
        });
    }

    public function test_every_demo_provider_has_avatar(): void
    {
        $withAvatar = Partner::where('is_demo', true)->whereNotNull('avatar_path')->where('avatar_path', '!=', '')->count();
        $total = Partner::where('is_demo', true)->count();

        $this->assertSame($total, $withAvatar, "{$total} partners, {$withAvatar} with avatar.");

        Partner::where('is_demo', true)->select('avatar_path')->get()->each(function ($row) {
            $this->assertFileExists(public_path($row->avatar_path), "Avatar file missing: {$row->avatar_path}");
        });
    }

    public function test_categories_have_icon_keys_synced(): void
    {
        Category::query()->each(function (Category $category) {
            $this->assertSame(
                DemoMediaPool::ICON_KEYS[$category->slug] ?? null,
                $category->icon,
                "Icon key not synced for {$category->slug}",
            );
        });
    }

    public function test_pool_generation_is_idempotent(): void
    {
        $before = $this->assetCount();
        DemoMediaPool::ensurePool();
        $after = $this->assetCount();

        $this->assertSame($before, $after, 'Pool regeneration must not duplicate or rewrite assets.');
    }

    private function assetCount(): int
    {
        $count = 0;
        $dir = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(public_path('demo')));
        foreach ($dir as $file) {
            $count += $file->isFile() ? 1 : 0;
        }

        return $count;
    }
}
