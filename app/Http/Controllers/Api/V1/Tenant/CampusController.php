<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampusResource;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Campus;
use App\Models\Tenant\Enrollment;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Établissements / sites (« schools » côté interface). */
class CampusController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('schools.view');

        $query = $this->withCounts(Campus::query());

        return CampusResource::collection(ListQuery::paginate($query, $request,
            search: ['name', 'code', 'city'],
            filters: ['status' => 'status'],
            sorts: ['name' => 'name', 'city' => 'city', 'students_count' => 'students_count'],
            default: 'name',
        ));
    }

    public function store(Request $request): CampusResource
    {
        $this->authorize('schools.create');
        $campus = Campus::create($this->validated($request));
        $this->storeLogo($request, $campus);
        ActivityLog::record('campus.created', $campus);

        return new CampusResource($this->withCounts(Campus::query())->find($campus->id));
    }

    public function show(Campus $campus): CampusResource
    {
        $this->authorize('schools.view');

        return new CampusResource($this->withCounts(Campus::query())->find($campus->id));
    }

    public function update(Request $request, Campus $campus): CampusResource
    {
        $this->authorize('schools.update');
        $campus->update($this->validated($request, $campus));
        $this->storeLogo($request, $campus);

        return new CampusResource($this->withCounts(Campus::query())->find($campus->id));
    }

    public function destroy(Campus $campus): JsonResponse
    {
        $this->authorize('schools.delete');
        abort_if($campus->classRooms()->exists(), 422, 'Impossible de supprimer un établissement qui contient des classes. Désactivez-le plutôt.');
        $campus->delete();
        ActivityLog::record('campus.deleted', $campus);

        return response()->json(['message' => 'Établissement supprimé.']);
    }

    public function toggle(Campus $campus): CampusResource
    {
        $this->authorize('schools.update');
        $campus->update(['status' => $campus->status === 'active' ? 'inactive' : 'active']);

        return new CampusResource($this->withCounts(Campus::query())->find($campus->id));
    }

    protected function withCounts($query)
    {
        return $query->withCount(['classRooms', 'teachers'])
            ->addSelect(['students_count' => Enrollment::selectRaw('count(*)')
                ->join('class_rooms', 'class_rooms.id', '=', 'enrollments.class_room_id')
                ->whereColumn('class_rooms.campus_id', 'campuses.id')
                ->where('enrollments.status', 'active')]);
    }

    protected function validated(Request $request, ?Campus $campus = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', Rule::unique('tenant.campuses', 'code')->ignore($campus?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'manager' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]) + ['status' => 'active'];
    }

    protected function storeLogo(Request $request, Campus $campus): void
    {
        if ($request->hasFile('logo')) {
            $request->validate(['logo' => ['image', 'max:2048']]);
            $campus->update(['logo_path' => $request->file('logo')->store('tenants/'.tenant()->code.'/logos', 'public')]);
        }
    }
}
