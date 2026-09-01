<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\CustomerAddress;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->hasMany(CustomerAddress::class)->get();

        return $this->ok(['addresses' => $addresses]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:48'],
            'recipient_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'subdistrict_id' => ['nullable', 'integer', 'exists:locations,id'],
            'address_line' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $address = DB::transaction(function () use ($request, $data) {
            if ($data['is_default'] ?? false) {
                $request->user()->hasMany(CustomerAddress::class)->update(['is_default' => false]);
            }

            return CustomerAddress::create([
                ...$data,
                'user_id' => $request->user()->id,
                'is_default' => $data['is_default'] ?? $request->user()->hasMany(CustomerAddress::class)->count() === 0,
            ]);
        });

        return $this->created(['address' => $address->load('subdistrict')]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:48'],
            'recipient_name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'string', 'max:32'],
            'subdistrict_id' => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'address_line' => ['sometimes', 'string', 'max:500'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $address = $request->user()->hasMany(CustomerAddress::class)->findOrFail($id);

        DB::transaction(function () use ($request, $address, $data) {
            $address->update($data);

            if ($data['is_default'] ?? false) {
                $request->user()->hasMany(CustomerAddress::class)
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }
        });

        return $this->ok(['address' => $address->fresh('subdistrict')]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->hasMany(CustomerAddress::class)->where('id', $id)->delete();

        return $this->noContent();
    }
}
