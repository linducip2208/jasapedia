<?php

namespace App\Support\Http;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function ok(mixed $data = null, ?string $message = null, array $meta = []): JsonResponse
    {
        return response()->json(array_filter([
            'data' => $data,
            'message' => $message,
            'meta' => $meta ?: null,
        ], static fn ($v) => $v !== null));
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return response()->json(['data' => $data, 'message' => $message], 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function paginated(LengthAwarePaginator $paginator, string $message = ''): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'message' => $message,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    protected function fail(string $code, string $message, int $status = 400, array $details = [], ?string $referenceId = null): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'reference_id' => $referenceId ?? (string) str()->uuid(),
            ],
        ], $status);
    }
}
