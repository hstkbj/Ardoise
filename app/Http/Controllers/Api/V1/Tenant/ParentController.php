<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParentResource;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\ParentProfile;
use App\Services\AccountService;
use App\Services\ParentAccessService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ParentController extends Controller
{
    public function __construct(protected AccountService $accounts, protected ParentAccessService $codes) {}

    public function index(Request $request)
    {
        $this->authorize('parents.view');

        return ParentResource::collection(ListQuery::paginate($this->baseQuery(), $request,
            search: ['first_name', 'last_name', 'phone', 'email'],
            filters: ['status' => 'status'],
            sorts: ['full_name' => fn ($q, $d) => $q->orderBy('last_name', $d)->orderBy('first_name', $d)],
            default: 'full_name',
        ));
    }

    /** Création : un code d'accès est généré et renvoyé une première fois dans la réponse. */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('parents.create');
        $data = $this->validated($request);

        [$parent] = $this->accounts->createParent(collect($data)->except('student_ids')->all());
        $parent->students()->sync($data['student_ids'] ?? []);
        ActivityLog::record('parent.created', $parent);

        return $this->present($parent, true)->response()->setStatusCode(201);
    }

    /** Le code est visible pour le personnel autorisé, afin de réimprimer la fiche d'accès. */
    public function show(Request $request, ParentProfile $parent): ParentResource
    {
        $this->authorize('parents.view');

        return $this->present($parent, $request->user()->can('parents.update'));
    }

    public function update(Request $request, ParentProfile $parent): ParentResource
    {
        $this->authorize('parents.update');
        $data = $this->validated($request);

        $parent->update(collect($data)->except('student_ids')->all());
        $request->has('student_ids') && $parent->students()->sync($data['student_ids'] ?? []);
        $this->accounts->syncParentAccount($parent);

        return $this->present($parent, true);
    }

    public function destroy(ParentProfile $parent): JsonResponse
    {
        $this->authorize('parents.delete');
        $this->codes->revoke($parent);
        $parent->user?->update(['status' => 'suspended']);
        $parent->update(['status' => 'inactive']);
        $parent->delete();
        ActivityLog::record('parent.deleted', $parent);

        return response()->json(['message' => 'Parent supprimé, son code d’accès est désactivé.']);
    }

    /** Nouveau code : l'ancien ne fonctionne plus et les appareils connectés sont déconnectés. */
    public function regenerateCode(ParentProfile $parent): ParentResource
    {
        $this->authorize('parents.update');
        $this->codes->issue($parent);
        $parent->status === 'inactive' && $parent->update(['status' => 'pending']);
        ActivityLog::record('parent.code_regenerated', $parent);

        return $this->present($parent->refresh(), true);
    }

    public function toggle(ParentProfile $parent): ParentResource
    {
        $this->authorize('parents.update');

        if ($parent->status === 'inactive') {
            $parent->update(['status' => 'pending']);
            $parent->user?->update(['status' => 'active']);
            $this->codes->issue($parent);
        } else {
            $parent->update(['status' => 'inactive']);
            $parent->user?->update(['status' => 'suspended']);
            $this->codes->revoke($parent);
        }

        return $this->present($parent->refresh(), true);
    }

    protected function present(ParentProfile $parent, bool $withCode): ParentResource
    {
        $resource = new ParentResource($this->baseQuery()->findOrFail($parent->id));
        $resource->withCode = $withCode;

        return $resource;
    }

    protected function baseQuery()
    {
        return ParentProfile::with('students.currentEnrollment.classRoom');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'profession' => ['nullable', 'string', 'max:100'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', Rule::exists('tenant.students', 'id')],
        ]);
    }
}
