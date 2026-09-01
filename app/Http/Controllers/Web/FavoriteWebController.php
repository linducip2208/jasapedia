<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Partner;
use App\Models\Service;
use Illuminate\Http\Request;

class FavoriteWebController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('web.favorites', [
            'services' => Service::query()
                ->whereHas('favoritedBy', fn ($q) => $q->where('user_id', $user->id))
                ->with('category:id,name,slug', 'partner:id,display_name,slug,rating_avg')
                ->paginate(12)->withQueryString(),
            'providers' => Partner::query()
                ->whereHas('favoritedBy', fn ($q) => $q->where('user_id', $user->id))
                ->get(),
        ]);
    }

    public function toggle(Request $request)
    {
        $data = $request->validate([
            'service_id' => ['nullable', 'required_without:partner_id', 'integer'],
            'partner_id' => ['nullable', 'required_without:service_id', 'integer'],
        ]);

        $attrs = [
            'user_id' => $request->user()->id,
            'service_id' => $data['service_id'] ?? null,
            'partner_id' => $data['partner_id'] ?? null,
        ];

        $existing = Favorite::where($attrs)->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['favorited' => false]);
        }

        Favorite::create($attrs); // DB unique constraints prevent duplicates

        return response()->json(['favorited' => true]);
    }
}
