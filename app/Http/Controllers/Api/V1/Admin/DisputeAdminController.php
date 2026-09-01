<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Trust\DisputeService;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Dispute;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisputeAdminController extends Controller
{
    use ApiResponse;

    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'dispute_id' => ['required', 'integer'],
            'resolution' => ['required', 'in:release_payment,partial_refund,full_refund,rework,service_credit,claim_rejected'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'note' => ['required', 'string', 'max:5000'],
        ]);

        $dispute = Dispute::findOrFail($data['dispute_id']);
        $dispute = app(DisputeService::class)->resolve($dispute, $request->user(), $data['resolution'], $data['amount'] ?? null, $data['note']);

        return $this->ok(['dispute' => $dispute], 'Dispute resolved.');
    }
}
