<?php

namespace App\Http\Controllers\Web;

use App\Domain\Deal\ProjectService;
use App\Domain\Deal\RfqService;
use App\Domain\Partner\PartnerService;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Category;
use App\Models\Contract;
use App\Models\Order;
use App\Models\Partner;
use App\Models\PayoutDestination;
use App\Models\Proposal;
use App\Models\Quotation;
use App\Models\Rfq;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\ServicePackage;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Partner Center — dedicated professional layout, reuses existing domain services.
 */
class PartnerWebController extends Controller
{
    public function partner(): ?Partner
    {
        return Partner::where('user_id', Auth::id())->first();
    }

    public function dashboard(Request $request)
    {
        $partner = $this->partner();
        if (! $partner) {
            return redirect()->route('web.partner.onboarding');
        }

        $orderQuery = Order::where('partner_id', $partner->id);

        return view('web.partner.dashboard', [
            'partner' => $partner,
            'pendingOffers' => Assignment::where('partner_id', $partner->id)->where('status', 'offered')->count(),
            'todayJobs' => (clone $orderQuery)->whereDate('scheduled_at', today())->count(),
            'inProgress' => (clone $orderQuery)->whereIn('status', ['accepted', 'assigned', 'on_the_way', 'arrived', 'checked_in', 'working'])->count(),
            'monthEarnings' => (clone $orderQuery)->where('status', 'settled')->whereMonth('settled_at', now()->month)->sum('total'),
            'rating' => $partner->rating_avg,
            'newOrders' => (clone $orderQuery)->whereIn('status', ['paid', 'searching_provider'])->latest()->take(5)->with('service:id,title,slug')->get(),
            'activeJobs' => (clone $orderQuery)->whereIn('status', ['assigned', 'on_the_way', 'arrived', 'checked_in', 'working'])->latest()->take(5)->with('service:id,title,slug')->get(),
        ]);
    }

    public function orders(Request $request)
    {
        $partner = $this->partner();
        $query = Order::where('partner_id', $partner->id)->with('service:id,title,slug')->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return view('web.partner.orders', ['orders' => $query->paginate(15), 'partner' => $partner]);
    }

    public function orderAction(Request $request, int $id)
    {
        $data = $request->validate(['action' => ['required', 'in:accept,reject,start,arrive,submit'], 'note' => ['nullable', 'string', 'max:500']]);

        $order = Order::where('partner_id', $this->partner()->id)->findOrFail($id);
        $sm = app(\App\Domain\Order\OrderStateMachine::class);

        $action = $data['action'];
        match ($action) {
            'accept' => $sm->transition($order, $order->status === 'searching_provider' ? 'accepted' : 'assigned', $request->user(), $data['note'] ?? null),
            'reject' => $sm->transition($order, 'searching_provider', $request->user(), 'Partner menolak'),
            'arrive' => $sm->transition($order, 'arrived', $request->user(), $data['note'] ?? null),
            'start' => $sm->transition($order, 'working', $request->user(), $data['note'] ?? null),
            'submit' => $sm->transition($order, 'awaiting_customer_confirmation', $request->user(), $data['note'] ?? null),
        };

        return back()->with('success', 'Status pesanan diperbarui.');
    }

    public function services(Request $request)
    {
        $partner = $this->partner();

        return view('web.partner.services', [
            'partner' => $partner,
            'services' => Service::where('partner_id', $partner->id)->with('category:id,name')->latest()->paginate(15),
        ]);
    }

    public function createService(Request $request)
    {
        return view('web.partner.services-create', [
            'partner' => $this->partner(),
            'categories' => Category::where('is_active', true)->whereNotNull('parent_id')->orderBy('sort')->get(),
        ]);
    }

    public function storeService(Request $request, \App\Domain\Catalog\CatalogService $catalog)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:8000'],
            'price_model' => ['required', 'in:fixed,per_unit,hourly,daily,package'],
            'base_price' => ['required', 'integer', 'min:1000'],
            'unit_label' => ['nullable', 'string', 'max:30'],
            'fulfillment_type' => ['required', 'string', 'max:30'],
            'delivery_mode' => ['nullable', 'in:onsite,online,hybrid'],
            'warranty_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'emergency_capable' => ['nullable', 'boolean'],
            'gallery' => ['nullable', 'array', 'max:6'],
            'gallery.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $partner = $this->partner();
        $data['delivery_mode'] ??= 'onsite';

        if ($request->hasFile('gallery')) {
            try {
                $paths = app(\App\Domain\Catalog\MediaService::class)->storeServiceGallery($request->file('gallery'));
                $data['media'] = ['cover' => $paths[0], 'gallery' => $paths];
            } catch (\RuntimeException $e) {
                return back()->withErrors(['gallery' => $e->getMessage()])->withInput();
            }
        }

        try {
            $catalog->createService($partner, $data);
        } catch (\App\Domain\Auth\DomainException $e) {
            return back()->withErrors(['title' => $e->getMessage()])->withInput();
        }

        return redirect()->route('web.partner.services')->with('success', 'Jasa berhasil dibuat!');
    }

    public function toggleService(Request $request, int $id)
    {
        $service = Service::where('partner_id', $this->partner()->id)->findOrFail($id);
        $service->update(['status' => $service->status === 'active' ? 'paused' : 'active']);

        return back()->with('success', $service->status === 'active' ? 'Jasa diaktifkan.' : 'Jasa dijeda.');
    }

    public function requests(Request $request)
    {
        return view('web.partner.rfqs', [
            'partner' => $this->partner(),
            'rfqs' => Rfq::where('status', 'open')->latest()->paginate(15),
        ]);
    }

    public function submitQuotation(Request $request, RfqService $rfqs)
    {
        $data = $request->validate([
            'rfq_id' => ['required', 'integer'],
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.name' => ['required', 'string', 'max:150'],
            'line_items.*.qty' => ['required', 'integer', 'min:1'],
            'line_items.*.unit_price' => ['required', 'integer', 'min:0'],
            'terms' => ['nullable', 'string', 'max:3000'],
        ]);

        $rfqs->submitQuotation($this->partner(), $data);

        return back()->with('success', 'Penawaran terkirim!');
    }

    public function quotations(Request $request)
    {
        return view('web.partner.quotations', [
            'partner' => $this->partner(),
            'quotations' => Quotation::where('partner_id', $this->partner()->id)->with('rfq:id,code,title,status')->latest()->paginate(15),
        ]);
    }

    public function projects(Request $request)
    {
        return view('web.partner.projects', [
            'partner' => $this->partner(),
            'projects' => \App\Models\Project::where('status', 'receiving_proposals')->with('category:id,name')->latest()->paginate(15),
            'myProposals' => Proposal::where('partner_id', $this->partner()->id)->with('project:id,code,title,status')->latest()->take(10)->get(),
        ]);
    }

    public function submitProposal(Request $request, ProjectService $projects)
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'cover_letter' => ['required', 'string', 'max:5000'],
            'price' => ['required', 'integer', 'min:1000'],
            'timeline_days' => ['required', 'integer', 'min:1'],
        ]);

        $projects->submitProposal($this->partner(), $data);

        return back()->with('success', 'Proposal terkirim!');
    }

    public function finance(Request $request)
    {
        $partner = $this->partner();
        $available = app(\App\Domain\Finance\SettlementService::class) ? app(\App\Domain\Finance\WithdrawalService::class)->availableBalance($partner) : 0;
        $pending = (int) \App\Models\Settlement::where('partner_id', $partner->id)->where('status', 'pending')->sum('vendor_net');

        return view('web.partner.finance', [
            'partner' => $partner,
            'available' => max(0, $available),
            'pending' => $pending,
            'withdrawals' => Withdrawal::where('partner_id', $partner->id)->latest()->take(15)->get(),
            'payouts' => PayoutDestination::where('partner_id', $partner->id)->get(),
            'settlements' => \App\Models\Settlement::where('partner_id', $partner->id)->latest()->take(15)->get(),
        ]);
    }

    public function withdraw(Request $request, \App\Domain\Finance\WithdrawalService $withdrawals)
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:50000'],
            'payout_destination_id' => ['required', 'integer'],
        ]);

        $partner = $this->partner();
        $dest = PayoutDestination::where('partner_id', $partner->id)->findOrFail($data['payout_destination_id']);

        try {
            $withdrawals->request($partner, $dest, (int) $data['amount'], $request->user());
        } catch (\App\Domain\Auth\DomainException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Permintaan penarikan diajukan.');
    }

    public function addPayout(Request $request, PartnerService $partners)
    {
        $data = $request->validate([
            'type' => ['required', 'in:bank,ewallet'],
            'bank_code' => ['nullable', 'string', 'max:16'],
            'account_number' => ['required', 'string', 'max:64'],
            'account_name' => ['required', 'string', 'max:120'],
        ]);

        $partners->addPayoutDestination($this->partner(), $data);

        return back()->with('success', 'Rekening tujuan ditambahkan.');
    }

    public function onboarding(Request $request)
    {
        $partner = $this->partner();

        return view('web.partner.onboarding', [
            'partner' => $partner,
            'categories' => Category::where('is_active', true)->whereNotNull('parent_id')->orderBy('sort')->get(),
        ]);
    }

    public function completeOnboarding(Request $request, PartnerService $partners)
    {
        $data = $request->validate([
            'type' => ['required', 'in:freelancer,individual,vendor_company'],
            'display_name' => ['required', 'string', 'max:120'],
            'about' => ['nullable', 'string', 'max:3000'],
            'city' => ['nullable', 'string', 'max:64'],
            'skills' => ['nullable', 'array', 'max:12'],
            'skills.*' => ['string', 'max:96'],
            'organization.name' => ['required_if:type,vendor_company', 'string', 'max:150'],
            'organization.legal_name' => ['nullable', 'string', 'max:150'],
            'organization.npwp' => ['nullable', 'string', 'max:32'],
        ]);

        if ($partner = $this->partner()) {
            $partner->update(collect($data)->except(['type', 'skills', 'organization'])->all());
        } else {
            $partner = $partners->register($request->user(), $data);
        }

        if (! empty($data['skills'])) {
            foreach ($data['skills'] as $skill) {
                $partner->skills()->firstOrCreate(['name' => $skill]);
            }
        }

        return redirect()->route('web.partner.dashboard')->with('success', 'Profil partner tersimpan! Lengkapi KYC untuk aktif.');
    }

    public function submitKyc(Request $request, \App\Domain\Trust\KycService $kyc)
    {
        $partner = $this->partner();

        if ($partner->verification_state !== 'unverified') {
            return back()->with('info', 'Verifikasi sudah diajukan.');
        }

        $partners = app(PartnerService::class);
        $partners->submitForVerification($partner);

        return back()->with('success', 'Dokumen diajukan. Tim kami akan meninjau dalam 1-2 hari kerja.');
    }

    public function reviews(Request $request)
    {
        return view('web.partner.reviews', [
            'partner' => $this->partner(),
            'reviews' => \App\Models\Review::where('partner_id', $this->partner()->id)->with('author:id,name')->latest()->paginate(15),
        ]);
    }

    public function respondReview(Request $request, int $id)
    {
        $data = $request->validate(['response' => ['required', 'string', 'max:2000']]);

        $review = \App\Models\Review::where('partner_id', $this->partner()->id)->whereNull('partner_response')->findOrFail($id);
        $review->update(['partner_response' => $data['response'], 'responded_at' => now()]);

        return back()->with('success', 'Tanggapan terkirim.');
    }
}
