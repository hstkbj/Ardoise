<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Support\ListQuery;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

/** Comptes du personnel. Les parents ont leur propre écran (codes d'accès). */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('users.view');

        $query = User::with(['roles', 'campuses'])
            ->when(! $request->input('filter.role'), fn ($q) => $q->whereDoesntHave('roles', fn ($r) => $r->where('key', 'parent')));

        return UserResource::collection(ListQuery::paginate($query, $request,
            search: ['name', 'email', 'phone'],
            filters: [
                'role' => fn ($q, $v) => $q->whereHas('roles', fn ($r) => $r->where('key', $v)),
                'status' => 'status',
            ],
            sorts: ['full_name' => 'name', 'last_login_at' => 'last_login_at'],
            default: 'full_name',
        ));
    }

    /** Invitation : l'utilisateur reçoit un lien pour choisir son mot de passe. */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('users.create');
        $data = $this->validated($request);

        $user = User::create(['name' => $data['full_name'], 'email' => $data['email'], 'phone' => $data['phone'], 'status' => 'invited']);
        $this->syncRelations($user, $data);
        rescue(fn () => Password::broker()->sendResetLink(['email' => $user->email]), report: true);
        ActivityLog::record('user.invited', $user, ['role' => $data['role']]);

        return (new UserResource($user->load('roles', 'campuses')))->response()->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('users.view');

        return new UserResource($user->load('roles', 'campuses'));
    }

    public function update(Request $request, User $user): UserResource
    {
        $this->authorize('users.update');
        $data = $this->validated($request, $user);

        $user->update(['name' => $data['full_name'], 'email' => $data['email'], 'phone' => $data['phone']]);
        $this->syncRelations($user, $data);
        ActivityLog::record('user.updated', $user, ['role' => $data['role']]);

        return new UserResource($user->load('roles', 'campuses'));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('users.delete');
        abort_if($user->is($request->user()), 422, 'Vous ne pouvez pas supprimer votre propre compte.');
        $this->ensureNotLastAdmin($user);
        $user->tokens()->delete();
        $user->delete();
        ActivityLog::record('user.deleted', null, ['email' => $user->email]);

        return response()->json(['message' => 'Utilisateur supprimé.']);
    }

    public function toggle(Request $request, User $user): UserResource
    {
        $this->authorize('users.update');
        abort_if($user->is($request->user()), 422, 'Vous ne pouvez pas suspendre votre propre compte.');

        if ($user->status === 'suspended') {
            $user->update(['status' => $user->password ? 'active' : 'invited']);
        } else {
            $this->ensureNotLastAdmin($user);
            $user->update(['status' => 'suspended']);
            $user->tokens()->delete();
        }

        ActivityLog::record('user.status_changed', $user, ['status' => $user->status]);

        return new UserResource($user->load('roles', 'campuses'));
    }

    public function bulk(Request $request, string $action): JsonResponse
    {
        $this->authorize('users.update');
        abort_unless($action === 'suspend', 404);
        $ids = collect($request->validate(['ids' => ['required', 'array']])['ids'])->reject(fn ($id) => (int) $id === $request->user()->id);

        User::whereIn('id', $ids)->whereDoesntHave('roles', fn ($r) => $r->where('key', 'school_admin'))->update(['status' => 'suspended']);

        return response()->json(['message' => 'Comptes suspendus.']);
    }

    protected function syncRelations(User $user, array $data): void
    {
        $roleId = Role::where('key', $data['role'])->value('id');
        // Un compte enseignant ou parent garde ce rôle en plus du rôle choisi
        $keep = $user->roles()->whereIn('key', ['teacher', 'parent'])->pluck('roles.id')->all();
        $user->roles()->sync(array_unique(array_merge([$roleId], $keep)));
        $user->campuses()->sync($data['school_ids'] ?? []);
    }

    protected function ensureNotLastAdmin(User $user): void
    {
        if ($user->hasRole('school_admin') && User::whereHas('roles', fn ($r) => $r->where('key', 'school_admin'))->where('status', '!=', 'suspended')->count() <= 1) {
            abort(422, 'Il doit rester au moins un administrateur actif.');
        }
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('tenant.users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', 'string', Rule::exists('tenant.roles', 'key'), Rule::notIn(['parent', 'student'])],
            'school_ids' => ['nullable', 'array'],
            'school_ids.*' => ['integer', 'exists:tenant.campuses,id'],
        ]);

        $data['email'] = strtolower($data['email']);
        $data['phone'] = Phone::normalize($data['phone'] ?? null);

        if ($data['phone'] && User::where('phone', $data['phone'])->when($user, fn ($q) => $q->whereKeyNot($user->id))->exists()) {
            abort(422, 'Ce numéro de téléphone est déjà utilisé.');
        }

        return $data;
    }
}
