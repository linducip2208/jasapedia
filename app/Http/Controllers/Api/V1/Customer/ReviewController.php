<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Trust\DisputeService;
use App\Domain\Trust\KycService;
use App\Domain\Trust\ReviewService;
use App\Domain\Trust\WarrantyService;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Review;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    use ApiResponse;

    public function store(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'overall' => ['required', 'integer', 'min:1', 'max:5'],
            'dimension_ratings' => ['required', 'array'],
            'dimension_ratings.*' => ['integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:3000'],
            'images' => ['nullable', 'array', 'max:6'],
        ]);

        $order = $request->user()->hasMany(Order::class)->findOrFail($id);
        $review = app(ReviewService::class)->create($order, $request->user(), $data);

        return $this->created(['review' => $review], 'Review submitted.');
    }

    public function partnerReviews(Request $request, int $partnerId): JsonResponse
    {
        $reviews = Review::where('partner_id', $partnerId)
            ->where('status', 'published')
            ->with('author:id,name')
            ->latest()->paginate(20);

        return $this->paginated($reviews);
    }

    public function respond(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['response' => ['required', 'string', 'max:3000']]);

        $review = Review::findOrFail($id);
        $review = app(ReviewService::class)->respond($review, $request->user(), $data['response']);

        return $this->ok(['review' => $review], 'Response saved.');
    }

    public function warrantyClaim(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'issue' => ['required', 'string', 'max:3000'],
            'evidence' => ['nullable', 'array'],
        ]);

        $order = $request->user()->hasMany(Order::class)->findOrFail($id);
        $claim = app(WarrantyService::class)->claim($order, $request->user(), $data['issue'], $data['evidence'] ?? []);

        return $this->created(['warranty_claim' => $claim], 'Warranty claim submitted.');
    }

    public function openDispute(Request $request, int $orderId): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $order = Order::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)
                ->orWhereHas('partner', fn ($p) => $p->where('user_id', $request->user()->id));
        })->findOrFail($orderId);

        $dispute = app(DisputeService::class)->open($order, $request->user(), $data['reason'], $data['description']);

        return $this->created(['dispute' => $dispute], 'Dispute opened.');
    }

    public function addEvidence(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:message,photo,video,document,gps,work_log,quotation,contract,milestone,payment'],
            'file_path' => ['nullable', 'string', 'max:512'],
            'ref_type' => ['nullable', 'string', 'max:32'],
            'ref_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $dispute = Dispute::findOrFail($id);
        $evidence = app(DisputeService::class)->addEvidence($dispute, $request->user(), $data);

        return $this->created(['evidence' => $evidence]);
    }

    public function kycSubmit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identity' => ['nullable', 'array'],
            'company' => ['nullable', 'array'],
            'documents' => ['nullable', 'array'],
            'documents.*.type' => ['required_with:documents', 'string', 'max:32'],
            'documents.*.path' => ['required_with:documents', 'string', 'max:512'],
            'documents.*.number' => ['nullable', 'string', 'max:96'],
            'bank_account' => ['nullable', 'string', 'max:64'],
        ]);

        $partner = Partner::where('user_id', $request->user()->id)->first()
            ?? throw new \App\Domain\Auth\DomainException('No partner profile.', 'PARTNER_NOT_FOUND', 404);

        $submission = app(KycService::class)->submit($partner, $data);

        return $this->created(['submission' => $submission], 'Verification submitted.');
    }
}
