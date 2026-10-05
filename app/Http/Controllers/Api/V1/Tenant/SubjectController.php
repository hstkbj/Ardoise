<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubjectResource;
use App\Models\Tenant\Subject;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('subjects.view');

        return SubjectResource::collection(ListQuery::paginate(Subject::with('teacher'), $request,
            search: ['name', 'code', 'category'],
            filters: ['status' => 'status', 'level' => 'level', 'category' => 'category'],
            sorts: ['name' => 'name', 'coefficient' => 'default_coefficient'],
            default: 'name',
        ));
    }

    public function store(Request $request): SubjectResource
    {
        $this->authorize('subjects.create');

        return new SubjectResource(Subject::create($this->validated($request))->load('teacher'));
    }

    public function show(Subject $subject): SubjectResource
    {
        $this->authorize('subjects.view');

        return new SubjectResource($subject->load('teacher'));
    }

    public function update(Request $request, Subject $subject): SubjectResource
    {
        $this->authorize('subjects.update');
        $subject->update($this->validated($request, $subject));

        return new SubjectResource($subject->load('teacher'));
    }

    public function destroy(Subject $subject): JsonResponse
    {
        $this->authorize('subjects.delete');
        abort_if(\App\Models\Tenant\ClassSubject::where('subject_id', $subject->id)->whereHas('assessments')->exists(), 422, 'Cette matière a déjà des évaluations : désactivez-la plutôt.');
        $subject->delete();

        return response()->json(['message' => 'Matière supprimée.']);
    }

    protected function validated(Request $request, ?Subject $subject = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('tenant.subjects', 'code')->ignore($subject?->id)],
            'coefficient' => ['required', 'numeric', 'min:0', 'max:20'],
            'level' => ['nullable', Rule::in(['Primaire', 'Collège', 'Lycée'])],
            'category' => ['nullable', 'string', 'max:100'],
            'teacher_id' => ['nullable', 'integer', 'exists:tenant.teachers,id'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        return [
            'name' => $data['name'],
            'code' => strtoupper($data['code']),
            'default_coefficient' => $data['coefficient'],
            'level' => $data['level'] ?? null,
            'category' => $data['category'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];
    }
}
