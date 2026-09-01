<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Domain\Order\OrderService;
use App\Domain\Payment\PaymentService;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Order;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly OrderService $orders,
    ) {
    }

    /** Create (or reuse) a payment intent for an order. */
    public function createIntent(Request $request): JsonResponse
    {
        $data = $request->validate(['order_id' => ['required', 'integer']]);

        $order = $request->user()->hasMany(Order::class)->findOrFail($data['order_id']);

        if ($order->status !== 'pending_payment') {
            return $this->fail('INVALID_ORDER_STATE', "Order is {$order->status}, not payable.", 409);
        }

        $tx = $this->payments->initialize($order);

        return $this->ok([
            'payment' => [
                'id' => $tx->id,
                'gateway_ref' => $tx->gateway_ref,
                'status' => $tx->status,
                'amount' => $tx->amount,
                'gateway' => $tx->gateway,
                'payment_data' => $tx->meta['payment_data'] ?? null,
            ],
        ]);
    }

    /** Webhook receiver — signature verified, idempotent, retry safe. */
    public function webhook(Request $request, string $gateway): \Illuminate\Http\Response
    {
        $event = app(\App\Domain\Payment\Contracts\PaymentGatewayInterface::class)->verifyWebhook($request);

        if (! $event) {
            abort(401, 'Invalid webhook signature');
        }

        app(PaymentService::class)->handleWebhook($gateway, $event);

        return response('OK', 200);
    }

    /** Manual confirm (sandbox/testing + manual transfer staff flow). */
    public function sandboxPay(Request $request): JsonResponse
    {
        if (app()->environment('production')) {
            abort(404);
        }

        $data = $request->validate([
            'order_code' => ['required', 'string'],
            'event_id' => ['nullable', 'string'],
            'method' => ['nullable', 'string', 'max:32'],
        ]);

        $order = Order::where('code', $data['order_code'])->firstOrFail();
        $tx = \App\Models\PaymentTransaction::where('order_id', $order->id)
            ->whereIn('status', ['created', 'pending'])
            ->first();

        if (! $tx) {
            $tx = app(PaymentService::class)->initialize($order);
        }

        $payload = [
            'order_code' => $order->code,
            'gateway_ref' => $tx->gateway_ref,
            'amount' => (int) $order->total,
            'status' => 'paid',
            'event_id' => $data['event_id'] ?? 'EVT-'.strtoupper(bin2hex(random_bytes(6))),
            'method' => $data['method'] ?? 'sandbox_qris',
        ];
        $payload['signature'] = hash_hmac('sha256', $order->code, config('services.payments.sandbox_secret', 'sandbox-secret'));

        $request->headers->set('X-Sandbox-Signature', $payload['signature']);
        $request->merge($payload);

        $event = app(\App\Domain\Payment\Contracts\PaymentGatewayInterface::class)->verifyWebhook($request);
        app(PaymentService::class)->handleWebhook('sandbox', $event);

        return $this->ok(['order' => $order->fresh(), 'payment' => $tx->fresh()], 'Payment simulated.');
    }
}
