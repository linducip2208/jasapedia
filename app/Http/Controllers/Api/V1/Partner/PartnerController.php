<?php

namespace App\Http\Controllers\Api\V1\Partner;

use App\Domain\Partner\PartnerService;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Partner;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PartnerService $partners)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:freelancer,individual,vendor_company'],
            'display_name' => ['required', 'string', 'max:120'],
            'about' => ['nullable', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:64'],
            'organization' => ['required_if:type,vendor_company', 'array'],
            'organization.name' => ['required_with:organization', 'string', 'max:120'],
            'organization.legal_name' => ['nullable', 'string', 'max:190'],
            'organization.npwp' => ['nullable', 'string', 'max:32'],
            'organization.nib' => ['nullable', 'string', 'max:48'],
            'organization.address' => ['nullable', 'string', 'max:500'],
            'organization.pic_name' => ['nullable', 'string', 'max:120'],
            'organization.pic_phone' => ['nullable', 'string', 'max:32'],
        ]);

        $partner = $this->partners->register($request->user(), $data);

        return $this->created(['partner' => $partner->load('organization')], 'Partner registered.');
    }

    public function me(Request $request): JsonResponse
    {
        $partner = Partner::where('user_id', $request->user()->id)
            ->with('organization.members.user', 'skills', 'documents', 'serviceAreas', 'payoutDestinations')
            ->first();

        if (! $partner) {
            return $this->fail('PARTNER_NOT_FOUND', 'No partner profile.', 404);
        }

        return $this->ok(['partner' => $partner]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:120'],
            'about' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'city' => ['sometimes', 'nullable', 'string', 'max:64'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        $partner = $this->myPartner($request);
        $partner->update($data);

        return $this->ok(['partner' => $partner->fresh()], 'Partner updated.');
    }

    public function setOnlineStatus(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:offline,online,busy']]);

        $partner = $this->myPartner($request);
        $this->partners->setOnlineStatus($partner, $data['status']);

        return $this->ok(['online_status' => $data['status']]);
    }

    public function submitVerification(Request $request): JsonResponse
    {
        $partner = $this->myPartner($request);
        $this->partners->submitForVerification($partner);

        return $this->ok(['verification_state' => 'submitted'], 'Submitted for verification.');
    }

    public function addSkill(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:96'],
            'level' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $partner = $this->myPartner($request);
        $skill = $partner->skills()->updateOrCreate(
            ['name' => $data['name']],
            ['level' => $data['level'] ?? 3],
        );

        return $this->created(['skill' => $skill]);
    }

    public function removeSkill(Request $request, int $skillId): JsonResponse
    {
        $partner = $this->myPartner($request);
        $partner->skills()->where('id', $skillId)->delete();

        return $this->noContent();
    }

    public function addDocument(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:ktp,sim,npwp,nib,certificate,portfolio,other'],
            'file_path' => ['required', 'string', 'max:512'],
            'number' => ['nullable', 'string', 'max:96'],
        ]);

        $partner = $this->myPartner($request);
        $doc = $partner->documents()->create([...$data, 'status' => 'pending']);

        return $this->created(['document' => $doc]);
    }

    public function addServiceArea(Request $request): JsonResponse
    {
        $data = $request->validate([
            'coverage_type' => ['required', 'in:city,district,radius,polygon'],
            'location_id' => ['nullable', 'integer'],
            'radius_km' => ['nullable', 'numeric', 'min:0.5', 'max:100'],
            'polygon' => ['nullable', 'array'],
        ]);

        $partner = $this->myPartner($request);
        $area = $partner->serviceAreas()->create($data);

        return $this->created(['service_area' => $area]);
    }

    public function addPayoutDestination(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:bank,ewallet'],
            'bank_code' => ['nullable', 'string', 'max:16'],
            'account_number' => ['required', 'string', 'max:64'],
            'account_name' => ['required', 'string', 'max:120'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $partner = $this->myPartner($request);
        $dest = $this->partners->addPayoutDestination($partner, $data);

        return $this->created(['payout_destination' => $dest]);
    }

    public function addMember(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'in:manager,dispatcher,finance,pm,worker'],
        ]);

        $partner = $this->myPartner($request);
        if (! $partner->isVendorCompany()) {
            return $this->fail('NOT_ORGANIZATION', 'Only vendor companies manage members.', 409);
        }

        $member = $this->partners->addMember($partner, $data);

        return $this->created(['member' => $member->load('user')]);
    }

    public function removeMember(Request $request, int $memberId): JsonResponse
    {
        $partner = $this->myPartner($request);
        $member = $partner->organization()->firstOrFail()->members()->findOrFail($memberId);
        $this->partners->removeMember($partner, $member);

        return $this->noContent();
    }

    private function myPartner(Request $request): Partner
    {
        $partner = Partner::where('user_id', $request->user()->id)->first();

        return $partner ?? throw new \App\Domain\Auth\DomainException('No partner profile.', 'PARTNER_NOT_FOUND', 404);
    }
}
