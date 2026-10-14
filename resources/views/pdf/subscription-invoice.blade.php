<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Facture {{ $number }}</title>
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #15171C; margin: 32px; }
    h1 { font-size: 20px; margin: 0; }
    .muted { color: #4F5560; }
    .grid { width: 100%; margin-top: 24px; }
    .grid td { vertical-align: top; width: 50%; }
    .box { border: 1px solid #E4E4E0; border-radius: 6px; padding: 12px; }
    table.lines { width: 100%; border-collapse: collapse; margin-top: 28px; }
    table.lines th { text-align: left; font-size: 11px; color: #4F5560; border-bottom: 2px solid #15171C; padding: 8px 0; }
    table.lines td { padding: 10px 0; border-bottom: 1px solid #E4E4E0; vertical-align: top; }
    .right, table.lines th.right, table.lines td.right { text-align: right; }
    table.totals { width: 45%; margin-left: 55%; margin-top: 16px; border-collapse: collapse; }
    table.totals td { padding: 5px 0; }
    .total { font-size: 16px; font-weight: bold; border-top: 2px solid #15171C; }
    .stamp { display: inline-block; margin-top: 24px; padding: 6px 12px; border: 2px solid #1F7A4D; color: #1F7A4D; font-weight: bold; border-radius: 4px; }
    .print { margin-top: 32px; }
    @media print { .print { display: none; } }
</style>
</head>
<body>
    <table class="grid" style="margin-top:0">
        <tr>
            <td>
                <strong style="font-size:15px">{{ $seller['name'] }}</strong><br>
                @if ($seller['address'])<span class="muted">{!! nl2br(e($seller['address'])) !!}</span><br>@endif
                @if ($seller['tax_id'])<span class="muted">IFU / NIF : {{ $seller['tax_id'] }}</span><br>@endif
                @if ($seller['registration'])<span class="muted">RCCM : {{ $seller['registration'] }}</span><br>@endif
                @if ($seller['email'] || $seller['phone'])<span class="muted">{{ collect([$seller['email'], $seller['phone']])->filter()->implode(' · ') }}</span>@endif
            </td>
            <td class="right">
                <h1>Facture</h1>
                <span class="muted">N° {{ $number }}</span><br>
                <span class="muted">Date : {{ $date }}</span>
            </td>
        </tr>
    </table>

    <table class="grid">
        <tr>
            <td></td>
            <td>
                <div class="box">
                    <span class="muted">Facturé à</span><br>
                    <strong>{{ $buyer['name'] }}</strong><br>
                    @if ($buyer['contact']){{ $buyer['contact'] }}<br>@endif
                    @if ($buyer['email'])<span class="muted">{{ $buyer['email'] }}</span><br>@endif
                    <span class="muted">Code établissement : {{ $buyer['code'] }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr><th>Désignation</th><th class="right">Montant</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    {{ $description }}<br>
                    @if ($period)<span class="muted">{{ $period }}</span><br>@endif
                    <span class="muted">Référence du paiement : {{ $payment->reference }}</span>
                </td>
                <td class="right">{{ number_format($net, 0, ',', ' ') }} {{ $currency }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        @if ($vat_rate > 0)
            <tr><td>Total hors taxes</td><td class="right">{{ number_format($net, 0, ',', ' ') }} {{ $currency }}</td></tr>
            <tr><td>TVA ({{ rtrim(rtrim(number_format($vat_rate, 2, ',', ''), '0'), ',') }} %)</td><td class="right">{{ number_format($vat, 0, ',', ' ') }} {{ $currency }}</td></tr>
        @endif
        <tr class="total"><td>Total {{ $vat_rate > 0 ? 'TTC' : '' }}</td><td class="right">{{ number_format($total, 0, ',', ' ') }} {{ $currency }}</td></tr>
    </table>

    <div class="stamp">ACQUITTÉE — {{ $payment->method ?? 'paiement' }} le {{ $date }}</div>

    @if ($footer)
        <p class="muted" style="margin-top:32px">{{ $footer }}</p>
    @endif

    @unless ($forPdf)
        <p class="print"><button onclick="window.print()">Imprimer / enregistrer en PDF</button></p>
    @endunless
</body>
</html>
