<?php

namespace Database\Seeders\Demo;

use App\Domain\Corporate\CorporateService;
use App\Models\CorporateOrganization;
use App\Models\User;
use App\Support\Demo\DemoContext;
use App\Support\Demo\DemoNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ~50 corporate organizations with branches, departments, cost centers,
 * employees, budgets, approval policies and service requests (CSR flow via
 * CorporateService + approvals; a few carry PO references).
 */
class DemoCorporateSeeder extends Seeder
{
    private const REQUEST_TEMPLATES = [
        'ac-electronics' => ['Maintenance AC unit lantai 3', 'Cek dan cuci 12 unit AC area kerja.'],
        'cleaning' => ['Cleaning kantor area lantai 1-2', 'Deep cleaning ruang meeting dan pantry.'],
        'technology-programming' => ['IT support untuk migrasi jaringan', 'Dukungan teknis pemindahan server kantor pusat.'],
        'cctv-security' => ['Maintenance CCTV 32 kamera', 'Pengecekan rutin seluruh kamera dan NVR.'],
        'renovation' => ['Renovasi ruang meeting lantai 5', 'Partisi baru, cat, dan pencahayaan.'],
        'moving-logistics' => ['Pindahan arsip gudang dokumen', 'Pemindahan 200 karton arsip ke gudang baru.'],
        'event-services' => ['Annual gathering karyawan', 'Event tahunan 250 orang termasuk konsumsi.'],
    ];

    public function run(DemoContext $ctx, int $corporates, array $customerIds): int
    {
        $existing = CorporateOrganization::where('is_demo', true)->count();
        $toCreate = max(0, $corporates - $existing);
        if ($toCreate === 0) {
            return 0;
        }

        $bar = $this->command?->getOutput()->createProgressBar($toCreate);
        $bar?->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $bar?->setMessage('Corporate');
        $bar?->start();

        $orgIds = DB::table('users')->where('is_demo', true)->where('email', 'like', 'customer%')->orderBy('id')->pluck('id')->take($toCreate)->all();

        $orgRows = [];
        $i = 0;
        foreach ($orgIds as $idx => $ownerId) {
            $i++;
            $orgRows[] = [
                'owner_user_id' => $ownerId,
                'name' => DemoNames::company(),
                'npwp' => DemoNames::dummyNpwp($i * 97),
                'billing_email' => 'finance.corp'.$i.'@'.$ctx->emailDomain,
                'settings' => json_encode(['demo_seed' => true, 'tier' => ['sme', 'mid', 'enterprise'][$i % 3]]),
                'is_demo' => true,
                'created_at' => now()->subDays(mt_rand(30, 400)),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($orgRows, 500) as $chunk) {
            DB::table('corporate_organizations')->insert($chunk);
        }

        $orgs = CorporateOrganization::where('is_demo', true)->orderBy('id')->get(['id', 'owner_user_id', 'name']);

        $this->seedStructure($ctx, $orgs);
        $this->seedRequests($orgs, $orgIds);

        $bar?->finish();
        $this->command?->getOutput()->writeln('');

        return $orgs->count();
    }

    private function seedStructure(DemoContext $ctx, $orgs): void
    {
        $branchRows = [];
        $costCenterRows = [];
        $cityIds = array_column($ctx->cities, 'id');

        foreach ($orgs as $oi => $org) {
            $branchCount = mt_rand(2, 4);
            for ($b = 0; $b < $branchCount; $b++) {
                $branchRows[] = [
                    'organization_id' => $org->id,
                    'name' => ($b === 0 ? 'Kantor Pusat' : 'Cabang '.$b),
                    'city_id' => $cityIds[($oi + $b) % count($cityIds)],
                    'address' => 'Jl. Corporate Demo No. '.mt_rand(1, 150),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            for ($c = 0; $c < mt_rand(2, 4); $c++) {
                $costCenterRows[] = [
                    'organization_id' => $org->id,
                    'name' => ['GA', 'IT', 'Marketing', 'Operasional', 'HRD'][$c % 5],
                    'code' => 'CC-'.str_pad((string) ($org->id * 10 + $c), 4, '0', STR_PAD_LEFT),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($branchRows, 500) as $chunk) {
            DB::table('corporate_branches')->insert($chunk);
        }
        foreach (array_chunk($costCenterRows, 500) as $chunk) {
            DB::table('corporate_cost_centers')->insert($chunk);
        }

        $branches = DB::table('corporate_branches')->get(['id', 'organization_id']);
        $centers = DB::table('corporate_cost_centers')->get(['id', 'organization_id']);

        // Departments
        $deptRows = [];
        $centersByOrg = [];
        foreach ($centers as $center) {
            $centersByOrg[$center->organization_id][] = $center->id;
        }
        foreach ($orgs as $org) {
            $orgCenters = $centersByOrg[$org->id] ?? [];
            foreach (['HRD', 'Keuangan', 'Operasional', 'IT'] as $di => $name) {
                $deptRows[] = [
                    'organization_id' => $org->id,
                    'name' => $name,
                    'cost_center_id' => $orgCenters[$di % max(1, count($orgCenters))] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk($deptRows, 500) as $chunk) {
            DB::table('corporate_departments')->insert($chunk);
        }

        // Employees: owner + 3-8 members pulled from demo customers
        $employeeRows = [];
        $customerPool = DB::table('users')->where('is_demo', true)->where('email', 'like', 'customer%')->orderBy('id')->pluck('id')->all();
        $branchesByOrg = [];
        $deptsByOrg = [];
        foreach ($branches as $branch) {
            $branchesByOrg[$branch->organization_id][] = $branch->id;
        }
        $departments = DB::table('corporate_departments')->get(['id', 'organization_id']);
        foreach ($departments as $dept) {
            $deptsByOrg[$dept->organization_id][] = $dept->id;
        }

        foreach ($orgs as $oi => $org) {
            $roles = ['admin', 'manager', 'finance', 'employee', 'employee', 'employee'];
            $members = array_slice($customerPool, $oi * 5, mt_rand(3, 6));
            foreach ($members as $mi => $memberId) {
                $employeeRows[] = [
                    'organization_id' => $org->id,
                    'user_id' => $memberId,
                    'branch_id' => $branchesByOrg[$org->id][$mi % count($branchesByOrg[$org->id])] ?? null,
                    'department_id' => $deptsByOrg[$org->id][$mi % max(1, count($deptsByOrg[$org->id]))] ?? null,
                    'role' => $roles[$mi % count($roles)],
                    'spend_limit' => mt_rand(1, 50) * 500000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($employeeRows, 500) as $chunk) {
            DB::table('corporate_employees')->insertOrIgnore($chunk);
        }

        // Budgets (current month) per org
        $budgetRows = [];
        $period = now()->format('Y-m');
        foreach ($orgs as $org) {
            foreach ($centersByOrg[$org->id] ?? [null] as $centerId) {
                $budgetRows[] = [
                    'organization_id' => $org->id,
                    'cost_center_id' => $centerId,
                    'period' => $period,
                    'amount' => mt_rand(10, 200) * 1000000,
                    'used' => mt_rand(0, 40) * 1000000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk($budgetRows, 500) as $chunk) {
            DB::table('corporate_budgets')->insertOrIgnore($chunk);
        }

        // Approval policies: manager above 5jt, finance above 25jt
        $policyRows = [];
        foreach ($orgs as $org) {
            $policyRows[] = [
                'organization_id' => $org->id,
                'threshold' => mt_rand(3, 8) * 1000000,
                'finance_threshold' => mt_rand(15, 40) * 1000000,
                'require_category_approval' => false,
                'allowed_categories' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($policyRows, 500) as $chunk) {
            DB::table('corporate_approval_policies')->insertOrIgnore($chunk);
        }
    }

    private function seedRequests($orgs, array $orgIds): void
    {
        $service = app(CorporateService::class);
        $created = 0;

        foreach ($orgs as $oi => $org) {
            $employees = DB::table('corporate_employees')
                ->where('organization_id', $org->id)
                ->orderBy('id')->get(['user_id', 'role']);

            if ($employees->isEmpty()) {
                continue;
            }

            $branches = DB::table('corporate_branches')->where('organization_id', $org->id)->pluck('id')->all();
            $depts = DB::table('corporate_departments')->where('organization_id', $org->id)->pluck('id')->all();
            $centers = DB::table('corporate_cost_centers')->where('organization_id', $org->id)->pluck('id')->all();

            $requester = $employees->first();
            $requesterUser = User::find($requester->user_id);
            if (! $requesterUser) {
                continue;
            }

            $count = mt_rand(2, 5);
            for ($r = 0; $r < $count; $r++) {
                $catSlug = array_keys(self::REQUEST_TEMPLATES)[($oi + $r) % count(self::REQUEST_TEMPLATES)];
                [$title, $desc] = self::REQUEST_TEMPLATES[$catSlug];
                $catId = DB::table('categories')->where('slug', $catSlug)->value('id');
                $estimated = mt_rand(1, 60) * 500000;

                try {
                    $request = $service->createRequest($org, $requesterUser, [
                        'branch_id' => $branches !== [] ? $branches[array_rand($branches)] : null,
                        'department_id' => $depts !== [] ? $depts[array_rand($depts)] : null,
                        'cost_center_id' => $centers !== [] ? $centers[array_rand($centers)] : null,
                        'category_id' => $catId,
                        'title' => $title,
                        'description' => $desc,
                        'estimated_amount' => $estimated,
                    ]);

                    $created++;

                    // Approve through manager (and finance if large)
                    if (mt_rand(1, 100) <= 65) {
                        $manager = $employees->firstWhere('role', 'manager') ?? $requester;
                        $managerUser = User::find($manager->user_id);
                        if ($managerUser) {
                            try {
                                $request = $service->approve($request, $managerUser, 'manager');
                            } catch (\Throwable) {
                            }
                        }
                    }

                    if ($request->status === 'pending_finance' && mt_rand(1, 100) <= 70) {
                        $finance = $employees->firstWhere('role', 'finance');
                        $financeUser = $finance ? User::find($finance->user_id) : null;
                        if ($financeUser) {
                            try {
                                $service->approve($request, $financeUser, 'finance');
                            } catch (\Throwable) {
                            }
                        }
                    }

                    // PO reference on approved requests
                    if ($request->status === 'approved' && mt_rand(1, 100) <= 60) {
                        DB::table('corporate_service_requests')
                            ->where('id', $request->id)
                            ->update(['po_reference' => 'PO-'.now()->format('Y').'-'.str_pad((string) $created, 4, '0', STR_PAD_LEFT)]);
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        $this->command?->getOutput()->writeln("   CSR requests created: {$created}");
    }
}
