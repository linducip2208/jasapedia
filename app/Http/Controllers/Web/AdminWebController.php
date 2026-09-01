<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\KycSubmission;
use App\Models\LedgerEntry;
use App\Models\Order;
use App\Models\Partner;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Settlement;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin Command Center — all values computed from real data. No fabricated analytics.
 */
class AdminWebController extends Controller
{
    public function dashboard(Request $request)
    {
        $this->authorizeAdmin($request);

        $finalStatuses = ['completed', 'settled', 'closed', 'cancelled', 'expired', 'failed', 'refunded'];
        $gmv = (int) Order::whereIn('status', ['completed', 'settled', 'closed'])->sum('total');
        $ordersTotal = Order::count();
        $cancelled = Order::whereIn('status', ['cancelled', 'expired', 'failed'])->count();

        return view('web.admin.dashboard', [
            'gmv' => $gmv,
            'orders' => $ordersTotal,
            'activeOrders' => Order::whereNotIn('status', $finalStatuses)->count(),
            'completedOrders' => Order::whereIn('status', ['completed', 'settled', 'closed'])->count(),
            'cancelRate' => $ordersTotal > 0 ? round($cancelled / $ordersTotal * 100, 1) : 0,
            'disputeRate' => $ordersTotal > 0 ? round(Dispute::count() / max(1, Order::whereIn('status', ['completed', 'settled', 'closed'])->count()) * 100, 1) : 0,
            'activeProviders' => Partner::where('verification_state', 'verified')->count(),
            'customers' => User::where('status', 'active')->whereDoesntHave('roles')->count(),
            'commission' => (int) LedgerEntry::query()
                ->join('ledger_accounts', 'ledger_accounts.id', '=', 'ledger_entries.ledger_account_id')
                ->where('ledger_accounts.code', '4201')
                ->sum('ledger_entries.credit'),
            'pendingSettlement' => Settlement::where('status', 'pending')->count(),
            'pendingWithdrawal' => Withdrawal::whereIn('status', ['requested', 'under_review', 'approved', 'processing'])->count(),
            // Operations
            'searchingProvider' => Order::where('status', 'searching_provider')->count(),
            'onTheWay' => Order::where('status', 'on_the_way')->count(),
            'working' => Order::where('status', 'working')->count(),
            'awaitingConfirmation' => Order::where('status', 'awaiting_customer_confirmation')->count(),
            'kycPending' => KycSubmission::where('status', 'pending')->count(),
            'disputesOpen' => Dispute::where('status', 'open')->count(),
            // Charts: last 14 days from real data
            'orderSeries' => $this->dailySeries('orders', 'created_at'),
            'gmvSeries' => $this->dailyGmv(),
        ]);
    }

    public function orders(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = Order::with(['user:id,name', 'partner:id,display_name', 'service:id,title'])->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return view('web.admin.orders', ['orders' => $query->paginate(25)]);
    }

    public function partners(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('web.admin.partners', [
            'partners' => Partner::with('user:id,email')->latest()->paginate(25),
        ]);
    }

    public function verifyPartner(Request $request, int $id)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate(['state' => ['required', 'in:verified,rejected,needs_revision,suspended']]);

        $partner = Partner::findOrFail($id);
        $partner->update(['verification_state' => $data['state']]);

        app(\App\Support\Audit\AuditLogger::class)->log('admin.partner.verification', $partner, null, $data, 'Admin changed verification state', $request->ip(), $request->user());

        return back()->with('success', 'Status verifikasi diperbarui.');
    }

    public function finance(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('web.admin.finance', [
            'settlements' => Settlement::with('order:id,code')->latest()->paginate(25),
            'withdrawals' => Withdrawal::with('partner:id,display_name')->latest()->paginate(25),
            'refunds' => Refund::latest()->take(15)->get(),
            'debits' => (int) LedgerEntry::sum('debit'),
            'credits' => (int) LedgerEntry::sum('credit'),
        ]);
    }

    public function withdrawalAction(Request $request, int $id)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate(['action' => ['required', 'in:under_review,approved,rejected,processing,completed,failed']]);

        $withdrawal = Withdrawal::findOrFail($id);

        try {
            app(\App\Domain\Finance\WithdrawalService::class)->transition(
                $withdrawal,
                $data['action'],
                $request->user(),
                $request->string('provider_ref')->toString() ?: null,
            );
        } catch (\App\Domain\Common\Exceptions\StateTransitionException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('success', 'Status withdrawal diperbarui.');
    }

    public function disputes(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('web.admin.disputes', [
            'disputes' => Dispute::with(['order:id,code,total'])->latest()->paginate(20),
        ]);
    }

    public function resolveDispute(Request $request, int $id)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'resolution' => ['required', 'in:refund_customer,reject,partial_refund'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'note' => ['required', 'string', 'max:2000'],
        ]);

        $dispute = Dispute::findOrFail($id);

        try {
            app(\App\Domain\Trust\DisputeService::class)->resolve($dispute, $request->user(), $data);
        } catch (\Throwable $e) {
            return back()->withErrors(['resolution' => $e->getMessage()]);
        }

        return back()->with('success', 'Sengketa diselesaikan.');
    }

    public function users(Request $request)
    {
        $this->authorizeAdmin($request);

        return view('web.admin.users', [
            'users' => User::with('roles:id,name')->latest()->paginate(25),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        $user = $request->user();
        abort_unless((bool) $user, 403, 'Akses khusus admin.');

        $permissions = $user->permissions();
        $isAdmin = collect($permissions)->contains(fn ($p) => str_starts_with($p, 'admin.'))
            || in_array('audit.view', $permissions, true)
            || in_array('reports.view', $permissions, true);

        abort_unless($isAdmin, 403, 'Akses khusus admin.');
    }

    private function dailySeries(string $table, string $column, int $days = 14): array
    {
        $rows = DB::table($table)
            ->selectRaw("DATE({$column}) as d, COUNT(*) as c")
            ->where($column, '>=', now()->subDays($days))
            ->groupBy('d')->orderBy('d')->get();

        return $rows->map(fn ($r) => ['date' => $r->d, 'count' => (int) $r->c])->all();
    }

    private function dailyGmv(int $days = 14): array
    {
        $rows = DB::table('orders')
            ->selectRaw('DATE(created_at) as d, SUM(total) as c')
            ->whereIn('status', ['completed', 'settled', 'closed'])
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('d')->orderBy('d')->get();

        return $rows->map(fn ($r) => ['date' => $r->d, 'total' => (int) $r->c])->all();
    }
}
