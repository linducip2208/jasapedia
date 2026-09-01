<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\Review;
use App\Models\Service;
use Illuminate\Http\Request;

class ProviderWebController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $partner = Partner::query()
            ->where('slug', $slug)
            ->with(['organization', 'skills', 'serviceAreas.location'])
            ->first();

        // Unverified providers are hidden from public (owner can still preview).
        if (! $partner || (! $partner->isVerified() && $partner->user_id !== $request->user()?->id)) {
            abort(404);
        }

        $services = $partner->services()->active()->with('category:id,name,slug')->paginate(9, ['*'], 'services_page');
        $reviews = Review::where('partner_id', $partner->id)->latest()->take(10)->get();
        $reviewCount = $partner->rating_count ?? Review::where('partner_id', $partner->id)->count();
        $completed = $partner->completed_jobs ?? 0;

        return view('web.provider.show', [
            'partner' => $partner,
            'services' => $services,
            'reviews' => $reviews,
            'reviewCount' => $reviewCount,
            'completed' => $completed,
            'level' => $this->level($partner, $reviewCount, $completed),
            'responseTime' => $partner->response_minutes <= 30 ? '< 30 menit' : ($partner->response_minutes < 120 ? '< 2 jam' : '2+ jam'),
            'memberSince' => $partner->created_at->translatedFormat('F Y'),
        ]);
    }

    /**
     * Transparent level computation — no fake badges.
     * New < Verified < Preferred < Top < Jasapedia Pro.
     */
    private function level(Partner $partner, int $reviewCount, int $completed): string
    {
        if ($partner->verification_state !== 'verified') {
            return 'Penyedia Baru';
        }

        $rating = (float) ($partner->rating_avg ?? 0);

        if ($completed >= 200 && $rating >= 4.8 && $reviewCount >= 150) {
            return 'Jasapedia Pro';
        }
        if ($completed >= 80 && $rating >= 4.6) {
            return 'Top Provider';
        }
        if ($completed >= 20 && $rating >= 4.3) {
            return 'Preferred Provider';
        }

        return 'Verified Provider';
    }
}
