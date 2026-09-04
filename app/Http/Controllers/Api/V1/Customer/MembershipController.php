<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Domain\Growth\MembershipService;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\User;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MembershipController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly MembershipService $memberships)
    {
    }

    public function plans(): JsonResponse
    {
        return $this->ok(['plans' => MembershipPlan::where('is_active', true)->get()]);
    }

    public function myMembership(Request $request): JsonResponse
    {
        return $this->ok(['membership' => $this->current($request)]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = MembershipPlan::findOrFail($data['plan_id']);
        $order = $this->memberships->subscribe($request->user(), $plan, $data['cycle']);

        return $this->created(['order' => $order, 'membership' => $this->current($request)], 'Membership invoice paid.');
    }

    public function renew(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:membership_plans,id'],
            'cycle' => ['required', 'in:monthly,yearly'],
        ]);

        $plan = MembershipPlan::findOrFail($data['plan_id']);
        $order = $this->memberships->subscribe($request->user(), $plan, $data['cycle']);

        return $this->created(['order' => $order, 'membership' => $this->current($request)], 'Membership renewed.');
    }

    public function cancel(Request $request): JsonResponse
    {
        $updated = Membership::where('member_type', User::class)
            ->where('member_id', $request->user()->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        if ($updated === 0) {
            return $this->ok(['cancelled' => false], 'No active membership.');
        }

        return $this->ok(['cancelled' => true], 'Membership cancelled.');
    }

    private function current(Request $request): ?Membership
    {
        return Membership::where('member_type', User::class)
            ->where('member_id', $request->user()->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->with('plan')
            ->orderByDesc('ends_at')
            ->first();
    }
}
