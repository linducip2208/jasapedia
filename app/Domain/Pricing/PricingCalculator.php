<?php

namespace App\Domain\Pricing;

use App\Domain\Auth\DomainException;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\ServicePackage;
use App\Support\Money\Money;

/**
 * Backend-authoritative price calculator (doc 22/23).
 * Every finalized transaction must snapshot this structure.
 */
class PricingCalculator
{
    public function quote(Service $service, PricingInput $input): PriceQuote
    {
        $this->validate($service, $input);

        $lines = [];

        if ($input->packageId !== null) {
            $package = ServicePackage::where('service_id', $service->id)->findOrFail($input->packageId);
            $lines[] = PriceLine::make($package->name, 1, $package->price, 'package', $package->id);
        } else {
            $qty = $input->quantity ?? 1;
            $qty = max($service->min_quantity ?: 1, $qty);
            if ($service->max_quantity) {
                $qty = min($service->max_quantity, $qty);
            }

            $unitPrice = (int) $service->base_price;

            switch ($service->price_model) {
                case 'per_unit':
                    $lines[] = PriceLine::make($service->title, $qty, $unitPrice, 'unit', null, $service->unit_label);
                    break;

                case 'hourly':
                    $hours = (int) ceil(($input->durationMinutes ?? $service->duration_minutes ?? 60) / 60);
                    $lines[] = PriceLine::make($service->title, $hours, $unitPrice, 'hour', null, 'jam');
                    break;

                case 'daily':
                    $days = max(1, (int) ($input->durationDays ?? 1));
                    $lines[] = PriceLine::make($service->title, $days, $unitPrice, 'day', null, 'hari');
                    break;

                default: // fixed, starting_from, package(no pkg id → base), quotation base deposit, etc.
                    $lines[] = PriceLine::make($service->title, 1, $unitPrice, 'base', null);
                    break;
            }
        }

        if ($input->addonIds !== []) {
            $addons = ServiceAddon::where('service_id', $service->id)
                ->whereIn('id', $input->addonIds)
                ->where('is_active', true)
                ->get();

            if ($addons->count() !== count($input->addonIds)) {
                throw new DomainException('One or more addons are invalid.', 'INVALID_ADDON', 422);
            }

            foreach ($addons as $addon) {
                $lines[] = PriceLine::make($addon->name, 1, (int) $addon->price, 'addon', $addon->id);
            }
        }

        $subtotal = 0;
        foreach ($lines as $line) {
            $subtotal += $line->qty * $line->unitPrice;
        }

        $emergencySurcharge = 0;
        if ($input->emergency && $service->emergency_capable) {
            $emergencySurcharge = (int) $service->emergency_surcharge;
            $lines[] = PriceLine::make('Surcharge darurat', 1, $emergencySurcharge, 'emergency', null);
        }

        $total = $subtotal + $emergencySurcharge;

        return new PriceQuote(
            serviceId: $service->id,
            partnerId: $service->partner_id,
            priceModel: $service->price_model,
            lines: $lines,
            subtotal: new Money($subtotal),
            emergencySurcharge: new Money($emergencySurcharge),
            total: new Money($total),
            currency: 'IDR',
            calculatedAt: now()->toIso8601String(),
        );
    }

    private function validate(Service $service, PricingInput $input): void
    {
        if (! in_array($service->status, ['active'], true)) {
            throw new DomainException('Service is not bookable.', 'SERVICE_INACTIVE', 409);
        }

        if ($service->price_model === 'package' && $input->packageId === null) {
            throw new DomainException('A package must be selected for this service.', 'PACKAGE_REQUIRED', 422);
        }

        if ($service->price_model === 'quotation') {
            throw new DomainException('This service requires a quotation request, not direct booking.', 'QUOTATION_REQUIRED', 409);
        }
    }
}
