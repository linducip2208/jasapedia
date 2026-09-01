<?php

namespace App\Domain\Partner;

use App\Domain\Auth\DomainException;
use App\Models\Partner;
use App\Models\PartnerMember;
use App\Models\PartnerOrganization;
use App\Models\PayoutDestination;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PartnerService
{
    public function register(User $user, array $data): Partner
    {
        return DB::transaction(function () use ($user, $data) {
            if (Partner::where('user_id', $user->id)->exists()) {
                throw new DomainException('User already has a partner profile.', 'PARTNER_EXISTS', 409);
            }

            $partner = Partner::create([
                'user_id' => $user->id,
                'type' => $data['type'],
                'display_name' => $data['display_name'],
                'slug' => $this->uniqueSlug($data['display_name']),
                'about' => $data['about'] ?? null,
                'city' => $data['city'] ?? null,
            ]);

            $user->roles()->syncWithoutDetaching([
                \App\Models\Role::where('name', 'Partner')->value('id'),
            ]);

            if ($data['type'] === 'vendor_company') {
                $org = PartnerOrganization::create([
                    'partner_id' => $partner->id,
                    'name' => $data['organization']['name'],
                    'legal_name' => $data['organization']['legal_name'] ?? null,
                    'npwp' => $data['organization']['npwp'] ?? null,
                    'nib' => $data['organization']['nib'] ?? null,
                    'address' => $data['organization']['address'] ?? null,
                    'pic_name' => $data['organization']['pic_name'] ?? $user->name,
                    'pic_phone' => $data['organization']['pic_phone'] ?? $user->phone,
                ]);

                PartnerMember::create([
                    'organization_id' => $org->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'status' => 'active',
                    'joined_at' => now(),
                ]);

                // Owner gets org-scoped VendorOwner role
                $user->roles()->syncWithoutDetaching([
                    \App\Models\Role::where('name', 'VendorOwner')->value('id') => ['organization_id' => $org->id],
                ]);
            }

            return $partner;
        });
    }

    public function addMember(Partner $partner, array $data): PartnerMember
    {
        $org = $partner->organization()->firstOrFail();

        return DB::transaction(function () use ($org, $data) {
            $user = User::where('email', $data['email'])->first()
                ?? throw new DomainException('User with this email not found. They must register first.', 'USER_NOT_FOUND', 404);

            if (PartnerMember::where('organization_id', $org->id)->where('user_id', $user->id)->exists()) {
                throw new DomainException('User is already a member.', 'MEMBER_EXISTS', 409);
            }

            $member = PartnerMember::create([
                'organization_id' => $org->id,
                'user_id' => $user->id,
                'role' => $data['role'],
                'status' => 'active',
                'invited_by' => auth()->id(),
                'joined_at' => now(),
            ]);

            $rbacRole = \App\Models\Role::where('name', PartnerMember::roleToRbac($data['role']))->value('id');
            $user->roles()->syncWithoutDetaching([$rbacRole => ['organization_id' => $org->id]]);

            $org->update(['worker_count' => $org->members()->where('role', 'worker')->count()]);

            return $member;
        });
    }

    public function removeMember(Partner $partner, PartnerMember $member): void
    {
        $org = $partner->organization()->firstOrFail();

        if ($member->role === 'owner') {
            throw new DomainException('Owner cannot be removed.', 'OWNER_IMMUTABLE', 409);
        }

        DB::transaction(function () use ($org, $member) {
            $member->user->roles()
                ->newPivotStatement()
                ->where('user_id', $member->user_id)
                ->where('organization_id', $org->id)
                ->delete();

            // Clean up other vendor roles tied to this org
            $vendorRoleIds = \App\Models\Role::whereIn('name', [
                'VendorOwner', 'VendorManager', 'VendorDispatcher', 'VendorFinance', 'VendorPM', 'VendorWorker',
            ])->pluck('id');

            DB::table('user_role')
                ->where('user_id', $member->user_id)
                ->where('organization_id', $org->id)
                ->whereIn('role_id', $vendorRoleIds)
                ->delete();

            $member->delete();
            $org->update(['worker_count' => $org->members()->where('role', 'worker')->count()]);
        });
    }

    public function addPayoutDestination(Partner $partner, array $data): PayoutDestination
    {
        return DB::transaction(function () use ($partner, $data) {
            if ($data['is_default'] ?? false) {
                $partner->payoutDestinations()->update(['is_default' => false]);
            }

            $dest = PayoutDestination::create([
                ...$data,
                'partner_id' => $partner->id,
                'is_default' => $data['is_default'] ?? $partner->payoutDestinations()->count() === 0,
            ]);

            return $dest;
        });
    }

    public function setOnlineStatus(Partner $partner, string $status): void
    {
        if ($partner->verification_state !== 'verified') {
            throw new DomainException('Partner must be verified to go online.', 'NOT_VERIFIED', 403);
        }

        $partner->update(['online_status' => $status]);
    }

    public function submitForVerification(Partner $partner): void
    {
        if (! in_array($partner->verification_state, ['unverified', 'needs_revision', 'rejected'], true)) {
            throw new DomainException('Verification already submitted.', 'INVALID_STATE', 409);
        }

        $partner->update(['verification_state' => 'submitted']);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'partner';
        $slug = $base;
        $i = 1;

        while (Partner::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
