<?php

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\Central\Plan;
use App\Support\PlanFeatures;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Plan::withCount('tenants')->orderBy('id')->get()->map(fn ($p) => self::present($p))]);
    }

    public function show(Plan $plan): JsonResponse
    {
        return response()->json(['data' => self::present($plan)]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => self::present(Plan::create($this->validated($request)))], 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $plan->update($this->validated($request));

        return response()->json(['data' => self::present($plan)]);
    }

    public function destroy(Plan $plan): JsonResponse
    {
        abort_if($plan->tenants()->exists(), 422, 'Ce plan est utilisé par des écoles : désactivez-le plutôt.');
        $plan->delete();

        return response()->json(['message' => 'Plan supprimé.']);
    }

    public static function present(Plan $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'description' => $p->description,
            'price' => $p->price,
            'currency' => $p->currency,
            'period' => $p->period,
            'max_schools' => $p->max_schools,
            'max_students' => $p->max_students,
            'max_users' => $p->max_users,
            'features' => $features = PlanFeatures::fromLegacy($p->features ?? []),
            'feature_labels' => array_map(fn (string $key) => PlanFeatures::MODULES[$key], $features),
            'status' => $p->status,
            'tenants_count' => (int) ($p->tenants_count ?? 0),
        ];
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['nullable', 'integer', 'min:0'],
            'period' => ['required', Rule::in(['monthly', 'yearly'])],
            'max_schools' => ['nullable', 'integer', 'min:1'],
            'max_students' => ['nullable', 'integer', 'min:1'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', Rule::in(PlanFeatures::keys())],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]) + ['status' => 'active'];
    }
}
