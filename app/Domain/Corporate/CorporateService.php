<?php

namespace App\Domain\Corporate;

use App\Domain\Auth\DomainException;
use App\Models\CorporateApprovalPolicy;
use App\Models\CorporateOrganization;
use App\Models\CorporateServiceRequest;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Corporate flow (doc 72): Employee → Service Request → Manager Approval
 * → Finance Approval (if required) → Order/RFQ.
 */
class CorporateService
{
    public function createRequest(CorporateOrganization $org, User $employee, array $data): CorporateServiceRequest
    {
        return DB::transaction(function () use ($org, $employee, $data) {
            $membership = DB::table('corporate_employees')
                ->where('organization_id', $org->id)
                ->where('user_id', $employee->id)
                ->first();

            if (! $membership) {
                throw new DomainException('Not a member of this organization.', 'FORBIDDEN', 403);
            }

            $policy = CorporateApprovalPolicy::where('organization_id', $org->id)->first();
            $estimated = $data['estimated_amount'] ?? 0;

            // Approval matrix (doc 72/73): first manager (when above threshold),
            // then finance only when amount crosses finance_threshold.
            $status = 'approved';
            if ($policy) {
                $needsFinance = $policy->finance_threshold !== null && $estimated >= $policy->finance_threshold;
                $needsManager = $estimated >= $policy->threshold && $policy->threshold > 0 || $estimated > 0;

                if ($needsManager) {
                    $status = 'pending_manager';
                }
                // finance step is decided at manager approval time (after manager signs off)
                unset($needsFinance);
            } elseif ($estimated > 0) {
                $status = 'pending_manager'; // default: manager approval required
            }

            return CorporateServiceRequest::create([
                'code' => 'CSR-'.now()->format('ymd').'-'.strtoupper(Str::random(5)),
                'organization_id' => $org->id,
                'requested_by' => $employee->id,
                'branch_id' => $data['branch_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'estimated_amount' => $estimated,
                'status' => $status,
                'po_reference' => $data['po_reference'] ?? null,
            ]);
        });
    }

    public function approve(CorporateServiceRequest $request, User $approver, string $level, string $note = ''): CorporateServiceRequest
    {
        return DB::transaction(function () use ($request, $approver, $level, $note) {
            $membership = DB::table('corporate_employees')
                ->where('organization_id', $request->organization_id)
                ->where('user_id', $approver->id)
                ->first();

            if (! $membership) {
                throw new DomainException('Not a member of this organization.', 'FORBIDDEN', 403);
            }

            if ($level === 'manager') {
                if ($request->status !== 'pending_manager') {
                    throw new DomainException("Request is {$request->status}.", 'INVALID_STATE', 409);
                }

                $policy = CorporateApprovalPolicy::where('organization_id', $request->organization_id)->first();
                $needsFinance = $policy && $policy->finance_threshold !== null
                    && (int) $request->estimated_amount >= $policy->finance_threshold;

                $request->update([
                    'manager_approved_by' => $approver->id,
                    'manager_approved_at' => now(),
                    'status' => $needsFinance ? 'pending_finance' : 'approved',
                ]);
            } elseif ($level === 'finance') {
                if (! in_array($membership->role, ['finance', 'admin'], true)) {
                    throw new DomainException('Finance role required.', 'FORBIDDEN', 403);
                }

                if ($request->status !== 'pending_finance') {
                    throw new DomainException("Request is {$request->status}.", 'INVALID_STATE', 409);
                }

                $request->update([
                    'finance_approved_by' => $approver->id,
                    'finance_approved_at' => now(),
                    'status' => 'approved',
                ]);
            } else {
                throw new DomainException('Invalid approval level.', 'INVALID_LEVEL', 422);
            }

            return $request->fresh();
        });
    }

    /** Convert an approved request into a real order (customer = requester). */
    public function convertToOrder(CorporateServiceRequest $request, int $serviceId, array $orderData): Order
    {
        return DB::transaction(function () use ($request, $serviceId, $orderData) {
            if ($request->status !== 'approved') {
                throw new DomainException("Request is {$request->status}; convert requires approved.", 'INVALID_STATE', 409);
            }

            $order = app(\App\Domain\Order\OrderService::class)->createServiceOrder(
                User::findOrFail($request->requested_by),
                \App\Models\Service::findOrFail($serviceId),
                $orderData,
            );

            $order->forceFill(['meta' => array_merge($order->meta ?? [], [
                'corporate_request_id' => $request->id,
                'po_reference' => $request->po_reference,
            ])])->save();

            $request->update(['status' => 'converted', 'order_id' => $order->id]);

            return $order;
        });
    }
}
