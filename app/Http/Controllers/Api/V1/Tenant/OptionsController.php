<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\Campus;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Fee;
use App\Models\Tenant\Level;
use App\Models\Tenant\Role;
use App\Models\Tenant\Student;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Teacher;
use App\Models\Tenant\Term;
use Illuminate\Http\JsonResponse;

/**
 * Listes de référence des formulaires et filtres : GET /options
 * → { schools: [{value,label}], classes: [...], … }
 */
class OptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $year = AcademicYear::current();
        $teacherClasses = $this->teacherClassIds();
        $opt = fn ($rows, string $label = 'name') => $rows->map(fn ($r) => ['value' => $r->id, 'label' => $r->{$label}])->values();

        $classes = ClassRoom::with('campus')->where('academic_year_id', $year?->id)
            ->when($teacherClasses !== null, fn ($q) => $q->whereIn('id', $teacherClasses))
            ->orderBy('level_id')->orderBy('name')->get();

        return response()->json(['data' => [
            'schools' => $opt(Campus::where('status', 'active')->orderBy('name')->get()),
            'academicYears' => $opt(AcademicYear::orderByDesc('starts_on')->get()),
            'currentAcademicYear' => $year?->id,
            'terms' => $opt($year ? $year->terms()->get() : collect()),
            'currentTerm' => Term::current()?->id,
            'levels' => Level::orderBy('position')->get()->map(fn ($l) => ['value' => $l->name, 'label' => $l->name, 'id' => $l->id]),
            'classes' => $classes->map(fn ($c) => ['value' => $c->id, 'label' => $c->name, 'school_id' => $c->campus_id, 'school' => $c->campus?->name]),
            'subjects' => $opt(Subject::where('status', 'active')->orderBy('name')->get()),
            'teachers' => $opt(Teacher::where('status', '!=', 'inactive')->orderBy('last_name')->get(), 'full_name'),
            'students' => Student::with('currentEnrollment.classRoom')->where('status', 'active')
                ->when($teacherClasses !== null, fn ($q) => $q->whereHas('currentEnrollment', fn ($e) => $e->whereIn('class_room_id', $teacherClasses)))
                ->orderBy('last_name')->limit(3000)->get()
                ->map(fn ($s) => ['value' => $s->id, 'label' => $s->full_name.($s->currentEnrollment?->classRoom ? ' · '.$s->currentEnrollment->classRoom->name : '')]),
            'fees' => $opt(Fee::where('academic_year_id', $year?->id)->where('status', 'active')->orderBy('name')->get()),
            'roles' => Role::orderBy('id')->get()->map(fn ($r) => ['value' => $r->key, 'label' => $r->name]),
        ]]);
    }
}
