<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Corporate\CorporateService;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\CorporateApprovalPolicy;
use App\Models\CorporateOrganization;
use App\Models\CorporateServiceRequest;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CorporateController extends Controller
{
    use ApiResponse;

    public function createOrg(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'npwp' => ['nullable', 'string', 'max:32'],
            'billing_email' => ['nullable', 'email'],
        ]);

        $org = CorporateOrganization::create([...$data, 'owner_user_id' => $request->user()->id]);

        // owner becomes admin member
        DB::table('corporate_employees')->insert([
            'organization_id' => $org->id,
            'user_id' => $request->user()->id,
            'role' => 'admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->created(['organization' => $org], 'Corporate organization created.');
    }

    public function addEmployee(Request $request, int $orgId): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'in:employee,manager,finance,admin'],
        ]);

        $org = CorporateOrganization::where('owner_user_id', $request->user()->id)->findOrFail($orgId);
        $user = \App\Models\User::where('email', $data['email'])->firstOrFail();

        DB::table('corporate_employees')->updateOrInsert(
            ['organization_id' => $org->id, 'user_id' => $user->id],
            ['role' => $data['role'], 'updated_at' => now()],
        );

        return $this->created(['employee' => ['email' => $data['email'], 'role' => $data['role']]]);
    }

    public function setPolicy(Request $request, int $orgId): JsonResponse
    {
        $data = $request->validate([
            'threshold' => ['required', 'integer', 'min:0'],
            'finance_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $org = CorporateOrganization::where('owner_user_id', $request->user()->id)->findOrFail($orgId);
        $policy = CorporateApprovalPolicy::updateOrCreate(
            ['organization_id' => $org->id],
            $data,
        );

        return $this->ok(['policy' => $policy]);
    }

    public function createRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'estimated_amount' => ['nullable', 'integer', 'min:0'],
            'category_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'cost_center_id' => ['nullable', 'integer'],
            'po_reference' => ['nullable', 'string', 'max:64'],
        ]);

        $org = CorporateOrganization::findOrFail($data['organization_id']);
        $req = app(CorporateService::class)->createRequest($org, $request->user(), $data);

        return $this->created(['service_request' => $req], 'Service request created.');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'level' => ['required', 'in:manager,finance'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $req = CorporateServiceRequest::findOrFail($id);
        $req = app(CorporateService::class)->approve($req, $request->user(), $data['level'], $data['note'] ?? '');

        return $this->ok(['service_request' => $req], "Approved by {$data['level']}.");
    }

    public function convertToOrder(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'scheduled_at' => ['nullable', 'date'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $req = CorporateServiceRequest::findOrFail($id);
        $order = app(CorporateService::class)->convertToOrder($req, $data['service_id'], $data);

        return $this->created(['order' => $order], 'Order created from request.');
    }

    public function myRequests(Request $request): JsonResponse
    {
        $orgIds = DB::table('corporate_employees')->where('user_id', $request->user()->id)->pluck('organization_id');

        $requests = CorporateServiceRequest::whereIn('organization_id', $orgIds)
            ->latest()->paginate(20);

        return $this->paginated($requests);
    }
}
