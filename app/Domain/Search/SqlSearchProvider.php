<?php

namespace App\Domain\Search;

use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SqlSearchProvider implements SearchProviderInterface
{
    public function searchServices(string $query, array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $q = Service::query()
            ->active()
            ->with('category:id,name,slug,icon', 'partner:id,display_name,slug,avatar_path,rating_avg,verification_state,lat,lng,city')
            ->select('services.*');

        if ($query !== '') {
            $terms = array_filter(explode(' ', $query));
            $q->where(function ($w) use ($terms) {
                foreach ($terms as $term) {
                    $w->where(fn ($c) => $c
                        ->where('services.title', 'like', "%{$term}%")
                        ->orWhere('services.description', 'like', "%{$term}%"));
                }
            });
        }

        $this->applyFilters($q, $filters);

        $sorted = match ($filters['sort'] ?? null) {
            'price_asc' => $q->orderBy('services.base_price'),
            'price_desc' => $q->orderByDesc('services.base_price'),
            'rating' => $q->join('partners', 'partners.id', '=', 'services.partner_id')->orderByDesc('partners.rating_avg'),
            'most_orders' => $q->withCount('orders')->orderByDesc('orders_count'),
            'newest' => $q->latest('services.created_at'),
            'recommended' => $q
                ->join('partners', 'partners.id', '=', 'services.partner_id')
                ->orderByDesc('partners.rating_avg')
                ->orderByDesc('partners.completed_jobs')
                ->orderBy('services.base_price'),
            default => $q->latest('services.created_at'),
        };

        return $sorted->paginate($perPage);
    }

    private function applyFilters($q, array $filters): void
    {
        if (! empty($filters['category'])) {
            $q->where(function ($w) use ($filters) {
                $w->whereHas('category', fn ($c) => $c->where('slug', $filters['category']))
                    ->orWhereHas('category.parent', fn ($p) => $p->where('slug', $filters['category']));
            });
        }
        if (! empty($filters['province'])) {
            $q->whereHas('partner.serviceAreas.location', fn ($l) => $l->where('locations.parent_id', $filters['province']));
        }
        if (! empty($filters['city'])) {
            $q->whereHas('partner.serviceAreas.location', fn ($l) => $l->where('locations.id', $filters['city']));
        }
        if (! empty($filters['min_price'])) {
            $q->where('services.base_price', '>=', (int) $filters['min_price']);
        }
        if (! empty($filters['max_price'])) {
            $q->where('services.base_price', '<=', (int) $filters['max_price']);
        }
        if (! empty($filters['min_rating'])) {
            $q->whereHas('partner', fn ($p) => $p->where('rating_avg', '>=', (float) $filters['min_rating']));
        }
        if (! empty($filters['verified'])) {
            $q->whereHas('partner', fn ($p) => $p->where('verification_state', 'verified'));
        }
        if (! empty($filters['emergency'])) {
            $q->where('services.emergency_capable', true);
        }
        if (! empty($filters['delivery_mode'])) {
            $q->whereIn('services.delivery_mode', (array) $filters['delivery_mode']);
        }
        if (! empty($filters['instant'])) {
            $q->where('services.fulfillment_type', 'instant');
        }
        if (! empty($filters['warranty'])) {
            $q->where('services.warranty_days', '>', 0);
        }
        if (! empty($filters['lat']) && ! empty($filters['lng']) && ! empty($filters['radius_km'])) {
            app(\App\Domain\Location\GeoService::class)->applyRadius($q, (float) $filters['lat'], (float) $filters['lng'], (float) $filters['radius_km']);
        }
    }

    public function suggest(string $query, int $limit = 8): array
    {
        if (mb_strlen(trim($query)) < 2) {
            return [];
        }

        return Service::query()
            ->active()
            ->where('title', 'like', "%{$query}%")
            ->limit($limit)
            ->get(['id', 'title', 'slug'])
            ->map(fn ($s) => ['id' => $s->id, 'text' => $s->title, 'url' => route('web.service', $s)])
            ->all();
    }
}
