<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeeResource;
use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Fee;
use App\Models\Tenant\Level;
use App\Services\FeeService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Frais scolaires : à la création, les échéances des élèves concernés sont générées. */
class FeeController extends Controller
{
    public const CATEGORIES = ['scolarite', 'inscription', 'cantine', 'transport', 'autre'];

    public function __construct(protected FeeService $fees) {}

    public function index(Request $request)
    {
        $this->authorize('fees.view');

        $query = Fee::with(['levels', 'campus', 'academicYear'])->withCount('assignments')
            ->when(! $request->input('filter.academic_year_id'), fn ($q) => $q->where('academic_year_id', AcademicYear::current()?->id));

        return FeeResource::collection(ListQuery::paginate($query, $request,
            search: ['name'],
            filters: ['category' => 'category', 'status' => 'status', 'academic_year_id' => 'academic_year_id', 'school_id' => 'campus_id'],
            sorts: ['name' => 'name', 'amount' => 'amount'],
            default: 'name',
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('fees.create');
        $data = $this->validated($request);

        $fee = DB::connection('tenant')->transaction(function () use ($data) {
            $fee = Fee::create(collect($data)->except('level_ids')->all());
            $fee->levels()->sync($data['level_ids']);
            $this->fees->assign($fee);

            return $fee;
        });

        ActivityLog::record('fee.created', $fee);

        return (new FeeResource($fee->load('levels', 'campus', 'academicYear')->loadCount('assignments')))->response()->setStatusCode(201);
    }

    public function show(Fee $fee): FeeResource
    {
        $this->authorize('fees.view');

        return new FeeResource($fee->load('levels', 'campus', 'academicYear')->loadCount('assignments'));
    }

    public function update(Request $request, Fee $fee): FeeResource
    {
        $this->authorize('fees.update');
        $data = $this->validated($request);

        DB::connection('tenant')->transaction(function () use ($fee, $data) {
            $fee->update(collect($data)->except('level_ids')->all());
            $fee->levels()->sync($data['level_ids']);
            $this->fees->resync($fee);
        });

        return new FeeResource($fee->load('levels', 'campus', 'academicYear')->loadCount('assignments'));
    }

    public function destroy(Fee $fee): JsonResponse
    {
        $this->authorize('fees.delete');
        abort_if($fee->assignments()->where('paid_amount', '>', 0)->exists(), 422, 'Des paiements existent pour ce frais : désactivez-le plutôt.');
        $fee->delete();

        return response()->json(['message' => 'Frais supprimé.']);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'amount' => ['required', 'integer', 'min:1'],
            'installments' => ['nullable', 'integer', 'min:1', 'max:12'],
            'interval_months' => ['nullable', 'integer', 'min:1', 'max:12'],
            'first_due_date' => ['nullable', 'date'],
            'academic_year_id' => ['nullable', 'integer', 'exists:tenant.academic_years,id'],
            'school_id' => ['nullable', 'integer', 'exists:tenant.campuses,id'],
            'level_ids' => ['nullable', 'array'],
            'level_ids.*' => ['string'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        // Les niveaux arrivent par nom (« 6e ») ou par identifiant
        $levelIds = collect($data['level_ids'] ?? [])
            ->map(fn ($v) => is_numeric($v) ? (int) $v : Level::where('name', $v)->value('id'))
            ->filter()->unique()->values()->all();

        return [
            'name' => $data['name'],
            'category' => $data['category'],
            'amount' => $data['amount'],
            'installments' => $data['installments'] ?? 1,
            'interval_months' => $data['interval_months'] ?? null,
            'first_due_date' => $data['first_due_date'] ?? null,
            'academic_year_id' => $data['academic_year_id'] ?? AcademicYear::current()?->id,
            'campus_id' => $data['school_id'] ?? null,
            'status' => $data['status'] ?? 'active',
            'level_ids' => $levelIds,
        ];
    }
}
