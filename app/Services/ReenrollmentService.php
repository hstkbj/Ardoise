<?php

namespace App\Services;

use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Fee;
use App\Models\Tenant\Level;
use App\Models\Tenant\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Réinscription : les élèves déjà dans l'établissement passent dans leur classe de l'année
 * courante (promotion, redoublement ou changement de site). Aucune nouvelle fiche n'est créée.
 */
class ReenrollmentService
{
    public function __construct(protected EnrollmentService $enrollments) {}

    /** Élèves actifs d'une année précédente, pas encore inscrits dans l'année courante. */
    public function pendingQuery(AcademicYear $year): Builder
    {
        return Student::query()
            ->where('status', 'active')
            ->whereHas('enrollments', fn ($e) => $e->where('academic_year_id', '!=', $year->id)->whereIn('status', ['completed', 'active']))
            ->whereDoesntHave('enrollments', fn ($e) => $e->where('academic_year_id', $year->id)->where('status', 'active'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function pending(AcademicYear $year, ?int $fromClassId = null, ?int $campusId = null): Collection
    {
        $students = $this->pendingQuery($year)
            ->with(['enrollments' => fn ($e) => $e->where('academic_year_id', '!=', $year->id)->whereIn('status', ['completed', 'active'])
                ->with('classRoom.level', 'classRoom.campus')->orderByDesc('enrolled_on')->orderByDesc('id')])
            ->orderBy('last_name')->orderBy('first_name')
            ->get();

        $classes = ClassRoom::with('campus', 'level')->where('status', 'active')->orderBy('name')->get();

        return $students
            ->map(function (Student $student) use ($classes) {
                $from = $student->enrollments->first()?->classRoom;
                $suggested = $from ? $this->suggestNextClass($from, $classes) : null;

                return [
                    'student_id' => $student->id,
                    'full_name' => $student->full_name,
                    'matricule' => $student->matricule,
                    'from_class_id' => $from?->id,
                    'from_class' => $from ? $from->name.($from->campus ? ' · '.$from->campus->name : '') : null,
                    'from_school_id' => $from?->campus_id,
                    'suggested_class_id' => $suggested?->id,
                    'suggested_class' => $suggested?->name,
                ];
            })
            ->when($fromClassId, fn (Collection $c) => $c->where('from_class_id', $fromClassId))
            ->when($campusId, fn (Collection $c) => $c->where('from_school_id', $campusId))
            ->values();
    }

    /** Classe du niveau suivant, dans le même site (la première par nom s'il y en a plusieurs). */
    public function suggestNextClass(ClassRoom $from, ?Collection $classes = null): ?ClassRoom
    {
        $position = $from->level?->position;

        if ($position === null) {
            return null;
        }

        $next = Level::where('position', '>', $position)->orderBy('position')->first();

        if (! $next) {
            return null;
        }

        return ($classes ?? ClassRoom::where('status', 'active')->orderBy('name')->get())
            ->first(fn (ClassRoom $c) => $c->level_id === $next->id && $c->campus_id === $from->campus_id);
    }

    /**
     * @param  array<int, array{student_id: int, class_id: int}>  $items
     * @return array{enrolled: int, skipped: int}
     */
    public function reenroll(AcademicYear $year, array $items): array
    {
        $pending = $this->pendingQuery($year)->whereIn('id', array_column($items, 'student_id'))->get()->keyBy('id');
        $enrolled = 0;

        foreach ($items as $item) {
            $student = $pending->get($item['student_id']);

            if (! $student) {
                continue; // déjà inscrit cette année, archivé ou parti
            }

            $from = $student->enrollments()->where('academic_year_id', '!=', $year->id)->latest('enrolled_on')->latest('id')->first()?->classRoom;
            $class = ClassRoom::findOrFail($item['class_id']);

            $this->enrollments->enroll($student, $class);
            ActivityLog::record('student.reenrolled', $student, ['from' => $from?->name, 'to' => $class->name, 'year' => $year->name]);
            $enrolled++;
        }

        return ['enrolled' => $enrolled, 'skipped' => count($items) - $enrolled];
    }

    public function hasFees(AcademicYear $year): bool
    {
        return Fee::where('academic_year_id', $year->id)->where('status', 'active')->exists();
    }
}
