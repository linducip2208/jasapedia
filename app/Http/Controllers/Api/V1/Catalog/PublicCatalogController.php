<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\Category;
use App\Models\Location;
use App\Models\Service;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicCatalogController extends Controller
{
    use ApiResponse;

    public function categories(Request $request): JsonResponse
    {
        $categories = Category::whereNull('parent_id')
            ->where('is_active', true)
            ->with('children:id,parent_id,name,slug,icon,sort,is_active')
            ->orderBy('sort')
            ->get(['id', 'parent_id', 'name', 'slug', 'icon', 'sort', 'is_active']);

        return $this->ok(['categories' => $categories]);
    }

    public function category(Request $request, string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->with('children', 'attributes')->first();

        if (! $category) {
            return $this->fail('NOT_FOUND', 'Category not found.', 404);
        }

        return $this->ok(['category' => $category]);
    }

    public function services(Request $request): JsonResponse
    {
        $query = Service::query()->active()
            ->with(['category:id,name,slug,icon', 'partner:id,display_name,slug,rating_avg,rating_count,completed_jobs,verification_state,city', 'packages', 'addons']);

        if ($q = $request->string('q')->toString()) {
            $query->where(fn ($w) => $w
                ->where('title', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));
        }

        if ($category = $request->string('category')->toString()) {
            $query->whereHas('category', fn ($c) => $c->where('slug', $category)
                ->orWhereHas('parent', fn ($p) => $p->where('slug', $category)));
        }

        if ($city = $request->string('city')->toString()) {
            $query->whereHas('partner', fn ($p) => $p->where('city', 'like', "%{$city}%"));
        }

        if ($request->boolean('emergency')) {
            $query->where('emergency_capable', true);
        }

        $fulfillment = $request->string('fulfillment_type')->toString();
        if ($fulfillment !== '') {
            $query->where('fulfillment_type', $fulfillment);
        }

        $delivery = $request->string('delivery_mode')->toString();
        if ($delivery !== '') {
            $query->where('delivery_mode', $delivery);
        }

        $minPrice = $request->integer('min_price');
        if ($minPrice > 0) {
            $query->where('base_price', '>=', $minPrice);
        }

        $maxPrice = $request->integer('max_price');
        if ($maxPrice > 0) {
            $query->where('base_price', '<=', $maxPrice);
        }

        match ($request->string('sort')->toString()) {
            'price_asc' => $query->orderBy('base_price'),
            'price_desc' => $query->orderByDesc('base_price'),
            'rating' => $query->select('services.*')
                ->join('partners', 'partners.id', '=', 'services.partner_id')
                ->orderByDesc('partners.rating_avg'),
            default => $query->orderByDesc('services.created_at'),
        };

        return $this->paginated($query->paginate(20));
    }

    public function service(Request $request, string $slug): JsonResponse
    {
        $service = Service::query()->active()
            ->where('slug', $slug)
            ->with(['category:id,name,slug,icon,config', 'template', 'packages', 'addons', 'partner'])
            ->first();

        if (! $service) {
            return $this->fail('NOT_FOUND', 'Service not found.', 404);
        }

        return $this->ok(['service' => $service]);
    }

    public function locations(Request $request): JsonResponse
    {
        $query = Location::query();

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($parentId = $request->integer('parent_id')) {
            $query->where('parent_id', $parentId);
        }

        if ($q = $request->string('q')->toString()) {
            $query->where('name', 'like', "%{$q}%");
        }

        return $this->ok(['locations' => $query->orderBy('name')->limit(50)->get(['id', 'parent_id', 'type', 'name', 'slug'])]);
    }
}
