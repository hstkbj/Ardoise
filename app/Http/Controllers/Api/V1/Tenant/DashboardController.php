<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Campus;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Enrollment;
use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\Payment;
use App\Models\Tenant\Setting;
use App\Models\Tenant\Teacher;
use App\Models\Tenant\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Tableau de bord de l'administration : GET /dashboard/school */
class DashboardController extends Controller
{
    public function school(Request $request): JsonResponse
    {
        abort_unless($request->user()->isStaff(), 403);

        $year = $request->integer('academic_year_id') ? AcademicYear::find($request->integer('academic_year_id')) : AcademicYear::current();
        $term = $request->integer('term_id') ? Term::find($request->integer('term_id')) : Term::current();
        $campusId = $request->integer('school_id') ?: null;
        $classId = $request->integer('class_id') ?: null;
        $today = now()->toDateString();

        $enrollments = Enrollment::where('enrollments.status', 'active')
            ->where('enrollments.academic_year_id', $year?->id)
            ->when($campusId, fn ($q) => $q->whereHas('classRoom', fn ($c) => $c->where('campus_id', $campusId)))
            ->when($classId, fn ($q) => $q->where('class_room_id', $classId));

        $students = (clone $enrollments)->count();
        $classes = ClassRoom::where('academic_year_id', $year?->id)->when($campusId, fn ($q) => $q->where('campus_id', $campusId))->count();
        $parents = ParentProfile::count();

        // Finances de l'année
        $fees = FeeAssignment::whereHas('fee', fn ($q) => $q->where('academic_year_id', $year?->id)->when($campusId, fn ($f) => $f->where(fn ($x) => $x->whereNull('campus_id')->orWhere('campus_id', $campusId))));
        $expected = (int) (clone $fees)->sum('amount');
        $collected = (int) (clone $fees)->sum('paid_amount');
        $lateDays = (int) Setting::get('finance', 'late_after_days', 30);
        $lateFamilies = (clone $fees)->where('status', '!=', 'paid')->whereDate('due_date', '<', now()->subDays($lateDays)->toDateString())->distinct()->count('student_id');

        // Absences du jour
        $todayAttendance = Attendance::whereDate('date', $today)->when($classId, fn ($q) => $q->where('class_room_id', $classId))
            ->when($campusId, fn ($q) => $q->whereHas('classRoom', fn ($c) => $c->where('campus_id', $campusId)));
        $absentToday = (clone $todayAttendance)->where('status', 'absent')->distinct()->count('student_id');
        $byClass = (clone $todayAttendance)->where('status', 'absent')
            ->select('class_room_id', DB::raw('count(distinct student_id) as total'))->groupBy('class_room_id')
            ->orderByDesc('total')->limit(5)->with('classRoom.campus')->get()
            ->map(fn ($row) => ['label' => $row->classRoom?->name.' · '.$row->classRoom?->campus?->name, 'value' => (int) $row->total]);

        // Moyennes par niveau (évaluations validées de la période)
        $averages = $term ? DB::connection('tenant')->table('grades')
            ->join('assessments', 'assessments.id', '=', 'grades.assessment_id')
            ->join('class_subject', 'class_subject.id', '=', 'assessments.class_subject_id')
            ->join('class_rooms', 'class_rooms.id', '=', 'class_subject.class_room_id')
            ->join('levels', 'levels.id', '=', 'class_rooms.level_id')
            ->where('assessments.term_id', $term->id)
            ->where('assessments.status', 'validated')
            ->whereNotNull('grades.score')
            ->when($campusId, fn ($q) => $q->where('class_rooms.campus_id', $campusId))
            ->groupBy('levels.name', 'levels.position')
            ->orderBy('levels.position')
            ->select('levels.name as label', DB::raw('avg(grades.score * 20.0 / assessments.max_score) as value'))
            ->get()
            ->map(fn ($r) => ['label' => $r->label, 'value' => round((float) $r->value, 1)]) : collect();

        $byCampus = Campus::where('status', 'active')->get()->map(fn (Campus $c) => [
            'label' => $c->name,
            'value' => Enrollment::where('status', 'active')->where('academic_year_id', $year?->id)->whereHas('classRoom', fn ($q) => $q->where('campus_id', $c->id))->count(),
        ])->values();

        $genders = (clone $enrollments)->join('students', 'students.id', '=', 'enrollments.student_id')
            ->select('students.gender', DB::raw('count(*) as total'))->groupBy('students.gender')->pluck('total', 'gender');
        $genderTotal = max(1, $genders->sum());

        return response()->json(['data' => [
            'kpis' => [
                'students' => $students,
                'new_students' => (clone $enrollments)->whereDate('enrollments.enrolled_on', '>=', now()->startOfMonth()->toDateString())->count(),
                'teachers' => Teacher::where('status', '!=', 'inactive')->when($campusId, fn ($q) => $q->whereHas('campuses', fn ($c) => $c->where('campuses.id', $campusId)))->count(),
                'part_time_teachers' => Teacher::where('status', '!=', 'inactive')->where('contract', 'vacataire')->count(),
                'classes' => $classes,
                'schools' => Campus::where('status', 'active')->count(),
                'parents' => $parents,
                'parents_activated_rate' => $parents ? (int) round(ParentProfile::where('status', 'active')->count() * 100 / $parents) : 0,
            ],
            'finance' => [
                'collected' => $collected,
                'pending' => $expected - $collected,
                'expected' => $expected,
                'late_families' => $lateFamilies,
            ],
            'attendance_today' => [
                'absent' => $absentToday,
                'rate' => $students ? round($absentToday * 100 / $students, 1) : 0,
                'late' => (clone $todayAttendance)->where('status', 'late')->count(),
                'justified' => (clone $todayAttendance)->where('status', 'absent')->where('is_justified', true)->count(),
                'by_class' => $byClass,
            ],
            'averages_by_level' => $averages,
            'students_by_school' => $byCampus,
            'gender' => [
                'female' => (int) round(($genders['F'] ?? 0) * 100 / $genderTotal),
                'male' => (int) round(($genders['M'] ?? 0) * 100 / $genderTotal),
                'scholarship' => null,
            ],
            'alerts' => $this->alerts($year, $lateFamilies, $lateDays),
            'events' => Assessment::with('classSubject.classRoom', 'classSubject.subject')
                ->whereIn('type', ['composition', 'examen'])
                ->whereDate('date', '>=', $today)
                ->orderBy('date')->limit(4)->get()
                ->map(fn (Assessment $a) => ['date' => $a->date->toDateString(), 'title' => $a->title.' · '.$a->classSubject?->subject?->name, 'place' => $a->classSubject?->classRoom?->name]),
            'term' => $term ? ['id' => $term->id, 'name' => $term->name] : null,
        ]]);
    }

    protected function alerts(?AcademicYear $year, int $lateFamilies, int $lateDays): array
    {
        $alerts = [];

        if ($lateFamilies > 0) {
            $alerts[] = ['tone' => 'danger', 'label' => 'Urgent', 'text' => "{$lateFamilies} famille(s) ont plus de {$lateDays} jours de retard de paiement", 'link' => ['label' => 'Voir les impayés', 'to' => '/admin/payments?status=overdue']];
        }

        $toValidate = Assessment::with('classSubject.classRoom')->where('status', 'draft')->whereDate('date', '<', now()->subDays(7)->toDateString())->get();

        if ($toValidate->isNotEmpty()) {
            $classes = $toValidate->pluck('classSubject.classRoom.name')->filter()->unique()->take(4)->implode(', ');
            $alerts[] = ['tone' => 'warning', 'label' => 'À valider', 'text' => $toValidate->count()." évaluation(s) en attente de validation — {$classes}", 'link' => ['label' => 'Ouvrir les évaluations', 'to' => '/admin/assessments']];
        }

        $pendingOnline = Payment::where('status', 'pending')->count();

        if ($pendingOnline > 0) {
            $alerts[] = ['tone' => 'warning', 'label' => 'Paiements', 'text' => "{$pendingOnline} paiement(s) en ligne à confirmer", 'link' => ['label' => 'Voir les paiements', 'to' => '/admin/payments']];
        }

        ClassRoom::where('academic_year_id', $year?->id)->whereNotNull('capacity')
            ->withCount(['enrollments as students_count' => fn ($q) => $q->where('status', 'active')])->get()
            ->filter(fn ($c) => $c->students_count >= $c->capacity)->take(3)
            ->each(function ($c) use (&$alerts) {
                $alerts[] = ['tone' => 'neutral', 'label' => 'Info', 'text' => "La {$c->name} a atteint sa capacité maximale ({$c->students_count}/{$c->capacity})"];
            });

        return $alerts;
    }
}
