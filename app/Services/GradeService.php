<?php

namespace App\Services;

use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\Grade;
use App\Models\Tenant\User;
use App\Support\GradeCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Feuille de notes d'une évaluation : lecture, enregistrement, validation (verrouillage). */
class GradeService
{
    public function __construct(protected Notifier $notifier) {}

    /** @return array<int, array<string, mixed>> */
    public function sheet(Assessment $assessment): array
    {
        $class = $assessment->classSubject->classRoom;
        $grades = $assessment->grades()->get()->keyBy('student_id');

        return $class->students()->get()->map(function ($student) use ($grades) {
            $grade = $grades->get($student->id);

            return [
                'student_id' => $student->id,
                'full_name' => $student->full_name,
                'matricule' => $student->matricule,
                'score' => $grade?->score !== null ? str_replace('.', ',', rtrim(rtrim(number_format($grade->score, 2, '.', ''), '0'), '.')) : '',
                'absent' => (bool) $grade?->is_absent,
                'comment' => $grade?->comment ?? '',
            ];
        })->values()->all();
    }

    /** @param  array<int, array{student_id: int, score?: mixed, absent?: bool, comment?: string|null}>  $rows */
    public function save(Assessment $assessment, array $rows): void
    {
        if ($assessment->isLocked()) {
            throw ValidationException::withMessages(['grades' => 'Cette évaluation est verrouillée.']);
        }

        $allowed = $assessment->classSubject->classRoom->students()->pluck('students.id')->map(fn ($id) => (int) $id)->all();
        $errors = [];
        $clean = [];

        foreach ($rows as $i => $row) {
            $studentId = (int) ($row['student_id'] ?? 0);

            if (! in_array($studentId, $allowed, true)) {
                $errors["grades.{$i}.student_id"] = 'Élève absent de cette classe.';

                continue;
            }

            $absent = (bool) ($row['absent'] ?? false);
            $score = $absent ? null : GradeCalculator::parseScore($row['score'] ?? null, $assessment->max_score);

            if ($score === false) {
                $errors["grades.{$i}.score"] = 'La note doit être comprise entre 0 et '.rtrim(rtrim(number_format($assessment->max_score, 2, '.', ''), '0'), '.').'.';

                continue;
            }

            $clean[] = ['student_id' => $studentId, 'score' => $score, 'is_absent' => $absent, 'comment' => mb_substr((string) ($row['comment'] ?? ''), 0, 255) ?: null];
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::connection('tenant')->transaction(function () use ($assessment, $clean) {
            foreach ($clean as $row) {
                Grade::updateOrCreate(
                    ['assessment_id' => $assessment->id, 'student_id' => $row['student_id']],
                    ['score' => $row['score'], 'is_absent' => $row['is_absent'], 'comment' => $row['comment']],
                );
            }
        });
    }

    /** Toutes les notes doivent être saisies (ou l'élève marqué absent). */
    public function validate(Assessment $assessment, User $by): void
    {
        $studentIds = $assessment->classSubject->classRoom->students()->pluck('students.id');
        $done = $assessment->grades()->where(fn ($q) => $q->whereNotNull('score')->orWhere('is_absent', true))->pluck('student_id');
        $missing = $studentIds->diff($done)->count();

        if ($missing > 0) {
            throw ValidationException::withMessages(['grades' => "Il manque {$missing} note(s) : complétez-les ou marquez les élèves absents."]);
        }

        $assessment->update(['status' => 'validated', 'validated_at' => now(), 'validated_by' => $by->id]);
        ActivityLog::record('assessment.validated', $assessment);

        $subject = $assessment->classSubject->subject->name;
        $students = $assessment->grades()->with('student')->whereNotNull('score')->get()->pluck('student')->filter();
        $this->notifier->parentsOf($students, 'Nouvelle note · '.$subject, $assessment->title.' : la note est disponible.', '/parent/grades');
    }

    /** Réservé à la direction : journalisé. */
    public function unlock(Assessment $assessment, ?string $reason = null): void
    {
        $assessment->update(['status' => 'draft', 'validated_at' => null, 'validated_by' => null]);
        ActivityLog::record('assessment.unlocked', $assessment, ['reason' => $reason]);
    }
}
