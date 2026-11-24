<?php

namespace App\Services\Imports;

use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Campus;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\ClassSubject;
use App\Models\Tenant\Level;
use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\Student;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Teacher;
use App\Services\AccountService;
use App\Services\Billing\PlanLimits;
use App\Support\Phone;
use App\Support\Spreadsheet\CellParser;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Mise en route d'une école avec un seul classeur Excel à plusieurs feuilles :
 * Enseignants, Matières, Classes, Affectations, Élèves, Parents.
 *
 * Principe « chercher ou créer » : une personne ou un élément cité dans une feuille est
 * d'abord cherché (enseignant : e-mail, téléphone, puis nom ; matière : nom ou code ;
 * classe : établissement + nom). S'il existe, on le réutilise et on lui AJOUTE les liens
 * manquants (ex. un nouvel établissement) sans rien retirer ; sinon on crée le minimum.
 *
 * Tout se fait dans une transaction : au moindre problème, ou en simple vérification,
 * rien n'est enregistré.
 */
class SchoolSetupImport
{
    /** @var array<int, array{line: int, message: string}> */
    protected array $errors = [];

    /** @var array<string, int> */
    protected array $stats = [];

    /** @var Collection<int, Campus> */
    protected Collection $campuses;

    /** @var Collection<int, Level> */
    protected Collection $levels;

    /** @var Collection<int, Teacher> */
    protected Collection $teachers;

    /** @var Collection<int, Subject> */
    protected Collection $subjects;

    /** @var Collection<int, ClassRoom> */
    protected Collection $classes;

    public function __construct(protected StudentImport $students, protected AccountService $accounts, protected PlanLimits $limits) {}

    /** @return array<string, array<int, array<int, string>>> nom de la feuille => lignes (en-têtes d'abord) */
    public static function templateSheets(): array
    {
        return [
            'Instructions' => [
                ['Comment remplir ce classeur'],
                ['1. Remplissez les feuilles dans l’ordre : Enseignants, Matières, Classes, Affectations, Élèves, Parents. Les feuilles inutiles peuvent rester vides.'],
                ['2. Gardez la première ligne de chaque feuille (les en-têtes) et supprimez la ligne d’exemple.'],
                ['3. Un enseignant, une matière ou une classe cité dans une feuille est d’abord cherché : s’il existe déjà, il est réutilisé ; sinon il est créé avec le minimum (nom et prénoms).'],
                ['4. Un enseignant retrouvé dans un autre établissement garde ses établissements actuels : le nouveau est simplement ajouté.'],
                ['5. Établissement : indiquez son nom. S’il n’existe pas encore, il est créé (dans la limite de votre plan) ; vous pourrez compléter son adresse ensuite. Vous pouvez laisser la colonne vide si l’école n’en a qu’un. Plusieurs établissements se séparent par une virgule.'],
                ['6. Niveau : un des niveaux de l’école (CP1, 6e, 2nde…). Année scolaire : elle doit être ouverte pour inscrire des élèves.'],
                ['7. Envoyez le fichier dans Ardoise : « Vérifier » contrôle tout sans rien enregistrer, « Importer » enregistre.'],
            ],
            'Enseignants' => [
                ['Nom', 'Prénoms', 'E-mail', 'Téléphone', 'Établissement', 'Matières'],
                ['DUPONT', 'Jean', 'jean.dupont@exemple.com', '+22997000000', 'Collège Akoma', 'Mathématiques, Physique'],
            ],
            'Matières' => [
                ['Matière', 'Code', 'Catégorie', 'Coefficient', 'Enseignant'],
                ['Mathématiques', 'MATH', 'Sciences', '4', 'Jean DUPONT'],
            ],
            'Classes' => [
                ['Classe', 'Niveau', 'Établissement', 'Capacité', 'Salle', 'Professeur principal'],
                ['6e A', '6e', 'Collège Akoma', '45', 'Salle 3', 'Jean DUPONT'],
            ],
            'Affectations' => [
                ['Classe', 'Établissement', 'Matière', 'Enseignant', 'Coefficient'],
                ['6e A', 'Collège Akoma', 'Mathématiques', 'Jean DUPONT', '4'],
            ],
            'Élèves' => [StudentImport::templateHeaders(), StudentImport::templateExample()],
            'Parents' => [
                ['Nom complet', 'Téléphone', 'Lien', 'Matricules des élèves'],
                ['Fatou KONÉ', '+2250700000000', 'Mère', 'M2026-0001, M2026-0002'],
            ],
        ];
    }

    /**
     * @param  array<string, array<int, array<string, string>>>  $sheets  feuilles lues (SpreadsheetReader::readSheets)
     * @return array<string, mixed>
     */
    public function run(array $sheets, bool $dryRun = false): array
    {
        $known = ['instructions', 'enseignants', 'matieres', 'classes', 'affectations', 'eleves', 'parents'];
        $unknown = array_diff(array_keys($sheets), $known);

        if (! array_filter(Arr::only($sheets, array_diff($known, ['instructions'])))) {
            throw new ImportException([['line' => 1, 'message' => 'Aucune donnée trouvée. Utilisez le modèle : les feuilles doivent s’appeler Enseignants, Matières, Classes, Affectations, Élèves et Parents.'.($unknown ? ' Feuilles non reconnues : '.implode(', ', $unknown).'.' : '')]]);
        }

        $this->errors = [];
        $this->stats = array_fill_keys(['campuses_created', 'teachers_created', 'teachers_linked', 'subjects_created', 'classes_created', 'classes_updated', 'assignments', 'students_created', 'students_updated', 'parents_created', 'parents_linked'], 0);

        $connection = DB::connection('tenant');
        $connection->beginTransaction();

        try {
            $this->campuses = Campus::where('status', 'active')->get();
            $this->levels = Level::all();
            $this->teachers = Teacher::with('campuses')->get();
            $this->subjects = Subject::with('teachers')->get();
            $this->classes = ClassRoom::all();

            $this->importTeachers($sheets['enseignants'] ?? []);
            $this->importSubjects($sheets['matieres'] ?? []);
            $this->importClasses($sheets['classes'] ?? []);
            $this->importAssignments($sheets['affectations'] ?? []);
            $this->importStudents($sheets['eleves'] ?? []);
            $this->importParents($sheets['parents'] ?? []);

            if ($this->errors) {
                throw new ImportException($this->errors);
            }

            // Simple vérification : on annule tout, le résumé est renvoyé
            $dryRun ? $connection->rollBack() : $connection->commit();
        } catch (Throwable $e) {
            $connection->transactionLevel() > 0 && $connection->rollBack();

            throw $e;
        }

        if (! $dryRun) {
            ActivityLog::record('import.setup', null, $this->stats);
        }

        return ['dry_run' => $dryRun] + $this->stats;
    }

    // ── Enseignants ────────────────────────────────────────────────────────

    /** @param  array<int, array<string, string>>  $rows */
    protected function importTeachers(array $rows): void
    {
        foreach ($rows as $row) {
            $last = $this->cell($row, 'nom');
            $first = $this->cell($row, 'prenoms', 'prenom');

            if ($last === '' || $first === '') {
                $this->fail('Enseignants', $row, 'Nom et prénoms obligatoires.');

                continue;
            }

            $campusIds = $this->campusIds('Enseignants', $row, $this->cell($row, 'etablissement', 'etablissements', 'ecole', 'site'), required: false, create: true);
            $teacher = $this->findOrCreateTeacher($first, $last, $this->cell($row, 'e_mail', 'email', 'mail'), $this->cell($row, 'telephone', 'tel'), $campusIds);

            foreach ($this->splitList($this->cell($row, 'matieres', 'matiere')) as $subjectName) {
                $subject = $this->findOrCreateSubject($subjectName);
                $this->linkSubjectTeacher($subject, $teacher);
            }
        }
    }

    // ── Matières ───────────────────────────────────────────────────────────

    /** @param  array<int, array<string, string>>  $rows */
    protected function importSubjects(array $rows): void
    {
        foreach ($rows as $row) {
            $name = $this->cell($row, 'matiere', 'nom');

            if ($name === '') {
                $this->fail('Matières', $row, 'Nom de la matière obligatoire.');

                continue;
            }

            $coefficient = $this->coefficient('Matières', $row, $this->cell($row, 'coefficient', 'coef'));
            $subject = $this->findOrCreateSubject($name, $this->cell($row, 'code'), $this->cell($row, 'categorie'), $coefficient);

            if (($teacherName = $this->cell($row, 'enseignant')) !== '') {
                $teacher = $this->findOrCreateTeacherByFullName($teacherName, []);
                $this->linkSubjectTeacher($subject, $teacher);
            }
        }
    }

    // ── Classes ────────────────────────────────────────────────────────────

    /** @param  array<int, array<string, string>>  $rows */
    protected function importClasses(array $rows): void
    {
        foreach ($rows as $row) {
            $name = $this->cell($row, 'classe', 'nom');

            if ($name === '') {
                $this->fail('Classes', $row, 'Nom de la classe obligatoire.');

                continue;
            }

            $campusIds = $this->campusIds('Classes', $row, $this->cell($row, 'etablissement', 'ecole', 'site'), required: true, create: true);

            if (count($campusIds) !== 1) {
                count($campusIds) > 1 && $this->fail('Classes', $row, 'Une classe appartient à un seul établissement.');

                continue;
            }

            $campusId = $campusIds[0];
            $capacity = $this->cell($row, 'capacite', 'effectif_max');

            if ($capacity !== '' && (! ctype_digit($capacity) || (int) $capacity < 1 || (int) $capacity > 1000)) {
                $this->fail('Classes', $row, 'Capacité invalide (nombre entier).');

                continue;
            }

            $headName = $this->cell($row, 'professeur_principal', 'prof_principal');
            $head = $headName !== '' ? $this->findOrCreateTeacherByFullName($headName, [$campusId]) : null;
            $class = $this->classes->first(fn (ClassRoom $c) => $c->campus_id === $campusId && self::key($c->name) === self::key($name));

            if ($class) {
                $changes = array_filter([
                    'head_teacher_id' => $class->head_teacher_id ? null : $head?->id,
                    'capacity' => $class->capacity ? null : ($capacity !== '' ? (int) $capacity : null),
                    'room' => $class->room ? null : ($this->cell($row, 'salle') ?: null),
                ], fn ($v) => $v !== null);

                if ($changes) {
                    $class->update($changes);
                    $this->stats['classes_updated']++;
                }

                continue;
            }

            $levelName = $this->cell($row, 'niveau');
            $level = $this->levels->first(fn (Level $l) => self::key($l->name) === self::key($levelName));

            if (! $level) {
                $this->fail('Classes', $row, $levelName === ''
                    ? 'Niveau obligatoire pour créer la classe.'
                    : "Niveau « {$levelName} » inconnu. Niveaux de l’école : ".$this->levels->pluck('name')->implode(', ').'.');

                continue;
            }

            $this->classes->push(ClassRoom::create([
                'campus_id' => $campusId,
                'level_id' => $level->id,
                'head_teacher_id' => $head?->id,
                'name' => mb_substr($name, 0, 100),
                'capacity' => $capacity !== '' ? (int) $capacity : null,
                'room' => mb_substr($this->cell($row, 'salle'), 0, 100) ?: null,
                'status' => 'active',
            ]));
            $this->stats['classes_created']++;
        }
    }

    // ── Affectations : matière + enseignant + coefficient dans une classe ──

    /** @param  array<int, array<string, string>>  $rows */
    protected function importAssignments(array $rows): void
    {
        foreach ($rows as $row) {
            $className = $this->cell($row, 'classe');
            $subjectName = $this->cell($row, 'matiere');

            if ($className === '' || $subjectName === '') {
                $this->fail('Affectations', $row, 'Classe et matière obligatoires.');

                continue;
            }

            $class = $this->findClass('Affectations', $row, $className, $this->cell($row, 'etablissement', 'ecole', 'site'));

            if (! $class) {
                continue;
            }

            $coefficient = $this->coefficient('Affectations', $row, $this->cell($row, 'coefficient', 'coef'));
            $subject = $this->findOrCreateSubject($subjectName);
            $teacherName = $this->cell($row, 'enseignant');
            $teacher = $teacherName !== '' ? $this->findOrCreateTeacherByFullName($teacherName, [$class->campus_id]) : null;
            $teacher && $this->linkSubjectTeacher($subject, $teacher);

            $assignment = ClassSubject::where('class_room_id', $class->id)->where('subject_id', $subject->id)->first();

            if ($assignment) {
                $assignment->update(array_filter([
                    'teacher_id' => $teacher?->id,
                    'coefficient' => $coefficient,
                ], fn ($v) => $v !== null));
            } else {
                ClassSubject::create([
                    'class_room_id' => $class->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teacher?->id,
                    'coefficient' => $coefficient ?? $subject->default_coefficient ?? 1,
                ]);
            }

            $this->stats['assignments']++;
        }
    }

    // ── Élèves et parents ──────────────────────────────────────────────────

    /** @param  array<int, array<string, string>>  $rows */
    protected function importStudents(array $rows): void
    {
        if (! $rows) {
            return;
        }

        try {
            $result = $this->students->run($rows, false);
            $this->stats['students_created'] = $result['created'];
            $this->stats['students_updated'] = $result['updated'];
            $this->stats['parents_created'] += $result['parents_created'];
        } catch (ImportException $e) {
            foreach ($e->lines as $line) {
                $this->errors[] = ['line' => $line['line'], 'message' => 'Feuille « Élèves » : '.$line['message']];
            }
        }
    }

    /** @param  array<int, array<string, string>>  $rows */
    protected function importParents(array $rows): void
    {
        foreach ($rows as $row) {
            $name = $this->cell($row, 'nom_complet', 'nom', 'parent');
            $matricules = $this->splitList($this->cell($row, 'matricules_des_eleves', 'matricules', 'matricule', 'eleves'));

            if ($name === '' || ! $matricules) {
                $this->fail('Parents', $row, 'Nom du parent et matricule(s) de ses élèves obligatoires.');

                continue;
            }

            foreach ($matricules as $matricule) {
                $student = Student::where('matricule', $matricule)->first();

                if (! $student) {
                    $this->fail('Parents', $row, "Élève de matricule « {$matricule} » introuvable (ajoutez-le dans la feuille Élèves).");

                    continue;
                }

                $code = $this->accounts->attachGuardian($student, [
                    'guardian_name' => mb_substr($name, 0, 150),
                    'guardian_phone' => mb_substr($this->cell($row, 'telephone', 'tel'), 0, 30) ?: null,
                    'guardian_relation' => mb_substr($this->cell($row, 'lien'), 0, 30) ?: null,
                ]);
                $code ? $this->stats['parents_created']++ : $this->stats['parents_linked']++;
            }
        }
    }

    // ── Chercher ou créer ──────────────────────────────────────────────────

    /**
     * Enseignant : retrouvé par e-mail, puis téléphone, puis nom (prénom et nom dans n'importe quel ordre).
     * Retrouvé → on ajoute les établissements manquants ; absent → fiche créée avec le nom et les établissements.
     *
     * @param  array<int, int>  $campusIds
     */
    protected function findOrCreateTeacher(string $first, string $last, string $email = '', string $phone = '', array $campusIds = []): Teacher
    {
        $email = Str::lower($email);
        $phone = Phone::normalize($phone);
        $nameKey = self::nameKey($first.' '.$last);

        $teacher = ($email !== '' ? $this->teachers->first(fn (Teacher $t) => $t->email && Str::lower($t->email) === $email) : null)
            ?? ($phone ? $this->teachers->first(fn (Teacher $t) => Phone::normalize($t->phone) === $phone) : null)
            ?? $this->teachers->first(fn (Teacher $t) => self::nameKey($t->first_name.' '.$t->last_name) === $nameKey);

        if (! $teacher) {
            $teacher = Teacher::create([
                'first_name' => mb_substr($first, 0, 100),
                'last_name' => mb_substr($last, 0, 100),
                'email' => $email !== '' ? mb_substr($email, 0, 150) : null,
                'phone' => $phone ?: null,
                'status' => 'active',
            ]);
            $teacher->setRelation('campuses', collect());
            $this->teachers->push($teacher);
            $this->stats['teachers_created']++;
        }

        $missing = array_values(array_diff($campusIds, $teacher->campuses->pluck('id')->all()));

        if ($missing) {
            $teacher->campuses()->syncWithoutDetaching($missing);
            $teacher->setRelation('campuses', $teacher->campuses()->get());
            $teacher->wasRecentlyCreated || $this->stats['teachers_linked']++;
        }

        return $teacher;
    }

    /**
     * Nom saisi en une seule cellule (« Jean DUPONT », « DUPONT Jean »…) : les mots tout en majuscules forment le nom,
     * les autres le prénom ; à défaut, le dernier mot est le nom.
     *
     * @param  array<int, int>  $campusIds
     */
    protected function findOrCreateTeacherByFullName(string $fullName, array $campusIds): Teacher
    {
        [$first, $last] = self::splitFullName($fullName);

        return $this->findOrCreateTeacher($first, $last, campusIds: $campusIds);
    }

    protected function findOrCreateSubject(string $name, string $code = '', string $category = '', ?float $coefficient = null): Subject
    {
        $subject = $this->subjects->first(fn (Subject $s) => self::key($s->name) === self::key($name)
            || ($code !== '' && Str::lower($s->code) === Str::lower($code)));

        if ($subject) {
            return $subject;
        }

        $subject = Subject::create([
            'name' => mb_substr($name, 0, 150),
            'code' => $this->uniqueSubjectCode($code !== '' ? $code : $name),
            'category' => $category !== '' ? mb_substr($category, 0, 100) : null,
            'default_coefficient' => $coefficient ?? 1,
            'status' => 'active',
        ]);
        $subject->setRelation('teachers', collect());
        $this->subjects->push($subject);
        $this->stats['subjects_created']++;

        return $subject;
    }

    protected function linkSubjectTeacher(Subject $subject, Teacher $teacher): void
    {
        if (! $subject->teachers->contains('id', $teacher->id)) {
            $subject->teachers()->syncWithoutDetaching([$teacher->id]);
            $subject->setRelation('teachers', $subject->teachers()->get());
        }
    }

    protected function uniqueSubjectCode(string $source): string
    {
        $base = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($source)), 0, 6)) ?: 'MAT';
        $code = $base;
        $i = 2;

        while ($this->subjects->contains(fn (Subject $s) => Str::upper($s->code) === $code) || Subject::where('code', $code)->exists()) {
            $code = $base.$i++;
        }

        return $code;
    }

    protected function findClass(string $sheet, array $row, string $name, string $campusCell): ?ClassRoom
    {
        $matches = $this->classes->filter(fn (ClassRoom $c) => self::key($c->name) === self::key($name));

        if ($campusCell !== '') {
            $ids = $this->campusIds($sheet, $row, $campusCell, required: false);
            $matches = $matches->filter(fn (ClassRoom $c) => in_array($c->campus_id, $ids, true));
        }

        if ($matches->count() === 1) {
            return $matches->first();
        }

        $this->fail($sheet, $row, $matches->isEmpty()
            ? "Classe « {$name} » introuvable (ajoutez-la dans la feuille Classes)."
            : "Plusieurs classes « {$name} » : précisez l’établissement.");

        return null;
    }

    /**
     * Établissements d'une cellule (noms ou codes séparés par une virgule). Vide : l'unique établissement de l'école,
     * sinon une erreur si « required ». Un établissement inconnu est créé si « create » (feuilles Enseignants et Classes),
     * sinon c'est une erreur (simple recherche, ex. feuille Affectations).
     *
     * @return array<int, int>
     */
    protected function campusIds(string $sheet, array $row, string $cell, bool $required, bool $create = false): array
    {
        $names = $this->splitList($cell);

        if (! $names) {
            if ($this->campuses->count() === 1) {
                return [$this->campuses->first()->id];
            }

            $required && $this->fail($sheet, $row, 'Établissement obligatoire (l’école en compte plusieurs).');

            return [];
        }

        $ids = [];

        foreach ($names as $name) {
            $campus = $this->campuses->first(fn (Campus $c) => in_array(self::key($name), [self::key($c->name), self::key($c->code)], true));

            if (! $campus && $create) {
                $campus = $this->createCampus($sheet, $row, $name);
            } elseif (! $campus) {
                $this->fail($sheet, $row, "Établissement « {$name} » introuvable. Établissements de l’école : ".($this->campuses->pluck('name')->implode(', ') ?: 'aucun').'.');
            }

            $campus && $ids[] = $campus->id;
        }

        return array_values(array_unique($ids));
    }

    /** Établissement cité mais absent : créé avec son nom (adresse, ville… à compléter plus tard), dans la limite du plan. */
    protected function createCampus(string $sheet, array $row, string $name): ?Campus
    {
        try {
            $this->limits->ensureCanAdd('schools');
        } catch (ValidationException $e) {
            $this->fail($sheet, $row, "Établissement « {$name} » à créer : ".collect($e->errors())->flatten()->first());

            return null;
        }

        $campus = Campus::create([
            'name' => mb_substr($name, 0, 150),
            'code' => $this->uniqueCampusCode($name),
            'status' => 'active',
        ]);
        $this->campuses->push($campus);
        $this->stats['campuses_created']++;

        return $campus;
    }

    protected function uniqueCampusCode(string $name): string
    {
        $base = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', Str::ascii($name)), 0, 12)) ?: 'ECOLE';
        $code = $base;
        $i = 2;

        while (Campus::withTrashed()->where('code', $code)->exists()) {
            $code = $base.$i++;
        }

        return $code;
    }

    // ── Utilitaires ────────────────────────────────────────────────────────

    protected function coefficient(string $sheet, array $row, string $value): ?float
    {
        if ($value === '') {
            return null;
        }

        $number = str_replace(',', '.', $value);

        if (! is_numeric($number) || (float) $number <= 0 || (float) $number > 99) {
            $this->fail($sheet, $row, "Coefficient invalide : « {$value} ».");

            return null;
        }

        return (float) $number;
    }

    /** @return array<int, string> */
    protected function splitList(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\/]+/', $value) ?: []), fn ($v) => $v !== ''));
    }

    protected function cell(array $row, string ...$keys): string
    {
        return CellParser::pick($row, ...$keys);
    }

    protected function fail(string $sheet, array $row, string $message): void
    {
        $this->errors[] = ['line' => (int) ($row['_line'] ?? 0), 'message' => "Feuille « {$sheet} » : {$message}"];
    }

    protected static function key(?string $value): string
    {
        return Str::lower(Str::ascii(preg_replace('/\s+/', ' ', trim((string) $value))));
    }

    /** Mots du nom triés : « Jean DUPONT » et « Dupont jean » donnent la même clé. */
    protected static function nameKey(string $value): string
    {
        $words = array_filter(explode(' ', self::key(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value))));
        sort($words);

        return implode(' ', $words);
    }

    /** @return array{0: string, 1: string} [prénoms, nom] */
    protected static function splitFullName(string $fullName): array
    {
        $words = preg_split('/\s+/', trim($fullName)) ?: [];
        $upper = array_values(array_filter($words, fn ($w) => mb_strlen($w) > 1 && $w === mb_strtoupper($w) && $w !== mb_strtolower($w)));

        if ($upper && count($upper) < count($words)) {
            $first = array_values(array_filter($words, fn ($w) => ! in_array($w, $upper, true)));

            return [implode(' ', $first), implode(' ', $upper)];
        }

        if (count($words) === 1) {
            return [$words[0], $words[0]];
        }

        return [implode(' ', array_slice($words, 0, -1)), end($words)];
    }
}
