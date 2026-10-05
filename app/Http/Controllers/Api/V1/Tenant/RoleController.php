<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use App\Support\PermissionCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('roles.view');

        $roles = Role::with('permissions:id,key')->withCount('users')->orderBy('id')->get();

        return response()->json(['data' => $roles->map(fn (Role $r) => $this->present($r))]);
    }

    public function show(Role $role): JsonResponse
    {
        $this->authorize('roles.view');

        return response()->json(['data' => $this->present($role->load('permissions:id,key')->loadCount('users'))]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('roles.create');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'key' => ['nullable', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('tenant.roles', 'key')],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $role = Role::create([
            'name' => $data['name'],
            'key' => $data['key'] ?? Str::slug($data['name'], '_'),
            'description' => $data['description'] ?? null,
            'is_system' => false,
        ]);

        return response()->json(['data' => $this->present($role->loadCount('users'))], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        $this->authorize('roles.update');
        $role->update($request->validate(['name' => ['required', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:255']]));

        return response()->json(['data' => $this->present($role->load('permissions:id,key')->loadCount('users'))]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('roles.delete');
        abort_if($role->is_system, 422, 'Les rôles par défaut ne peuvent pas être supprimés.');
        abort_if($role->users()->exists(), 422, 'Ce rôle est attribué à des utilisateurs.');
        $role->delete();

        return response()->json(['message' => 'Rôle supprimé.']);
    }

    /** PUT /roles/{id}/permissions { permissions: ["students.view", …] } */
    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $this->authorize('roles.update');
        abort_if($role->key === 'school_admin', 422, 'Le rôle Administrateur dispose de toutes les permissions.');

        $keys = $request->validate(['permissions' => ['present', 'array'], 'permissions.*' => ['string', Rule::exists('tenant.permissions', 'key')]])['permissions'];
        $role->permissions()->sync(Permission::whereIn('key', $keys)->pluck('id'));
        ActivityLog::record('role.permissions_updated', $role, ['permissions' => $keys]);

        return response()->json(['data' => $this->present($role->load('permissions:id,key')->loadCount('users'))]);
    }

    /** GET /permissions : catalogue groupé */
    public function permissions(): JsonResponse
    {
        $this->authorize('roles.view');

        return response()->json(['data' => collect(PermissionCatalog::GROUPS)->map(fn ($g, $key) => [
            'key' => $key, 'label' => $g[0], 'actions' => $g[1],
        ])->values(), 'labels' => PermissionCatalog::ACTION_LABELS]);
    }

    protected function present(Role $role): array
    {
        return [
            'id' => $role->id,
            'key' => $role->key,
            'name' => $role->name,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'users_count' => (int) ($role->users_count ?? 0),
            'permissions' => $role->key === 'school_admin' ? ['*'] : ($role->relationLoaded('permissions') ? $role->permissions->pluck('key')->values() : []),
        ];
    }
}
