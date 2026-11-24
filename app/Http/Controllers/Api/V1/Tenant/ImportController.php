<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\Campus;
use App\Models\Tenant\ClassRoom;
use App\Models\Tenant\ParentProfile;
use App\Models\Tenant\Student;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Teacher;
use App\Services\Imports\PaymentImport;
use App\Services\Imports\SchoolSetupImport;
use App\Services\Imports\StudentImport;
use App\Support\Spreadsheet\CsvResponse;
use App\Support\Spreadsheet\SpreadsheetReader;
use App\Support\Spreadsheet\XlsxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Imports CSV / Excel (élèves, paiements). Les notes s'importent depuis
 * l'écran de saisie de chaque évaluation (AssessmentController).
 *
 *   GET  /imports/{type}/template        modèle à remplir (CSV pour Excel)
 *   POST /imports/{type} { file, dry_run } vérification seule, ou enregistrement
 *
 * Mise en route de l'école (classeur Excel à plusieurs feuilles) :
 *   GET  /imports/setup/status           ce qui est déjà enregistré
 *   GET  /imports/setup/template         classeur modèle
 *   POST /imports/setup { file, dry_run } enseignants, matières, classes, affectations, élèves, parents
 */
class ImportController extends Controller
{
    public function template(string $type): StreamedResponse
    {
        $this->authorize($type === 'students' ? 'students.create' : 'payments.create');
        $import = $type === 'students' ? StudentImport::class : PaymentImport::class;

        return CsvResponse::download('modele-import-'.($type === 'students' ? 'eleves' : 'paiements').'.csv', $import::templateHeaders(), [$import::templateExample()]);
    }

    /** Ce que l'école a déjà enregistré : sert à la liste de départ et au guide de première connexion. */
    public function setupStatus(): JsonResponse
    {
        $this->authorizeSetup();

        $counts = [
            'schools' => Campus::where('status', 'active')->count(),
            'classes' => ClassRoom::count(),
            'subjects' => Subject::count(),
            'teachers' => Teacher::count(),
            'students' => Student::count(),
            'parents' => ParentProfile::count(),
        ];

        return response()->json(['data' => $counts + [
            'has_open_year' => AcademicYear::where('status', 'active')->exists(),
            'is_empty' => $counts['classes'] === 0 && $counts['students'] === 0 && $counts['teachers'] === 0,
        ]]);
    }

    public function setupTemplate(): StreamedResponse
    {
        $this->authorizeSetup();

        return XlsxWriter::download('modele-mise-en-route-ecole.xlsx', SchoolSetupImport::templateSheets());
    }

    public function setup(Request $request, SchoolSetupImport $import): JsonResponse
    {
        $this->authorizeSetup();
        $file = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,csv,txt'],
            'dry_run' => ['nullable', 'boolean'],
        ], ['file.mimes' => 'Envoyez le classeur Excel (.xlsx) du modèle.'])['file'];

        $result = $import->run(SpreadsheetReader::readSheets($file->getRealPath(), $file->getClientOriginalName()), $request->boolean('dry_run'));

        $campuses = $result['campuses_created'] ? "{$result['campuses_created']} établissement(s) créé(s), " : '';
        $teachers = $campuses.$result['teachers_created'].' enseignant(s) créé(s)'.($result['teachers_linked'] ? ", {$result['teachers_linked']} rattaché(s) à un nouvel établissement" : '');
        $summary = "{$teachers}, {$result['subjects_created']} matière(s), {$result['classes_created']} classe(s), {$result['assignments']} affectation(s), {$result['students_created']} élève(s) inscrit(s) et {$result['students_updated']} mis à jour, {$result['parents_created']} parent(s) créé(s).";

        return response()->json([
            'message' => ($result['dry_run'] ? 'Fichier valide, rien n’est enregistré : ' : 'Import terminé : ').$summary,
            'data' => $result,
        ]);
    }

    protected function authorizeSetup(): void
    {
        foreach (['classes.create', 'subjects.create', 'teachers.create', 'students.create'] as $ability) {
            $this->authorize($ability);
        }
    }

    public function students(Request $request, StudentImport $import): JsonResponse
    {
        $this->authorize('students.create');
        $result = $import->run($this->rows($request), $request->boolean('dry_run'));

        return response()->json([
            'message' => $result['dry_run']
                ? "Fichier valide : {$result['created']} élève(s) à inscrire, {$result['updated']} à mettre à jour."
                : "{$result['created']} élève(s) inscrit(s), {$result['updated']} mis à jour, {$result['parents_created']} parent(s) créé(s) avec leur code d’accès.",
            'data' => $result,
        ]);
    }

    public function payments(Request $request, PaymentImport $import): JsonResponse
    {
        $this->authorize('payments.create');
        $result = $import->run($this->rows($request), $request->boolean('dry_run'), $request->boolean('notify'));
        $amount = number_format($result['amount'], 0, ',', ' ');

        return response()->json([
            'message' => $result['dry_run']
                ? "Fichier valide : {$result['total']} paiement(s) pour {$amount} FCFA."
                : "{$result['total']} paiement(s) enregistré(s) pour {$amount} FCFA.",
            'data' => $result,
        ]);
    }

    /** @return array<int, array<string, string>> */
    public static function rows(Request $request): array
    {
        $file = $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt,xlsx'],
            'dry_run' => ['nullable', 'boolean'],
        ], ['file.mimes' => 'Envoyez un fichier Excel (.xlsx) ou CSV.'])['file'];

        return SpreadsheetReader::read($file->getRealPath(), $file->getClientOriginalName());
    }
}
