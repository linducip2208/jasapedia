<?php

namespace Database\Seeders\Demo;

use App\Support\Demo\DemoContext;
use App\Support\Demo\DemoDictionary;
use App\Support\Demo\DemoMediaPool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * EXACTLY $services active demo listings across all 21 categories.
 * Bulk inserts, deterministic title generator, category-specific pricing.
 * Distribution via DemoDictionary::normalizedDistribution() = always exact.
 */
class DemoServiceSeeder extends Seeder
{
    public function run(DemoContext $ctx, int $services, array $partnerMap): array
    {
        $distribution = DemoDictionary::normalizedDistribution($services);
        $dictionary = app(DemoDictionary::class);
        $catIds = $ctx->categoryIds;

        // Existing demo services (idempotency): count per category
        $existing = DB::table('services')->where('is_demo', true)
            ->selectRaw('category_id, COUNT(*) as c')->groupBy('category_id')->pluck('c', 'category_id');

        $plan = [];
        $total = 0;
        foreach ($distribution as $slug => $target) {
            $catId = $catIds[$slug];
            $have = (int) ($existing[$catId] ?? 0);
            $todo = max(0, $target - $have);
            if ($todo > 0) {
                $plan[] = ['slug' => $slug, 'catId' => $catId, 'count' => $todo, 'offset' => $have];
            }
            $total += $have;
        }

        $remaining = max(0, $services - $total);
        $bar = $this->command?->getOutput()->createProgressBar($remaining);
        $bar?->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar?->setMessage('Services');
        $bar?->start();

        $now = now();
        $serviceRows = [];
        $packageRows = [];
        $packageServiceIdx = [];
        $addonRows = [];
        $addonServiceIdx = [];
        $serviceMeta = [];

        $seq = (int) (DB::table('services')->max('id') ?? 0);
        $genIndex = 0;

        // Round-robin partners so every provider gets listings
        $partnerCount = count($partnerMap);
        if ($partnerCount === 0) {
            throw new \RuntimeException('No demo partners available for services.');
        }

        foreach ($plan as $p) {
            $slug = $p['slug'];
            $dict = $dictionary->category($slug);

            for ($i = 0; $i < $p['count']; $i++) {
                $seq++;
                $globalIdx = $p['offset'] + $i;
                $genIndexCurrent = $genIndex++;

                $partner = $partnerMap[$seq % $partnerCount];
                $citySlug = DemoDictionary::weightedPick($ctx->cityWeighted);
                $city = $ctx->cityBySlug($citySlug);

                $title = $dictionary->serviceTitle($slug, $globalIdx * 7 + $seq);
                [$fulfillmentType, $priceModel, $unitLabel, $duration] = $dictionary->pickFulfillment($slug);
                $delivery = $dict['delivery'];
                $price = $dictionary->priceFor($slug, $priceModel);

                $isEmergency = $dict['emergency'] && mt_rand(1, 100) <= 35;
                $warranty = in_array($slug, ['ac-electronics', 'handyman', 'plumbing', 'electrical'], true)
                    ? 7
                    : (in_array($slug, ['technology-programming', 'design-creative'], true) ? 30 : (mt_rand(1, 100) <= 30 ? 7 : 0));

                $slugStr = Str::slug($title).'-'.Str::lower(Str::random(5));

                // Media from the category pool — cover always, gallery per distribution:
                // 100% cover | 70% 2 gallery | 35% 3 | 10% 4-5 (cumulative tiers, no dupes)
                $pool = DemoMediaPool::forCategory($slug);
                $coverIdx = ($seq + $globalIdx) % count($pool);
                $cover = $pool[$coverIdx];

                $roll = mt_rand(1, 100);
                $galleryTarget = match (true) {
                    $roll <= 10 => mt_rand(4, 5),
                    $roll <= 35 => 3,
                    $roll <= 70 => 2,
                    default => 1,
                };

                $gallery = [$cover]; // cover doubles as first gallery item
                $poolSize = count($pool);
                $step = 5; // deterministic stride; distinct indices modulo pool size
                while (count($gallery) < $galleryTarget) {
                    $coverIdx = ($coverIdx + $step) % $poolSize;
                    if (! in_array($pool[$coverIdx], $gallery, true)) {
                        $gallery[] = $pool[$coverIdx];
                    } else {
                        $coverIdx = ($coverIdx + 1) % $poolSize;
                    }
                }

                $serviceRows[] = [
                    'partner_id' => $partner['id'],
                    'category_id' => $p['catId'],
                    'title' => $title,
                    'slug' => $slugStr,
                    'description' => $dictionary->serviceDescription($slug)."\n\n".$this->usageNote($city['name'], $delivery),
                    'inclusions' => $dict['inclusions'],
                    'exclusions' => $dict['exclusions'],
                    'fulfillment_type' => $fulfillmentType,
                    'delivery_mode' => $delivery,
                    'price_model' => $priceModel,
                    'base_price' => $price,
                    'unit_label' => $unitLabel,
                    'min_quantity' => 1,
                    'max_quantity' => $priceModel === 'per_unit' ? [5, 10, 20, 50][mt_rand(0, 3)] : null,
                    'duration_minutes' => $duration,
                    'emergency_capable' => $isEmergency,
                    'emergency_surcharge' => $isEmergency ? [50000, 75000, 100000, 150000][mt_rand(0, 3)] : 0,
                    'warranty_days' => $warranty,
                    'status' => 'active',
                    'media' => json_encode(['cover' => $cover, 'gallery' => array_values(array_unique($gallery))]),
                    'attributes' => json_encode($this->attributes($slug, $city['name'])),
                    'is_demo' => true,
                    'created_at' => $now->copy()->subDays(mt_rand(5, 300)),
                    'updated_at' => $now,
                ];

                // Packages for package-friendly services (~70%)
                if (mt_rand(1, 100) <= 70) {
                    foreach ($dict['packages'] as $pi => $pkg) {
                        $packageRows[] = [
                            'name' => $pkg['name'],
                            'description' => $pkg['desc'],
                            'price' => max(10000, (int) round($price * $pkg['mult'] / 1000) * 1000),
                            'duration_minutes' => $duration !== null ? (int) ($duration * ($pi + 1)) : null,
                            'inclusions' => json_encode([$dict['inclusions']]),
                            'is_default' => $pi === 1,
                            'sort' => $pi,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $packageServiceIdx[] = $genIndexCurrent;
                    }
                }

                // Addons (~55%, 1-3 each)
                if (mt_rand(1, 100) <= 55) {
                    $addonCount = mt_rand(1, 3);
                    for ($a = 0; $a < $addonCount; $a++) {
                        [$addonName, $lo, $hi] = $dictionary->addonFor($slug);
                        $addonRows[] = [
                            'name' => $addonName,
                            'description' => null,
                            'price' => mt_rand($lo, $hi),
                            'unit' => $unitLabel,
                            'is_active' => true,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $addonServiceIdx[] = $genIndexCurrent;
                    }
                }

                $serviceMeta[] = [
                    'partner_id' => $partner['id'],
                    'category_slug' => $slug,
                    'price_model' => $priceModel,
                    'price' => $price,
                    'fulfillment_type' => $fulfillmentType,
                    'delivery_mode' => $delivery,
                    'duration' => $duration,
                    'emergency_capable' => $isEmergency,
                ];
            }
        }

        $bar?->finish();
        $this->command?->getOutput()->writeln('');

        // Chunked inserts (500 rows — safe InnoDB packet size)
        $serviceIds = [];
        foreach (array_chunk($serviceRows, 500) as $chunk) {
            DB::table('services')->insert($chunk);
        }

        // Positional id resolution: inserted in generation order
        $idList = DB::table('services')->where('is_demo', true)->orderBy('id')->pluck('id')->all();
        $startAt = count($idList) - count($serviceRows);
        $newIds = array_slice($idList, $startAt);

        foreach ($packageRows as $i => $row) {
            $packageRows[$i]['service_id'] = $newIds[$packageServiceIdx[$i]];
        }
        foreach ($addonRows as $i => $row) {
            $addonRows[$i]['service_id'] = $newIds[$addonServiceIdx[$i]];
        }

        foreach (array_chunk($packageRows, 500) as $chunk) {
            DB::table('service_packages')->insert($chunk);
        }
        foreach (array_chunk($addonRows, 500) as $chunk) {
            DB::table('service_addons')->insert($chunk);
        }

        foreach ($newIds as $i => $id) {
            $serviceMeta[$i]['id'] = $id;
        }

        return $serviceMeta;
    }

    private function usageNote(string $cityName, string $delivery): string
    {
        return $delivery === 'remote'
            ? "Melayani {$cityName} dan sekitarnya secara remote/ daring."
            : "Melayani {$cityName} dan sekitarnya (onsite).";
    }

    private function attributes(string $slug, string $cityName): array
    {
        return [
            'area' => $cityName,
            'demo_seed' => true,
            'category_key' => $slug,
        ];
    }
}
