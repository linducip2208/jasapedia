<?php

namespace App\Console\Commands;

use App\Support\Demo\DemoDataWiper;
use Database\Seeders\Demo\DemoDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * jasapedia:seed-demo — production-quality demo dataset seeding.
 *
 * SAFETY:
 *  - Refuses to run when APP_ENV=production without BOTH --force and a typed confirmation.
 *  - Refuses when a demo dataset already exists (use --fresh-demo to wipe & reseed).
 *  - --fresh-demo deletes ONLY is_demo-tagged rows (see DemoDataWiper).
 */
class SeedDemoCommand extends Command
{
    protected $signature = 'jasapedia:seed-demo
                            {--services=10000 : Jumlah service listing demo (target utama)}
                            {--providers=2500 : Jumlah provider demo}
                            {--customers=5000 : Jumlah customer demo}
                            {--orders=3000 : Jumlah order demo}
                            {--projects=500 : Jumlah project marketplace demo}
                            {--rfqs=500 : Jumlah RFQ demo}
                            {--reviews=7000 : Jumlah review demo}
                            {--corporates=50 : Jumlah corporate organization demo}
                            {--fresh-demo : Hapus data demo lama (is_demo=1) sebelum seed}
                            {--force : Wajib untuk menjalankan di environment production}';

    protected $description = 'Seed dataset demo Jasapedia (10.000 service listing + data pendukung). TIDAK UNTUK PRODUCTION.';

    public function handle(): int
    {
        // ---- Production guard (prompt §3) ----
        if (app()->environment('production')) {
            if (! $this->option('force')) {
                $this->error('DILARANG: demo seed tidak boleh dijalankan di production tanpa --force.');

                return self::FAILURE;
            }

            if (! $this->confirm('APP_ENV=production. Demo data akan tercatat di database production. Lanjutkan? Ketik ya untuk konfirmasi.')) {
                $this->warn('Dibatalkan.');

                return self::FAILURE;
            }
        }

        $counts = $this->counts();

        // ---- Idempotency (prompt §22) ----
        $existingDemo = (int) DB::table('services')->where('is_demo', true)->count();
        $existingUsers = (int) DB::table('users')->where('is_demo', true)->count();

        if (($existingDemo > 0 || $existingUsers > 0) && ! $this->option('fresh-demo')) {
            $this->error("Dataset demo sudah ada ({$existingDemo} services, {$existingUsers} users).");
            $this->line('Gunakan <fg=yellow>--fresh-demo</> untuk menghapus data demo lalu seed ulang, atau naikkan target count untuk menambah data.');

            return self::FAILURE;
        }

        if ($this->option('fresh-demo')) {
            if (! app()->environment('production') || $this->option('force')) {
                $this->warn('Menghapus data demo lama (is_demo = 1)…');
                DemoDataWiper::wipe();
                $this->info('Data demo lama dihapus. Data produksi/customer asli tidak tersentuh.');
            }
        }

        $this->info('Mulai seeding demo dataset…');

        $started = microtime(true);

        $seeder = app(DemoDataSeeder::class);
        $seeder->setCommand($this);
        $seeder->run($counts);

        $elapsed = round(microtime(true) - $started, 1);

        $this->renderSummary($counts, $elapsed);

        if (! app()->environment('production')) {
            $this->renderDemoCredentials();
        }

        return self::SUCCESS;
    }

    private function counts(): array
    {
        return [
            'services' => max(0, (int) $this->option('services')),
            'providers' => max(0, (int) $this->option('providers')),
            'customers' => max(0, (int) $this->option('customers')),
            'orders' => max(0, (int) $this->option('orders')),
            'projects' => max(0, (int) $this->option('projects')),
            'rfqs' => max(0, (int) $this->option('rfqs')),
            'reviews' => max(0, (int) $this->option('reviews')),
            'corporates' => max(0, (int) $this->option('corporates')),
        ];
    }

    private function renderSummary(array $counts, float $elapsed): void
    {
        // Category table (prompt §25)
        $rows = DB::table('services')
            ->join('categories', 'categories.id', '=', 'services.category_id')
            ->where('services.is_demo', true)
            ->groupBy('categories.id')
            ->orderBy('categories.sort')
            ->selectRaw('categories.name, COUNT(*) as c')
            ->pluck('c', 'name');

        $this->newLine();
        $this->line('Category                          Services');
        $this->line('------------------------------------------');
        foreach ($rows as $name => $count) {
            $this->line(str_pad($name, 34).' '.str_pad((string) $count, 7, ' ', STR_PAD_LEFT));
        }
        $this->line('------------------------------------------');
        $this->line(str_pad('TOTAL', 34).' '.str_pad((string) $rows->sum(), 7, ' ', STR_PAD_LEFT));

        $this->newLine();

        $summary = [
            ['Services', DB::table('services')->where('is_demo', true)->count()],
            ['Customers (incl. bulk)', DB::table('users')->where('is_demo', true)->where('email', 'like', 'customer%')->count()],
            ['Providers', DB::table('partners')->where('is_demo', true)->count()],
            ['Reviews', DB::table('reviews')->where('is_demo', true)->count()],
            ['Orders', DB::table('orders')->where('is_demo', true)->count()],
            ['Projects', DB::table('projects')->where('is_demo', true)->count()],
            ['Proposals', DB::table('proposals')->whereExists(fn ($q) => $q->select(DB::raw(1))->from('projects')->whereColumn('projects.id', 'proposals.project_id')->where('projects.is_demo', true))->count()],
            ['RFQs', DB::table('rfqs')->where('is_demo', true)->count()],
            ['Quotations', DB::table('quotations')->whereExists(fn ($q) => $q->select(DB::raw(1))->from('rfqs')->whereColumn('rfqs.id', 'quotations.rfq_id')->where('rfqs.is_demo', true))->count()],
            ['Corporates', DB::table('corporate_organizations')->where('is_demo', true)->count()],
            ['Time (s)', $elapsed],
        ];

        $this->table(['Entity', 'Count'], $summary);
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

    private function renderDemoCredentials(): void
    {
        $this->newLine();
        $this->line('<fg=yellow>Demo accounts (LOCAL/DEMO ONLY — jangan tampilkan di production):</>');
        $this->line('  Customer  : customer@jasapedia.test  / password');
        $this->line('  Provider  : provider@jasapedia.test  / password');
        $this->line('  Company   : company@jasapedia.test    / password');
        $this->line('  Corporate : corporate@jasapedia.test  / password');
        $this->line('  Admin     : admin@jasapedia.test       / password (InitialAdminSeeder)');
    }
}
