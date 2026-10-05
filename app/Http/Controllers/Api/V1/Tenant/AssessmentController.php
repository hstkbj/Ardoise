<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssessmentResource;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\ClassSubject;
use App\Models\Tenant\Enrollment;
use App\Models\Tenant\Term;
use App\Services\GradeService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Évaluations et feuilles de notes. */
class AssessmentController extends Controller
{
    public function __construct(protected GradeService $grades) {}

    public function index(Request $request)
    {
        $this->authorize('assessments.view');

        $query = $this->baseQuery()
            ->when($this->teacherClassIds() !== null, fn ($q) => $q->whereHas('classSubject', fn ($cs) => $cs->where('teacher_id', $this->teacherId())));

        return AssessmentResource::collection(ListQuery::paginate($query, $request,
            search: ['title', 'classSubject.subject.name', 'classSubject.classRoom.name'],
            filters: [
                'type' => 'type',
                'term_id' => 'term_id',
                'status' => fn ($q, $v) => $q->where('status', in_array($v, ['locked', 'published'], true) ? 'validated' : $v),
                'class_id' => fn ($q, $v) => $q->whereHas('classSubject', fn ($cs) => $cs->where('class_room_id', $v)),
                'class_name' => fn ($q, $v) => $q->whereHas('classSubject.classRoom', fn ($c) => $c->where('name', $v)),
                'subject_id' => fn ($q, $v) => $q->whereHas('classSubject', fn ($cs) => $cs->where('subject_id', $v)),
            ],
            sorts: ['title' => 'title', 'date' => 'date'],
            default: '-date',
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('assessments.create');
        $data = $this->validated($request);
        $assessment = Assessment::create($data);
        ActivityLog::record('assessment.created', $assessment);

        return (new AssessmentResource($this->baseQuery()->findOrFail($assessment->id)))->response()->setStatusCode(201);
    }

    public function show(Assessment $assessment): AssessmentResource
    {
        $this->authorize('assessments.view');
        $this->ensureOwn($assessment);

        return new AssessmentResource($this->baseQuery()->findOrFail($assessment->id));
    }

    public function update(Request $request, Assessment $assessment): AssessmentResource
    {
        $this->authorize('assessments.update');
        $this->ensureOwn($assessment);
        abort_if($assessment->isLocked(), 422, 'Évaluation verrouillée : demandez à la direction de la déverrouiller.');
        $assessment->update($this->validated($request, $assessment));

        return new AssessmentResource($this->baseQuery()->findOrFail($assessment->id));
    }

    public function destroy(Assessment $assessment): JsonResponse
    {
        $this->authorize('assessments.delete');
        $this->ensureOwn($assessment);
        abort_if($assessment->isLocked(), 422, 'Une évaluation validée ne peut pas être supprimée.');
        $assessment->delete();
        ActivityLog::record('assessment.deleted', $assessment);

        return response()->json(['message' => 'Évaluation supprimée.']);
    }

    /** GET /assessments/{id}/grades */
    public function sheet(Assessment $assessment): JsonResponse
    {
        $this->authorize('grades.view');
        $this->ensureOwn($assessment);

        return response()->json([
            'data' => $this->grades->sheet($assessment->load('classSubject.classRoom')),
            'assessment' => (new AssessmentResource($this->baseQuery()->findOrFail($assessment->id)))->resolve(),
        ]);
    }

    /** PUT /assessments/{id}/grades { grades: [{ student_id, score, absent, comment }] } */
    public function saveGrades(Request $request, Assessment $assessment): JsonResponse
    {
        $this->authorize('grades.create');
        $this->ensureOwn($assessment);
        $data = $request->validate(['grades' => ['required', 'array', 'max:500'], 'grades.*.student_id' => ['required', 'integer']]);

        $this->grades->save($assessment->load('classSubject.classRoom'), $request->input('grades', $data['grades']));

        return response()->json(['message' => 'Notes enregistrées.', 'saved_at' => now()]);
    }

    /** POST /assessments/{id}/validate : verrouille et notifie les parents. */
    public function validateGrades(Request $request, Assessment $assessment): AssessmentResource
    {
        $this->authorize('grades.update');
        $this->ensureOwn($assessment);

        if ($request->has('grades')) {
            $this->grades->save($assessment->load('classSubject.classRoom'), $request->input('grades'));
        }

        $this->grades->validate($assessment->load('classSubject.classRoom', 'classSubject.subject'), $request->user());

        return new AssessmentResource($this->baseQuery()->findOrFail($assessment->id));
    }

    /** POST /assessments/{id}/unlock : réservé à la direction, journalisé. */
    public function unlock(Request $request, Assessment $assessment): AssessmentResource
    {
        $this->authorize('grades.publish');
        abort_if($request->user()->isTeacherOnly(), 403);
        $this->grades->unlock($assessment, $request->input('reason'));

        return new AssessmentResource($this->baseQuery()->findOrFail($assessment->id));
    }

    protected function baseQuery()
    {
        return Assessment::with(['classSubject.subject', 'classSubject.classRoom', 'classSubject.teacher', 'teacher', 'term'])
            ->withCount(['grades as graded_count' => fn ($q) => $q->where(fn ($g) => $g->whereNotNull('score')->orWhere('is_absent', true))])
            ->addSelect(['students_count' => Enrollment::selectRaw('count(*)')
                ->join('class_subject', 'class_subject.class_room_id', '=', 'enrollments.class_room_id')
                ->whereColumn('class_subject.id', 'assessments.class_subject_id')
                ->where('enrollments.status', 'active')]);
    }

    /** Un enseignant ne gère que les évaluations de ses cours. */
    protected function ensureOwn(Assessment $assessment): void
    {
        if ($teacherId = $this->teacherId()) {
            abort_unless((int) $assessment->classSubject?->teacher_id === $teacherId || (int) $assessment->teacher_id === $teacherId, 403);
        } elseif ($this->user()?->isTeacherOnly()) {
            abort(403);
        }
    }

    protected function validated(Request $request, ?Assessment $assessment = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(Assessment::TYPES)],
            'date' => ['required', 'date'],
            'class_id' => ['required', 'integer', 'exists:tenant.class_rooms,id'],
            'subject_id' => ['required', 'integer', 'exists:tenant.subjects,id'],
            'term_id' => ['nullable', 'integer', 'exists:tenant.terms,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:tenant.teachers,id'],
            'coefficient' => ['required', 'numeric', 'min:0.25', 'max:20'],
            'max_score' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $class = ClassRoom::with('academicYear')->findOrFail($data['class_id']);

        if ($class->academicYear?->isClosed()) {
            throw ValidationException::withMessages(['class_id' => 'Cette année scolaire est clôturée.']);
        }

        $classSubject = ClassSubject::firstOrCreate(
            ['class_room_id' => $class->id, 'subject_id' => $data['subject_id']],
            ['teacher_id' => $this->teacherId(), 'coefficient' => \App\Models\Tenant\Subject::find($data['subject_id'])?->default_coefficient ?? 1],
        );

        if (($teacherId = $this->teacherId()) && (int) $classSubject->teacher_id !== $teacherId) {
            throw ValidationException::withMessages(['subject_id' => 'Vous n’enseignez pas cette matière dans cette classe.']);
        }

        $termId = $data['term_id'] ?? Term::current()?->id;
        abort_unless($termId, 422, 'Aucune période définie pour l’année en cours.');

        return [
            'class_subject_id' => $classSubject->id,
            'term_id' => $termId,
            'teacher_id' => $this->teacherId() ?? $data['teacher_id'] ?? $classSubject->teacher_id,
            'title' => $data['title'],
            'type' => $data['type'],
            'date' => $data['date'],
            'coefficient' => $data['coefficient'],
            'max_score' => $data['max_score'],
        ];
    }
}
