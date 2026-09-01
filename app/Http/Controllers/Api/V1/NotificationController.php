<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $notifications = DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->where('channel', 'in_app')
            ->orderByDesc('created_at')
            ->paginate(30);

        return $this->paginated($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->where('channel', 'in_app')
            ->whereNull('read_at')
            ->count();

        return $this->ok(['unread' => $count]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['nullable', 'array'], 'ids.*' => ['string']]);

        $query = DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at');

        if ($data['ids'] ?? null) {
            $query->whereIn('id', $data['ids']);
        }

        $count = $query->update(['read_at' => now()]);

        return $this->ok(['marked' => $count]);
    }

    public function preferences(Request $request): JsonResponse
    {
        return $this->ok(['preferences' => $request->user()->notification_preferences ?? []]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.event' => ['required', 'string', 'max:64'],
            'preferences.*.channel' => ['required', 'in:email,push,sms,whatsapp'],
            'preferences.*.opted_out' => ['required', 'boolean'],
        ]);

        $prefs = [];
        foreach ($data['preferences'] as $pref) {
            $prefs[$pref['event']][$pref['channel']] = $pref['opted_out'];
        }

        $request->user()->forceFill(['notification_preferences' => $prefs])->save();

        return $this->ok(['preferences' => $prefs]);
    }
}
