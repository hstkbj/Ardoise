<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassRoomResource;
use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\ClassSubject;
use App\Models\Tenant\Subject;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassRoomController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('classes.view');

        $query = $this->baseQuery()
            ->when(! $request->input('filter.academic_year_id'), fn ($q) => $q->where('academic_year_id', AcademicYear::current()?->id))
            ->when($this->teacherClassIds() !== null, fn ($q) => $q->whereIn('id', $this->teacherClassIds()));

        return ClassRoomResource::collection(ListQuery::paginate($query, $request,
            search: ['name', 'room', 'level.name'],
            filters: [
                'school_id' => 'campus_id',
                'academic_year_id' => 'academic_year_id',
                'level' => fn ($q, $v) => $q->whereHas('level', fn ($l) => $l->where('name', $v)),
                'level_id' => 'level_id',
                'status' => 'status',
            ],
            sorts: ['name' => 'name', 'level' => 'level_id', 'students_count' => 'students_count'],
            default: 'level',
        ));
    }

    public function store(Request $request): ClassRoomResource
    {
        $this->authorize('classes.create');
        $class = ClassRoom::create($this->validated($request));
        $this->attachLevelSubjects($class);
        ActivityLog::record('class.created', $class);

        return $this->present($class->id);
    }

    public function show(ClassRoom $class): ClassRoomResource
    {
        $this->authorize('classes.view');
        $this->ensureVisible($class);

        return $this->present($class->id);
    }

    public function update(Request $request, ClassRoom $class): ClassRoomResource
    {
        $this->authorize('classes.update');
        $class->update($this->validated($request, $class));

        return $this->present($class->id);
    }

    public function destroy(ClassRoom $class): JsonResponse
    {
        $this->authorize('classes.delete');
        abort_if($class->enrollments()->where('status', 'active')->exists(), 422, 'La classe contient des élèves : changez-les de classe avant de la supprimer.');
        $class->delete();
        ActivityLog::record('class.deleted', $class);

        return response()->json(['message' => 'Classe supprimée.']);
    }

    /** Matières de la classe : PUT /classes/{id}/subjects { subjects: [{ subject_id, teacher_id, coefficient }] } */
    public function syncSubjects(Request $request, ClassRoom $class): ClassRoomResource
    {
        $this->authorize('classes.update');

        $data = $request->validate([
            'subjects' => ['present', 'array'],
            'subjects.*.subject_id' => ['required', 'integer', 'exists:tenant.subjects,id'],
            'subjects.*.teacher_id' => ['nullable', 'integer', 'exists:tenant.teachers,id'],
            'subjects.*.coefficient' => ['required', 'numeric', 'min:0', 'max:20'],
        ]);

        $keep = [];

        foreach ($data['subjects'] as $row) {
            $cs = ClassSubject::updateOrCreate(
                ['class_room_id' => $class->id, 'subject_id' => $row['subject_id']],
                ['teacher_id' => $row['teacher_id'] ?? null, 'coefficient' => $row['coefficient']],
            );
            $keep[] = $cs->id;
        }

        // On ne supprime pas une matière qui a déjà des évaluations
        $class->classSubjects()->whereNotIn('id', $keep)->whereDoesntHave('assessments')->delete();

        return $this->present($class->id);
    }

    /** À la création : toutes les matières actives du cycle, avec leur coefficient par défaut. */
    protected function attachLevelSubjects(ClassRoom $class): void
    {
        $cycle = ['primaire' => 'Primaire', 'college' => 'Collège', 'lycee' => 'Lycée'][$class->level?->cycle] ?? null;

        Subject::where('status', 'active')
            ->where(fn ($q) => $q->whereNull('level')->orWhere('level', $cycle))
            ->get()
            ->each(fn (Subject $s) => ClassSubject::firstOrCreate(
                ['class_room_id' => $class->id, 'subject_id' => $s->id],
                ['teacher_id' => $s->teacher_id, 'coefficient' => $s->default_coefficient],
            ));
    }

    protected function baseQuery()
    {
        return ClassRoom::with(['campus', 'level', 'headTeacher', 'academicYear'])
            ->withCount(['enrollments as students_count' => fn ($q) => $q->where('status', 'active')]);
    }

    protected function present(int $id): ClassRoomResource
    {
        return new ClassRoomResource($this->baseQuery()->with('classSubjects.subject', 'classSubjects.teacher')->findOrFail($id));
    }

    protected function ensureVisible(ClassRoom $class): void
    {
        $ids = $this->teacherClassIds();
        abort_if($ids !== null && ! in_array((int) $class->id, $ids, true), 403);
    }

    protected function validated(Request $request, ?ClassRoom $class = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'level_id' => ['nullable', 'integer', 'exists:tenant.levels,id'],
            'level' => ['nullable', 'string', 'exists:tenant.levels,name'],
            'school_id' => ['required', 'integer', 'exists:tenant.campuses,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:tenant.academic_years,id'],
            'head_teacher_id' => ['nullable', 'integer', 'exists:tenant.teachers,id'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'room' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $levelId = $data['level_id'] ?? \App\Models\Tenant\Level::where('name', $data['level'] ?? null)->value('id');
        abort_unless($levelId, 422, 'Le niveau est obligatoire.');

        return [
            'name' => $data['name'],
            'level_id' => $levelId,
            'campus_id' => $data['school_id'],
            'academic_year_id' => $data['academic_year_id'] ?? $class?->academic_year_id ?? AcademicYear::current()?->id,
            'head_teacher_id' => $data['head_teacher_id'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'room' => $data['room'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];
    }
}
