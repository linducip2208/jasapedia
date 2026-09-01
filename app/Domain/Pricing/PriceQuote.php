<?php

namespace App\Domain\Pricing;

use App\Support\Money\Money;

final readonly class PriceQuote
{
    /**
     * @param PriceLine[] $lines
     */
    public function __construct(
        public int $serviceId,
        public int $partnerId,
        public string $priceModel,
        public array $lines,
        public Money $subtotal,
        public Money $emergencySurcharge,
        public Money $total,
        public string $currency,
        public string $calculatedAt,
    ) {
    }

    public function snapshot(): array
    {
        return [
            'service_id' => $this->serviceId,
            'partner_id' => $this->partnerId,
            'price_model' => $this->priceModel,
            'lines' => array_map(fn ($l) => $l->toArray(), $this->lines),
            'subtotal' => $this->subtotal->amount,
            'emergency_surcharge' => $this->emergencySurcharge->amount,
            'total' => $this->total->amount,
            'currency' => $this->currency,
            'calculated_at' => $this->calculatedAt,
        ];
    }
}
