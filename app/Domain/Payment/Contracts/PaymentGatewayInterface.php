<?php

namespace App\Domain\Payment\Contracts;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /** Create a payment intent at the provider. Returns [ref, redirect/payment data]. */
    public function createIntent(string $orderCode, int $amountIdr, array $options = []): array;

    /** Verify & normalize an incoming webhook. Returns null if invalid. */
    public function verifyWebhook(Request $request): ?GatewayEvent;

    /** Query live status (reconciliation helper). */
    public function status(string $gatewayRef): string;

    /** Refund an amount. Returns [ref, status]. */
    public function refund(string $gatewayRef, int $amountIdr, string $reason = ''): array;
}
