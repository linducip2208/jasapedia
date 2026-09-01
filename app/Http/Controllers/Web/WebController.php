<?php

namespace App\Http\Controllers\Web;

use App\Domain\Order\OrderService;
use App\Domain\Payment\PaymentService;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Service;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Customer web storefront (Blade). Thin controllers delegating to domain services —
 * identical business rules as /api/v1 (no duplicated logic).
 */
class WebController extends Controller
{
    public function home()
    {
        return view('web.home', [
            'categories' => \App\Models\Category::where('is_active', true)->orderBy('sort')->get(),
            'services' => Service::query()->active()->with('category:id,name,icon', 'partner')->latest()->take(8)->get(),
            'activeOrder' => Auth::check()
                ? Order::where('user_id', Auth::id())->whereNotIn('status', ['completed', 'settled', 'closed', 'cancelled', 'expired', 'failed', 'refunded'])->latest()->first()
                : null,
        ]);
    }

    public function explore(Request $request)
    {
        $query = Service::query()->active()->with('category:id,name,icon', 'partner');

        if ($q = $request->string('q')->toString()) {
            $query->where(fn ($w) => $w->where('title', 'like', "%{$q}%")->orWhere('description', 'like', "%{$q}%"));
        }
        if ($category = $request->string('category')->toString()) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $category)
                ->orWhereHas('parent', fn ($p) => $p->where('slug', $category)));
        }
        if ($min = $request->integer('min_price')) {
            $query->where('base_price', '>=', $min);
        }
        if ($max = $request->integer('max_price')) {
            $query->where('base_price', '<=', $max);
        }
        if ($request->boolean('emergency')) {
            $query->where('emergency_capable', true);
        }

        match ($request->string('sort')->toString()) {
            'price_asc' => $query->orderBy('base_price'),
            'price_desc' => $query->orderByDesc('base_price'),
            'rating' => $query->select('services.*')->join('partners', 'partners.id', '=', 'services.partner_id')->orderByDesc('partners.rating_avg'),
            default => $query->latest(),
        };

        return view('web.explore', [
            'categories' => \App\Models\Category::where('is_active', true)->orderBy('sort')->get(),
            'services' => $query->paginate(12)->withQueryString(),
        ]);
    }

    public function service(Service $service)
    {
        abort_unless($service->status === 'active', 404);

        return view('web.service', [
            'service' => $service->load('category', 'partner', 'addons', 'packages'),
        ]);
    }

    /** Checkout → create order via the SAME domain service as the API. */
    public function checkout(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'emergency' => ['nullable', 'boolean'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $service = Service::findOrFail($data['service_id']);
        $order = $orders->createServiceOrder($request->user(), $service, $data);

        return redirect()->route('web.orders.show', $order->id)
            ->with('success', 'Pesanan dibuat! Lanjutkan pembayaran.');
    }

    public function orders(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('service:id,title', 'items')->latest()->paginate(10);

        return view('web.orders.index', ['orders' => $orders]);
    }

    public function orderShow(Request $request, int $id)
    {
        $order = Order::where('user_id', $request->user()->id)
            ->with('service', 'items', 'history', 'review')->findOrFail($id);
        $order->canCancel = true;

        return view('web.orders.show', ['order' => $order]);
    }

    public function pay(Request $request, int $id, PaymentService $payments)
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        if ($order->status !== 'pending_payment') {
            return back()->withErrors(['order' => 'Pesanan tidak dalam status menunggu pembayaran.']);
        }

        $payments->initialize($order);

        // Sandbox auto-pay for the web flow (dev only)
        if (! app()->environment('production')) {
            $tx = $order->paymentTransactions()->where('status', 'pending')->first();
            $event = app(\App\Domain\Payment\Gateways\SandboxGateway::class)->verifyWebhook(
                new Request($payload = [
                    'order_code' => $order->code,
                    'gateway_ref' => $tx->gateway_ref,
                    'amount' => (int) $order->total,
                    'status' => 'paid',
                    'event_id' => 'EVT-'.strtoupper(bin2hex(random_bytes(5))),
                    'method' => 'sandbox_qris',
                    'signature' => hash_hmac('sha256', $order->code, config('services.payments.sandbox_secret', 'sandbox-secret')),
                ]),
            );

            // inject signature header check path: verifyWebhook uses header — emulate signed call
            if (! $event) {
                $request->headers->set('X-Sandbox-Signature', $payload['signature']);
                $request->merge($payload);
                $event = app(\App\Domain\Payment\Contracts\PaymentGatewayInterface::class)->verifyWebhook($request);
            }

            app(PaymentService::class)->handleWebhook('sandbox', $event);

            return redirect()->route('web.orders.show', $order->id)->with('success', 'Pembayaran berhasil!');
        }

        return back()->with('info', 'Metode pembayaran production belum terpasang.');
    }

    public function confirm(Request $request, int $id, OrderService $orders)
    {
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);
        $orders->confirmCompletion($order, $request->user());

        return back()->with('success', 'Pesanan dikonfirmasi selesai. Terima kasih!');
    }

    public function checkin(Request $request, int $id)
    {
        $data = $request->validate(['otp' => ['required', 'string', 'size:6']]);
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);

        app(\App\Domain\FieldService\FieldServiceService::class)->verifyCheckin($order, $data['otp'], $request->user());

        return back()->with('success', 'Teknisi terkonfirmasi hadir.');
    }

    public function cancel(Request $request, int $id, OrderService $orders)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);
        $orders->cancel($order, $request->user(), $data['reason']);

        return back()->with('success', 'Pesanan dibatalkan.');
    }

    public function review(Request $request, int $id)
    {
        $data = $request->validate([
            'overall' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:3000'],
            'dimension_ratings' => ['required', 'array'],
        ]);

        $order = Order::where('user_id', $request->user()->id)->findOrFail($id);
        app(\App\Domain\Trust\ReviewService::class)->create($order, $request->user(), $data);

        return back()->with('success', 'Ulasan terkirim. Terima kasih!');
    }

    public function page(string $slug)
    {
        $page = \App\Models\CmsPage::where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('web.page', ['page' => $page]);
    }

    public function blogIndex()
    {
        $posts = \App\Models\BlogPost::where('status', 'published')
            ->orderByDesc('published_at')->paginate(12);

        return view('web.blog.index', ['posts' => $posts]);
    }

    public function blogShow(string $slug)
    {
        $post = \App\Models\BlogPost::where('slug', $slug)->where('status', 'published')->firstOrFail();

        return view('web.blog.show', ['post' => $post]);
    }
}
