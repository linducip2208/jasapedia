<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\Contracts\GatewayEvent;
use App\Domain\Payment\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Xendit production adapter. Credentials via env only:
 * XENDIT_SECRET_KEY, XENDIT_WEBHOOK_TOKEN, XENDIT_CALLBACK_URL.
 * Webhook verification: X-Callback-Token must equal configured token (constant-time compare).
 */
class XenditPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $callbackToken,
    ) {
    }

    public static function fromConfig(): self
    {
        $key = (string) config('services.payments.xendit.secret_key');
        $token = (string) config('services.payments.xendit.callback_token');

        if ($key === '' || $token === '') {
            throw new RuntimeException('Xendit not configured: set XENDIT_SECRET_KEY and XENDIT_WEBHOOK_TOKEN.');
        }

        return new self($key, $token);
    }

    public function createIntent(string $orderCode, int $amountIdr, array $options = []): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(15)
            ->post('https://api.xendit.co/v2/invoices', [
                'external_id' => $orderCode,
                'amount' => $amountIdr,
                'description' => $options['description'] ?? 'Jasapedia order '.$orderCode,
                'success_redirect_url' => $options['success_url'] ?? url('/orders'),
                'failure_redirect_url' => $options['failure_url'] ?? url('/orders'),
                'currency' => 'IDR',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Xendit invoice creation failed: '.$response->status());
        }

        $data = $response->json();

        return [
            'ref' => (string) $data['id'],
            'payment_data' => [
                'type' => 'xendit',
                'pay_url' => $data['invoice_url'],
                'expires_at' => $data['expiry_date'] ?? null,
            ],
        ];
    }

    public function verifyWebhook(Request $request): ?GatewayEvent
    {
        $token = $request->header('x-callback-token', '');

        if (! hash_equals($this->callbackToken, $token)) {
            return null;
        }

        $raw = $request->json()->all();
        $status = match ((string) ($raw['status'] ?? '')) {
            'PAID', 'SETTLED' => 'paid',
            'EXPIRED' => 'expired',
            'VOIDED', 'CANCELLED' => 'cancelled',
            default => 'pending',
        };

        return new GatewayEvent(
            eventId: (string) ($raw['id'] ?? uniqid('xnd-', true)),
            type: 'payment.'.$status,
            orderCode: (string) ($raw['external_id'] ?? ''),
            gatewayRef: (string) ($raw['id'] ?? ''),
            status: $status,
            amountIdr: isset($raw['paid_amount']) ? (int) $raw['paid_amount'] : (isset($raw['amount']) ? (int) $raw['amount'] : null),
            raw: $raw,
        );
    }

    public function status(string $gatewayRef): string
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(10)
            ->get("https://api.xendit.co/v2/invoices/{$gatewayRef}");

        if (! $response->successful()) {
            return 'pending';
        }

        return match ($response->json('status')) {
            'PAID', 'SETTLED' => 'paid',
            'EXPIRED' => 'expired',
            'VOIDED' => 'cancelled',
            default => 'pending',
        };
    }

    public function refund(string $gatewayRef, int $amountIdr, string $reason = ''): array
    {
        $response = Http::withBasicAuth($this->secretKey, '')
            ->timeout(15)
            ->post('https://api.xendit.co/credit_card/refunds', [
                'invoice_id' => $gatewayRef,
                'amount' => $amountIdr,
                'reason' => $reason ?: 'Jasapedia refund',
            ]);

        return [
            'ref' => (string) ($response->json('id') ?? ''),
            'status' => $response->successful() ? 'refunded' : 'failed',
        ];
    }
}
