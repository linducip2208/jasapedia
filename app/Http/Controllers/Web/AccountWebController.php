<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Project;
use App\Models\Rfq;
use Illuminate\Http\Request;

class AccountWebController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();

        return view('web.account.dashboard', [
            'activeOrders' => Order::where('user_id', $user->id)
                ->whereNotIn('status', ['completed', 'settled', 'closed', 'cancelled', 'expired', 'failed', 'refunded'])
                ->with('service:id,title,slug')->latest()->take(5)->get(),
            'activeOrdersCount' => Order::where('user_id', $user->id)
                ->whereNotIn('status', ['completed', 'settled', 'closed', 'cancelled', 'expired', 'failed', 'refunded'])->count(),
            'upcomingBookings' => Order::where('user_id', $user->id)
                ->whereNotNull('scheduled_at')->where('scheduled_at', '>=', now())
                ->whereNotIn('status', ['cancelled', 'expired', 'failed'])
                ->orderBy('scheduled_at')->take(5)->get(),
            'openRequests' => Rfq::where('user_id', $user->id)->where('status', 'open')->count(),
            'activeProjects' => Project::where('user_id', $user->id)->whereIn('status', ['receiving_proposals', 'shortlisting', 'awarded', 'contracting', 'active'])->count(),
            'unreadChat' => 0, // computed client-side via poll; in-app badge on header
            'recentActivity' => Order::where('user_id', $user->id)->with('service:id,title')->latest()->take(6)->get(),
        ]);
    }

    public function profile(Request $request)
    {
        return view('web.account.profile', [
            'user' => $request->user()->load('profile'),
            'addresses' => CustomerAddress::where('user_id', $request->user()->id)->with('subdistrict')->get(),
        ]);
    }

    public function notifications(Request $request)
    {
        $user = $request->user();

        $notifications = \Illuminate\Support\Facades\DB::table('notifications')
            ->where('user_id', $user->id)
            ->latest()
            ->paginate(20);

        \Illuminate\Support\Facades\DB::table('notifications')
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return view('web.account.notifications', ['notifications' => $notifications]);
    }

    public function orders(Request $request, string $status = 'all')
    {
        $query = Order::where('user_id', $request->user()->id)->with('service:id,title,slug')->latest();

        $statusGroups = [
            'active' => ['pending_payment', 'paid', 'searching_provider', 'offered', 'accepted', 'assigned', 'on_the_way', 'arrived', 'checked_in', 'working', 'awaiting_customer_confirmation'],
            'done' => ['completed', 'settled', 'closed'],
            'cancelled' => ['cancelled', 'expired', 'failed', 'refunded'],
        ];

        if (isset($statusGroups[$status])) {
            $query->whereIn('status', $statusGroups[$status]);
        }

        return view('web.account.orders', [
            'orders' => $query->paginate(12),
            'tab' => $status,
        ]);
    }

    public function storeAddress(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:20'],
            'subdistrict_id' => ['required', 'integer', 'exists:locations,id'],
            'address_line' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $data['user_id'] = $request->user()->id;
        if ($data['is_default'] ?? false) {
            CustomerAddress::where('user_id', $request->user()->id)->update(['is_default' => false]);
        }

        CustomerAddress::create($data);

        return back()->with('success', 'Alamat tersimpan.');
    }

    public function destroyAddress(Request $request, int $id)
    {
        CustomerAddress::where('user_id', $request->user()->id)->where('id', $id)->delete();

        return back()->with('success', 'Alamat dihapus.');
    }
}
