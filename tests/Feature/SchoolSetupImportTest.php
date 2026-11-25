<?php

namespace Tests\Feature;

use App\Models\Tenant\Campus;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\ClassSubject;
use App\Models\Tenant\Level;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Teacher;
use App\Models\Tenant\TimetableEntry;
use App\Services\Imports\SchoolSetupImport;
use App\Support\Spreadsheet\XlsxWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;

/** Mise en route de l'école : un classeur Excel à plusieurs feuilles, « chercher ou créer ». */
class SchoolSetupImportTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    /** @param  array<string, array<int, array<int, string>>>  $sheets */
    protected function workbook(array $sheets): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'setup');
        file_put_contents($path, XlsxWriter::build($sheets));

        return new UploadedFile($path, 'mise-en-route.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /** @return array<string, array<int, array<int, string>>> */
    protected function sheets(string $level): array
    {
        return [
            'Enseignants' => [['Nom', 'Prénoms', 'E-mail', 'Téléphone', 'Établissement', 'Matières'], ['NOUVEAU', 'Paul', '', '', '', 'Anglais']],
            'Classes' => [['Classe', 'Niveau', 'Établissement', 'Capacité', 'Salle', 'Professeur principal'], ['Classe Test', $level, '', '30', '', 'Paul NOUVEAU']],
            'Affectations' => [['Classe', 'Établissement', 'Matière', 'Enseignant', 'Coefficient'], ['Classe Test', '', 'Anglais', 'Paul NOUVEAU', '2']],
        ];
    }

    public function test_workbook_creates_missing_teacher_subject_class_and_links_them(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $level = $this->inSchool(fn () => Level::where('name', '5e')->value('name'));

        $this->withToken($this->tokenFor())->post('/api/v1/imports/setup', ['file' => $this->workbook($this->sheets($level))], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.teachers_created', 1)
            ->assertJsonPath('data.classes_created', 1)
            ->assertJsonPath('data.assignments', 1);

        $this->inSchool(function () {
            $teacher = Teacher::where('last_name', 'NOUVEAU')->firstOrFail();
            $class = ClassRoom::where('name', 'Classe Test')->firstOrFail();
            $subject = Subject::where('name', 'Anglais')->firstOrFail();

            // Le professeur principal cité dans la classe est l'enseignant déjà créé : aucun doublon
            $this->assertSame(1, Teacher::where('last_name', 'NOUVEAU')->count());
            $this->assertSame($teacher->id, $class->head_teacher_id);
            $this->assertSame(30, $class->capacity);
            $this->assertTrue($teacher->campuses->contains('id', $class->campus_id));
            $this->assertTrue($subject->teachers->contains('id', $teacher->id));
            $this->assertSame(2.0, (float) ClassSubject::where('class_room_id', $class->id)->where('subject_id', $subject->id)->value('coefficient'));
        });
    }

    public function test_unknown_campuses_are_created_and_the_teacher_is_linked_to_them(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $level = $this->inSchool(fn () => Level::where('name', '5e')->value('name'));
        $sheets = $this->sheets($level);
        $sheets['Enseignants'][1][4] = 'Site Calavi';
        $sheets['Classes'][1][2] = 'Site Calavi';
        $sheets['Affectations'][1][1] = 'Site Calavi';

        $this->withToken($this->tokenFor())->post('/api/v1/imports/setup', ['file' => $this->workbook($sheets)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.campuses_created', 1);

        $this->inSchool(function () {
            $campus = Campus::where('name', 'Site Calavi')->firstOrFail();
            $this->assertSame('SITECALAVI', $campus->code);
            $this->assertSame($campus->id, ClassRoom::where('name', 'Classe Test')->value('campus_id'));
            $this->assertTrue(Teacher::where('last_name', 'NOUVEAU')->firstOrFail()->campuses->contains('id', $campus->id));
        });
    }

    public function test_importing_the_same_workbook_twice_creates_no_duplicates(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $level = $this->inSchool(fn () => Level::where('name', '5e')->value('name'));
        $token = $this->tokenFor();

        foreach ([1, 2] as $_) {
            $this->withToken($token)->post('/api/v1/imports/setup', ['file' => $this->workbook($this->sheets($level))], ['Accept' => 'application/json'])->assertOk();
        }

        $this->inSchool(function () {
            $this->assertSame(1, Teacher::where('last_name', 'NOUVEAU')->count());
            $this->assertSame(1, Subject::where('name', 'Anglais')->count());
            $this->assertSame(1, ClassRoom::where('name', 'Classe Test')->count());
            $this->assertSame(1, ClassSubject::where('class_room_id', ClassRoom::where('name', 'Classe Test')->value('id'))->count());
        });
    }

    public function test_dry_run_saves_nothing(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $level = $this->inSchool(fn () => Level::where('name', '5e')->value('name'));

        $this->withToken($this->tokenFor())->post('/api/v1/imports/setup', ['file' => $this->workbook($this->sheets($level)), 'dry_run' => 1], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.teachers_created', 1);

        $this->inSchool(function () {
            $this->assertSame(0, Teacher::where('last_name', 'NOUVEAU')->count());
            $this->assertSame(0, ClassRoom::where('name', 'Classe Test')->count());
        });
    }

    public function test_an_error_names_the_sheet_and_the_line_and_saves_nothing(): void
    {
        $this->createSchool();
        $this->seedSchoolData();

        $this->withToken($this->tokenFor())->post('/api/v1/imports/setup', ['file' => $this->workbook($this->sheets('Niveau inconnu'))], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('lines.0.line', 2);

        $this->inSchool(function () {
            $this->assertSame(0, Teacher::where('last_name', 'NOUVEAU')->count());
            $this->assertSame(0, Subject::where('name', 'Anglais')->count());
        });
    }

    public function test_a_csv_file_is_refused_because_it_cannot_hold_several_sheets(): void
    {
        $this->createSchool();
        $this->seedSchoolData();

        $this->withToken($this->tokenFor())->post('/api/v1/imports/setup', ['file' => UploadedFile::fake()->createWithContent('x.csv', "Nom\nA\n")], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_template_and_status_are_available_to_the_school_admin(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $token = $this->tokenFor();

        $this->withToken($token)->get('/api/v1/imports/setup/template')->assertOk();
        $this->withToken($token)->getJson('/api/v1/imports/setup/status')->assertOk()->assertJsonPath('data.is_empty', false);
    }

    public function test_a_teacher_cannot_run_the_setup_import(): void
    {
        $this->createSchool();
        $this->seedSchoolData();

        $this->withToken($this->tokenFor('teacher'))->getJson('/api/v1/imports/setup/status')->assertForbidden();
    }

    public function test_subject_cycle_and_referent_teacher_are_imported_and_copied_to_new_classes(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $level = $this->inSchool(fn () => Level::where('name', '5e')->value('name'));
        $sheets = $this->sheets($level);
        $sheets['Matières'] = [['Matière', 'Code', 'Cycle', 'Catégorie', 'Coefficient', 'Enseignant référent'], ['Latin', 'LAT', 'Collège', 'Lettres', '2', 'Paul NOUVEAU']];

        $this->withToken($this->tokenFor())->post('/api/v1/imports/setup', ['file' => $this->workbook($sheets)], ['Accept' => 'application/json'])->assertOk();

        $this->inSchool(function () {
            $subject = Subject::where('name', 'Latin')->firstOrFail();
            $teacher = Teacher::where('last_name', 'NOUVEAU')->firstOrFail();

            $this->assertSame('Collège', $subject->level);
            $this->assertSame($teacher->id, $subject->teacher_id);
            $this->assertTrue($subject->teachers->contains('id', $teacher->id));

            // La classe créée reçoit la matière du cycle avec son référent
            $class = ClassRoom::where('name', 'Classe Test')->firstOrFail();
            $this->assertSame($teacher->id, ClassSubject::where('class_room_id', $class->id)->where('subject_id', $subject->id)->value('teacher_id'));
        });
    }

    public function test_an_unknown_cycle_is_refused(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $sheets = ['Matières' => [['Matière', 'Cycle'], ['Latin', 'Université']]];

        $this->withToken($this->tokenFor())->post('/api/v1/imports/setup', ['file' => $this->workbook($sheets)], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->inSchool(fn () => $this->assertFalse(Subject::where('name', 'Latin')->exists()));
    }

    public function test_the_timetable_sheet_creates_slots_and_refuses_overlaps(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $level = $this->inSchool(fn () => Level::where('name', '5e')->value('name'));
        $sheets = $this->sheets($level);
        $head = ['Classe', 'Établissement', 'Jour', 'Début', 'Fin', 'Matière', 'Enseignant', 'Salle'];
        $sheets['Emploi du temps'] = [$head, ['Classe Test', '', 'Lundi', '8h', '09:00', 'Anglais', 'Paul NOUVEAU', 'S1'], ['Classe Test', '', 'mardi', '08:00', '09:30', 'Anglais', '', '']];

        $this->withToken($this->tokenFor())->post('/api/v1/imports/setup', ['file' => $this->workbook($sheets)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.timetable_entries', 2);

        $this->inSchool(function () {
            $teacher = Teacher::where('last_name', 'NOUVEAU')->firstOrFail();
            $monday = TimetableEntry::where('day_of_week', 0)->firstOrFail();
            $this->assertSame('08:00', $monday->starts_at);
            $this->assertSame($teacher->id, $monday->teacher_id);
            // Sans enseignant dans la ligne : celui de la matière dans la classe
            $this->assertSame($teacher->id, TimetableEntry::where('day_of_week', 1)->value('teacher_id'));
        });

        // Un second cours qui chevauche le premier est refusé, rien n'est enregistré
        $sheets['Emploi du temps'] = [$head, ['Classe Test', '', 'Lundi', '08:30', '09:30', 'Anglais', '', '']];
        $this->withToken($this->tokenFor())->post('/api/v1/imports/setup', ['file' => $this->workbook($sheets)], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_the_template_has_dropdowns_for_cycle_and_days(): void
    {
        $xlsx = XlsxWriter::build(SchoolSetupImport::templateSheets(), SchoolSetupImport::templateLists(['6e'], ['Collège Akoma']));
        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $xlsx);
        $zip = new \ZipArchive;
        $zip->open($path);
        $names = array_keys(SchoolSetupImport::templateSheets());
        $subjects = $zip->getFromName('xl/worksheets/sheet'.(array_search('Matières', $names) + 1).'.xml');
        $timetable = $zip->getFromName('xl/worksheets/sheet'.(array_search('Emploi du temps', $names) + 1).'.xml');
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('<formula1>"Primaire,Collège,Lycée"</formula1>', $subjects);
        $this->assertStringContainsString('Lundi,Mardi,Mercredi,Jeudi,Vendredi,Samedi', $timetable);
    }
}
