<?php

namespace App\Http\Controllers\Web;

use App\Domain\Deal\RfqService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Quotation;
use App\Models\Rfq;
use Illuminate\Http\Request;

class RequestWebController extends Controller
{
    /** Customer "Posting Kebutuhan" — reuses the RFQ domain (no duplicate subsystem). */
    public function index(Request $request)
    {
        return view('web.requests.index', [
            'requests' => Rfq::where('user_id', $request->user()->id)
                ->withCount('quotations')->latest()->paginate(12),
        ]);
    }

    public function create(Request $request)
    {
        return view('web.requests.create', [
            'categories' => Category::where('is_active', true)->whereNotNull('parent_id')->orderBy('sort')->get(),
        ]);
    }

    public function store(Request $request, RfqService $rfqs)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'requirements' => ['nullable', 'array'],
            'deadline' => ['nullable', 'date', 'after:now'],
            'attachments' => ['nullable', 'array', 'max:5'],
        ]);

        $rfq = $rfqs->create($request->user(), $data + ['visibility' => 'public']);

        return redirect()->route('web.requests.show', $rfq->id)
            ->with('success', 'Kebutuhan terpublikasi! Penyedia akan mengirim penawaran.');
    }

    public function show(Request $request, int $id)
    {
        $rfq = Rfq::with(['quotations' => fn ($q) => $q->where('status', 'sent')->with('partner:id,display_name,slug,rating_avg,verification_state')])
            ->withCount('quotations')
            ->where('user_id', $request->user()->id)
            ->findOrFail($id);

        return view('web.requests.show', ['rfq' => $rfq]);
    }

    public function close(Request $request, int $id, RfqService $rfqs)
    {
        $rfq = Rfq::where('user_id', $request->user()->id)->findOrFail($id);
        $rfqs->close($rfq, $request->user());

        return back()->with('success', 'Kebutuhan ditutup.');
    }

    public function acceptQuotation(Request $request, int $id, int $quotationId, RfqService $rfqs)
    {
        $quotation = Quotation::where('rfq_id', $id)->findOrFail($quotationId);
        $rfqs->approveQuotation($quotation, $request->user());

        return back()->with('success', 'Penawaran diterima! Lanjutkan ke pemesanan.');
    }
}
