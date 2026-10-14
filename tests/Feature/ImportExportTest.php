<?php

namespace Tests\Feature;

use App\Models\Tenant\Assessment;
use App\Models\Tenant\ClassSubject;
use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Grade;
use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\Payment;
use App\Models\Tenant\Student;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\InteractsWithTenantSchool;
use Tests\TestCase;
use ZipArchive;

/** Import CSV / Excel des élèves, des notes et des paiements. */
class ImportExportTest extends TestCase
{
    use InteractsWithTenantSchool, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownSchool();
        parent::tearDown();
    }

    protected function csv(array $lines, string $name = 'import.csv'): UploadedFile
    {
        $content = "\xEF\xBB\xBF".implode("\n", array_map(fn ($cells) => implode(';', $cells), $lines))."\n";

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /** Petit classeur .xlsx (texte en chaînes partagées, nombres bruts). */
    protected function xlsx(array $lines): UploadedFile
    {
        $strings = [];
        $rows = '';

        foreach ($lines as $r => $cells) {
            $rows .= '<row r="'.($r + 1).'">';

            foreach (array_values($cells) as $c => $value) {
                $ref = chr(65 + $c).($r + 1);

                if (is_int($value) || is_float($value)) {
                    $rows .= '<c r="'.$ref.'"><v>'.$value.'</v></c>';
                } else {
                    $strings[] = htmlspecialchars((string) $value, ENT_XML1);
                    $rows .= '<c r="'.$ref.'" t="s"><v>'.(count($strings) - 1).'</v></c>';
                }
            }

            $rows .= '</row>';
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Élèves" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('', array_map(fn ($s) => '<si><t>'.$s.'</t></si>', $strings)).'</sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rows.'</sheetData></worksheet>');
        $zip->close();

        return new UploadedFile($path, 'eleves.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    public function test_students_are_imported_from_csv_with_their_parent(): void
    {
        $this->createSchool();
        $this->seedSchoolData();
        $file = fn () => $this->csv([
            ['Matricule', 'Nom', 'Prénoms', 'Sexe', 'Date de naissance', 'Classe', 'Parent - nom', 'Parent - téléphone'],
            ['', 'KONÉ', 'Aïcha', 'Fille', '14/03/2014', '5e B', 'Fatou Koné', '+22997111111'],
            ['T-0001', 'TRAORÉ', 'Awa Marie', 'F', '', '5e B', '', ''],
        ]);
        $token = $this->tokenFor('secretary');

        $this->withToken($token)->post('/api/v1/imports/students', ['file' => $file(), 'dry_run' => 1], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 1);
        $this->assertSame(1, $this->inSchool(fn () => Student::count()));

        $this->withToken($token)->post('/api/v1/imports/students', ['file' => $file()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.parents_created', 1);

        $this->inSchool(function () {
            $aicha = Student::where('last_name', 'KONÉ')->with('parents')->firstOrFail();
            $this->assertSame('F', $aicha->gender);
            $this->assertSame('2014-03-14', $aicha->birth_date->toDateString());
            $this->assertSame('5e B', $aicha->currentClass()->name);
            $this->assertSame('+22997111111', $aicha->parents->first()->phone);
            $this->assertSame('Awa Marie', Student::where('matricule', 'T-0001')->value('first_name'));
        });
    }

    public function test_an_excel_file_is_read_like_a_csv(): void
    {
        $this->createSchool();
        $this->seedSchoolData();

        $this->withToken($this->tokenFor('secretary'))->post('/api/v1/imports/students', ['file' => $this->xlsx([
            ['Nom', 'Prénoms', 'Date de naissance', 'Classe'],
            ['DIALLO', 'Moussa', 41275, '5e B'],
        ])], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.created', 1);

        $this->assertSame('2013-01-01', $this->inSchool(fn () => Student::where('last_name', 'DIALLO')->first()->birth_date->toDateString()));
    }

    public function test_a_file_with_errors_is_refused_with_line_numbers_and_nothing_is_saved(): void
    {
        $this->createSchool();
        $this->seedSchoolData();

        $this->withToken($this->tokenFor('secretary'))->post('/api/v1/imports/students', ['file' => $this->csv([
            ['Nom', 'Prénoms', 'Sexe', 'Classe'],
            ['OUATTARA', 'Ali', 'M', '5e B'],
            ['BAMBA', 'Inès', 'X', '6e Z'],
        ])], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('lines.0.line', 3)
            ->assertJsonPath('lines.0.message', 'Classe « 6e Z » introuvable dans l’année en cours.')
            ->assertJsonPath('lines.1.message', 'Sexe invalide (F ou M).');

        $this->assertSame(0, $this->inSchool(fn () => Student::where('last_name', 'OUATTARA')->count()));
        $this->assertSame(0, $this->inSchool(fn () => ParentProfile::where('last_name', 'BAMBA')->count()));
    }

    public function test_a_teacher_cannot_import_students(): void
    {
        $this->createSchool();
        $this->seedSchoolData();

        $this->withToken($this->tokenFor('teacher'))->post('/api/v1/imports/students', ['file' => $this->csv([['Nom', 'Prénoms', 'Classe'], ['A', 'B', '5e B']])], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    public function test_grades_are_imported_for_an_assessment_and_blank_lines_keep_existing_grades(): void
    {
        $this->createSchool();
        $ids = $this->seedSchoolData();
        $assessmentId = $this->inSchool(function () use ($ids) {
            $subject = Subject::create(['name' => 'Mathématiques', 'code' => 'MATH']);
            $classSubject = ClassSubject::create(['class_room_id' => $ids['class_id'], 'subject_id' => $subject->id, 'coefficient' => 2]);

            return Assessment::create([
                'class_subject_id' => $classSubject->id, 'term_id' => Term::value('id'), 'title' => 'Devoir 1', 'type' => 'devoir',
                'date' => now()->toDateString(), 'max_score' => 20, 'coefficient' => 1, 'status' => 'draft',
            ])->id;
        });
        $token = $this->tokenFor('academic_manager');

        $this->withToken($token)->get("/api/v1/assessments/{$assessmentId}/grades/template")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertStreamedContent("\xEF\xBB\xBFMatricule;Élève;Note;Absent;Commentaire\nT-0001;\"TRAORÉ Awa\";;;\n");

        $this->withToken($token)->post("/api/v1/assessments/{$assessmentId}/grades/import", ['file' => $this->csv([['Matricule', 'Note', 'Commentaire'], ['T-0001', '25', '']])], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('lines.0.message', 'Note « 25 » invalide : elle doit être comprise entre 0 et 20.');

        $this->withToken($token)->post("/api/v1/assessments/{$assessmentId}/grades/import", ['file' => $this->csv([['Matricule', 'Note', 'Commentaire'], ['t-0001', '14,5', 'Bien']])], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.saved', 1);

        $this->withToken($token)->post("/api/v1/assessments/{$assessmentId}/grades/import", ['file' => $this->csv([['Matricule', 'Note'], ['T-0001', '']])], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.skipped', 1);

        $this->assertSame(14.5, (float) $this->inSchool(fn () => Grade::where('assessment_id', $assessmentId)->value('score')));
    }

    public function test_payments_are_imported_against_the_remaining_balance(): void
    {
        $this->createSchool();
        $ids = $this->seedSchoolData(feeAmount: 100000);
        $token = $this->tokenFor('accountant');

        $this->withToken($token)->post('/api/v1/imports/payments', ['file' => $this->csv([
            ['Matricule', 'Frais', 'Montant', 'Mode', 'Date'],
            ['T-0001', 'Scolarité', '60 000', 'Orange Money', now()->format('d/m/Y')],
            ['T-0001', 'Scolarité', '50000', 'Espèces', ''],
        ])], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('lines.0.line', 3)
            ->assertJsonPath('lines.0.message', 'Montant supérieur au reste dû (40 000 FCFA).');

        $this->withToken($token)->post('/api/v1/imports/payments', ['file' => $this->csv([
            ['Matricule', 'Frais', 'Montant', 'Mode', 'Référence'],
            ['T-0001', 'scolarite', '60 000', 'Orange Money', 'OM-1'],
            ['T-0001', 'Scolarité', '40000', '', ''],
        ])], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.amount', 100000);

        $this->inSchool(function () use ($ids) {
            $this->assertSame('paid', FeeAssignment::find($ids['assignment_id'])->status);
            $this->assertSame(['Espèces', 'Mobile money'], Payment::orderBy('method')->pluck('method')->all());
        });
    }
}
