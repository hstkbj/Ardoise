<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassRoomResource;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Enrollment;
use App\Models\Tenant\Homework;
use App\Models\Tenant\Term;
use App\Models\Tenant\TimetableEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Espace enseignant : GET /teacher/dashboard, GET /teacher/classes */
class TeacherSpaceController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $teacher = $request->user()->teacher;
        abort_unless($teacher, 403, 'Aucun profil enseignant n’est associé à ce compte.');

        $classIds = $teacher->classRoomIds();
        $day = now()->dayOfWeekIso - 1; // 0 = lundi
        $today = now()->toDateString();

        $todayEntries = TimetableEntry::with(['classRoom', 'subject'])
            ->where('teacher_id', $teacher->id)->where('day_of_week', $day)->orderBy('starts_at')->get();

        $toGrade = Assessment::with('classSubject.classRoom')
            ->where('teacher_id', $teacher->id)->where('status', 'draft')
            ->withCount(['grades as graded' => fn ($q) => $q->where(fn ($g) => $g->whereNotNull('score')->orWhere('is_absent', true))])
            ->orderBy('date')->limit(6)->get();

        return response()->json(['data' => [
            'today' => $todayEntries->map(fn ($e) => [
                'time' => $e->starts_at.' – '.$e->ends_at,
                'start' => $e->starts_at,
                'class_id' => $e->class_room_id,
                'class_name' => $e->classRoom?->name,
                'subject' => $e->subject?->name,
                'room' => $e->room,
                'attendance_done' => Attendance::where('class_room_id', $e->class_room_id)->whereDate('date', $today)->where('slot', $e->starts_at)->exists(),
            ]),
            'to_grade' => $toGrade->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'class_name' => $a->classSubject?->classRoom?->name,
                'graded' => (int) $a->graded,
                'total' => Enrollment::where('class_room_id', $a->classSubject?->class_room_id)->where('status', 'active')->count(),
                'due' => $a->date->copy()->addDays(7)->toDateString(),
            ]),
            'homework' => Homework::with('classRoom')->where('teacher_id', $teacher->id)->whereDate('due_date', '>=', now()->subDays(3)->toDateString())
                ->orderBy('due_date')->limit(5)->get()
                ->map(fn ($h) => ['id' => $h->id, 'title' => $h->title, 'class_name' => $h->classRoom?->name, 'due' => $h->due_date->toDateString(), 'status' => $h->status]),
            'stats' => [
                'classes' => count($classIds),
                'students' => Enrollment::whereIn('class_room_id', $classIds)->where('status', 'active')->count(),
                'assessments' => Assessment::where('teacher_id', $teacher->id)->where('term_id', Term::current()?->id)->count(),
                'absences_week' => Attendance::whereIn('class_room_id', $classIds)->where('status', 'absent')->whereDate('date', '>=', now()->startOfWeek()->toDateString())->count(),
            ],
        ]]);
    }

    public function classes(Request $request)
    {
        $teacher = $request->user()->teacher;
        abort_unless($teacher, 403);

        return ClassRoomResource::collection(ClassRoom::with(['campus', 'level', 'headTeacher', 'academicYear'])
            ->withCount(['enrollments as students_count' => fn ($q) => $q->where('status', 'active')])
            ->whereIn('id', $teacher->classRoomIds())
            ->orderBy('level_id')->get());
    }
}
