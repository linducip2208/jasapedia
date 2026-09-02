<?php

namespace Database\Seeders\Demo;

use App\Domain\Ledger\LedgerService;
use App\Support\Demo\DemoContext;
use App\Support\Demo\DemoOrderOrchestrator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Demo dataset orchestrator. Invoked by `jasapedia:seed-demo` (or opt-in from
 * DatabaseSeeder in local/demo + DEMO_DATA_ENABLED=true). Deterministic via
 * mt_srand; every row tagged is_demo for surgical --fresh-demo cleanup.
 */
class DemoDataSeeder extends Seeder
{
    public function run(?array $counts = null): void
    {
        // Deterministic seeding: same counts ⇒ same data shape (prompt §6)
        mt_srand(20260901);

        DB::statement('SET SESSION sql_mode = "NO_ENGINE_SUBSTITUTION"');

        $defaults = [
            'services' => (int) config('demo.services', 10000),
            'providers' => (int) config('demo.defaults.providers', 2500),
            'customers' => (int) config('demo.defaults.customers', 5000),
            'orders' => (int) config('demo.defaults.orders', 3000),
            'projects' => (int) config('demo.defaults.projects', 500),
            'rfqs' => (int) config('demo.defaults.rfqs', 500),
            'reviews' => (int) config('demo.defaults.reviews', 7000),
            'corporates' => (int) config('demo.defaults.corporates', 50),
        ];

        $counts = array_merge($defaults, $counts ?? []);
        $counts = array_map('intval', $counts);

        DB::disableQueryLog();

        $ctx = DemoContext::load(
            (string) config('demo.email_domain', 'example.test'),
            // ONE bcrypt total for thousands of users (prompt §21)
            Hash::make('password'),
        );

        $this->command?->getOutput()->writeln('<fg=cyan>Seeding demo dataset (seed '.config('demo.seed').')…</>');

        // 0. Demo media pool (covers, avatars, category banners) — must exist first
        $this->step(DemoMediaSeeder::class, 'media');

        // 1. Users (customers + provider owners + fixed demo logins)
        $userResult = $this->step(DemoUserSeeder::class, 'users', $ctx, $counts['customers'], $counts['providers']);
        $customerIds = $userResult['customerIds'];
        $providerIds = $userResult['providerIds'];

        // 2. Partners
        $partnerMap = $this->step(DemoPartnerSeeder::class, 'partners', $ctx, $counts['providers'], $providerIds);

        // 3. Services — EXACT target count assertion
        $serviceMeta = $this->step(DemoServiceSeeder::class, 'services', $ctx, $counts['services'], $partnerMap);

        $demoServiceCount = (int) (\DB::table('services')->where('is_demo', true)->count());
        if ($demoServiceCount !== $counts['services']) {
            throw new \RuntimeException("Service count assertion failed: expected {$counts['services']}, got {$demoServiceCount}.");
        }

        // 4. Availability (schedules + blocks)
        $this->step(DemoAvailabilitySeeder::class, 'availability', $ctx, $partnerMap);

        // 5. Orders via domain pipeline (finance-complete subset)
        $orderResult = $this->step(DemoOrderSeeder::class, 'orders', $ctx, $counts['orders'], $partnerMap, $serviceMeta, $customerIds, $userResult['fixed']);

        // 6. Reviews on eligible orders (+ historical backfill orders)
        $this->step(DemoReviewSeeder::class, 'reviews', $ctx, $counts['reviews'], $customerIds);

        // 7. Projects + proposals + contracts + milestones
        $this->step(DemoProjectSeeder::class, 'projects', $ctx, $counts['projects'], $customerIds, $partnerMap);

        // 8. RFQs + quotations
        $this->step(DemoRfqSeeder::class, 'rfqs', $ctx, $counts['rfqs'], $customerIds, $partnerMap);

        // 9. Corporate orgs
        $this->step(DemoCorporateSeeder::class, 'corporates', $ctx, $counts['corporates'], $customerIds);

        // 10. CMS content
        $this->step(DemoContentSeeder::class, 'content', $ctx);

        // 11. Hard invariant: ledger must be balanced
        DemoOrderOrchestrator::assertLedgerBalanced(app(LedgerService::class));

        // 12. Media coverage report
        $this->reportMediaCoverage();

        $this->command?->getOutput()->writeln('<fg=green>Demo dataset seeded.</>');
    }

    private function reportMediaCoverage(): void
    {
        $total = 0;
        $withCover = 0;
        $withGallery = 0;
        $galleryAssoc = 0;

        DB::table('services')->where('is_demo', true)->orderBy('id')->select('id', 'media')
            ->chunkById(500, function ($rows) use (&$total, &$withCover, &$withGallery, &$galleryAssoc) {
                foreach ($rows as $row) {
                    $total++;
                    $media = json_decode((string) $row->media, true);
                    if (! empty($media['cover'])) {
                        $withCover++;
                    }
                    $gallery = $media['gallery'] ?? [];
                    $galleryAssoc += count($gallery);
                    if (count($gallery) >= 1) {
                        $withGallery++;
                    }
                }
            });

        $avatars = DB::table('partners')->where('is_demo', true)->where('avatar_path', 'like', 'demo/providers/%')->count();
        $partners = DB::table('partners')->where('is_demo', true)->count();

        $this->command?->getOutput()->writeln(sprintf(
            '   Demo media pools ..... OK (%s files)',
            number_format($this->assetCount()),
        ));
        $this->command?->getOutput()->writeln(sprintf(
            '   Service covers ....... %s / %s',
            number_format($withCover),
            number_format($total),
        ));
        $this->command?->getOutput()->writeln(sprintf(
            '   Gallery associations . %s (services with gallery: %s / %s)',
            number_format($galleryAssoc),
            number_format($withGallery),
            number_format($total),
        ));
        $this->command?->getOutput()->writeln(sprintf(
            '   Provider avatars ..... %s / %s',
            number_format($avatars),
            number_format($partners),
        ));
    }

    private function assetCount(): int
    {
        if (! is_dir(public_path('demo'))) {
            return 0;
        }

        $count = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(public_path('demo'))) as $file) {
            $count += $file->isFile() ? 1 : 0;
        }

        return $count;
    }

    public function step(string $class, string $label, ...$args): mixed
    {
        $seeder = $this->bootSeeder($class);
        $this->command?->getOutput()->writeln('<info>  → '.ucfirst($label).'…</info>');

        return $seeder->run(...$args);
    }

    public function bootSeeder(string $class): object
    {
        $seeder = app($class);
        if ($seeder instanceof Seeder && $this->command !== null) {
            $seeder->setCommand($this->command);
        }

        return $seeder;
    }
}
