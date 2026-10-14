<?php

namespace App\Services\Imports;

use App\Models\Tenant\AcademicYear;
use App\Models\Tenant\ActivityLog;
use App\Models\Tenant\Fee;
use App\Models\Tenant\FeeAssignment;
use App\Models\Tenant\Student;
use App\Services\PaymentService;
use App\Support\Spreadsheet\CellParser;
use Illuminate\Support\Facades\DB;

/**
 * Import d'encaissements (reprise d'historique, relevé de banque ou de Mobile Money).
 * Chaque montant est réparti sur les échéances les plus anciennes du frais,
 * comme une saisie au guichet. Les parents ne sont notifiés que si demandé.
 */
class PaymentImport extends SpreadsheetImport
{
    public const METHODS = ['Espèces', 'Mobile money', 'Virement', 'Chèque', 'Carte'];

    public function __construct(protected PaymentService $payments) {}

    public static function templateHeaders(): array
    {
        return ['Matricule', 'Frais', 'Montant', 'Mode', 'Date', 'Référence', 'Note'];
    }

    public static function templateExample(): array
    {
        return ['PAL-26-0001', 'Scolarité', '50000', 'Mobile money', now()->format('d/m/Y'), 'MM-123456', ''];
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array{total: int, amount: int, dry_run: bool}
     */
    public function run(array $rows, bool $dryRun = false, bool $notify = false): array
    {
        $this->requireColumns($rows, 'matricule', 'frais', 'montant');

        $year = AcademicYear::current();
        $fees = Fee::where('academic_year_id', $year?->id)->get();
        $students = Student::whereIn('matricule', array_filter(array_column($rows, 'matricule')))->get()->keyBy(fn ($s) => self::key($s->matricule));
        $remaining = [];
        $clean = [];

        foreach ($rows as $row) {
            $matricule = $this->cell($row, 'matricule');
            $student = $students->get(self::key($matricule));
            $feeName = $this->cell($row, 'frais', 'motif');
            $fee = $fees->first(fn (Fee $f) => self::key($f->name) === self::key($feeName));
            $amount = CellParser::amount($this->cell($row, 'montant'));
            $paidAt = CellParser::date($this->cell($row, 'date', 'date_de_paiement'));
            $method = $this->method($this->cell($row, 'mode', 'mode_de_paiement', 'moyen'));

            if (! $student) {
                $this->error($row, $matricule === '' ? 'Matricule manquant.' : "Élève {$matricule} introuvable.");
            }

            if (! $fee) {
                $this->error($row, "Frais « {$feeName} » introuvable pour l’année en cours.");
            }

            if (! $amount) {
                $this->error($row, 'Montant invalide.');
            }

            if ($paidAt === false || ($paidAt && $paidAt > now()->toDateString())) {
                $this->error($row, 'Date invalide (format JJ/MM/AAAA, pas dans le futur).');
            }

            if ($method === null) {
                $this->error($row, 'Mode de paiement inconnu (Espèces, Mobile money, Virement, Chèque ou Carte).');
            }

            if (! $student || ! $fee || ! $amount) {
                continue;
            }

            // Reste dû, en tenant compte des lignes précédentes du fichier
            $key = $student->id.'-'.$fee->id;
            $remaining[$key] ??= (int) FeeAssignment::where('student_id', $student->id)->where('fee_id', $fee->id)->get()->sum(fn (FeeAssignment $a) => $a->remaining());

            if ($amount > $remaining[$key]) {
                $this->error($row, 'Montant supérieur au reste dû ('.number_format($remaining[$key], 0, ',', ' ').' FCFA).');

                continue;
            }

            $remaining[$key] -= $amount;
            $clean[] = [
                'student' => $student,
                'fee' => $fee,
                'amount' => $amount,
                'method' => $method,
                'paid_at' => $paidAt ?: null,
                'reference' => mb_substr($this->cell($row, 'reference', 'ref', 'transaction'), 0, 100) ?: null,
                'note' => mb_substr($this->cell($row, 'note', 'commentaire'), 0, 255) ?: 'Import',
            ];
        }

        $this->failIfErrors();

        $summary = ['total' => count($clean), 'amount' => array_sum(array_column($clean, 'amount')), 'dry_run' => $dryRun];

        if ($dryRun) {
            return $summary;
        }

        DB::connection('tenant')->transaction(function () use ($clean, $notify) {
            foreach ($clean as $item) {
                $this->payments->record($item['student'], $item['fee'], $item['amount'], $item['method'], $item['paid_at'], $item['reference'], $item['note'], notify: $notify);
            }
        });

        ActivityLog::record('import.payments', null, ['paiements' => $summary['total'], 'montant' => $summary['amount']]);

        return $summary;
    }

    protected function method(string $value): ?string
    {
        if ($value === '') {
            return 'Espèces';
        }

        foreach (self::METHODS as $method) {
            if (self::key($method) === self::key($value) || str_starts_with(self::key($value), self::key($method))) {
                return $method;
            }
        }

        return match (self::key($value)) {
            'cash', 'especes' => 'Espèces',
            'momo', 'mobile', 'orange money', 'mtn money', 'moov money', 'wave' => 'Mobile money',
            'cheque' => 'Chèque',
            'cb', 'carte bancaire' => 'Carte',
            default => null,
        };
    }
}
