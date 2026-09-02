<?php

namespace Database\Seeders\Demo;

use App\Support\Demo\DemoContext;
use App\Support\Demo\DemoDictionary;
use App\Support\Demo\DemoMediaPool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ~500 RFQ (posting kebutuhan) + 0-5 quotations each, spread across
 * open / closed / awarded / cancelled. Quotation invariants mirror
 * RfqService::submitQuotation + approveQuotation (version 1, totals =
 * subtotal âˆ’ discount + tax; approved â‡’ RFQ awarded, siblings superseded).
 */
class DemoRfqSeeder extends Seeder
{
    public function run(DemoContext $ctx, int $rfqs, array $customerIds, array $partnerMap): array
    {
        $existing = DB::table('rfqs')->where('is_demo', true)->count();
        $toCreate = max(0, $rfqs - $existing);
        if ($toCreate === 0) {
            return [];
        }

        $bar = $this->command?->getOutput()->createProgressBar($toCreate);
        $bar?->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar?->setMessage('RFQs');
        $bar?->start();

        $dictionary = app(DemoDictionary::class);
        $now = now();
        $rows = [];
        $meta = [];
        $seq = (int) (DB::table('rfqs')->max('id') ?? 0);

        for ($i = 0; $i < $toCreate; $i++) {
            $seq++;
            $catSlug = array_keys(DemoDictionary::SERVICE_WEIGHTS)[$seq % 21];
            $pool = $dictionary->rfqPool($catSlug);
            $spec = $pool[($seq * 3) % max(1, count($pool))];
            $customerId = $customerIds[$seq % count($customerIds)];

            // open 40% | closed 15% | awarded 20% | cancelled 5% | open-no-quote 20%
            $roll = $seq % 20;
            $status = match (true) {
                $roll < 8 => 'open',
                $roll < 11 => 'closed',
                $roll < 15 => 'awarded',
                $roll < 16 => 'cancelled',
                default => 'open',
            };

            $when = $now->copy()->subDays(mt_rand(2, 120));

            $rows[] = [
                'code' => 'RFQ-'.$when->format('ymd').'-'.strtoupper(Str::random(5)).$seq,
                'user_id' => $customerId,
                'category_id' => $ctx->categoryIds[$catSlug],
                'title' => $spec[0],
                'description' => $spec[1],
                'requirements' => json_encode([
                    'kebutuhan' => $spec[0],
                    'lokasi' => $ctx->randomCity()['name'],
                    'target_mulai' => $now->copy()->addDays(mt_rand(7, 45))->toDateString(),
                    'demo_seed' => true,
                ]),
                // ~35% attach a demo reference image from the category pool
                'attachments' => mt_rand(1, 100) <= 35 ? json_encode([
                    ['name' => 'Foto kondisi/ referensi', 'path' => DemoMediaPool::forCategory($catSlug)[($seq * 7) % DemoMediaPool::COVERS_PER_CATEGORY]],
                ]) : null,
                'deadline' => $now->copy()->addDays(mt_rand(3, 30)),
                'visibility' => 'public',
                'status' => $status,
                'is_demo' => true,
                'created_at' => $when,
                'updated_at' => $when,
            ];

            $meta[] = ['status' => $status, 'seq' => $seq, 'when' => $when];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('rfqs')->insert($chunk);
            $bar?->advance(count($chunk));
        }
        $bar?->finish();
        $this->command?->getOutput()->writeln('');

        $rfqCount = $this->seedQuotations($ctx, $meta, $partnerMap, $customerIds);

        return ['rfqs' => $toCreate, 'quotationGroups' => $rfqCount];
    }

    private function seedQuotations(DemoContext $ctx, array $meta, array $partnerMap, array $customerIds): int
    {
        $rows = [];
        $now = now();

        $rfqs = DB::table('rfqs')->where('is_demo', true)->orderBy('id')->get(['id', 'user_id', 'status', 'created_at']);
        $seq = (int) (DB::table('quotations')->max('id') ?? 0);

        foreach ($rfqs as $idx => $rfq) {
            $info = $meta[$idx] ?? null;
            if (! $info) {
                continue;
            }

            // 0 quotation 15% | 1 quotation 25% | 2-5 quotations 60%
            $roll = $info['seq'] % 20;
            if ($roll < 3) {
                continue; // 0 quotations
            }

            $count = $roll < 8 ? 1 : mt_rand(2, 5);
            $winnerIdx = mt_rand(0, $count - 1);
            $usedPartners = [];
            $hasRows = false;

            for ($q = 0; $q < $count; $q++) {
                $seq++;
                $partner = $partnerMap[($info['seq'] * 11 + $q * 53) % count($partnerMap)];
                if (in_array($partner['id'], $usedPartners, true)) {
                    continue;
                }
                $usedPartners[] = $partner['id'];
                $hasRows = true;

                $isWinner = $info['status'] === 'awarded' && $q === $winnerIdx;

                $price = mt_rand(300000, 8000000);
                $discount = $isWinner ? (int) round($price * 0.05 / 1000) * 1000 : 0;
                $subtotal = $price;
                $total = max(0, $subtotal - $discount);

                $status = $isWinner ? 'approved' : match ($info['status']) {
                    'awarded' => 'superseded',
                    'closed' => ['sent', 'rejected'][mt_rand(0, 1)],
                    'cancelled' => 'sent',
                    default => 'sent',
                };

                $rows[] = [
                    'code' => 'QUO-'.$info['when']->format('ymd').'-'.strtoupper(Str::random(5)).$seq,
                    'rfq_id' => $rfq->id,
                    'order_id' => null,
                    'partner_id' => $partner['id'],
                    'customer_id' => $rfq->user_id,
                    'version' => 1,
                    'line_items' => json_encode([
                        ['name' => 'Pekerjaan sesuai lingkup RFQ', 'qty' => 1, 'unit_price' => $subtotal, 'amount' => $subtotal],
                    ]),
                    'subtotal' => $subtotal,
                    'tax' => 0,
                    'discount' => $discount,
                    'total' => $total,
                    'terms' => 'Pengerjaan dimulai 7 hari kerja setelah deal. Garansi pengerjaan 30 hari.',
                    'valid_until' => $info['when']->copy()->addDays(21),
                    'status' => $status,
                    'approved_by' => $status === 'approved' ? $rfq->user_id : null,
                    'decided_at' => in_array($status, ['approved', 'rejected'], true) ? $info['when']->copy()->addDays(mt_rand(1, 10)) : null,
                    'is_demo' => true,
                    'created_at' => $info['when'],
                    'updated_at' => $now,
                ];
            }

            // An RFQ with quotations but 0 rows can't happen (skipped above)
            if (! $hasRows && $info['status'] === 'awarded') {
                DB::table('rfqs')->where('id', $rfq->id)->update(['status' => 'open']);
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('quotations')->insert($chunk);
        }

        return count($rows);
    }
}
