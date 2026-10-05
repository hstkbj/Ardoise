<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherResource;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Teacher;
use App\Services\AccountService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class TeacherController extends Controller
{
    public function __construct(protected AccountService $accounts) {}

    public function index(Request $request)
    {
        $this->authorize('teachers.view');

        return TeacherResource::collection(ListQuery::paginate($this->baseQuery(), $request,
            search: ['first_name', 'last_name', 'email', 'phone', 'subjects.name'],
            filters: [
                'school_id' => fn ($q, $v) => $q->whereHas('campuses', fn ($c) => $c->where('campuses.id', $v)),
                'school_name' => fn ($q, $v) => $q->whereHas('campuses', fn ($c) => $c->where('name', 'like', "%{$v}%")),
                'subject_id' => fn ($q, $v) => $q->whereHas('subjects', fn ($s) => $s->where('subjects.id', $v)),
                'contract' => 'contract',
                'status' => 'status',
            ],
            sorts: ['full_name' => fn ($q, $d) => $q->orderBy('last_name', $d)->orderBy('first_name', $d), 'hired_on' => 'hired_on'],
            default: 'full_name',
        ));
    }

    /** Création : un compte de connexion est créé ; s'il a un e-mail, il reçoit un lien pour choisir son mot de passe. */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('teachers.create');
        $data = $this->validated($request);

        $teacher = DB::connection('tenant')->transaction(function () use ($data) {
            $teacher = Teacher::create(collect($data)->except(['subject_ids', 'school_ids'])->all());
            $teacher->subjects()->sync($data['subject_ids'] ?? []);
            $teacher->campuses()->sync($data['school_ids'] ?? []);
            $this->accounts->ensureTeacherAccount($teacher);

            return $teacher;
        });

        if ($teacher->email) {
            rescue(fn () => Password::broker()->sendResetLink(['email' => $teacher->email]), report: true);
        }

        ActivityLog::record('teacher.created', $teacher);

        return (new TeacherResource($this->baseQuery()->findOrFail($teacher->id)))->response()->setStatusCode(201);
    }

    public function show(Teacher $teacher): TeacherResource
    {
        $this->authorize('teachers.view');

        return new TeacherResource($this->baseQuery()->findOrFail($teacher->id));
    }

    public function update(Request $request, Teacher $teacher): TeacherResource
    {
        $this->authorize('teachers.update');
        $data = $this->validated($request);

        $teacher->update(collect($data)->except(['subject_ids', 'school_ids'])->all());
        $request->has('subject_ids') && $teacher->subjects()->sync($data['subject_ids'] ?? []);
        $request->has('school_ids') && $teacher->campuses()->sync($data['school_ids'] ?? []);
        $this->accounts->ensureTeacherAccount($teacher->refresh());

        return new TeacherResource($this->baseQuery()->findOrFail($teacher->id));
    }

    public function destroy(Teacher $teacher): JsonResponse
    {
        $this->authorize('teachers.delete');
        $teacher->user?->update(['status' => 'suspended']);
        $teacher->user?->tokens()->delete();
        $teacher->update(['status' => 'inactive']);
        $teacher->delete();
        ActivityLog::record('teacher.deleted', $teacher);

        return response()->json(['message' => 'Enseignant supprimé, son compte est désactivé.']);
    }

    protected function baseQuery()
    {
        return Teacher::with(['subjects', 'campuses'])
            ->withCount(['classSubjects as classes_count' => fn ($q) => $q->select(DB::raw('count(distinct class_room_id)'))]);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'contract' => ['nullable', Rule::in(['permanent', 'vacataire'])],
            'hired_on' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['active', 'on_leave', 'inactive'])],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'exists:tenant.subjects,id'],
            'school_ids' => ['nullable', 'array'],
            'school_ids.*' => ['integer', 'exists:tenant.campuses,id'],
        ]);

        $data['email'] = isset($data['email']) ? strtolower($data['email']) : null;
        $data['contract'] ??= 'permanent';
        $data['status'] ??= 'active';

        return $data;
    }
}
