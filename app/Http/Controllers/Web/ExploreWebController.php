<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\Request;

class ExploreWebController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only([
            'category', 'min_price', 'max_price', 'min_rating', 'city', 'province',
            'verified', 'emergency', 'delivery_mode', 'instant', 'warranty', 'sort', 'lat', 'lng', 'radius_km',
        ]);

        $results = app(\App\Domain\Search\SearchProviderInterface::class)
            ->searchServices($request->string('q')->toString(), $filters, 12);

        return view('web.explore', [
            'services' => $results,
            'categories' => Category::where('is_active', true)->orderBy('sort')->get(),
            'filters' => $filters,
            'q' => $request->string('q')->toString(),
        ]);
    }

    public function suggest(Request $request)
    {
        return response()->json([
            'suggestions' => app(\App\Domain\Search\SearchProviderInterface::class)
                ->suggest($request->string('q')->toString()),
        ]);
    }
}
