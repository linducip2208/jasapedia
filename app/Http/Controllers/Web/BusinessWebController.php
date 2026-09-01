<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CorporateOrganization;
use App\Models\CorporateServiceRequest;
use App\Models\Order;
use App\Models\Rfq;
use Illuminate\Http\Request;

/**
 * Jasapedia Business — corporate procurement UI on existing corporate domain.
 */
class BusinessWebController extends Controller
{
    public function landing(Request $request)
    {
        return view('web.business.landing');
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $orgs = CorporateOrganization::query()
            ->where('owner_user_id', $user->id)
            ->orWhereIn('id', fn ($q) => $q->select('organization_id')
                ->from('corporate_employees')->where('user_id', $user->id))
            ->get();

        $orgIds = $orgs->pluck('id');
        $requests = CorporateServiceRequest::whereIn('organization_id', $orgIds)->latest()->take(10)->get();
        $rfqs = Rfq::whereIn('user_id', [$user->id])->latest()->take(10)->get();
        $orders = Order::whereIn('user_id', [$user->id])->with('service:id,title')->latest()->take(10)->get();

        return view('web.business.dashboard', [
            'organizations' => $orgs,
            'pendingApprovals' => CorporateServiceRequest::whereIn('organization_id', $orgIds)
                ->where('status', 'pending_manager')->count(),
            'activeRequests' => CorporateServiceRequest::whereIn('organization_id', $orgIds)
                ->whereIn('status', ['pending_manager', 'pending_finance', 'approved'])->count(),
            'convertedRequests' => CorporateServiceRequest::whereIn('organization_id', $orgIds)
                ->where('status', 'converted')->count(),
            'requests' => $requests,
            'rfqs' => $rfqs,
            'orders' => $orders,
        ]);
    }

    public function createOrg(Request $request, \App\Domain\Corporate\CorporateService $corporate)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'npwp' => ['nullable', 'string', 'max:32'],
            'billing_email' => ['nullable', 'email', 'max:150'],
        ]);

        $org = CorporateOrganization::create([
            'owner_user_id' => $request->user()->id,
            'name' => $data['name'],
            'npwp' => $data['npwp'] ?? null,
            'billing_email' => $data['billing_email'] ?? null,
        ]);

        return back()->with('success', 'Organisasi bisnis dibuat.');
    }

    public function createRequest(Request $request, \App\Domain\Corporate\CorporateService $corporate)
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'estimated_amount' => ['nullable', 'integer', 'min:0'],
            'po_reference' => ['nullable', 'string', 'max:64'],
        ]);

        $org = CorporateOrganization::query()
            ->where(function ($q) use ($request) {
                $q->where('owner_user_id', $request->user()->id)
                    ->orWhereIn('id', fn ($s) => $s->select('organization_id')
                        ->from('corporate_employees')->where('user_id', $request->user()->id));
            })
            ->findOrFail($data['organization_id']);

        $corporate->createRequest($org, $request->user(), $data);

        return back()->with('success', 'Service request dibuat — menunggu approval.');
    }
}
