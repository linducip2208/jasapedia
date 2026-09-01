<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Http\ApiResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    use ApiResponse;

    public function index(): \Illuminate\Http\JsonResponse
    {
        $db = false;
        $redis = false;

        try {
            DB::select('select 1');
            $db = true;
        } catch (\Throwable) {
        }

        try {
            \Illuminate\Support\Facades\Redis::connection()->ping();
            $redis = true;
        } catch (\Throwable) {
        }

        $healthy = $db;

        return $this->ok([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => ['database' => $db, 'redis' => $redis, 'cache' => config('cache.default')],
            'time' => now()->toIso8601String(),
        ], null, ['http_code' => $healthy ? 200 : 503]);
    }
}
