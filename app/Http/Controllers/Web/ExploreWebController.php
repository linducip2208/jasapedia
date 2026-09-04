<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use App\Models\Location;
use App\Models\SeoMetadata;
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

    /**
     * Programmatic SEO landing: /jasa/{category}/{city}.
     * Metadata from seo_metadata (category_city), services from the category
     * scoped to the city (partner city or service area), interlinks to
     * sibling cities. Noindex rows render meta robots accordingly.
     */
    public function seoLanding(string $category, string $city)
    {
        $categoryModel = Category::where('slug', $category)->where('is_active', true)->firstOrFail();
        $cityModel = Location::where('type', 'city')->where('slug', $city)->firstOrFail();

        $seo = SeoMetadata::where('page_type', 'category_city')
            ->where('category_id', $categoryModel->id)
            ->where(function ($q) use ($cityModel) {
                $q->where('city', $cityModel->name)->orWhereNull('city');
            })
            ->orderByRaw('CASE WHEN city IS NULL THEN 1 ELSE 0 END')
            ->first();

        $services = Service::query()->active()
            ->where('category_id', $categoryModel->id)
            ->with('category:id,name,icon', 'partner:id,display_name,slug,rating_avg,rating_count,verification_state,city,avatar_path')
            ->where(function ($q) use ($cityModel) {
                $q->whereHas('partner', fn ($p) => $p->where('city', $cityModel->name))
                    ->orWhereHas('partner.serviceAreas', fn ($a) => $a->where('location_id', $cityModel->id));
            })
            ->select('services.*')
            ->join('partners', 'partners.id', '=', 'services.partner_id')
            ->orderByDesc('partners.rating_avg')
            ->orderByDesc('partners.completed_jobs')
            ->limit(24)
            ->get();

        $siblingCities = Location::where('type', 'city')
            ->where('id', '!=', $cityModel->id)
            ->inRandomOrder(now()->format('Ymd')) // stable per day, not per request
            ->limit(6)
            ->get(['name', 'slug']);

        $h1 = $seo?->h1 ?? "Jasa {$categoryModel->name} di {$cityModel->name}";
        $title = $seo?->meta_title ?? "Jasa {$categoryModel->name} di {$cityModel->name} â€” Murah & Terpercaya | Jasapedia";
        $intro = $seo?->intro_copy ?? "Temukan penyedia {$categoryModel->name} terbaik di {$cityModel->name}. Bandingkan harga, baca ulasan asli, dan pesan dengan aman lewat Jasapedia.";

        return view('web.seo.landing', [
            'category' => $categoryModel,
            'city' => $cityModel,
            'seo' => $seo,
            'h1' => $h1,
            'title' => $title,
            'intro' => $intro,
            'services' => $services,
            'siblingCities' => $siblingCities,
        ]);
    }

    /** /sitemap.xml â€” cached, capped at 50k URLs per protocol limits. */
    public function sitemap()
    {
        $xml = \Illuminate\Support\Facades\Cache::remember('sitemap.xml', now()->addHours(6), function () {
            $urls = [];

            $urls[] = ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'daily'];
            $urls[] = ['loc' => route('web.explore'), 'priority' => '0.9', 'changefreq' => 'daily'];
            $urls[] = ['loc' => route('web.blog.index'), 'priority' => '0.6', 'changefreq' => 'weekly'];
            $urls[] = ['loc' => route('web.business.landing'), 'priority' => '0.6', 'changefreq' => 'weekly'];

            $categories = Category::where('is_active', true)->orderBy('sort')->get(['name', 'slug']);
            $cities = Location::where('type', 'city')->orderBy('id')->get(['name', 'slug']);

            foreach ($cities as $city) {
                foreach ($categories as $category) {
                    $urls[] = [
                        'loc' => route('web.seo.landing-city', [$category->slug, $city->slug]),
                        'priority' => '0.8',
                        'changefreq' => 'daily',
                    ];
                }
            }

            \Illuminate\Support\Facades\DB::table('services')
                ->where('status', 'active')
                ->orderByDesc('id')
                ->limit(20000)
                ->select('slug', 'updated_at')
                ->chunk(500, function ($rows) use (&$urls) {
                    foreach ($rows as $row) {
                        $urls[] = [
                            'loc' => route('web.service', $row->slug),
                            'priority' => '0.7',
                            'changefreq' => 'weekly',
                            'lastmod' => optional(\Illuminate\Support\Carbon::parse($row->updated_at))->toAtomString(),
                        ];
                    }
                });

            $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".
                '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach ($urls as $u) {
                $xml .= "  <url>\n".
                    '    <loc>'.e($u['loc'])."</loc>\n".
                    (isset($u['lastmod']) ? '    <lastmod>'.$u['lastmod']."</lastmod>\n" : '').
                    '    <changefreq>'.($u['changefreq'] ?? 'weekly')."</changefreq>\n".
                    '    <priority>'.($u['priority'] ?? '0.5')."</priority>\n".
                    "  </url>\n";
            }
            $xml .= '</urlset>';

            return $xml;
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
