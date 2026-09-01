<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Domain\Deal\MilestoneService;
use App\Domain\Deal\ProjectService;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Partner;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DealController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProjectService $projects,
        private readonly MilestoneService $milestones,
    ) {
    }

    public function submitProposal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'cover_letter' => ['required', 'string', 'max:5000'],
            'technical_approach' => ['nullable', 'string', 'max:10000'],
            'price' => ['required', 'integer', 'min:1'],
            'timeline_days' => ['nullable', 'integer', 'min:1'],
            'deliverables' => ['nullable', 'array'],
            'milestone_plan' => ['nullable', 'array'],
            'warranty_days' => ['nullable', 'integer', 'min:0'],
            'valid_until' => ['nullable', 'date', 'after:now'],
            'attachments' => ['nullable', 'array'],
        ]);

        $partner = $this->myPartner($request);
        $proposal = $this->projects->submitProposal($partner, $data);

        return $this->created(['proposal' => $proposal], 'Proposal submitted.');
    }

    public function withdrawProposal(Request $request, int $proposalId): JsonResponse
    {
        $partner = $this->myPartner($request);
        $proposal = \App\Models\Proposal::where('partner_id', $partner->id)->findOrFail($proposalId);

        if (! in_array($proposal->status, ['draft', 'submitted', 'shortlisted'], true)) {
            return $this->fail('INVALID_STATE', "Proposal is {$proposal->status}.", 409);
        }

        $proposal->update(['status' => 'withdrawn']);

        return $this->ok(['proposal' => $proposal->fresh()], 'Proposal withdrawn.');
    }

    public function myProposals(Request $request): JsonResponse
    {
        $partner = $this->myPartner($request);
        $proposals = \App\Models\Proposal::where('partner_id', $partner->id)
            ->with('project:id,code,title,status,budget_type,budget_min,budget_max')
            ->latest()->paginate(20);

        return $this->paginated($proposals);
    }

    /** Open projects feed for partners. */
    public function openProjects(Request $request): JsonResponse
    {
        $projects = \App\Models\Project::where('status', 'receiving_proposals')
            ->where('visibility', 'public')
            ->with('category:id,name,slug')
            ->latest()->paginate(20);

        return $this->paginated($projects);
    }

    public function myContracts(Request $request): JsonResponse
    {
        $partner = $this->myPartner($request);
        $contracts = \App\Models\Contract::where('partner_id', $partner->id)
            ->with('project:id,code,title', 'milestones')
            ->latest()->paginate(20);

        return $this->paginated($contracts);
    }

    public function startMilestone(Request $request, int $milestoneId): JsonResponse
    {
        $milestone = \App\Models\Milestone::findOrFail($milestoneId);

        return $this->ok(['milestone' => $this->milestones->start($milestone, $request->user())], 'Milestone in progress.');
    }

    public function submitMilestone(Request $request, int $milestoneId): JsonResponse
    {
        $data = $request->validate([
            'deliverables' => ['required', 'array', 'min:1'],
            'deliverables.*.file_path' => ['required', 'string', 'max:512'],
            'deliverables.*.kind' => ['nullable', 'in:file,image,video'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $milestone = \App\Models\Milestone::findOrFail($milestoneId);
        $milestone = $this->milestones->submit($milestone, $request->user(), $data['deliverables'], $data['note'] ?? null);

        return $this->ok(['milestone' => $milestone], 'Milestone submitted for review.');
    }

    public function storeWorkLog(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['nullable', 'integer'],
            'milestone_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'description' => ['nullable', 'string', 'max:2000'],
            'proof' => ['nullable', 'array'],
        ]);

        $partner = $this->myPartner($request);

        $duration = now()->parse($data['starts_at'])->diffInMinutes(now()->parse($data['ends_at']));

        $worklog = \App\Models\WorkLog::create([
            ...$data,
            'user_id' => $request->user()->id,
            'duration_minutes' => $duration,
            'source' => 'manual',
            'status' => 'pending',
        ]);

        return $this->created(['worklog' => $worklog]);
    }

    // ---------- RFQ / Quotations (Phase 17/19) ----------

    public function openRfqs(Request $request): JsonResponse
    {
        $rfqs = \App\Models\Rfq::where('status', 'open')
            ->where('visibility', 'public')
            ->with('category:id,name,slug')
            ->latest()->paginate(20);

        return $this->paginated($rfqs);
    }

    public function submitQuotation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rfq_id' => ['required', 'integer'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.name' => ['required', 'string', 'max:190'],
            'line_items.*.qty' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'integer', 'min:0'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'valid_until' => ['nullable', 'date', 'after:now'],
            'attachments' => ['nullable', 'array'],
        ]);

        $partner = $this->myPartner($request);
        $quotation = app(\App\Domain\Deal\RfqService::class)->submitQuotation($partner, $data);

        return $this->created(['quotation' => $quotation], 'Quotation submitted.');
    }

    public function reviseQuotation(Request $request, int $quotationId): JsonResponse
    {
        $data = $request->validate([
            'line_items' => ['sometimes', 'array', 'min:1'],
            'line_items.*.name' => ['required_with:line_items', 'string', 'max:190'],
            'line_items.*.qty' => ['required_with:line_items', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required_with:line_items', 'integer', 'min:0'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'valid_until' => ['nullable', 'date', 'after:now'],
        ]);

        $partner = $this->myPartner($request);
        $quotation = \App\Models\Quotation::findOrFail($quotationId);
        $version = app(\App\Domain\Deal\RfqService::class)->reviseQuotation($quotation, $partner, $data);

        return $this->created(['quotation' => $version], "Quotation v{$version->version} submitted.");
    }

    public function myQuotations(Request $request): JsonResponse
    {
        $partner = $this->myPartner($request);
        $quotes = \App\Models\Quotation::where('partner_id', $partner->id)
            ->with('rfq:id,code,title,status')
            ->latest()->paginate(20);

        return $this->paginated($quotes);
    }

    private function myPartner(Request $request): Partner
    {
        return Partner::where('user_id', $request->user()->id)->first()
            ?? throw new \App\Domain\Auth\DomainException('No partner profile.', 'PARTNER_NOT_FOUND', 404);
    }
}
