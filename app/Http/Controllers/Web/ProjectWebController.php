<?php

namespace App\Http\Controllers\Web;

use App\Domain\Deal\ProjectService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Milestone;
use App\Models\Project;
use App\Models\Proposal;
use Illuminate\Http\Request;

class ProjectWebController extends Controller
{
    public function index(Request $request)
    {
        $mine = $request->boolean('mine') && $request->user();

        $projects = Project::query()
            ->when($mine, fn ($q) => $q->where('user_id', $request->user()->id))
            ->whereIn('status', $mine
                ? ['receiving_proposals', 'shortlisting', 'negotiation', 'awarded', 'contracting', 'active', 'completed', 'closed', 'cancelled']
                : ['receiving_proposals'])
            ->with(['category:id,name,slug', 'proposals:id,project_id,partner_id,price', 'awardedPartner:id,display_name,slug'])
            ->withCount('proposals')
            ->latest()->paginate(12);

        return view('web.projects.index', [
            'projects' => $projects,
            'mine' => $mine,
            'categories' => Category::where('is_active', true)->orderBy('sort')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('web.projects.create', [
            'categories' => Category::where('is_active', true)->whereNotNull('parent_id')->orderBy('sort')->get(),
        ]);
    }

    public function store(Request $request, ProjectService $projects)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:8000'],
            'skills' => ['nullable', 'array', 'max:15'],
            'budget_type' => ['required', 'in:fixed,hourly,range'],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['nullable', 'integer', 'gte:budget_min'],
            'deadline' => ['nullable', 'date', 'after:today'],
            'visibility' => ['nullable', 'in:public,invited'],
        ]);

        $project = $projects->publish($request->user(), $data);

        return redirect()->route('web.projects.show', $project->id)
            ->with('success', 'Proyek terpublikasi! Freelancer dapat mulai mengirim proposal.');
    }

    public function show(Request $request, int $id)
    {
        $project = Project::with(['category:id,name,slug', 'proposals.partner:id,display_name,slug,avatar_path,rating_avg,verification_state', 'awardedPartner:id,display_name,slug', 'contracts'])
            ->withCount('proposals')
            ->findOrFail($id);

        $isOwner = $request->user()?->id === $project->user_id;

        // Non-owner sees only their own proposal + summary counts
        if (! $isOwner) {
            $project->setRelation('proposals', $project->proposals
                ->filter(fn ($p) => $p->partner?->user_id === $request->user()?->id)->values());
        }

        return view('web.projects.show', [
            'project' => $project,
            'isOwner' => $isOwner,
            'myPartner' => $request->user() ? \App\Models\Partner::where('user_id', $request->user()->id)->first() : null,
        ]);
    }

    public function submitProposal(Request $request, int $id, ProjectService $projects)
    {
        $data = $request->validate([
            'cover_letter' => ['required', 'string', 'max:5000'],
            'price' => ['required', 'integer', 'min:1000'],
            'timeline_days' => ['required', 'integer', 'min:1', 'max:365'],
            'deliverables' => ['nullable', 'array'],
        ]);

        $partner = \App\Models\Partner::where('user_id', $request->user()->id)->firstOrFail();
        $projects->submitProposal($partner, $data + ['project_id' => $id]);

        return back()->with('success', 'Proposal terkirim!');
    }

    public function decideProposal(Request $request, int $id, int $proposalId, ProjectService $projects)
    {
        $data = $request->validate(['decision' => ['required', 'in:shortlisted,rejected,accepted']]);

        $proposal = Proposal::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($proposalId);
        $projects->decideProposal($proposal, $request->user(), $data['decision']);

        return back()->with('success', 'Keputusan tersimpan.');
    }

    public function createContract(Request $request, int $id, int $proposalId, ProjectService $projects)
    {
        $proposal = Proposal::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->where('status', 'accepted')->findOrFail($proposalId);

        $contract = $projects->createContractFromProposal($proposal, []);
        $projects->generateMilestones($contract);
        $projects->acceptContract($contract, $request->user());

        return redirect()->route('web.projects.show', $id)
            ->with('success', 'Kontrak dibuat. Milestone siap didanai.');
    }
}
