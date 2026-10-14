<?php

namespace App\Services\Imports;

use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Assessment;
use App\Services\GradeService;
use App\Support\GradeCalculator;
use App\Support\Spreadsheet\CellParser;

/**
 * Import des notes d'une évaluation (modèle pré-rempli avec les élèves de la classe).
 * Une ligne sans note, sans absence ni commentaire est ignorée : la note
 * déjà saisie n'est pas effacée.
 */
class GradeImport extends SpreadsheetImport
{
    public function __construct(protected GradeService $grades) {}

    public static function templateHeaders(): array
    {
        return ['Matricule', 'Élève', 'Note', 'Absent', 'Commentaire'];
    }

    public static function templateExample(): array
    {
        return ['PAL-26-0001', 'KONÉ Aïcha', '14,5', '', 'Bon travail'];
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{total: int, saved: int, skipped: int, dry_run: bool}
     */
    public function run(Assessment $assessment, array $rows, bool $dryRun = false): array
    {
        $this->requireColumns($rows, 'matricule', 'note');

        $students = $assessment->classSubject->classRoom->students()->get();
        $byMatricule = $students->keyBy(fn ($s) => self::key($s->matricule));
        $seen = [];
        $grades = [];
        $skipped = 0;

        foreach ($rows as $row) {
            $matricule = $this->cell($row, 'matricule');
            $student = $byMatricule->get(self::key($matricule));
            $scoreText = $this->cell($row, 'note', 'note_sur_20');
            $absent = CellParser::boolean($this->cell($row, 'absent', 'absence'));
            $comment = mb_substr($this->cell($row, 'commentaire', 'appreciation'), 0, 255);

            if (! $student) {
                $this->error($row, $matricule === '' ? 'Matricule manquant.' : "Élève {$matricule} absent de la classe {$assessment->classSubject->classRoom->name}.");

                continue;
            }

            if (isset($seen[$student->id])) {
                $this->error($row, "Élève {$matricule} présent deux fois dans le fichier.");
            }

            $seen[$student->id] = true;

            if ($scoreText === '' && ! $absent && $comment === '') {
                $skipped++;

                continue;
            }

            $score = $absent ? null : GradeCalculator::parseScore($scoreText, (float) $assessment->max_score);

            if ($score === false) {
                $this->error($row, "Note « {$scoreText} » invalide : elle doit être comprise entre 0 et ".rtrim(rtrim(number_format((float) $assessment->max_score, 2, '.', ''), '0'), '.').'.');

                continue;
            }

            $grades[] = ['student_id' => $student->id, 'score' => $score === null ? '' : (string) $score, 'absent' => $absent, 'comment' => $comment];
        }

        $this->failIfErrors();

        if (! $dryRun && $grades) {
            $this->grades->save($assessment, $grades);
            ActivityLog::record('import.grades', $assessment, ['notes' => count($grades)]);
        }

        return ['total' => count($rows), 'saved' => count($grades), 'skipped' => $skipped, 'dry_run' => $dryRun];
    }
}
