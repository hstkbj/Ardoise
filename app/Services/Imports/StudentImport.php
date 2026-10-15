<?php

namespace App\Services\Imports;

use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\Student;
use App\Services\AccountService;
use App\Services\Billing\PlanLimits;
use App\Services\EnrollmentService;
use App\Support\Spreadsheet\CellParser;
use Illuminate\Support\Facades\DB;

/**
 * Import des élèves (inscription en masse) dans l'année scolaire en cours.
 *  - matricule connu → fiche mise à jour (et changement de classe si besoin) ;
 *  - sinon → élève inscrit, matricule généré s'il est vide ;
 *  - parent : retrouvé par téléphone, sinon créé avec son code d'accès.
 */
class StudentImport extends SpreadsheetImport
{
    public function __construct(
        protected EnrollmentService $enrollments,
        protected AccountService $accounts,
        protected PlanLimits $limits,
    ) {}

    public static function templateHeaders(): array
    {
        return ['Matricule', 'Nom', 'Prénoms', 'Sexe', 'Date de naissance', 'Lieu de naissance', 'Classe', 'Établissement', 'Parent - nom', 'Parent - téléphone', 'Parent - lien'];
    }

    public static function templateExample(): array
    {
        return ['', 'KONÉ', 'Aïcha Marie', 'F', '14/03/2014', 'Abidjan', '6e A', '', 'Fatou Koné', '+2250700000000', 'Mère'];
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{total: int, created: int, updated: int, parents_created: int, dry_run: bool}
     */
    public function run(array $rows, bool $dryRun = false): array
    {
        $this->requireColumns($rows, 'nom', 'prenoms', 'classe');

        $year = AcademicYear::current();
        $classes = ClassRoom::with('campus')->where('status', 'active')->get();
        $existing = Student::whereIn('matricule', array_filter(array_column($rows, 'matricule')))->get()->keyBy(fn ($s) => self::key($s->matricule));
        $seen = [];
        $clean = [];

        foreach ($rows as $row) {
            $matricule = $this->cell($row, 'matricule');
            $gender = CellParser::gender($this->cell($row, 'sexe', 'genre'));
            $birthDate = CellParser::date($this->cell($row, 'date_de_naissance', 'date_naissance', 'ne_le', 'nee_le'));
            $class = $this->findClass($classes, $this->cell($row, 'classe'), $this->cell($row, 'etablissement', 'ecole', 'site'), $row);

            if ($this->cell($row, 'nom') === '' || $this->cell($row, 'prenoms', 'prenom') === '') {
                $this->error($row, 'Nom et prénoms obligatoires.');
            }

            if ($gender === false) {
                $this->error($row, 'Sexe invalide (F ou M).');
            }

            if ($birthDate === false || ($birthDate && $birthDate >= now()->toDateString())) {
                $this->error($row, 'Date de naissance invalide (format JJ/MM/AAAA).');
            }

            if ($matricule !== '') {
                if (isset($seen[self::key($matricule)])) {
                    $this->error($row, "Matricule {$matricule} présent deux fois dans le fichier.");
                }

                $seen[self::key($matricule)] = true;
            }

            if ($this->cell($row, 'parent_telephone', 'telephone_parent') !== '' && $this->cell($row, 'parent_nom', 'nom_parent', 'parent') === '') {
                $this->error($row, 'Indiquez le nom du parent avec son téléphone.');
            }

            $clean[] = [
                'row' => $row,
                'student' => $matricule !== '' ? $existing->get(self::key($matricule)) : null,
                'class' => $class,
                'matricule' => $matricule,
                'last_name' => mb_substr($this->cell($row, 'nom'), 0, 100),
                'first_name' => mb_substr($this->cell($row, 'prenoms', 'prenom'), 0, 100),
                'gender' => $gender ?: null,
                'birth_date' => $birthDate ?: null,
                'birth_place' => mb_substr($this->cell($row, 'lieu_de_naissance', 'lieu_naissance'), 0, 100) ?: null,
                'guardian_name' => mb_substr($this->cell($row, 'parent_nom', 'nom_parent', 'parent'), 0, 150) ?: null,
                'guardian_phone' => mb_substr($this->cell($row, 'parent_telephone', 'telephone_parent'), 0, 30) ?: null,
                'guardian_relation' => mb_substr($this->cell($row, 'parent_lien', 'lien'), 0, 30) ?: null,
            ];
        }

        $this->failIfErrors();

        $toCreate = count(array_filter($clean, fn ($c) => $c['student'] === null));
        $toCreate > 0 && $this->limits->ensureCanAdd('students', $toCreate);

        $summary = ['total' => count($clean), 'created' => $toCreate, 'updated' => count($clean) - $toCreate, 'parents_created' => 0, 'dry_run' => $dryRun];

        if ($dryRun) {
            return $summary;
        }

        DB::connection('tenant')->transaction(function () use ($clean, &$summary) {
            foreach ($clean as $item) {
                $student = $item['student'];
                $identity = ['first_name' => $item['first_name'], 'last_name' => $item['last_name']]
                    + array_filter(['gender' => $item['gender'], 'birth_date' => $item['birth_date'], 'birth_place' => $item['birth_place']]);

                if ($student) {
                    $student->update($identity);

                    if ((int) $student->currentClass()?->id !== $item['class']->id) {
                        $this->enrollments->changeClass($student, $item['class']);
                    }
                } else {
                    $student = Student::create($identity + [
                        'matricule' => $item['matricule'] ?: $this->enrollments->nextMatricule(),
                        'status' => 'active',
                    ]);
                    $this->enrollments->enroll($student, $item['class']);
                }

                if ($item['guardian_name'] && $this->accounts->attachGuardian($student, $item)) {
                    $summary['parents_created']++;
                }
            }
        });

        ActivityLog::record('import.students', null, ['créés' => $summary['created'], 'mis à jour' => $summary['updated'], 'parents créés' => $summary['parents_created']]);

        return $summary;
    }

    protected function findClass($classes, string $name, string $campus, array $row): ?ClassRoom
    {
        if ($name === '') {
            $this->error($row, 'Classe obligatoire.');

            return null;
        }

        $matches = $classes->filter(fn (ClassRoom $c) => self::key($c->name) === self::key($name));

        if ($campus !== '') {
            $matches = $matches->filter(fn (ClassRoom $c) => in_array(self::key($campus), [self::key($c->campus?->name), self::key($c->campus?->code)], true));
        }

        if ($matches->count() === 1) {
            if (! $year || $year->isClosed()) {
                $this->error($row, 'Aucune année scolaire ouverte.');
            }

            return $matches->first();
        }

        $this->error($row, $matches->isEmpty()
            ? "Classe « {$name} » introuvable dans l’année en cours."
            : "Plusieurs classes « {$name} » : précisez l’établissement.");

        return null;
    }
}
