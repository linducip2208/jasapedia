<?php

namespace App\Support\Demo;

use Illuminate\Support\Facades\DB;

/**
 * Shared per-run context: preloaded locations, cached password hash,
 * Indonesian name pools. One instance is created by DemoDataSeeder and
 * passed to every sub-seeder — no repeated queries, one bcrypt total.
 */
final class DemoContext
{
    /** @var array<int, array{id:int,name:string,lat:?float,lng:?float,weight:int}> */
    public array $cities = [];

    /** @var array<string, int> */
    public array $cityWeighted = [];

    /** @var array<int, string> weighted city slug pool for O(1) picks */
    public array $cityPool = [];

    /** @var array<int, array{id:int,name:string,slug:string,config:array}> */
    public array $categories = [];

    /** @var array<string, int> slug => id */
    public array $categoryIds = [];

    public string $emailDomain;

    public string $passwordHash;

    public function __construct(string $emailDomain, string $passwordHash)
    {
        $this->emailDomain = $emailDomain;
        $this->passwordHash = $passwordHash;
    }

    public static function load(string $emailDomain, string $passwordHash): self
    {
        $ctx = new self($emailDomain, $passwordHash);

        $cities = DB::table('locations')
            ->whereIn('type', ['city'])
            ->get(['id', 'name', 'slug', 'lat', 'lng']);

        $weights = DemoDictionary::CITY_WEIGHTS;

        foreach ($cities as $city) {
            $weight = $weights[$city->slug] ?? 8;
            $ctx->cities[] = [
                'id' => (int) $city->id,
                'name' => $city->name,
                'slug' => $city->slug,
                'lat' => $city->lat !== null ? (float) $city->lat : null,
                'lng' => $city->lng !== null ? (float) $city->lng : null,
                'weight' => $weight,
            ];
            $ctx->cityWeighted[$city->slug] = $weight;
            $count = max(1, (int) round($weight / 4));
            for ($i = 0; $i < $count; $i++) {
                $ctx->cityPool[] = $city->slug;
            }
        }

        // City display names in proper case for title interpolation
        $ctx->cities = array_map(function ($city) {
            $city['name'] = ucwords(str_replace('-', ' ', $city['name']));

            return $city;
        }, $ctx->cities);

        // Guarantee every dictionary city exists; abort loudly otherwise.
        $missing = array_diff(array_keys($weights), array_column($ctx->cities, 'slug'));
        if ($missing !== []) {
            throw new \RuntimeException('LocationSeeder cities missing for demo: '.implode(', ', $missing));
        }

        $categories = DB::table('categories')->where('is_active', true)->get(['id', 'name', 'slug', 'config']);
        foreach ($categories as $category) {
            $ctx->categoryIds[$category->slug] = (int) $category->id;
            $ctx->categories[] = [
                'id' => (int) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'config' => json_decode((string) $category->config, true) ?: [],
            ];
        }

        $dictSlugs = array_keys(DemoDictionary::SERVICE_WEIGHTS);
        $dbSlugs = array_column($ctx->categories, 'slug');
        $missingCat = array_diff($dictSlugs, $dbSlugs);
        if ($missingCat !== []) {
            throw new \RuntimeException('CatalogSeeder categories missing for demo: '.implode(', ', $missingCat));
        }

        return $ctx;
    }

    public function randomCity(): array
    {
        $slug = $this->cityPool[mt_rand(0, count($this->cityPool) - 1)];

        return $this->cityBySlug($slug);
    }

    public function cityBySlug(string $slug): array
    {
        foreach ($this->cities as $city) {
            if ($city['slug'] === $slug) {
                return $city;
            }
        }

        return $this->cities[0];
    }

    public function categoryBySlug(string $slug): array
    {
        foreach ($this->categories as $category) {
            if ($category['slug'] === $slug) {
                return $category;
            }
        }

        throw new \InvalidArgumentException("Unknown category slug [{$slug}]");
    }

    /** Jittered coordinates near a valid city center (prompt §9). */
    public function jitter(float $lat, float $lng, float $km = 6.0): array
    {
        $angle = mt_rand(0, 359) * M_PI / 180;
        $dist = mt_rand(0, 1000) / 1000 * $km;

        return [
            round($lat + $dist * sin($angle) / 111.32, 7),
            round($lng + $dist * cos($angle) / (111.32 * max(0.2, cos(deg2rad($lat)))), 7),
        ];
    }

    /** Review dimensions per category config. */
    public function reviewDimensions(int $categoryId): array
    {
        foreach ($this->categories as $category) {
            if ($category['id'] === $categoryId) {
                return $category['config']['review_dimensions'] ?? ['quality', 'communication', 'value'];
            }
        }

        return ['quality', 'communication', 'value'];
    }
}
