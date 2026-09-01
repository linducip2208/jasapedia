<?php

namespace App\Domain\Payment\Contracts;

final readonly class GatewayEvent
{
    public function __construct(
        public string $eventId,
        public string $type,
        public string $orderCode,
        public string $gatewayRef,
        public string $status, // paid|failed|expired|cancelled|refunded|pending
        public ?int $amountIdr,
        public array $raw,
    ) {
    }
}
