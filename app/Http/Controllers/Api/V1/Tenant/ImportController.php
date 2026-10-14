<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Services\Imports\PaymentImport;
use App\Services\Imports\StudentImport;
use App\Support\Spreadsheet\CsvResponse;
use App\Support\Spreadsheet\SpreadsheetReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Imports CSV / Excel (élèves, paiements). Les notes s'importent depuis
 * l'écran de saisie de chaque évaluation (AssessmentController).
 *
 *   GET  /imports/{type}/template        modèle à remplir (CSV pour Excel)
 *   POST /imports/{type} { file, dry_run } vérification seule, ou enregistrement
 */
class ImportController extends Controller
{
    public function template(string $type): StreamedResponse
    {
        $this->authorize($type === 'students' ? 'students.create' : 'payments.create');
        $import = $type === 'students' ? StudentImport::class : PaymentImport::class;

        return CsvResponse::download('modele-import-'.($type === 'students' ? 'eleves' : 'paiements').'.csv', $import::templateHeaders(), [$import::templateExample()]);
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
