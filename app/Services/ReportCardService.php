<?php

namespace App\Services;

use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Grade;
use App\Models\Tenant\ReportCard;
use App\Models\Tenant\Setting;
use App\Models\Tenant\Student;
use App\Models\Tenant\Term;
use App\Support\GradeCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Calcul et génération des bulletins d'une classe pour une période.
 * Seules les évaluations VALIDÉES sont prises en compte.
 */
class ReportCardService
{
    public function __construct(protected Notifier $notifier) {}

    public function calculator(): GradeCalculator
    {
        return new GradeCalculator((float) Setting::get('grading', 'rounding', '0.01'));
    }

    /**
     * @return array{students: array<int, array>, subjects: array<int, array>, class_average: float|null}
     */
    public function compute(ClassRoom $class, Term $term): array
    {
        $calc = $this->calculator();
        $students = $class->students()->get();
        $classSubjects = $class->classSubjects()->with(['subject', 'teacher'])->get();

        $assessments = Assessment::whereIn('class_subject_id', $classSubjects->pluck('id'))
            ->where('term_id', $term->id)
            ->where('status', 'validated')
            ->get()
            ->groupBy('class_subject_id');

        $grades = Grade::whereIn('assessment_id', $assessments->flatten()->pluck('id'))->get()->groupBy('student_id');

        $rows = [];

        foreach ($students as $student) {
            $studentGrades = ($grades->get($student->id) ?? collect())->keyBy('assessment_id');
            $subjects = [];

            foreach ($classSubjects as $cs) {
                $marks = ($assessments->get($cs->id) ?? collect())->map(fn (Assessment $a) => [
                    'score' => $studentGrades->get($a->id)?->score,
                    'max' => $a->max_score,
                    'coefficient' => $a->coefficient,
                    'absent' => (bool) $studentGrades->get($a->id)?->is_absent,
                ])->all();

                $subjects[$cs->id] = ['average' => $calc->subjectAverage($marks), 'coefficient' => $cs->coefficient];
            }

            $rows[$student->id] = [
                'student' => $student,
                'subjects' => $subjects,
                'general_average' => $calc->generalAverage(array_values($subjects)),
            ];
        }

        $ranks = $calc->rank(array_map(fn ($r) => $r['general_average'], $rows));

        foreach (array_keys($rows) as $id) {
            $rows[$id]['rank'] = $ranks[$id];
        }

        $subjectInfo = [];

        foreach ($classSubjects as $cs) {
            $subjectInfo[$cs->id] = [
                'subject_id' => $cs->subject_id,
                'name' => $cs->subject->name,
                'teacher' => $cs->teacher?->full_name,
                'coefficient' => $cs->coefficient,
                'class_average' => $calc->average(array_map(fn ($r) => $r['subjects'][$cs->id]['average'], $rows)),
            ];
        }

        return [
            'students' => $rows,
            'subjects' => $subjectInfo,
            'class_average' => $calc->average(array_map(fn ($r) => $r['general_average'], $rows)),
        ];
    }

    /** Génère (ou régénère) les bulletins ; commentaires et décisions déjà saisis sont conservés. */
    public function generate(ClassRoom $class, Term $term): int
    {
        $data = $this->compute($class, $term);
        $size = count($data['students']);

        DB::connection('tenant')->transaction(function () use ($data, $class, $term, $size) {
            foreach ($data['students'] as $studentId => $row) {
                [$absences, $late] = $this->attendanceCounts($studentId, $term);

                $card = ReportCard::firstOrNew(['student_id' => $studentId, 'term_id' => $term->id]);
                $card->fill([
                    'class_room_id' => $class->id,
                    'general_average' => $row['general_average'],
                    'rank' => $row['rank'],
                    'class_size' => $size,
                    'class_average' => $data['class_average'],
                    'absences_hours' => $absences,
                    'late_count' => $late,
                    'council_decision' => $card->council_decision ?? self::suggestDecision($row['general_average']),
                    'head_teacher_comment' => $card->head_teacher_comment ?? self::appreciation($row['general_average']),
                    'status' => $card->status ?? 'draft',
                    'generated_at' => now(),
                ])->save();

                $card->subjects()->delete();

                foreach ($data['subjects'] as $csId => $info) {
                    $average = $row['subjects'][$csId]['average'];
                    $card->subjects()->create([
                        'subject_id' => $info['subject_id'],
                        'subject_name' => $info['name'],
                        'teacher_name' => $info['teacher'],
                        'coefficient' => $info['coefficient'],
                        'average' => $average,
                        'class_average' => $info['class_average'],
                        'appreciation' => self::appreciation($average),
                    ]);
                }
            }
        });

        ActivityLog::record('report_cards.generated', $class, ['term' => $term->name, 'count' => $size]);

        return $size;
    }

    /** @param  array<int, int>  $studentIds */
    public function publish(array $studentIds, Term $term): int
    {
        $cards = ReportCard::with('student')->where('term_id', $term->id)->whereIn('student_id', $studentIds)->get();

        foreach ($cards as $card) {
            $card->update(['status' => 'published', 'published_at' => now()]);
        }

        ActivityLog::record('report_cards.published', null, ['term' => $term->name, 'count' => $cards->count()]);

        $this->notifier->event(
            'report_card.published',
            'Bulletin disponible',
            'Le bulletin du '.mb_strtolower($term->name).' est disponible.',
            $cards->pluck('student')->filter(),
            ['parent' => '/parent/report-cards', 'teacher' => '/teacher/classes', 'staff' => '/admin/report-cards'],
        );

        return $cards->count();
    }

    /** Bulletin au format attendu par le frontend (enregistré ou calculé à la volée). */
    public function present(Student $student, Term $term): array
    {
        $card = ReportCard::with(['subjects', 'classRoom.campus'])->where('student_id', $student->id)->where('term_id', $term->id)->first();
        $class = $card?->classRoom ?? $student->currentClass();
        $school = Setting::get('identity', 'name') ?? tenant()?->name;

        $base = [
            'school' => trim($school.($class?->campus ? ' — '.$class->campus->name : '')),
            'academic_year' => $term->academicYear->name,
            'term' => $term->name,
            'term_id' => $term->id,
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'matricule' => $student->matricule,
                'birth_date' => $student->birth_date?->toDateString(),
                'class_name' => $class?->name,
            ],
            'show_rank' => Setting::get('reports', 'show_rank', 'yes') === 'yes',
        ];

        if ($card) {
            return $base + [
                'id' => $card->id,
                'status' => $card->status,
                'published_at' => $card->published_at,
                'generated' => true,
                'subjects' => $card->subjects->map(fn ($s) => [
                    'name' => $s->subject_name, 'teacher' => $s->teacher_name, 'coefficient' => $s->coefficient,
                    'average' => $s->average, 'class_average' => $s->class_average, 'appreciation' => $s->appreciation,
                ])->values()->all(),
                'general_average' => $card->general_average,
                'class_average' => $card->class_average,
                'rank' => $card->rank,
                'class_size' => $card->class_size,
                'absences' => $card->absences_hours,
                'late' => $card->late_count,
                'head_teacher_comment' => $card->head_teacher_comment,
                'council_decision' => $card->council_decision,
            ];
        }

        // Aperçu non enregistré
        if (! $class) {
            return $base + ['generated' => false, 'status' => 'draft', 'subjects' => [], 'general_average' => null, 'class_average' => null, 'rank' => null, 'class_size' => 0, 'absences' => 0, 'late' => 0, 'head_teacher_comment' => null, 'council_decision' => null];
        }

        $data = $this->compute($class, $term);
        $row = $data['students'][$student->id] ?? null;
        [$absences, $late] = $this->attendanceCounts($student->id, $term);

        return $base + [
            'id' => null,
            'status' => 'draft',
            'generated' => false,
            'subjects' => collect($data['subjects'])->map(fn ($info, $csId) => [
                'name' => $info['name'], 'teacher' => $info['teacher'], 'coefficient' => $info['coefficient'],
                'average' => $row['subjects'][$csId]['average'] ?? null, 'class_average' => $info['class_average'],
                'appreciation' => self::appreciation($row['subjects'][$csId]['average'] ?? null),
            ])->values()->all(),
            'general_average' => $row['general_average'] ?? null,
            'class_average' => $data['class_average'],
            'rank' => $row['rank'] ?? null,
            'class_size' => count($data['students']),
            'absences' => $absences,
            'late' => $late,
            'head_teacher_comment' => self::appreciation($row['general_average'] ?? null),
            'council_decision' => self::suggestDecision($row['general_average'] ?? null),
        ];
    }

    /** @return array{0: int, 1: int} séances manquées, retards */
    protected function attendanceCounts(int $studentId, Term $term): array
    {
        $query = Attendance::where('student_id', $studentId)
            ->when($term->starts_on, fn ($q) => $q->whereDate('date', '>=', $term->starts_on))
            ->when($term->ends_on, fn ($q) => $q->whereDate('date', '<=', $term->ends_on));

        return [(clone $query)->where('status', 'absent')->count(), (clone $query)->where('status', 'late')->count()];
    }

    public static function appreciation(?float $average): ?string
    {
        return match (true) {
            $average === null => null,
            $average >= 16 => 'Excellent travail',
            $average >= 14 => 'Très bon travail',
            $average >= 12 => 'Bon travail',
            $average >= 10 => 'Assez bien, peut mieux faire',
            $average >= 8 => 'Résultats insuffisants',
            default => 'Travail très insuffisant',
        };
    }

    public static function suggestDecision(?float $average): ?string
    {
        return match (true) {
            $average === null => null,
            $average >= 16 => 'Félicitations',
            $average >= 14 => 'Encouragements',
            $average >= 12 => "Tableau d'honneur",
            $average < 8 => 'Avertissement travail',
            default => null,
        };
    }
}
