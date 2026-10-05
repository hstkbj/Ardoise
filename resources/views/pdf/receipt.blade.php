<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Reçu {{ $payment->reference }}</title>
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #15171C; margin: 24px; }
    h1 { font-size: 16px; margin: 0 0 4px; }
    .muted { color: #4F5560; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    td { padding: 6px 0; border-bottom: 1px solid #E4E4E0; }
    td:last-child { text-align: right; }
    .total { font-size: 18px; font-weight: bold; }
</style>
</head>
<body>
    <strong>{{ $school }}</strong><br>
    <span class="muted">{{ $contact['address'] ?? '' }} {{ $contact['phone'] ?? '' }}</span>
    <h1 style="margin-top:20px">Reçu de paiement n° {{ $payment->reference }}</h1>
    <span class="muted">Le {{ optional($payment->paid_at)->format('d/m/Y') }}</span>
    <table>
        <tr><td>Élève</td><td>{{ $line->student->full_name }} ({{ $line->student->matricule }})</td></tr>
        <tr><td>Classe</td><td>{{ $line->student->currentEnrollment?->classRoom?->name ?? '—' }}</td></tr>
        <tr><td>Motif</td><td>{{ $line->fee->name }}{{ $line->fee->installments > 1 ? ', échéance '.$line->installment_no : '' }}</td></tr>
        <tr><td>Mode de paiement</td><td>{{ $payment->method }}{{ $payment->transaction_ref ? ' · '.$payment->transaction_ref : '' }}</td></tr>
        <tr><td>Montant reçu</td><td class="total">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</td></tr>
        <tr><td>Reste à payer sur l'échéance</td><td>{{ number_format($line->remaining(), 0, ',', ' ') }} FCFA</td></tr>
    </table>
    <p class="muted" style="margin-top:24px">Reçu émis par {{ $payment->receiver?->name ?? 'la comptabilité' }}.</p>
</body>
</html>
