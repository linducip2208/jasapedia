<?php

namespace App\Domain\Pricing;

final readonly class PriceLine
{
    public function __construct(
        public string $name,
        public int $qty,
        public int $unitPrice,
        public string $type,
        public ?int $refId = null,
        public ?string $unitLabel = null,
    ) {
    }

    public static function make(string $name, int $qty, int $unitPrice, string $type, ?int $refId = null, ?string $unitLabel = null): self
    {
        return new self($name, $qty, $unitPrice, $type, $refId, $unitLabel);
    }

    public function amount(): int
    {
        return $this->qty * $this->unitPrice;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'qty' => $this->qty,
            'unit_price' => $this->unitPrice,
            'amount' => $this->amount(),
            'type' => $this->type,
            'ref_id' => $this->refId,
            'unit_label' => $this->unitLabel,
        ];
    }
}
