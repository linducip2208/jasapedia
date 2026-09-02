<?php

namespace Database\Seeders\Demo;

use App\Models\Contract;
use App\Models\Milestone;
use App\Models\Project;
use App\Support\Demo\DemoContext;
use App\Support\Demo\DemoDictionary;
use App\Support\Demo\DemoMediaPool;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ~500 marketplace projects (Technology/Design/Marketing/Business/Accounting/
 * Legal/Photography/Event/Construction) with proposals, and for awarded
 * projects: contract + milestones (+ submissions via status progression).
 * Uses ProjectService-VALID states; contract/milestone rows match the
 * domain's state shapes (no order funding â€” those stay 'ready').
 */
class DemoProjectSeeder extends Seeder
{
    private const PROJECT_CATEGORIES = [
        'technology-programming', 'design-creative', 'digital-marketing',
        'business-consulting', 'accounting-tax', 'legal', 'photography',
        'event-services', 'construction', 'renovation',
    ];

    public function run(DemoContext $ctx, int $projects, array $customerIds, array $partnerMap): array
    {
        $existing = Project::where('is_demo', true)->count();
        $toCreate = max(0, $projects - $existing);
        if ($toCreate === 0) {
            return [];
        }

        $bar = $this->command?->getOutput()->createProgressBar($toCreate);
        $bar?->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar?->setMessage('Projects');
        $bar?->start();

        $dictionary = app(DemoDictionary::class);
        $now = now();
        $rows = [];
        $meta = [];
        $seq = (int) (DB::table('projects')->max('id') ?? 0);

        for ($i = 0; $i < $toCreate; $i++) {
            $seq++;
            $catSlug = self::PROJECT_CATEGORIES[$seq % count(self::PROJECT_CATEGORIES)];
            $pool = $dictionary->projectPool($catSlug);
            $spec = $pool[$seq % count($pool)];
            $customerId = $customerIds[$seq % count($customerIds)];

            // Status spread: open 35% | proposal_received 20% | awarded 15% | in_progress 15% | completed 15%
            $roll = $seq % 20;
            $status = match (true) {
                $roll < 7 => 'receiving_proposals',
                $roll < 11 => 'shortlisting',
                $roll < 14 => 'awarded',
                $roll < 17 => 'active',
                default => 'completed',
            };

            $when = $now->copy()->subDays(mt_rand(10, 200));
            [$lo, $hi] = $dictionary->priceFor($catSlug, 'quotation') > 0
                ? $this->budgetRange($dictionary, $catSlug)
                : [1000000, 50000000];

            $code = 'PRJ-'.$when->format('ymd').'-'.strtoupper(Str::random(5)).$seq;

            $rows[] = [
                'code' => $code,
                'user_id' => $customerId,
                'category_id' => $ctx->categoryIds[$catSlug],
                'title' => $spec[0].' â€” '.ucfirst(str_replace('-', ' ', $catSlug)),
                'description' => $spec[1],
                'requirements' => json_encode([
                    'lokasi' => $ctx->randomCity()['name'],
                    'timeline' => ['2 minggu', '1 bulan', '2-3 bulan', 'fleksibel'][mt_rand(0, 3)],
                    'demo_seed' => true,
                ]),
                'skills' => json_encode(array_slice($dictionary->skillsFor($catSlug), 0, mt_rand(2, 4))),
                'budget_type' => 'range',
                'budget_min' => $lo,
                'budget_max' => $hi,
                'deadline' => $now->copy()->addDays(mt_rand(14, 120)),
                // ~40% carry a demo reference image (pool asset of the category)
                'attachments' => mt_rand(1, 100) <= 40 ? json_encode([
                    ['name' => 'Referensi visual kebutuhan', 'path' => DemoMediaPool::forCategory($catSlug)[($seq * 3) % DemoMediaPool::COVERS_PER_CATEGORY]],
                ]) : null,
                'visibility' => 'public',
                'status' => $status,
                'is_demo' => true,
                'created_at' => $when,
                'updated_at' => $when,
            ];

            $meta[] = ['status' => $status, 'catSlug' => $catSlug, 'customerId' => $customerId, 'seq' => $seq];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('projects')->insert($chunk);
            $bar?->advance(count($chunk));
        }
        $bar?->finish();
        $this->command?->getOutput()->writeln('');

        $projectsCreated = DB::table('projects')->where('is_demo', true)->orderBy('id')->get(['id', 'user_id', 'title', 'status', 'budget_min', 'budget_max']);
        $proposalCounts = $this->seedProposals($ctx, $projectsCreated, $meta, $partnerMap);

        $this->seedContractsAndMilestones($projectsCreated, $meta, $proposalCounts);

        return $projectsCreated->all();
    }

    private function budgetRange(DemoDictionary $dictionary, string $catSlug): array
    {
        $base = $dictionary->priceFor($catSlug, 'quotation');
        $min = max(500000, (int) round($base / 1000000) * 1000000);
        $max = $min * mt_rand(2, 5);

        return [$min, $max];
    }

    private function seedProposals(DemoContext $ctx, $projects, array $meta, array $partnerMap): array
    {
        $rows = [];
        $decisions = [];
        $now = now();

        foreach ($projects as $idx => $project) {
            $info = $meta[$idx] ?? null;
            if (! $info) {
                continue;
            }

            // open: 0-3 proposals; others: 2-6
            $count = $info['status'] === 'receiving_proposals' ? mt_rand(0, 3) : mt_rand(2, 6);
            if ($count === 0) {
                $decisions[$project->id] = [];

                continue;
            }

            $winnerIdx = mt_rand(0, $count - 1);
            $usedPartners = [];

            for ($p = 0; $p < $count; $p++) {
                $partner = $partnerMap[($info['seq'] * 7 + $p * 31) % count($partnerMap)];
                if (in_array($partner['id'], $usedPartners, true)) {
                    $partner = $partnerMap[($info['seq'] + $p) % count($partnerMap)];
                }
                $usedPartners[] = $partner['id'];

                $isWinner = $info['status'] !== 'receiving_proposals' && $p === $winnerIdx;
                $price = mt_rand(max(500000, (int) $project->budget_min), max(1000000, (int) $project->budget_max));

                $status = match ($info['status']) {
                    'receiving_proposals' => 'submitted',
                    'shortlisting' => $isWinner ? 'shortlisted' : 'submitted',
                    'awarded', 'active', 'completed', 'contracting' => $isWinner ? 'accepted' : 'rejected',
                    default => 'submitted',
                };

                $rows[] = [
                    'project_id' => $project->id,
                    'rfq_id' => null,
                    'partner_id' => $partner['id'],
                    'cover_letter' => 'Kami berpengalaman menangani kebutuhan serupa. Berikut rencana kerja dan penawaran kami.',
                    'technical_approach' => 'Tahap 1: analisis kebutuhan. Tahap 2: pengerjaan dengan checkpoint mingguan. Tahap 3: serah terima + garansi.',
                    'price' => $price,
                    'timeline_days' => mt_rand(14, 120),
                    'deliverables' => json_encode(['Dokumen lengkap', 'Hasil kerja final', 'Pendampingan pasca serah terima']),
                    'milestone_plan' => json_encode([
                        ['title' => 'Tahap 1 â€” Kickoff & analisis', 'amount' => (int) round($price * 0.3)],
                        ['title' => 'Tahap 2 â€” Pengerjaan', 'amount' => (int) round($price * 0.5)],
                        ['title' => 'Tahap 3 â€” Serah terima', 'amount' => (int) round($price * 0.2)],
                    ]),
                    'warranty_days' => 30,
                    'valid_until' => $now->copy()->addDays(30),
                    'status' => $status,
                    'is_demo' => true,
                    'created_at' => $now->copy()->subDays(mt_rand(5, 90)),
                    'updated_at' => $now,
                ];

                $decisions[$project->id][] = ['status' => $status, 'partner_id' => $partner['id'], 'price' => $price];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('proposals')->insert($chunk);
        }

        return $decisions;
    }

    private function seedContractsAndMilestones($projects, array $meta, array $proposalCounts): void
    {
        $contractRows = [];
        $contractMeta = [];
        $now = now();

        foreach ($projects as $idx => $project) {
            $info = $meta[$idx] ?? null;
            if (! $info || ! in_array($info['status'], ['active', 'completed', 'contracting'], true)) {
                continue;
            }

            $proposals = $proposalCounts[$project->id] ?? [];
            $accepted = null;
            foreach ($proposals as $p) {
                if ($p['status'] === 'accepted') {
                    $accepted = $p;
                    break;
                }
            }
            if (! $accepted) {
                continue;
            }

            $when = $now->copy()->subDays(mt_rand(5, 60));

            $contractRows[] = [
                'code' => 'CTR-'.$when->format('ymd').'-'.strtoupper(Str::random(5)),
                'project_id' => $project->id,
                'partner_id' => $accepted['partner_id'],
                'customer_id' => $info['customerId'],
                'proposal_id' => null,
                'version' => 1,
                'scope' => json_encode(['description' => Str::limit($project->title, 180)]),
                'deliverables' => json_encode(['Deliverable per milestone']),
                'price' => $accepted['price'],
                'payment_terms' => 'Per milestone',
                'milestone_plan' => json_encode([
                    ['title' => 'Tahap 1 â€” Kickoff & analisis', 'amount' => (int) round($accepted['price'] * 0.3)],
                    ['title' => 'Tahap 2 â€” Pengerjaan', 'amount' => (int) round($accepted['price'] * 0.5)],
                    ['title' => 'Tahap 3 â€” Serah terima', 'amount' => (int) round($accepted['price'] * 0.2)],
                ]),
                'revision_limit' => 2,
                'warranty_days' => 30,
                'ip_terms' => 'Full IP transfer upon full payment.',
                'status' => 'accepted',
                'customer_accepted_at' => $when,
                'partner_accepted_at' => $when,
                'is_demo' => true,
                'created_at' => $when,
                'updated_at' => $when,
            ];

            $contractMeta[] = ['projectStatus' => $info['status'], 'when' => $when, 'price' => $accepted['price']];
        }

        foreach (array_chunk($contractRows, 500) as $chunk) {
            DB::table('contracts')->insert($chunk);
        }

        $contracts = DB::table('contracts')->whereNotNull('project_id')->orderBy('id')->get(['id', 'project_id', 'milestone_plan']);

        $milestoneRows = [];
        foreach ($contracts as $ci => $contract) {
            $info = $contractMeta[$ci] ?? null;
            if (! $info) {
                continue;
            }

            $plan = json_decode((string) $contract->milestone_plan, true) ?: [];
            $total = max(1, count($plan));

            foreach ($plan as $mi => $m) {
                // 'released' requires funded milestone_funding orders + ledger â€”
                // demo stops at 'approved' (no fake money movement).
                $progress = $info['projectStatus'] === 'completed'
                    ? 'approved'
                    : ($info['projectStatus'] === 'active'
                        ? ($mi === 0 ? (mt_rand(1, 2) === 1 ? 'approved' : 'submitted') : ($mi === 1 ? 'in_progress' : 'ready'))
                        : 'ready');

                $milestoneRows[] = [
                    'contract_id' => $contract->id,
                    'title' => $m['title'],
                    'description' => null,
                    'amount' => (int) $m['amount'],
                    'deadline' => $info['when']->copy()->addDays(30 * ($mi + 1)),
                    'sort' => $mi,
                    'status' => $progress,
                    'submitted_at' => in_array($progress, ['submitted', 'approved'], true) ? $info['when'] : null,
                    'approved_at' => $progress === 'approved' ? $info['when'] : null,
                    'is_demo' => true,
                    'created_at' => $info['when'],
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($milestoneRows, 500) as $chunk) {
            DB::table('milestones')->insert($chunk);
        }
    }
}
