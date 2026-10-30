<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassRoomResource;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\ClassSubject;
use App\Models\Tenant\Subject;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClassRoomController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('classes.view');

        $query = $this->baseQuery()
            ->when($this->teacherClassIds() !== null, fn ($q) => $q->whereIn('id', $this->teacherClassIds()));

        return ClassRoomResource::collection(ListQuery::paginate($query, $request,
            search: ['name', 'room', 'level.name'],
            filters: [
                'school_id' => 'campus_id',
                'academic_year_id' => fn ($q, $v) => $q->whereHas('enrollments', fn ($e) => $e->where('academic_year_id', $v)),
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

    /**
     * Matières affectables à la classe : GET /classes/{id}/subjects
     * Chaque matière indique si elle est affectée, son coefficient, l'enseignant retenu
     * et la liste des enseignants qui enseignent cette matière (pour le choix).
     */
    public function subjects(ClassRoom $class): JsonResponse
    {
        $this->authorize('classes.view');
        $this->ensureVisible($class);

        $assigned = $class->classSubjects()->get()->keyBy('subject_id');

        $rows = Subject::with(['teachers' => fn ($q) => $q->where('teachers.status', '!=', 'inactive')])
            ->where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', $assigned->keys()))
            ->orderBy('name')->get()
            ->map(function (Subject $subject) use ($assigned) {
                $cs = $assigned->get($subject->id);
                $teachers = $subject->teachers->map(fn ($t) => ['value' => $t->id, 'label' => $t->full_name]);

                // Enseignant déjà retenu mais plus rattaché à la matière : on le garde visible
                if ($cs?->teacher_id && ! $teachers->contains('value', $cs->teacher_id) && $cs->teacher) {
                    $teachers->push(['value' => $cs->teacher_id, 'label' => $cs->teacher->full_name]);
                }

                return [
                    'subject_id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'assigned' => (bool) $cs,
                    'coefficient' => $cs?->coefficient ?? $subject->default_coefficient,
                    'teacher_id' => $cs?->teacher_id,
                    'teachers' => $teachers->values(),
                ];
            })
            ->sortByDesc('assigned')->values();

        return response()->json(['data' => $rows]);
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

        $this->ensureTeachersTeachTheirSubject($class, $data['subjects']);

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

    /** L'enseignant choisi pour une matière doit faire partie de ceux qui l'enseignent. */
    protected function ensureTeachersTeachTheirSubject(ClassRoom $class, array $rows): void
    {
        $current = $class->classSubjects()->pluck('teacher_id', 'subject_id');

        foreach ($rows as $i => $row) {
            $teacherId = $row['teacher_id'] ?? null;

            if (! $teacherId || (int) $current->get($row['subject_id']) === (int) $teacherId) {
                continue; // aucun enseignant, ou affectation déjà en place
            }

            $subject = Subject::findOrFail($row['subject_id']);
            $teaches = $subject->teachers()->whereKey($teacherId)->exists() || (int) $subject->teacher_id === (int) $teacherId;

            if (! $teaches) {
                throw ValidationException::withMessages(["subjects.{$i}.teacher_id" => 'Cet enseignant n’enseigne pas « '.$subject->name.' ». Rattachez d’abord la matière à l’enseignant.']);
            }
        }
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
        return ClassRoom::with(['campus', 'level', 'headTeacher'])
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
            'name' => ['required', 'string', 'max:50', Rule::unique('tenant.class_rooms', 'name')->where('campus_id', $request->input('school_id'))->ignore($class?->id)],
            'level_id' => ['nullable', 'integer', 'exists:tenant.levels,id'],
            'level' => ['nullable', 'string', 'exists:tenant.levels,name'],
            'school_id' => ['required', 'integer', 'exists:tenant.campuses,id'],
            'head_teacher_id' => ['nullable', 'integer', 'exists:tenant.teachers,id'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'room' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        app(\App\Services\CampusScope::class)->ensureAllows((int) $data['school_id']);

        $levelId = $data['level_id'] ?? \App\Models\Tenant\Level::where('name', $data['level'] ?? null)->value('id');
        abort_unless($levelId, 422, 'Le niveau est obligatoire.');

        return [
            'name' => $data['name'],
            'level_id' => $levelId,
            'campus_id' => $data['school_id'],
            'head_teacher_id' => $data['head_teacher_id'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'room' => $data['room'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];
    }
}
