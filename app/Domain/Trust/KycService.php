<?php

namespace App\Domain\Trust;

use App\Domain\Auth\DomainException;
use App\Models\KycSubmission;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class KycService
{
    public function submit(Partner $partner, array $data): KycSubmission
    {
        return DB::transaction(function () use ($partner, $data) {
            $kind = $partner->isVendorCompany() ? 'kyb' : 'kyc';

            $submission = KycSubmission::create([
                'partner_id' => $partner->id,
                'kind' => $kind,
                'identity' => $data['identity'] ?? null,
                'company' => $data['company'] ?? null,
                'documents' => $data['documents'] ?? [],
                'bank_account' => $data['bank_account'] ?? null,
                'status' => 'submitted',
            ]);

            $partner->update(['verification_state' => 'submitted']);

            return $submission;
        });
    }

    /** KYC officer decision (permission-gated at route level). */
    public function decide(KycSubmission $submission, User $officer, string $decision, ?string $notes = null): KycSubmission
    {
        return DB::transaction(function () use ($submission, $officer, $decision, $notes) {
            if (! in_array($decision, ['approve', 'reject', 'needs_revision', 'suspend'], true)) {
                throw new DomainException('Invalid decision.', 'INVALID_DECISION', 422);
            }

            if (! in_array($submission->status, ['submitted', 'under_review', 'needs_revision'], true)) {
                throw new DomainException("Submission is {$submission->status}.", 'INVALID_STATE', 409);
            }

            $map = [
                'approve' => ['verified', 'verified'],
                'reject' => ['rejected', 'rejected'],
                'needs_revision' => ['needs_revision', 'needs_revision'],
                'suspend' => ['suspended', 'suspended'],
            ];

            [$submissionStatus, $partnerState] = $map[$decision];

            $submission->update([
                'status' => $submissionStatus,
                'review_notes' => $notes,
                'reviewed_by' => $officer->id,
                'reviewed_at' => now(),
            ]);

            $submission->partner->update(['verification_state' => $partnerState]);

            app(\App\Support\Audit\AuditLogger::class)->log(
                "kyc.{$decision}",
                $submission,
                ['status' => $submission->getOriginal('status')],
                ['status' => $submissionStatus],
                $notes,
                null,
                $officer,
            );

            return $submission->fresh();
        });
    }
}
