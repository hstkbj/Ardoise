@php
    $fmt = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ');
    $totalCoef = collect($report['subjects'])->sum('coefficient');
    $totalPoints = collect($report['subjects'])->sum(fn ($s) => ($s['average'] ?? 0) * $s['coefficient']);
    // En-tête réglé par l'école : App\Support\ReportCardHeader
    $h = $header ?? \App\Support\ReportCardHeader::for(null, $report['school']);
    $preview = $h['preview'] ?? false;
    $sideLogo = $h['logo'] && $h['logo_position'] === 'left';
    $hasSides = $sideLogo || $h['second_logo'];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>Bulletin — {{ $report['student']['full_name'] }} — {{ $report['term'] }}</title>
<style>
    @page { margin: 12mm 12mm 10mm; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10.5px; color: #15171C; margin: 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 12px; }
    th, td { border: 1px solid #D6D6D0; padding: 3px 6px; text-align: left; }
    th { background: #F1F1EE; font-size: 10px; }
    .num { text-align: right; }
    .low { color: #A12B2B; font-weight: bold; }
    .boxes { display: table; width: 100%; margin-top: 12px; border-spacing: 8px 0; }
    .box { display: table-cell; border: 1px solid #D6D6D0; padding: 8px; width: 33%; }
    .big { font-size: 18px; font-weight: bold; }
    .line { border-top: 1px solid #999; margin-top: 44px; width: 80%; }

    /* En-tête réglé par l'école (Paramètres › Bulletins) */
    table.hdr, table.hdr td, table.who, table.who td, table.who-grid td, table.sign, table.sign td { border: 0; padding: 0; margin: 0; }
    table.hdr { margin: 0; }
    .hdr-side { width: 80px; vertical-align: middle; }
    .hdr-side-right { text-align: right; }
    .hdr-logo { max-width: 72px; max-height: 72px; }
    .hdr-logo-center { display: block; margin: 3px auto; max-height: 54px; }
    .hdr-mid { text-align: center; vertical-align: middle; padding: 0 8px; }
    .official { font-size: 9px; text-transform: uppercase; letter-spacing: 0.3px; color: #4F5560; }
    .official-first { font-weight: bold; color: #15171C; }
    .official-rule { width: 60px; margin: 4px auto 6px; border-top: 1px solid {{ $h['color'] }}; }
    .school { font-size: 15px; font-weight: bold; text-transform: uppercase; color: {{ $h['color'] }}; margin-top: 2px; }
    .detail { font-size: 9.5px; color: #4F5560; }
    .band { margin-top: 8px; padding: 6px 10px; background: {{ $h['color'] }}; color: #FFFFFF; }
    .band-title { font-size: 13px; font-weight: bold; text-transform: uppercase; }
    .band-term { float: right; font-size: 10px; padding-top: 2px; }
    table.who { margin-top: 8px; }
    table.who > tbody > tr > td, table.who > tr > td { vertical-align: top; }
    .who-name { font-size: 13px; font-weight: bold; margin-bottom: 4px; }
    table.who-grid { margin-top: 0; width: auto; }
    table.who-grid td { padding: 1px 14px 1px 0; }
    .photo-cell { width: 72px; text-align: right; }
    .photo { width: 66px; height: 82px; border: 1px solid #D6D6D0; }
    .photo-empty { display: inline-block; background: #F1F1EE; color: #8A8F98; font-size: 8px; text-align: center; height: 46px; padding-top: 36px; }
    table.sign { margin-top: 16px; page-break-inside: avoid; }
    table.sign td { width: 50%; vertical-align: top; }
    .sign-right { text-align: center; }
    .sign-title { font-weight: bold; }
    .sign-images { height: 62px; margin-top: 6px; }
    .signature { max-height: 60px; max-width: 150px; vertical-align: middle; }
    .stamp { max-height: 62px; max-width: 90px; vertical-align: middle; margin-left: -20px; opacity: 0.9; }
    .sign-right .line { margin: 44px auto 0; }
    .signatory { margin-top: 2px; }
    .preview-note { margin-top: 18px; font-size: 9px; color: #8A8F98; text-align: center; }
    @media print { body { margin: 0; } }
</style>
</head>
<body>
<table class="hdr">
    <tr>
        @if ($hasSides)<td class="hdr-side">@if ($sideLogo)<img src="{{ $h['logo'] }}" class="hdr-logo" alt="">@endif</td>@endif
        <td class="hdr-mid">
            @foreach ($h['lines'] as $i => $line)
                <div class="official {{ $i === 0 ? 'official-first' : '' }}">{{ $line }}</div>
            @endforeach
            @if (count($h['lines']))<div class="official-rule"></div>@endif
            @if ($h['logo'] && $h['logo_position'] === 'center')<img src="{{ $h['logo'] }}" class="hdr-logo hdr-logo-center" alt="">@endif
            <div class="school">{{ $h['school'] }}</div>
            @foreach ($h['details'] as $detail)
                <div class="detail">{{ $detail }}</div>
            @endforeach
        </td>
        @if ($hasSides)<td class="hdr-side hdr-side-right">@if ($h['second_logo'])<img src="{{ $h['second_logo'] }}" class="hdr-logo" alt="">@endif</td>@endif
    </tr>
</table>

<div class="band">
    <span class="band-title">{{ $h['title'] }}</span>
    <span class="band-term">{{ $report['term'] }} · Année scolaire {{ $report['academic_year'] }}</span>
</div>

<table class="who">
    <tr>
        <td>
            <div class="who-name">{{ $report['student']['full_name'] }}</div>
            <table class="who-grid">
                <tr><td class="muted">Matricule</td><td>{{ $report['student']['matricule'] }}</td><td class="muted">Classe</td><td>{{ $report['student']['class_name'] }} ({{ $report['class_size'] }} élèves)</td></tr>
                <tr><td class="muted">Né(e) le</td><td>{{ $report['student']['birth_date'] ? \Illuminate\Support\Carbon::parse($report['student']['birth_date'])->format('d/m/Y') : '—' }}</td><td class="muted">Période</td><td>{{ $report['term'] }}</td></tr>
            </table>
        </td>
        @if ($h['photo'])
            <td class="photo-cell"><img src="{{ $h['photo'] }}" class="photo" alt=""></td>
        @elseif ($h['show_photo'] && $preview)
            <td class="photo-cell"><div class="photo photo-empty">Photo de l’élève</div></td>
        @endif
    </tr>
</table>

<table>
    <thead>
        <tr><th>Matière</th><th class="num">Coef.</th><th class="num">Moyenne</th><th class="num">Moy. × coef.</th><th class="num">Moy. classe</th><th>Appréciation</th></tr>
    </thead>
    <tbody>
    @foreach ($report['subjects'] as $s)
        <tr>
            <td>{{ $s['name'] }}</td>
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

<table class="sign">
    <tr>
        <td>
            <div class="sign-title">Le professeur principal</div>
            <div class="line"></div>
        </td>
        <td class="sign-right">
            <div class="sign-title">{{ $h['signature_title'] }}</div>
            <div class="sign-images">
                @if ($h['signature'])<img src="{{ $h['signature'] }}" class="signature" alt="">@endif
                @if ($h['stamp'])<img src="{{ $h['stamp'] }}" class="stamp" alt="">@endif
                @if (! $h['signature'] && ! $h['stamp'])<div class="line"></div>@endif
            </div>
            @if ($h['signatory_name'])<div class="signatory">{{ $h['signatory_name'] }}</div>@endif
        </td>
    </tr>
</table>
@if ($preview)<p class="preview-note">Aperçu avec des données fictives — l’en-tête et le pied sont ceux qui seront imprimés.</p>@endif
</body>
</html>
