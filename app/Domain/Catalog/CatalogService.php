<?php

namespace App\Domain\Catalog;

use App\Domain\Auth\DomainException;
use App\Models\Partner;
use App\Models\Service;
use App\Models\ServiceAddon;
use App\Models\ServicePackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CatalogService
{
    /** Allowed price models per fulfillment type (ADR-005, doc 12 §22). */
    public const PRICE_MODELS_BY_FULFILLMENT = [
        'instant_booking' => ['fixed', 'per_unit'],
        'appointment' => ['fixed', 'per_unit', 'hourly', 'daily'],
        'fixed_package' => ['fixed', 'package'],
        'hourly' => ['hourly'],
        'daily' => ['daily'],
        'per_unit' => ['per_unit'],
        'survey_required' => ['starting_from'],
        'request_quotation' => ['quotation'],
        'rfq' => ['quotation'],
        'project' => ['quotation', 'milestone'],
        'milestone_project' => ['milestone'],
    ];

    public function createService(Partner $partner, array $data): Service
    {
        return DB::transaction(function () use ($partner, $data) {
            $this->assertPriceModelAllowed($data['fulfillment_type'], $data['price_model']);

            if (! $partner->isVerified()) {
                throw new DomainException('Partner must be verified to publish services. Save as draft instead.', 'NOT_VERIFIED', 403);
            }

            $service = Service::create([
                ...$data,
                'partner_id' => $partner->id,
                'slug' => $this->uniqueSlug($data['title']),
                'status' => 'active', // verified partner publishes directly; moderation hook later
            ]);

            foreach ($data['packages'] ?? [] as $i => $pkg) {
                ServicePackage::create([
                    'service_id' => $service->id,
                    'name' => $pkg['name'],
                    'description' => $pkg['description'] ?? null,
                    'price' => $pkg['price'],
                    'duration_minutes' => $pkg['duration_minutes'] ?? null,
                    'inclusions' => $pkg['inclusions'] ?? null,
                    'is_default' => $pkg['is_default'] ?? $i === 0,
                    'sort' => $i,
                ]);
            }

            foreach ($data['addons'] ?? [] as $i => $addon) {
                ServiceAddon::create([
                    'service_id' => $service->id,
                    'name' => $addon['name'],
                    'description' => $addon['description'] ?? null,
                    'price' => $addon['price'],
                    'unit' => $addon['unit'] ?? null,
                    'is_active' => true,
                ]);
            }

            return $service->load('packages', 'addons');
        });
    }

    public function updateService(Service $service, array $data): Service
    {
        DB::transaction(function () use ($service, $data) {
            if (isset($data['fulfillment_type'], $data['price_model'])) {
                $this->assertPriceModelAllowed($data['fulfillment_type'], $data['price_model']);
            }

            $service->update(collect($data)->except(['packages', 'addons'])->all());

            if (array_key_exists('packages', $data)) {
                $service->packages()->delete();
                foreach ($data['packages'] as $i => $pkg) {
                    ServicePackage::create([
                        'service_id' => $service->id,
                        'name' => $pkg['name'],
                        'description' => $pkg['description'] ?? null,
                        'price' => $pkg['price'],
                        'duration_minutes' => $pkg['duration_minutes'] ?? null,
                        'inclusions' => $pkg['inclusions'] ?? null,
                        'is_default' => $pkg['is_default'] ?? $i === 0,
                        'sort' => $i,
                    ]);
                }
            }

            if (array_key_exists('addons', $data)) {
                $service->addons()->delete();
                foreach ($data['addons'] as $addon) {
                    ServiceAddon::create([
                        'service_id' => $service->id,
                        'name' => $addon['name'],
                        'description' => $addon['description'] ?? null,
                        'price' => $addon['price'],
                        'unit' => $addon['unit'] ?? null,
                        'is_active' => true,
                    ]);
                }
            }
        });

        return $service->fresh('packages', 'addons');
    }

    private function assertPriceModelAllowed(string $fulfillment, string $priceModel): void
    {
        $allowed = self::PRICE_MODELS_BY_FULFILLMENT[$fulfillment]
            ?? throw new DomainException("Unknown fulfillment type: {$fulfillment}", 'INVALID_FULFILLMENT', 422);

        if (! in_array($priceModel, $allowed, true)) {
            throw new DomainException(
                "Price model '{$priceModel}' is not allowed for fulfillment '{$fulfillment}'.",
                'INVALID_PRICE_MODEL',
                422,
                ['allowed' => $allowed],
            );
        }
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'jasa';
        $slug = $base;
        $i = 1;

        while (Service::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
