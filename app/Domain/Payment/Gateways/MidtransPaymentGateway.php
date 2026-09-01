<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\Contracts\GatewayEvent;
use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Midtrans production adapter (Snap). Credentials via env only:
 * MIDTRANS_SERVER_KEY, MIDTRANS_CLIENT_KEY, MIDTRANS_IS_PRODUCTION.
 * Webhook verification: recompute sha512(order_id+status_code+gross_amount+server_key) (constant-time).
 */
class MidtransPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $serverKey,
        private readonly bool $production,
    ) {
    }

    public static function fromConfig(): self
    {
        $key = (string) config('services.payments.midtrans.server_key');

        if ($key === '') {
            throw new RuntimeException('Midtrans not configured: set MIDTRANS_SERVER_KEY.');
        }

        return new self($key, (bool) config('services.payments.midtrans.production', false));
    }

    private function apiBase(): string
    {
        return $this->production
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    public function createIntent(string $orderCode, int $amountIdr, array $options = []): array
    {
        $response = Http::withBasicAuth($this->serverKey, '')
            ->timeout(15)
            ->post($this->apiBase().'/snap/transactions', [
                'transaction_details' => [
                    'order_id' => $orderCode,
                    'gross_amount' => $amountIdr,
                ],
                'item_details' => [[
                    'id' => $orderCode,
                    'name' => Str::limit($options['description'] ?? 'Jasapedia order', 40),
                    'price' => $amountIdr,
                    'quantity' => 1,
                ]],
                'credit_card' => ['secure' => true],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Midtrans snap creation failed: '.$response->status());
        }

        $data = $response->json();

        return [
            'ref' => (string) ($data['token'] ?? ''),
            'payment_data' => [
                'type' => 'midtrans',
                'pay_url' => $data['redirect_url'] ?? null,
                'snap_token' => $data['token'] ?? null,
            ],
        ];
    }

    public function verifyWebhook(Request $request): ?GatewayEvent
    {
        $raw = $request->json()->all();
        $orderId = (string) ($raw['order_id'] ?? '');
        $statusCode = (string) ($raw['status_code'] ?? '');
        $grossAmount = (string) ($raw['gross_amount'] ?? '');
        $expectedSig = hash('sha512', $orderId.$statusCode.$grossAmount.$this->serverKey);

        if (! hash_equals($expectedSig, (string) $request->input('signature_key', ''))) {
            return null;
        }

        $status = match ((string) ($raw['transaction_status'] ?? '')) {
            'settlement', 'capture' => 'paid',
            'deny', 'cancel' => 'cancelled',
            'expire' => 'expired',
            'refund', 'partial_refund' => 'refunded',
            default => 'pending',
        };

        return new GatewayEvent(
            eventId: (string) ($raw['transaction_id'] ?? uniqid('mdt-', true)),
            type: 'payment.'.$status,
            orderCode: $orderId,
            gatewayRef: (string) ($raw['transaction_id'] ?? ''),
            status: $status,
            amountIdr: $grossAmount !== '' ? (int) round((float) $grossAmount) : null,
            raw: $raw,
        );
    }

    public function status(string $gatewayRef): string
    {
        // Note: Midtrans status endpoint is keyed by order_id.
        $response = Http::withBasicAuth($this->serverKey, '')
            ->timeout(10)
            ->get("{$this->apiBase()}/{$gatewayRef}/status");

        if (! $response->successful()) {
            return 'pending';
        }

        return match ($response->json('transaction_status')) {
            'settlement', 'capture' => 'paid',
            'deny', 'cancel' => 'cancelled',
            'expire' => 'expired',
            default => 'pending',
        };
    }

    public function refund(string $gatewayRef, int $amountIdr, string $reason = ''): array
    {
        $response = Http::withBasicAuth($this->serverKey, '')
            ->timeout(15)
            ->post($this->apiBase()."/{$gatewayRef}/refund", [
                'amount' => $amountIdr,
                'reason' => $reason ?: 'Jasapedia refund',
            ]);

        return [
            'ref' => (string) ($response->json('refund_key') ?? ''),
            'status' => $response->successful() ? 'refunded' : 'failed',
        ];
    }
}
