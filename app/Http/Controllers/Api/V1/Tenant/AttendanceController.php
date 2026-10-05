<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\ClassRoom;
use App\Services\AttendanceService;
use App\Support\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendance) {}

    /** GET /attendance/session?class_id=&date=&slot= */
    public function session(Request $request): JsonResponse
    {
        $this->authorize('attendance.view');
        $data = $this->sessionParams($request);
        $class = $this->visibleClass($data['class_id']);

        return response()->json(['data' => $this->attendance->roster($class, $data['date'], $data['slot'])]);
    }

    /** POST /attendance/session { class_id, date, slot, records: [...] } */
    public function saveSession(Request $request): JsonResponse
    {
        $this->authorize('attendance.create');
        $data = $this->sessionParams($request) + $request->validate([
            'records' => ['required', 'array', 'max:500'],
            'records.*.student_id' => ['required', 'integer'],
            'records.*.status' => ['required', 'in:present,absent,late'],
        ]);

        $class = $this->visibleClass($data['class_id']);
        $counts = $this->attendance->save($class, $data['date'], $data['slot'], $request->input('records'), $request->user());

        return response()->json(['message' => 'Appel enregistré.', 'data' => $counts]);
    }

    /** GET /attendance : historique filtrable */
    public function index(Request $request)
    {
        $this->authorize('attendance.view');

        $query = Attendance::with(['student', 'classRoom'])
            ->where('status', '!=', 'present')
            ->when($this->teacherClassIds() !== null, fn ($q) => $q->whereIn('class_room_id', $this->teacherClassIds()));

        $page = ListQuery::paginate($query, $request,
            search: ['student.first_name', 'student.last_name', 'student.matricule'],
            filters: [
                'class_id' => 'class_room_id',
                'class_name' => fn ($q, $v) => $q->whereHas('classRoom', fn ($c) => $c->where('name', $v)),
                'school_id' => fn ($q, $v) => $q->whereHas('classRoom', fn ($c) => $c->where('campus_id', $v)),
                'student_id' => 'student_id',
                'status' => 'status',
                'justified' => fn ($q, $v) => $q->where('is_justified', filter_var($v, FILTER_VALIDATE_BOOLEAN)),
                'from' => fn ($q, $v) => $q->whereDate('date', '>=', $v),
                'to' => fn ($q, $v) => $q->whereDate('date', '<=', $v),
            ],
            sorts: ['date' => 'date'],
            default: '-date',
        );

        $page->getCollection()->transform(fn (Attendance $a) => $this->present($a));

        return ListQuery::json($page);
    }

    /** POST /attendance/{id}/justify { reason, file? } */
    public function justify(Request $request, Attendance $attendance): JsonResponse
    {
        $this->authorize('attendance.update');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255'], 'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120']]);
        $path = $request->file('file')?->store('tenants/'.tenant()->code.'/justifications');

        $this->attendance->justify($attendance, $data['reason'] ?? null, $path, $request->user());

        return response()->json(['message' => 'Absence justifiée.', 'data' => $this->present($attendance->load('student', 'classRoom'))]);
    }

    public static function present(Attendance $a): array
    {
        return [
            'id' => $a->id,
            'date' => $a->date?->toDateString(),
            'slot' => $a->slot,
            'student_id' => $a->student_id,
            'student_name' => $a->student?->full_name,
            'class_name' => $a->classRoom?->name,
            'status' => $a->status,
            'type' => $a->status,
            'minutes' => $a->minutes_late,
            'justified' => $a->is_justified,
            'reason' => $a->reason,
        ];
    }

    protected function sessionParams(Request $request): array
    {
        return $request->validate([
            'class_id' => ['required', 'integer'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'slot' => ['required', 'regex:/^\d{2}:\d{2}$/'],
        ]);
    }

    protected function visibleClass(int $id): ClassRoom
    {
        $class = ClassRoom::with('academicYear')->findOrFail($id);
        $ids = $this->teacherClassIds();
        abort_if($ids !== null && ! in_array((int) $class->id, $ids, true), 403);

        return $class;
    }
}
