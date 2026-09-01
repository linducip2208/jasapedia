<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Deal\RfqService;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Quotation;
use App\Models\Rfq;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfqController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly RfqService $rfqs)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:10000'],
            'requirements' => ['nullable', 'array'],
            'attachments' => ['nullable', 'array'],
            'deadline' => ['nullable', 'date', 'after:now'],
            'visibility' => ['nullable', 'in:public,invited'],
            'invited_partner_ids' => ['nullable', 'array'],
        ]);

        $rfq = $this->rfqs->create($request->user(), $data);

        return $this->created(['rfq' => $rfq], 'RFQ published.');
    }

    public function index(Request $request): JsonResponse
    {
        $rfqs = Rfq::where('user_id', $request->user()->id)
            ->with('quotations.partner:id,display_name,slug,rating_avg')
            ->latest()->paginate(20);

        return $this->paginated($rfqs);
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $rfq = Rfq::where('user_id', $request->user()->id)->findOrFail($id);
        $rfq = $this->rfqs->close($rfq, $request->user());

        return $this->ok(['rfq' => $rfq], 'RFQ closed.');
    }

    public function approveQuotation(Request $request, int $quotationId): JsonResponse
    {
        $quotation = Quotation::findOrFail($quotationId);
        $quotation = $this->rfqs->approveQuotation($quotation, $request->user());

        return $this->ok(['quotation' => $quotation], 'Quotation approved.');
    }
}
