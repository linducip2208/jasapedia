<?php

namespace App\Domain\Pricing;

final readonly class PricingInput
{
    public function __construct(
        public ?int $quantity = null,
        public ?int $packageId = null,
        public array $addonIds = [],
        public bool $emergency = false,
        public ?int $durationMinutes = null,
        public ?int $durationDays = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            quantity: isset($data['quantity']) ? (int) $data['quantity'] : null,
            packageId: isset($data['package_id']) ? (int) $data['package_id'] : null,
            addonIds: isset($data['addon_ids']) ? array_map('intval', $data['addon_ids']) : [],
            emergency: (bool) ($data['emergency'] ?? false),
            durationMinutes: isset($data['duration_minutes']) ? (int) $data['duration_minutes'] : null,
            durationDays: isset($data['duration_days']) ? (int) $data['duration_days'] : null,
        );
    }
}
