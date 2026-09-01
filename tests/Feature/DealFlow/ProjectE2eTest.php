<?php

namespace Tests\Feature\DealFlow;

use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRITICAL E2E (§127): post project → proposals → shortlist → award →
 * contract → milestone funding → work → revision → approval → release → review-ready.
 */
class ProjectE2eTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\LocationSeeder::class);
        $this->seed(CatalogSeeder::class);
    }

    private function setupActors(): array
    {
        $customer = $this->postJson('/api/v1/auth/register', [
            'name' => 'Owner', 'email' => 'owner@test.id', 'password' => 'RahasiaKuat99',
        ]);
        $customerToken = $customer->json('data.token');

        $partner = $this->postJson('/api/v1/auth/register', [
            'name' => 'Dev', 'email' => 'dev@test.id', 'password' => 'RahasiaKuat99',
        ]);
        $partnerToken = $partner->json('data.token');

        $this->withToken($partnerToken)->postJson('/api/v1/partner', [
            'type' => 'freelancer', 'display_name' => 'Dev Studio',
        ]);
        \App\Models\Partner::query()->update(['verification_state' => 'verified']);

        return [$customerToken, $partnerToken];
    }

    public function test_full_project_lifecycle(): void
    {
        [$customerToken, $partnerToken] = $this->setupActors();
        $catId = \App\Models\Category::where('slug', 'technology-programming')->value('id');

        // 1. Post project
        $project = $this->withToken($customerToken)->postJson('/api/v1/projects', [
            'category_id' => $catId,
            'title' => 'Website Company Profile',
            'description' => 'Butuh website company profile dengan CMS.',
            'requirements' => ['Laravel', 'Responsive', 'SEO ready'],
            'skills' => ['laravel', 'tailwindcss'],
            'budget_type' => 'range',
            'budget_min' => 5000000,
            'budget_max' => 10000000,
            'deadline' => now()->addMonth()->toDateString(),
        ]);
        $project->assertCreated()->assertJsonPath('data.project.status', 'receiving_proposals');
        $projectId = $project->json('data.project.id');

        // 2. Vendor finds it & submits proposal
        $feed = $this->withToken($partnerToken)->getJson('/api/v1/partner/deals/projects');
        $feed->assertOk()->assertJsonCount(1, 'data');

        $proposal = $this->withToken($partnerToken)->postJson('/api/v1/partner/deals/proposals', [
            'project_id' => $projectId,
            'cover_letter' => 'Saya punya pengalaman 50+ project Laravel.',
            'price' => 8000000,
            'timeline_days' => 30,
            'deliverables' => ['Website + CMS', 'Training'],
            'milestone_plan' => [
                ['title' => 'Design & Setup', 'amount' => 3000000],
                ['title' => 'Development', 'amount' => 3500000],
                ['title' => 'Deploy & Training', 'amount' => 1500000],
            ],
            'warranty_days' => 30,
        ]);
        $proposal->assertCreated()->assertJsonPath('data.proposal.status', 'submitted');
        $proposalId = $proposal->json('data.proposal.id');

        // Duplicate proposal blocked
        $this->withToken($partnerToken)->postJson('/api/v1/partner/deals/proposals', [
            'project_id' => $projectId, 'cover_letter' => 'x', 'price' => 1,
        ])->assertStatus(409);

        // 3. Shortlist then accept (award)
        $this->withToken($customerToken)->postJson("/api/v1/projects/proposals/{$proposalId}/decide", [
            'decision' => 'shortlisted',
        ])->assertOk()->assertJsonPath('data.proposal.status', 'shortlisted');
        $this->assertSame('shortlisting', \App\Models\Project::find($projectId)->status);

        $this->withToken($customerToken)->postJson("/api/v1/projects/proposals/{$proposalId}/decide", [
            'decision' => 'accepted',
        ])->assertOk();
        $this->assertSame('awarded', \App\Models\Project::find($projectId)->status);
        $this->assertSame('accepted', \App\Models\Proposal::find($proposalId)->status);

        // 4. Contract
        $contract = $this->withToken($customerToken)->postJson("/api/v1/projects/proposals/{$proposalId}/contract", [
            'payment_terms' => 'Per milestone, 50% DP milestone 1',
            'milestone_plan' => [
                ['title' => 'Design & Setup', 'amount' => 3000000],
                ['title' => 'Development', 'amount' => 3500000],
                ['title' => 'Deploy & Training', 'amount' => 1500000],
            ],
        ]);
        $contract->assertCreated()->assertJsonPath('data.contract.status', 'sent');
        $contractId = $contract->json('data.contract.id');
        $this->assertCount(3, $contract->json('data.contract.milestones'));
        $this->assertSame('contracting', \App\Models\Project::find($projectId)->status);

        // 5. Both accept contract → project active
        $this->withToken($partnerToken)->postJson("/api/v1/projects/contracts/{$contractId}/accept")->assertOk();
        $this->withToken($customerToken)->postJson("/api/v1/projects/contracts/{$contractId}/accept")->assertOk();
        $this->assertSame('accepted', \App\Models\Contract::find($contractId)->status);
        $this->assertSame('active', \App\Models\Project::find($projectId)->status);

        // 6. Fund milestone 1
        $m1Id = $contract->json('data.contract.milestones.0.id');
        $fundOrder = $this->withToken($customerToken)->postJson("/api/v1/projects/milestones/{$m1Id}/fund");
        $fundOrder->assertCreated();
        $fundOrderCode = $fundOrder->json('data.order.code');

        // Milestone submitted before funding? blocked
        $this->withToken($partnerToken)->postJson("/api/v1/partner/deals/milestones/{$m1Id}/submit", [
            'deliverables' => [['file_path' => 'd/1.zip']],
        ])->assertStatus(409);

        // Pay funding → milestone funded
        $this->postJson('/api/v1/payments/sandbox/pay', ['order_code' => $fundOrderCode])->assertOk();
        $this->assertSame('funded', \App\Models\Milestone::find($m1Id)->status);

        // 7. Work: start → submit deliverables
        $this->withToken($partnerToken)->postJson("/api/v1/partner/deals/milestones/{$m1Id}/start")->assertOk();
        $this->withToken($partnerToken)->postJson("/api/v1/partner/deals/milestones/{$m1Id}/submit", [
            'deliverables' => [
                ['file_path' => 'milestones/1/design.fig', 'kind' => 'file'],
                ['file_path' => 'milestones/1/setup.png', 'kind' => 'image'],
            ],
            'note' => 'Draft design + environment setup selesai.',
        ])->assertOk()->assertJsonPath('data.milestone.status', 'submitted');

        // 8. Revision requested → resubmit
        $this->withToken($customerToken)->postJson("/api/v1/projects/milestones/{$m1Id}/revision", [
            'note' => 'Warna brand belum sesuai guideline.',
        ])->assertOk()->assertJsonPath('data.milestone.status', 'revision_requested');

        $this->withToken($partnerToken)->postJson("/api/v1/partner/deals/milestones/{$m1Id}/submit", [
            'deliverables' => [['file_path' => 'milestones/1/design-v2.fig']],
            'note' => 'Revisi warna selesai.',
        ])->assertOk()->assertJsonPath('data.milestone.status', 'submitted');

        // 9. Approve
        $this->withToken($customerToken)->postJson("/api/v1/projects/milestones/{$m1Id}/approve")
            ->assertOk()->assertJsonPath('data.milestone.status', 'approved');

        // Double approve blocked
        $this->withToken($customerToken)->postJson("/api/v1/projects/milestones/{$m1Id}/approve")
            ->assertStatus(409);

        // 10. Release → ledger movement + settlement
        $this->withToken($customerToken)->postJson("/api/v1/projects/milestones/{$m1Id}/release")
            ->assertOk()->assertJsonPath('data.milestone.status', 'released');

        // Funding order settled with commission snapshot
        $fundingOrderId = $fundOrder->json('data.order.id');
        $this->assertSame('settled', \App\Models\Order::find($fundingOrderId)->status);
        $this->assertDatabaseHas('settlements', ['order_id' => $fundingOrderId, 'status' => 'completed']);
        $this->assertDatabaseHas('commissions', ['order_id' => $fundingOrderId, 'amount' => 300000]);

        // Ledger balanced
        $this->assertTrue(app(\App\Domain\Ledger\LedgerService::class)->ledgerIsBalanced());
    }

    public function test_non_owner_cannot_decide(): void
    {
        [$customerToken, $partnerToken] = $this->setupActors();

        $other = $this->postJson('/api/v1/auth/register', [
            'name' => 'Other', 'email' => 'other@test.id', 'password' => 'RahasiaKuat99',
        ]);
        $otherToken = $other->json('data.token');

        $catId = \App\Models\Category::first()->id;
        $project = $this->withToken($customerToken)->postJson('/api/v1/projects', [
            'category_id' => $catId,
            'title' => 'T', 'description' => 'D', 'budget_type' => 'fixed', 'budget_min' => 1000000,
        ])->json('data.project');

        $proposal = $this->withToken($partnerToken)->postJson('/api/v1/partner/deals/proposals', [
            'project_id' => $project['id'], 'cover_letter' => 'x', 'price' => 1000000,
        ])->json('data.proposal');

        $this->withToken($otherToken)->postJson("/api/v1/projects/proposals/{$proposal['id']}/decide", [
            'decision' => 'accepted',
        ])->assertStatus(403);
    }

    public function test_worklog_for_hourly_milestone(): void
    {
        [$customerToken, $partnerToken] = $this->setupActors();

        $partnerId = \App\Models\Partner::first()->id;
        $catId = \App\Models\Category::first()->id;

        $project = $this->withToken($customerToken)->postJson('/api/v1/projects', [
            'category_id' => $catId, 'title' => 'Hourly gig', 'description' => 'D',
            'budget_type' => 'hourly', 'budget_min' => 100000,
        ])->json('data.project');

        $proposal = $this->withToken($partnerToken)->postJson('/api/v1/partner/deals/proposals', [
            'project_id' => $project['id'], 'cover_letter' => 'x', 'price' => 500000, 'timeline_days' => 5,
        ])->json('data.proposal');

        $this->withToken($customerToken)->postJson("/api/v1/projects/proposals/{$proposal['id']}/decide", ['decision' => 'accepted']);
        $contract = $this->withToken($customerToken)->postJson("/api/v1/projects/proposals/{$proposal['id']}/contract", [])->json('data.contract');
        $this->withToken($partnerToken)->postJson("/api/v1/projects/contracts/{$contract['id']}/accept");
        $this->withToken($customerToken)->postJson("/api/v1/projects/contracts/{$contract['id']}/accept");

        $milestoneId = \App\Models\Milestone::where('contract_id', $contract['id'])->first()->id;

        // Work log belongs to funding order once paid
        $fundOrder = $this->withToken($customerToken)->postJson("/api/v1/projects/milestones/{$milestoneId}/fund")->json('data.order');
        $this->postJson('/api/v1/payments/sandbox/pay', ['order_code' => $fundOrder['code']]);
        $this->withToken($partnerToken)->postJson("/api/v1/partner/deals/milestones/{$milestoneId}/start");

        $res = $this->withToken($partnerToken)->postJson('/api/v1/partner/deals/worklogs', [
            'milestone_id' => $milestoneId,
            'starts_at' => now()->subHours(2)->toIso8601String(),
            'ends_at' => now()->toIso8601String(),
            'description' => 'Coding fitur X',
        ]);

        $res->assertCreated()->assertJsonPath('data.worklog.duration_minutes', 120);
    }
}
