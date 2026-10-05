<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\AcademicYearResource;
use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\ActivityLog;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicYearController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('academic_years.view');

        return AcademicYearResource::collection(ListQuery::paginate(AcademicYear::withCount('terms'), $request,
            search: ['name'],
            filters: ['status' => 'status'],
            sorts: ['name' => 'name', 'starts_on' => 'starts_on'],
            default: '-starts_on',
        ));
    }

    public function show(AcademicYear $academicYear): AcademicYearResource
    {
        $this->authorize('academic_years.view');

        return new AcademicYearResource($academicYear->load('terms')->loadCount('terms'));
    }

    /** Création : les périodes (trimestres ou semestres) sont générées automatiquement. */
    public function store(Request $request): AcademicYearResource
    {
        $this->authorize('academic_years.create');
        $data = $this->validated($request);

        $year = DB::connection('tenant')->transaction(function () use ($data) {
            $year = AcademicYear::create([
                'name' => $data['name'],
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'period_type' => (int) ($data['terms_count'] ?? 3) === 2 ? 'semester' : 'trimester',
                'status' => 'upcoming',
            ]);
            $this->createTerms($year, (int) ($data['terms_count'] ?? 3));

            return $year;
        });

        return new AcademicYearResource($year->load('terms')->loadCount('terms'));
    }

    public function update(Request $request, AcademicYear $academicYear): AcademicYearResource
    {
        $this->authorize('academic_years.update');
        $data = $this->validated($request, $academicYear);
        $academicYear->update(['name' => $data['name'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on']]);

        return new AcademicYearResource($academicYear->load('terms')->loadCount('terms'));
    }

    /** Une seule année active : elle sert de valeur par défaut partout. */
    public function setActive(AcademicYear $academicYear): AcademicYearResource
    {
        $this->authorize('academic_years.update');
        abort_if($academicYear->isClosed(), 422, 'Une année clôturée ne peut pas redevenir active.');

        DB::connection('tenant')->transaction(function () use ($academicYear) {
            AcademicYear::where('status', 'active')->whereKeyNot($academicYear->id)->update(['status' => 'upcoming']);
            $academicYear->update(['status' => 'active']);
        });

        ActivityLog::record('academic_year.activated', $academicYear);

        return new AcademicYearResource($academicYear->loadCount('terms'));
    }

    public function close(AcademicYear $academicYear): AcademicYearResource
    {
        $this->authorize('academic_years.update');
        $academicYear->update(['status' => 'closed']);
        ActivityLog::record('academic_year.closed', $academicYear);

        return new AcademicYearResource($academicYear->loadCount('terms'));
    }

    public function destroy(AcademicYear $academicYear): JsonResponse
    {
        $this->authorize('academic_years.update');
        abort_if($academicYear->classRooms()->exists(), 422, 'Cette année contient des classes : clôturez-la plutôt.');
        $academicYear->delete();

        return response()->json(['message' => 'Année supprimée.']);
    }

    protected function createTerms(AcademicYear $year, int $count): void
    {
        $count = in_array($count, [2, 3], true) ? $count : 3;
        $start = Carbon::parse($year->starts_on);
        $days = $start->diffInDays(Carbon::parse($year->ends_on));
        $label = $count === 2 ? 'Semestre' : 'Trimestre';

        for ($i = 0; $i < $count; $i++) {
            $from = $start->copy()->addDays((int) floor($days * $i / $count));
            $to = $i === $count - 1 ? Carbon::parse($year->ends_on) : $start->copy()->addDays((int) floor($days * ($i + 1) / $count) - 1);

            $year->terms()->create(['name' => "{$label} ".($i + 1), 'position' => $i + 1, 'starts_on' => $from->toDateString(), 'ends_on' => $to->toDateString()]);
        }
    }

    protected function validated(Request $request, ?AcademicYear $year = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:20', Rule::unique('tenant.academic_years', 'name')->ignore($year?->id)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
            'terms_count' => ['nullable', Rule::in([2, 3, '2', '3'])],
        ]);
    }
}
