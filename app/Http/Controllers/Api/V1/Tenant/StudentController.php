<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\ReportCard;
use App\Models\Tenant\Student;
use App\Services\AccountService;
use App\Services\EnrollmentService;
use App\Support\ListQuery;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function __construct(protected EnrollmentService $enrollments, protected AccountService $accounts) {}

    public function index(Request $request)
    {
        $this->authorize('students.view');

        $query = $this->baseQuery()
            ->when($this->teacherClassIds() !== null, fn ($q) => $q->whereHas('currentEnrollment', fn ($e) => $e->whereIn('class_room_id', $this->teacherClassIds())))
            ->when(! $request->input('filter.status'), fn ($q) => $q->where('status', '!=', 'archived'));

        return StudentResource::collection(ListQuery::paginate($query, $request,
            search: ['first_name', 'last_name', 'matricule', fn ($q, $t) => $q->whereRaw('('.ListQuery::concat('tenant', 'last_name', 'first_name').') like ?', ["%{$t}%"])],
            filters: [
                'class_id' => fn ($q, $v) => $q->whereHas('currentEnrollment', fn ($e) => $e->where('class_room_id', $v)),
                'school_id' => fn ($q, $v) => $q->whereHas('currentEnrollment.classRoom', fn ($c) => $c->where('campus_id', $v)),
                'status' => 'status',
                'gender' => 'gender',
            ],
            sorts: [
                'full_name' => fn ($q, $d) => $q->orderBy('last_name', $d)->orderBy('first_name', $d),
                'matricule' => 'matricule',
                'average' => 'latest_average',
                'class_name' => 'id',
            ],
            default: 'full_name',
        ));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('students.create');
        $data = $this->validated($request);
        $class = ClassRoom::findOrFail($data['class_id']);
        $parentCode = null;

        $student = DB::connection('tenant')->transaction(function () use ($data, $class, $request, &$parentCode) {
            $student = Student::create([
                'matricule' => $data['matricule'] ?: $this->enrollments->nextMatricule(),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'gender' => $data['gender'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'birth_place' => $data['birth_place'] ?? null,
                'status' => 'active',
            ]);

            $this->storePhoto($request, $student);
            $this->enrollments->enroll($student, $class, $data['enrolled_on'] ?? null);
            $parentCode = $this->attachGuardian($student, $data);

            return $student;
        });

        ActivityLog::record('student.created', $student);

        return (new StudentResource($this->baseQuery()->findOrFail($student->id)))
            ->additional(['meta' => ['parent_access_code' => $parentCode]])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Student $student): StudentResource
    {
        $this->authorize('students.view');
        $this->ensureVisible($student);

        return new StudentResource($this->baseQuery()->findOrFail($student->id));
    }

    public function update(Request $request, Student $student): StudentResource
    {
        $this->authorize('students.update');
        $data = $this->validated($request, $student);

        $student->update(collect($data)->only(['matricule', 'first_name', 'last_name', 'gender', 'birth_date', 'birth_place'])->filter(fn ($v, $k) => $k !== 'matricule' || $v)->all());
        $this->storePhoto($request, $student);

        if ((int) $data['class_id'] !== (int) $student->currentClass()?->id) {
            $this->enrollments->changeClass($student, ClassRoom::findOrFail($data['class_id']));
        }

        return new StudentResource($this->baseQuery()->findOrFail($student->id));
    }

    public function destroy(Student $student): JsonResponse
    {
        $this->authorize('students.delete');
        $this->enrollments->archive($student);
        $student->delete();
        ActivityLog::record('student.deleted', $student);

        return response()->json(['message' => 'Élève supprimé.']);
    }

    /** POST /students/{id}/{archive|transfer|change-class} */
    public function action(Request $request, Student $student, string $action): StudentResource
    {
        $this->authorize('students.update');
        $this->runAction($request, $student, $action);

        return new StudentResource($this->baseQuery()->findOrFail($student->id));
    }

    /** POST /students/bulk/{action} { ids: [], class_id? } */
    public function bulk(Request $request, string $action): JsonResponse
    {
        $this->authorize('students.update');
        $ids = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']])['ids'];

        $students = Student::whereIn('id', $ids)->get();
        $students->each(fn (Student $s) => $this->runAction($request, $s, str_replace('_', '-', $action)));

        return response()->json(['message' => $students->count().' élève(s) traité(s).', 'count' => $students->count()]);
    }

    protected function runAction(Request $request, Student $student, string $action): void
    {
        match ($action) {
            'archive' => $this->enrollments->archive($student),
            'transfer' => $this->enrollments->transfer($student, $request->input('note')),
            'change-class' => $this->enrollments->changeClass($student, ClassRoom::findOrFail($request->validate(['class_id' => ['required', 'integer']])['class_id'])),
            'restore' => $student->update(['status' => 'active']),
            default => abort(404, 'Action inconnue.'),
        };
    }

    /** Parent principal saisi dans le formulaire d'inscription : retrouvé par téléphone, sinon créé (avec son code). */
    protected function attachGuardian(Student $student, array $data): ?string
    {
        if (! empty($data['guardian_id'])) {
            $student->parents()->syncWithoutDetaching([$data['guardian_id'] => ['relation' => $data['guardian_relation'] ?? null, 'is_primary' => true]]);

            return null;
        }

        if (empty($data['guardian_name'])) {
            return null;
        }

        $phone = Phone::normalize($data['guardian_phone'] ?? null);
        $parent = $phone
            ? ParentProfile::get()->first(fn ($p) => Phone::normalize($p->phone) === $phone)
            : null;
        $code = null;

        if (! $parent) {
            $parts = preg_split('/\s+/', trim($data['guardian_name']));
            $first = count($parts) > 1 ? implode(' ', array_slice($parts, 0, -1)) : $parts[0];
            $last = count($parts) > 1 ? end($parts) : '';
            [$parent, $code] = $this->accounts->createParent(['first_name' => $first, 'last_name' => $last, 'phone' => $data['guardian_phone'] ?? null]);
        }

        $student->parents()->syncWithoutDetaching([$parent->id => ['relation' => $data['guardian_relation'] ?? null, 'is_primary' => true]]);

        return $code;
    }

    protected function storePhoto(Request $request, Student $student): void
    {
        if ($request->hasFile('photo')) {
            $request->validate(['photo' => ['image', 'max:2048']]);
            $student->update(['photo_path' => $request->file('photo')->store('tenants/'.tenant()->code.'/students', 'public')]);
        }
    }

    protected function baseQuery()
    {
        return Student::query()
            ->with(['currentEnrollment.classRoom.campus', 'currentEnrollment.classRoom.level', 'parents'])
            ->addSelect(['latest_average' => ReportCard::select('general_average')
                ->whereColumn('report_cards.student_id', 'students.id')
                ->latest('term_id')
                ->limit(1)]);
    }

    protected function ensureVisible(Student $student): void
    {
        $ids = $this->teacherClassIds();
        abort_if($ids !== null && ! in_array((int) $student->currentClass()?->id, $ids, true), 403);
    }

    protected function validated(Request $request, ?Student $student = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::in(['F', 'M'])],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'matricule' => ['nullable', 'string', 'max:30', Rule::unique('tenant.students', 'matricule')->ignore($student?->id)],
            'school_id' => ['nullable', 'integer'],
            'class_id' => ['required', 'integer', 'exists:tenant.class_rooms,id'],
            'enrolled_on' => ['nullable', 'date'],
            'guardian_id' => ['nullable', 'integer', 'exists:tenant.parent_profiles,id'],
            'guardian_name' => ['nullable', 'string', 'max:150'],
            'guardian_phone' => ['nullable', 'string', 'max:30'],
            'guardian_relation' => ['nullable', 'string', 'max:30'],
        ]) + ['matricule' => null];
    }
}
