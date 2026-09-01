<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Domain\Catalog\CatalogService;
use App\Domain\Auth\DomainException;
use App\Http\Controllers\Api\V1\Controller;
use App\Models\Category;
use App\Models\Partner;
use App\Models\Service;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CatalogService $catalog)
    {
    }

    // ---------- Partner-owned ----------

    public function myServices(Request $request): JsonResponse
    {
        $partner = Partner::where('user_id', $request->user()->id)->firstOrFail();
        $services = $partner->services()->with('category', 'packages', 'addons')->paginate(20);

        return $this->paginated($services);
    }

    public function storeService(Request $request): JsonResponse
    {
        $data = $this->validateService($request, creating: true);

        $partner = Partner::where('user_id', $request->user()->id)->first()
            ?? throw new DomainException('No partner profile.', 'PARTNER_NOT_FOUND', 404);

        $service = $this->catalog->createService($partner, $data);

        return $this->created(['service' => $service], 'Service created.');
    }

    public function updateService(Request $request, int $id): JsonResponse
    {
        $data = $this->validateService($request, creating: false);

        $partner = Partner::where('user_id', $request->user()->id)->firstOrFail();
        $service = $partner->services()->findOrFail($id);

        $service = $this->catalog->updateService($service, $data);

        return $this->ok(['service' => $service], 'Service updated.');
    }

    public function pauseService(Request $request, int $id): JsonResponse
    {
        $partner = Partner::where('user_id', $request->user()->id)->firstOrFail();
        $service = $partner->services()->findOrFail($id);
        $service->update(['status' => $service->status === 'paused' ? 'active' : 'paused']);

        return $this->ok(['service' => $service->fresh()]);
    }

    private function validateService(Request $request, bool $creating): array
    {
        $rules = [
            'category_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:categories,id'],
            'template_id' => ['nullable', 'integer', 'exists:service_templates,id'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'inclusions' => ['nullable', 'string', 'max:2000'],
            'exclusions' => ['nullable', 'string', 'max:2000'],
            'fulfillment_type' => [$creating ? 'required' : 'sometimes', 'in:instant_booking,appointment,fixed_package,hourly,daily,per_unit,survey_required,request_quotation,rfq,project,milestone_project'],
            'delivery_mode' => [$creating ? 'required' : 'sometimes', 'in:remote,onsite,hybrid,provider_location'],
            'price_model' => [$creating ? 'required' : 'sometimes', 'in:fixed,per_unit,hourly,daily,starting_from,package,quotation,milestone'],
            'base_price' => ['nullable', 'integer', 'min:0'],
            'unit_label' => ['nullable', 'string', 'max:32'],
            'min_quantity' => ['nullable', 'integer', 'min:1'],
            'max_quantity' => ['nullable', 'integer', 'min:1'],
            'duration_minutes' => ['nullable', 'integer', 'min:15'],
            'emergency_capable' => ['nullable', 'boolean'],
            'emergency_surcharge' => ['nullable', 'integer', 'min:0'],
            'warranty_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'media' => ['nullable', 'array'],
            'attributes' => ['nullable', 'array'],
            'packages' => ['nullable', 'array'],
            'packages.*.name' => ['required_with:packages', 'string', 'max:120'],
            'packages.*.price' => ['required_with:packages', 'integer', 'min:0'],
            'packages.*.description' => ['nullable', 'string', 'max:1000'],
            'packages.*.duration_minutes' => ['nullable', 'integer', 'min:15'],
            'packages.*.inclusions' => ['nullable', 'array'],
            'packages.*.is_default' => ['nullable', 'boolean'],
            'addons' => ['nullable', 'array'],
            'addons.*.name' => ['required_with:addons', 'string', 'max:120'],
            'addons.*.price' => ['required_with:addons', 'integer', 'min:0'],
            'addons.*.description' => ['nullable', 'string', 'max:500'],
            'addons.*.unit' => ['nullable', 'string', 'max:32'],
        ];

        return $request->validate($rules);
    }
}
