<?php

namespace App\Domain\Deal;

use App\Domain\Auth\DomainException;
use App\Models\Contract;
use App\Models\Milestone;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Project → Proposal → Award → Contract → Milestone flow (doc 11).
 */
class ProjectService
{
    public function publish(User $customer, array $data): Project
    {
        return DB::transaction(function () use ($customer, $data) {
            $project = Project::create([
                ...collect($data)->except(['status'])->all(),
                'user_id' => $customer->id,
                'code' => 'PRJ-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'status' => 'receiving_proposals',
            ]);

            return $project;
        });
    }

    public function submitProposal(Partner $partner, array $data): Proposal
    {
        return DB::transaction(function () use ($partner, $data) {
            $project = Project::lockForUpdate()->findOrFail($data['project_id']);

            if ($project->status !== 'receiving_proposals') {
                throw new DomainException("Project is {$project->status}.", 'PROJECT_NOT_ACCEPTING', 409);
            }

            $duplicate = Proposal::where('project_id', $project->id)->where('partner_id', $partner->id)->exists();
            if ($duplicate) {
                throw new DomainException('Already submitted a proposal.', 'PROPOSAL_EXISTS', 409);
            }

            return Proposal::create([
                ...collect($data)->except(['project_id'])->all(),
                'project_id' => $project->id,
                'partner_id' => $partner->id,
                'status' => 'submitted',
            ]);
        });
    }

    /** Customer decision on a proposal. */
    public function decideProposal(Proposal $proposal, User $customer, string $decision): Proposal
    {
        return DB::transaction(function () use ($proposal, $customer, $decision) {
            $project = $proposal->project ?? $proposal->rfq;

            if ($proposal->project && $proposal->project->user_id !== $customer->id) {
                throw new DomainException('Not your project.', 'FORBIDDEN', 403);
            }

            if (! in_array($decision, ['shortlisted', 'rejected', 'accepted'], true)) {
                throw new DomainException('Invalid decision.', 'INVALID_DECISION', 422);
            }

            if (! in_array($proposal->status, ['submitted', 'shortlisted'], true)) {
                throw new DomainException("Proposal is {$proposal->status}.", 'INVALID_STATE', 409);
            }

            if ($decision === 'accepted') {
                if (! in_array($proposal->project?->status, ['receiving_proposals', 'shortlisting', 'negotiation'], true)) {
                    throw new DomainException("Project is {$proposal->project->status}.", 'INVALID_STATE', 409);
                }

                $proposal->update(['status' => 'accepted']);
                $proposal->project()->update([
                    'status' => 'awarded',
                    'awarded_partner_id' => $proposal->partner_id,
                ]);

                // Reject siblings
                Proposal::where('project_id', $proposal->project_id)
                    ->where('id', '!=', $proposal->id)
                    ->whereIn('status', ['submitted', 'shortlisted'])
                    ->update(['status' => 'rejected']);

                return $proposal->fresh();
            }

            $proposal->update(['status' => $decision]);

            if ($decision === 'shortlisted') {
                $proposal->project()->update(['status' => 'shortlisting']);
            }

            return $proposal->fresh();
        });
    }

    /** Create contract from accepted proposal (versioned, acceptance-gated). */
    public function createContractFromProposal(Proposal $proposal, array $terms): Contract
    {
        return DB::transaction(function () use ($proposal, $terms) {
            if ($proposal->status !== 'accepted') {
                throw new DomainException('Only accepted proposals can be contracted.', 'PROPOSAL_NOT_ACCEPTED', 409);
            }

            $project = $proposal->project;
            $project->update(['status' => 'contracting']);

            $existing = Contract::where('project_id', $project->id)->where('partner_id', $proposal->partner_id)->first();
            if ($existing && in_array($existing->status, ['sent', 'accepted'], true)) {
                throw new DomainException('Contract already exists for this proposal.', 'CONTRACT_EXISTS', 409);
            }

            return Contract::create([
                'code' => 'CTR-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'project_id' => $project->id,
                'partner_id' => $proposal->partner_id,
                'customer_id' => $project->user_id,
                'proposal_id' => $proposal->id,
                'version' => 1,
                'scope' => $terms['scope'] ?? ['description' => $project->title],
                'deliverables' => $terms['deliverables'] ?? $proposal->deliverables,
                'price' => $terms['price'] ?? $proposal->price,
                'payment_terms' => $terms['payment_terms'] ?? 'Per milestone',
                'milestone_plan' => $terms['milestone_plan'] ?? $proposal->milestone_plan,
                'revision_limit' => $terms['revision_limit'] ?? 2,
                'warranty_days' => $terms['warranty_days'] ?? $proposal->warranty_days,
                'ip_terms' => $terms['ip_terms'] ?? 'Full IP transfer upon full payment.',
                'cancellation_terms' => $terms['cancellation_terms'] ?? null,
                'dispute_terms' => $terms['dispute_terms'] ?? null,
                'status' => 'sent',
            ]);
        });
    }

    /** Both parties accept the SAME version; accepted contracts are immutable (amend via new version). */
    public function acceptContract(Contract $contract, User $user): Contract
    {
        return DB::transaction(function () use ($contract, $user) {
            if ($contract->status === 'accepted') {
                return $contract;
            }

            $isCustomer = $contract->customer_id === $user->id;
            $isPartner = $contract->partner->user_id === $user->id;

            if (! $isCustomer && ! $isPartner) {
                throw new DomainException('Not a contract party.', 'FORBIDDEN', 403);
            }

            $update = $isCustomer ? ['customer_accepted_at' => now()] : ['partner_accepted_at' => now()];

            $contract->update($update);

            if ($contract->customer_accepted_at && $contract->partner_accepted_at) {
                $contract->update(['status' => 'accepted']);
                $contract->project()->update(['status' => 'active']);
            }

            return $contract->fresh();
        });
    }

    /** Amend = new version row; original untouched. */
    public function amendContract(Contract $contract, User $actor, array $changes): Contract
    {
        if ($contract->status !== 'accepted') {
            throw new DomainException('Only accepted contracts can be amended.', 'NOT_ACCEPTED', 409);
        }

        return DB::transaction(function () use ($contract, $actor, $changes) {
            $amended = $contract->replicate();
            $amended->version = $contract->version + 1;
            $amended->amends = $contract->id;
            $amended->code = $contract->code.'-A'.$amended->version;
            $amended->status = 'sent';
            $amended->customer_accepted_at = null;
            $amended->partner_accepted_at = null;
            $amended->fill($changes);
            $amended->save();

            app(\App\Support\Audit\AuditLogger::class)->log('contract.amended', $amended, $contract->toArray(), $changes, 'Contract amendment', null, $actor);

            return $amended;
        });
    }

    /** Milestones from contract milestone_plan. */
    public function generateMilestones(Contract $contract): void
    {
        DB::transaction(function () use ($contract) {
            if (Milestone::where('contract_id', $contract->id)->exists()) {
                return; // idempotent
            }

            $plan = $contract->milestone_plan ?? [
                ['title' => 'Pekerjaan 1', 'amount' => (int) $contract->price],
            ];

            foreach ($plan as $i => $m) {
                Milestone::create([
                    'contract_id' => $contract->id,
                    'title' => $m['title'] ?? ('Milestone '.($i + 1)),
                    'description' => $m['description'] ?? null,
                    'amount' => (int) $m['amount'],
                    'deadline' => $m['deadline'] ?? null,
                    'sort' => $i,
                    'status' => 'ready',
                ]);
            }
        });
    }
}
