<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Deal\MilestoneService;
use App\Domain\Deal\ProjectService;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Contract;
use App\Models\Milestone;
use App\Models\Project;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProjectService $projects,
        private readonly MilestoneService $milestones,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['required', 'string', 'max:10000'],
            'requirements' => ['nullable', 'array'],
            'skills' => ['nullable', 'array'],
            'budget_type' => ['required', 'in:fixed,hourly,range'],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'min:0', 'gte:budget_min'],
            'deadline' => ['nullable', 'date', 'after:today'],
            'attachments' => ['nullable', 'array'],
            'visibility' => ['nullable', 'in:public,invited'],
        ]);

        $project = $this->projects->publish($request->user(), $data);

        return $this->created(['project' => $project], 'Project published.');
    }

    public function index(Request $request): JsonResponse
    {
        $projects = Project::where('user_id', $request->user()->id)
            ->with('category:id,name,slug', 'awardedPartner:id,display_name,slug')
            ->latest()->paginate(20);

        return $this->paginated($projects);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = Project::with('category', 'proposals.partner:id,display_name,slug,rating_avg,verification_state', 'contracts', 'awardedPartner')
            ->findOrFail($id);

        $isOwner = $project->user_id === $request->user()->id;
        if (! $isOwner && $project->visibility === 'invited') {
            return $this->fail('FORBIDDEN', 'Not visible.', 403);
        }

        if (! $isOwner) {
            $project->makeHidden('proposals'); // non-owners don't see competing proposals
        }

        return $this->ok(['project' => $project, 'is_owner' => $isOwner]);
    }

    public function decideProposal(Request $request, int $proposalId): JsonResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:shortlisted,rejected,accepted']]);

        $proposal = \App\Models\Proposal::findOrFail($proposalId);
        $proposal = $this->projects->decideProposal($proposal, $request->user(), $data['decision']);

        return $this->ok(['proposal' => $proposal], "Proposal {$data['decision']}.");
    }

    public function createContract(Request $request, int $proposalId): JsonResponse
    {
        $data = $request->validate([
            'scope' => ['nullable', 'array'],
            'deliverables' => ['nullable', 'array'],
            'price' => ['nullable', 'integer', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'milestone_plan' => ['nullable', 'array'],
            'revision_limit' => ['nullable', 'integer', 'min:0', 'max:10'],
            'warranty_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $proposal = \App\Models\Proposal::findOrFail($proposalId);
        $contract = $this->projects->createContractFromProposal($proposal, $data);
        $this->projects->generateMilestones($contract);

        return $this->created(['contract' => $contract->load('milestones')], 'Contract drafted & sent.');
    }

    public function acceptContract(Request $request, int $contractId): JsonResponse
    {
        $contract = Contract::findOrFail($contractId);
        $contract = $this->projects->acceptContract($contract, $request->user());

        return $this->ok(['contract' => $contract], 'Contract accepted.');
    }

    public function fundMilestone(Request $request, int $milestoneId): JsonResponse
    {
        $milestone = Milestone::findOrFail($milestoneId);
        $order = $this->milestones->fund($milestone, $request->user());

        return $this->created(['order' => $order], 'Funding order created — pay to fund milestone.');
    }

    public function approveMilestone(Request $request, int $milestoneId): JsonResponse
    {
        $milestone = Milestone::findOrFail($milestoneId);
        $milestone = $this->milestones->approve($milestone, $request->user());

        return $this->ok(['milestone' => $milestone], 'Milestone approved.');
    }

    public function requestMilestoneRevision(Request $request, int $milestoneId): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);

        $milestone = Milestone::findOrFail($milestoneId);
        $milestone = $this->milestones->requestRevision($milestone, $request->user(), $data['note']);

        return $this->ok(['milestone' => $milestone], 'Revision requested.');
    }

    public function releaseMilestone(Request $request, int $milestoneId): JsonResponse
    {
        $milestone = Milestone::findOrFail($milestoneId);
        $this->milestones->release($milestone);

        return $this->ok(['milestone' => $milestone->fresh()], 'Funds released to vendor payable.');
    }
}
