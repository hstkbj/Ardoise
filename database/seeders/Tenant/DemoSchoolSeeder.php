<?php

namespace Database\Seeders\Tenant;

use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\Announcement;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\Attendance;
use App\Models\Tenant\Campus;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\ClassSubject;
use App\Models\Tenant\Fee;
use App\Models\Tenant\Grade;
use App\Models\Tenant\Homework;
use App\Models\Tenant\Level;
use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\Student;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Teacher;
use App\Models\Tenant\TimetableEntry;
use App\Models\Tenant\User;
use App\Services\AccountService;
use App\Services\EnrollmentService;
use App\Services\FeeService;
use App\Services\ParentAccessService;
use App\Services\PaymentService;
use App\Services\ReportCardService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Données de démonstration (développement uniquement) : un groupe scolaire
 * ivoirien fictif avec 3 sites, 8 classes, enseignants, élèves, parents,
 * notes, absences, frais et paiements. Les codes parents sont affichés.
 */
class DemoSchoolSeeder extends Seeder
{
    /** @var array<int, array{0: string, 1: string}> codes parents créés (nom, code) */
    public static array $parentCodes = [];

    public function run(): void
    {
        mt_srand(2026);
        $year = AcademicYear::current();
        $term1 = $year->terms()->first();
        $accounts = app(AccountService::class);
        $enroll = app(EnrollmentService::class);

        $campuses = collect([
            ['Les Palmiers — Cocody', 'PAL-CCY', 'Rue des Jardins, II Plateaux', 'Abidjan', 'Mme Aminata Koné'],
            ['Les Palmiers — Riviera', 'PAL-RIV', 'Riviera 3, Allée B', 'Abidjan', 'M. Paul Yao'],
            ['Les Palmiers — Bingerville', 'PAL-BGV', 'Quartier Résidentiel', 'Bingerville', 'Mme Rose Ahoua'],
        ])->map(fn ($c) => Campus::create(['name' => $c[0], 'code' => $c[1], 'address' => $c[2], 'city' => $c[3], 'manager' => $c[4], 'phone' => '+225 27 22 41 00 10', 'status' => 'active']));

        $subjects = collect([
            ['Mathématiques', 'MATH', 3, 'Sciences'], ['Français', 'FRA', 3, 'Lettres'], ['Anglais', 'ANG', 2, 'Langues'],
            ['SVT', 'SVT', 2, 'Sciences'], ['Histoire-Géographie', 'HG', 2, 'Sciences humaines'], ['Physique-Chimie', 'PC', 2, 'Sciences'], ['EPS', 'EPS', 1, 'Sport'],
        ])->mapWithKeys(fn ($s) => [$s[1] => Subject::create(['name' => $s[0], 'code' => $s[1], 'default_coefficient' => $s[2], 'category' => $s[3], 'status' => 'active'])]);

        $teachers = collect([
            ['Mamadou', 'Diallo', 'm.diallo@lespalmiers.ci', ['MATH'], 'permanent'],
            ['Awa', 'Touré', 'a.toure@lespalmiers.ci', ['FRA'], 'permanent'],
            ['Eric', 'Kouadio', 'e.kouadio@lespalmiers.ci', ['SVT', 'PC'], 'permanent'],
            ['Linda', 'Assi', 'l.assi@lespalmiers.ci', ['ANG'], 'vacataire'],
            ['Paul', 'Gbagbo', 'p.gbagbo@lespalmiers.ci', ['HG'], 'permanent'],
            ['Serge', 'Koffi', 's.koffi@lespalmiers.ci', ['EPS'], 'vacataire'],
        ])->mapWithKeys(function ($t, $i) use ($subjects, $campuses, $accounts) {
            $teacher = Teacher::create(['first_name' => $t[0], 'last_name' => $t[1], 'email' => $t[2], 'phone' => '+225 07 22 31 40 '.(50 + $i), 'contract' => $t[4], 'hired_on' => '2020-09-01', 'status' => 'active']);
            $teacher->subjects()->sync($subjects->only($t[3])->pluck('id'));
            $teacher->campuses()->sync($campuses->pluck('id'));
            $user = $accounts->ensureTeacherAccount($teacher);
            $user->update(['password' => 'password', 'status' => 'active']);

            return [$t[3][0] => $teacher];
        });

        foreach ($subjects as $code => $subject) {
            $subject->update(['teacher_id' => ($teachers[$code] ?? $teachers['SVT'])->id]);
        }

        $level = fn ($name) => Level::where('name', $name)->value('id');
        $classes = collect([
            ['6e C', '6e', 0, 45, 'B12', 'MATH'], ['5e B', '5e', 0, 40, 'B04', 'SVT'], ['4e B', '4e', 0, 40, 'A07', 'MATH'], ['3e A', '3e', 0, 40, 'A02', 'FRA'],
            ['Tle D', 'Tle', 1, 40, 'C01', 'ANG'], ['1re C', '1re', 1, 40, 'C03', 'HG'], ['CM2 A', 'CM2', 1, 35, 'P05', 'FRA'], ['CE2 A', 'CE2', 2, 35, 'P02', 'FRA'],
        ])->mapWithKeys(function ($c) use ($level, $campuses, $year, $teachers, $subjects) {
            $class = ClassRoom::create([
                'name' => $c[0], 'level_id' => $level($c[1]), 'campus_id' => $campuses[$c[2]]->id, 'academic_year_id' => $year->id,
                'head_teacher_id' => $teachers[$c[5]]->id, 'capacity' => $c[3], 'room' => $c[4], 'status' => 'active',
            ]);

            foreach ($subjects as $code => $subject) {
                ClassSubject::create(['class_room_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => ($teachers[$code] ?? $teachers['SVT'])->id, 'coefficient' => $subject->default_coefficient]);
            }

            return [$c[0] => $class];
        });

        // Frais (avant les inscriptions : les échéances sont générées à l'inscription)
        $start = Carbon::parse($year->starts_on);
        $college = ['6e', '5e', '4e', '3e'];
        $lycee = ['2nde', '1re', 'Tle'];
        $primaire = ['CP', 'CE1', 'CE2', 'CM1', 'CM2'];
        $fees = [
            ['Scolarité Collège', 'scolarite', 450000, 3, $college],
            ['Scolarité Lycée', 'scolarite', 600000, 3, $lycee],
            ['Scolarité Primaire', 'scolarite', 360000, 3, $primaire],
            ["Frais d'inscription", 'inscription', 75000, 1, []],
        ];

        foreach ($fees as [$name, $category, $amount, $installments, $levels]) {
            $fee = Fee::create(['academic_year_id' => $year->id, 'name' => $name, 'category' => $category, 'amount' => $amount, 'installments' => $installments, 'first_due_date' => $start->copy()->addDays(14)->toDateString(), 'status' => 'active']);
            $fee->levels()->sync(Level::whereIn('name', $levels)->pluck('id'));
        }

        // Élèves et parents
        $lastNames = ['BAMBA', 'BROU', 'CISSÉ', 'COULIBALY', 'DIABATÉ', 'EHUI', 'FOFANA', 'GNAGNE', 'KONÉ', "N'GUESSAN", 'KOUAMÉ', 'OUATTARA', 'AKÉ', 'SANOGO', 'MENSAH', 'YAO', 'DOSSO', 'KOFFI', 'TOURÉ', 'ASSI', 'KONAN', 'AHOUA', 'DIOMANDÉ', 'SORO'];
        $girls = ['Adjoua', 'Fatou', 'Grâce', 'Mariam', 'Aïcha', 'Nadia', 'Salimata', 'Emmanuella', 'Prisca', 'Awa', 'Ruth', 'Esther'];
        $boys = ['Kouassi', 'Ibrahim', 'Yao', 'Serge', 'Junior', 'Jean-Marc', 'Ismaël', 'Kofi', 'Moussa', 'Ange', 'Didier', 'Hervé'];

        $makeParent = function (string $first, string $last, string $phone, string $profession) use ($accounts) {
            [$parent, $code] = $accounts->createParent(['first_name' => $first, 'last_name' => $last, 'phone' => $phone, 'profession' => $profession, 'address' => 'Abidjan']);
            self::$parentCodes[] = [$parent->full_name, $code];

            return $parent;
        };

        $n = 0;

        foreach ($classes as $className => $class) {
            $count = in_array($className, ['4e B', '5e B'], true) ? 14 : 8;

            for ($i = 0; $i < $count; $i++) {
                $n++;
                $girl = mt_rand(0, 1) === 1;
                $last = $lastNames[$n % count($lastNames)];
                $student = Student::create([
                    'matricule' => 'PAL-'.now()->format('y').'-'.str_pad((string) (400 + $n), 4, '0', STR_PAD_LEFT),
                    'first_name' => $girl ? $girls[$n % count($girls)] : $boys[$n % count($boys)],
                    'last_name' => $last,
                    'gender' => $girl ? 'F' : 'M',
                    'birth_date' => Carbon::create(2026 - (int) Level::find($class->level_id)->position - 5, mt_rand(1, 12), mt_rand(1, 28))->toDateString(),
                    'birth_place' => 'Abidjan',
                    'status' => 'active',
                ]);
                $enroll->enroll($student, $class, $year->starts_on->toDateString());

                $parent = $makeParent(mt_rand(0, 1) ? 'Mme' : 'M.', ucfirst(mb_strtolower($last)), '+225 07 '.str_pad((string) (10 + $n), 2, '0', STR_PAD_LEFT).' '.mt_rand(10, 99).' '.mt_rand(10, 99).' '.mt_rand(10, 99), 'Commerçant(e)');
                $student->parents()->attach($parent->id, ['relation' => 'Mère', 'is_primary' => true]);
            }
        }

        // La famille Traoré (deux enfants, utilisée dans les écrans de démonstration)
        $mariam = $makeParent('Mariam', 'Traoré', '+225 07 48 21 33 90', 'Pharmacienne');
        foreach ([['Awa', 'F', '5e B', '2013-04-12'], ['Kofi', 'M', 'CM2 A', '2015-02-03']] as [$first, $gender, $className, $birth]) {
            $child = Student::create(['matricule' => 'PAL-'.now()->format('y').'-0'.(500 + ++$n), 'first_name' => $first, 'last_name' => 'TRAORÉ', 'gender' => $gender, 'birth_date' => $birth, 'birth_place' => 'Abidjan', 'status' => 'active']);
            $enroll->enroll($child, $classes[$className], $year->starts_on->toDateString());
            $child->parents()->attach($mariam->id, ['relation' => 'Mère', 'is_primary' => true]);
        }

        // Évaluations validées + notes
        $evalDate = $start->copy()->addWeeks(3);
        foreach ($classes as $class) {
            foreach (ClassSubject::where('class_room_id', $class->id)->get() as $k => $cs) {
                foreach ([['Interrogation n°1', 'interrogation', 1, 10, 0], ['Devoir n°1', 'devoir', 2, 20, 7]] as [$title, $type, $coef, $max, $offset]) {
                    $assessment = Assessment::create([
                        'class_subject_id' => $cs->id, 'term_id' => $term1->id, 'teacher_id' => $cs->teacher_id, 'title' => $title, 'type' => $type,
                        'date' => $evalDate->copy()->addDays($offset + $k)->toDateString(), 'coefficient' => $coef, 'max_score' => $max,
                        'status' => 'validated', 'validated_at' => now(),
                    ]);

                    foreach ($class->students()->get() as $student) {
                        $absent = mt_rand(1, 30) === 1;
                        $base = 8 + ($student->id % 9) + mt_rand(-3, 3);
                        Grade::create(['assessment_id' => $assessment->id, 'student_id' => $student->id, 'is_absent' => $absent, 'score' => $absent ? null : round(max(0, min(20, $base)) * $max / 20 * 2) / 2]);
                    }
                }
            }
        }

        // Une évaluation en cours de saisie (4e B, mathématiques)
        $math4b = ClassSubject::where('class_room_id', $classes['4e B']->id)->where('subject_id', $subjects['MATH']->id)->first();
        $draft = Assessment::create(['class_subject_id' => $math4b->id, 'term_id' => $term1->id, 'teacher_id' => $math4b->teacher_id, 'title' => 'Composition n°1', 'type' => 'composition', 'date' => now()->subDays(2)->toDateString(), 'coefficient' => 3, 'max_score' => 20, 'status' => 'draft']);
        foreach ($classes['4e B']->students()->get()->take(9) as $student) {
            Grade::create(['assessment_id' => $draft->id, 'student_id' => $student->id, 'score' => mt_rand(8, 18), 'is_absent' => false]);
        }

        // Absences des derniers jours
        foreach ($classes as $class) {
            foreach ($class->students()->get()->random(min(2, $class->students()->count())) as $student) {
                Attendance::create(['student_id' => $student->id, 'class_room_id' => $class->id, 'date' => now()->subDays(mt_rand(0, 6))->toDateString(), 'slot' => '07:30', 'status' => mt_rand(0, 2) ? 'absent' : 'late', 'minutes_late' => 10, 'is_justified' => (bool) mt_rand(0, 1)]);
            }
        }
        $awa = Student::where('first_name', 'Awa')->where('last_name', 'TRAORÉ')->first();
        Attendance::updateOrCreate(['student_id' => $awa->id, 'date' => now()->subDays(3)->toDateString(), 'slot' => '08:00'], ['class_room_id' => $classes['5e B']->id, 'status' => 'absent', 'is_justified' => false]);

        // Emploi du temps (4e B et 5e B)
        $plan = [[0, '07:30', '09:30', 'MATH'], [0, '09:30', '10:30', 'ANG'], [0, '10:45', '12:45', 'FRA'], [1, '07:30', '09:30', 'SVT'], [1, '10:45', '11:45', 'HG'], [1, '14:00', '16:00', 'EPS'],
            [2, '07:30', '09:30', 'FRA'], [2, '09:30', '11:45', 'PC'], [3, '07:30', '09:30', 'MATH'], [3, '10:45', '12:45', 'ANG'], [4, '07:30', '08:30', 'MATH'], [4, '08:30', '10:30', 'FRA']];
        foreach (['4e B' => 0, '5e B' => 2] as $className => $shift) {
            foreach ($plan as [$day, $from, $to, $code]) {
                TimetableEntry::create(['class_room_id' => $classes[$className]->id, 'subject_id' => $subjects[$code]->id, 'teacher_id' => ($teachers[$code] ?? $teachers['SVT'])->id, 'day_of_week' => ($day + $shift) % 5, 'starts_at' => $from, 'ends_at' => $to, 'room' => $classes[$className]->room]);
            }
        }

        // Devoirs
        foreach ([['5e B', 'ANG', 'Exercices 3 à 6, page 34', 1], ['5e B', 'HG', 'Lire le chapitre 2', 3], ['4e B', 'MATH', 'Problèmes sur les fractions', 4], ['CM2 A', 'MATH', 'Tables de multiplication de 7 à 9', 1]] as [$className, $code, $title, $days]) {
            Homework::create(['class_room_id' => $classes[$className]->id, 'subject_id' => $subjects[$code]->id, 'teacher_id' => ($teachers[$code] ?? $teachers['SVT'])->id, 'title' => $title, 'due_date' => now()->addDays($days)->toDateString(), 'status' => 'published', 'published_at' => now()]);
        }

        // Annonces
        $admin = User::whereHas('roles', fn ($q) => $q->where('key', 'school_admin'))->first();
        Announcement::create(['author_id' => $admin?->id, 'title' => 'Réunion parents-professeurs', 'body' => 'Samedi à 9 h, dans la salle polyvalente du site de Cocody.', 'audiences' => ['parents'], 'channels' => ['in_app'], 'status' => 'published', 'published_at' => now()->subDays(2)]);
        Announcement::create(['author_id' => $admin?->id, 'title' => 'Calendrier des compositions du 1er trimestre', 'body' => 'Les compositions débuteront dans trois semaines pour le collège et le lycée.', 'audiences' => ['parents', 'students'], 'channels' => ['in_app'], 'status' => 'published', 'published_at' => now()->subDays(4)]);

        // Paiements : la plupart des familles ont réglé l'inscription et la 1re échéance
        $payments = app(PaymentService::class);
        foreach (Student::with('feeAssignments.fee')->get() as $student) {
            foreach ($student->feeAssignments->groupBy('fee_id') as $assignments) {
                $fee = $assignments->first()->fee;
                $roll = mt_rand(1, 10);
                $amount = $fee->category === 'inscription' ? ($roll > 1 ? $fee->amount : 0) : ($roll > 3 ? $assignments->first()->amount : ($roll > 1 ? intdiv($assignments->first()->amount, 2) : 0));

                if ($amount > 0) {
                    $payments->record($student, $fee, $amount, ['Mobile money', 'Espèces', 'Virement'][mt_rand(0, 2)], $start->copy()->addDays(mt_rand(5, 30))->toDateString());
                }
            }
        }

        // Bulletins du 1er trimestre générés et publiés pour la 5e B
        $cards = app(ReportCardService::class);
        $cards->generate($classes['5e B'], $term1);
        $cards->publish($classes['5e B']->students()->pluck('students.id')->all(), $term1);
    }
}
