@php
    $fmt = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ');
    $totalCoef = collect($report['subjects'])->sum('coefficient');
    $totalPoints = collect($report['subjects'])->sum(fn ($s) => ($s['average'] ?? 0) * $s['coefficient']);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Bulletin — {{ $report['student']['full_name'] }} — {{ $report['term'] }}</title>
<style>
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #15171C; margin: 24px; }
    h1 { font-size: 15px; margin: 0; text-transform: uppercase; }
    .head { display: table; width: 100%; border-bottom: 2px solid #15171C; padding-bottom: 8px; }
    .head > div { display: table-cell; vertical-align: top; }
    .right { text-align: right; }
    .muted { color: #4F5560; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #D6D6D0; padding: 5px 6px; text-align: left; }
    th { background: #F1F1EE; font-size: 10px; }
    .num { text-align: right; }
    .low { color: #A12B2B; font-weight: bold; }
    .boxes { display: table; width: 100%; margin-top: 12px; border-spacing: 8px 0; }
    .box { display: table-cell; border: 1px solid #D6D6D0; padding: 8px; width: 33%; }
    .big { font-size: 18px; font-weight: bold; }
    .sign { display: table; width: 100%; margin-top: 36px; }
    .sign > div { display: table-cell; width: 50%; }
    .line { border-top: 1px solid #999; margin-top: 40px; width: 80%; }
    @media print { body { margin: 0; } }
</style>
</head>
<body>
<div class="head">
    <div><strong>{{ $report['school'] }}</strong><br><span class="muted">Année scolaire {{ $report['academic_year'] }}</span></div>
    <div class="right"><h1>Bulletin de notes</h1><span class="muted">{{ $report['term'] }}</span></div>
</div>

<p>
    <strong>{{ $report['student']['full_name'] }}</strong> · Matricule {{ $report['student']['matricule'] }}
    · Classe {{ $report['student']['class_name'] }} ({{ $report['class_size'] }} élèves)
    @if ($report['student']['birth_date']) · Né(e) le {{ \Illuminate\Support\Carbon::parse($report['student']['birth_date'])->format('d/m/Y') }} @endif
</p>

<table>
    <thead>
        <tr><th>Matière</th><th class="num">Coef.</th><th class="num">Moyenne</th><th class="num">Moy. × coef.</th><th class="num">Moy. classe</th><th>Appréciation</th></tr>
    </thead>
    <tbody>
    @foreach ($report['subjects'] as $s)
        <tr>
            <td>{{ $s['name'] }}<br><span class="muted">{{ $s['teacher'] }}</span></td>
            <td class="num">{{ rtrim(rtrim(number_format($s['coefficient'], 2, ',', ''), '0'), ',') }}</td>
            <td class="num {{ ($s['average'] ?? 20) < 10 ? 'low' : '' }}">{{ $fmt($s['average']) }}</td>
            <td class="num">{{ $s['average'] === null ? '—' : $fmt($s['average'] * $s['coefficient']) }}</td>
            <td class="num muted">{{ $fmt($s['class_average']) }}</td>
            <td>{{ $s['appreciation'] }}</td>
        </tr>
    @endforeach
        <tr><th>Total</th><th class="num">{{ rtrim(rtrim(number_format($totalCoef, 2, ',', ''), '0'), ',') }}</th><th></th><th class="num">{{ $fmt($totalPoints) }}</th><th colspan="2"></th></tr>
    </tbody>
</table>

<div class="boxes">
    <div class="box">Moyenne générale<br><span class="big">{{ $fmt($report['general_average']) }}</span> /20<br><span class="muted">Moyenne de la classe : {{ $fmt($report['class_average']) }}</span></div>
    <div class="box">Rang<br><span class="big">{{ $report['show_rank'] && $report['rank'] ? $report['rank'].($report['rank'] == 1 ? 'er' : 'e') : '—' }}</span> / {{ $report['class_size'] }}</div>
    <div class="box">Assiduité<br><strong>{{ (int) $report['absences'] }} séance(s) manquée(s)</strong><br><span class="muted">{{ $report['late'] }} retard(s)</span></div>
</div>

<div class="boxes">
    <div class="box" style="width:50%">Appréciation du professeur principal<br>{{ $report['head_teacher_comment'] }}</div>
    <div class="box" style="width:50%">Décision du conseil de classe<br><strong>{{ $report['council_decision'] ?? '—' }}</strong></div>
</div>

<div class="sign">
    <div>Le professeur principal<div class="line"></div></div>
    <div>Le chef d'établissement<div class="line"></div></div>
</div>
</body>
</html>
