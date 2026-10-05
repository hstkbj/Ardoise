<?php

namespace App\Services;

use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Enrollment;
use App\Models\Tenant\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Inscriptions : une inscription active par élève et par année, historique conservé. */
class EnrollmentService
{
    public function __construct(protected FeeService $fees) {}

    public function enroll(Student $student, ClassRoom $class, ?string $date = null): Enrollment
    {
        if ($class->academicYear?->isClosed()) {
            throw ValidationException::withMessages(['class_id' => 'Cette année scolaire est clôturée.']);
        }

        return DB::connection('tenant')->transaction(function () use ($student, $class, $date) {
            Enrollment::where('student_id', $student->id)
                ->where('academic_year_id', $class->academic_year_id)
                ->where('status', 'active')
                ->update(['status' => 'left', 'left_on' => now()->toDateString()]);

            $enrollment = Enrollment::create([
                'student_id' => $student->id,
                'class_room_id' => $class->id,
                'academic_year_id' => $class->academic_year_id,
                'enrolled_on' => $date ?: now()->toDateString(),
                'status' => 'active',
            ]);

            $this->fees->assignToStudent($student, $class);

            return $enrollment;
        });
    }

    public function changeClass(Student $student, ClassRoom $class): Enrollment
    {
        $from = $student->currentClass()?->name;
        $enrollment = $this->enroll($student, $class);
        ActivityLog::record('student.class_changed', $student, ['from' => $from, 'to' => $class->name]);

        return $enrollment;
    }

    /** Départ vers un autre établissement (hors organisation). */
    public function transfer(Student $student, ?string $note = null): void
    {
        $student->enrollments()->where('status', 'active')->update(['status' => 'transferred', 'left_on' => now()->toDateString(), 'note' => $note]);
        $student->update(['status' => 'transferred']);
        ActivityLog::record('student.transferred', $student, ['note' => $note]);
    }

    public function archive(Student $student): void
    {
        $student->enrollments()->where('status', 'active')->update(['status' => 'left', 'left_on' => now()->toDateString()]);
        $student->update(['status' => 'archived']);
        ActivityLog::record('student.archived', $student);
    }

    /** Matricule annuel : PAL-26-0412 */
    public function nextMatricule(): string
    {
        $prefix = strtoupper(substr(tenant()?->code ?? 'ECO', 0, 3)).'-'.now()->format('y').'-';
        $next = (int) Student::withTrashed()->max('id') + 1;

        do {
            $matricule = $prefix.str_pad((string) $next++, 4, '0', STR_PAD_LEFT);
        } while (Student::withTrashed()->where('matricule', $matricule)->exists());

        return $matricule;
    }
}
