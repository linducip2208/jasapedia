<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\Contracts\GatewayEvent;
use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic sandbox gateway for tests/dev. NOT for production.
 * Webhook simulation: POST /api/v1/payments/webhook/sandbox with
 * X-Sandbox-Signature = sha256(secret + order_code).
 */
class SandboxGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly string $secret = 'sandbox-secret')
    {
    }

    public function createIntent(string $orderCode, int $amountIdr, array $options = []): array
    {
        return [
            'ref' => 'SBX-'.strtoupper(bin2hex(random_bytes(6))),
            'payment_data' => [
                'type' => 'sandbox',
                'order_code' => $orderCode,
                'amount' => $amountIdr,
                'pay_url' => url("/sandbox/pay/{$orderCode}"),
                'signature' => hash_hmac('sha256', $orderCode, $this->secret),
            ],
        ];
    }

    public function verifyWebhook(Request $request): ?GatewayEvent
    {
        $expected = $request->header('X-Sandbox-Signature');
        $orderCode = (string) $request->input('order_code', '');

        if (! $expected || ! hash_equals(hash_hmac('sha256', $orderCode, $this->secret), $expected)) {
            return null;
        }

        $status = (string) $request->input('status', 'paid');

        return new GatewayEvent(
            eventId: (string) $request->input('event_id', (string) str()->uuid()),
            type: 'payment.'.$status,
            orderCode: $orderCode,
            gatewayRef: (string) $request->input('gateway_ref', ''),
            status: $status,
            amountIdr: $request->integer('amount') ?: null,
            raw: $request->all(),
        );
    }

    public function status(string $gatewayRef): string
    {
        return 'pending';
    }

    public function refund(string $gatewayRef, int $amountIdr, string $reason = ''): array
    {
        return ['ref' => 'SBX-REF-'.strtoupper(bin2hex(random_bytes(6))), 'status' => 'refunded'];
    }
}
