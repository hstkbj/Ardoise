@php
    $fmt = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ');
    $totalCoef = collect($report['subjects'])->sum('coefficient');
    $totalPoints = collect($report['subjects'])->sum(fn ($s) => ($s['average'] ?? 0) * $s['coefficient']);
    // Note brute : « 12 », « 14,5 » ; colonnes : au moins 3 interrogations (I1 à I3) et 2 devoirs (D1, D2)
    $note = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $interroCols = max(3, collect($report['subjects'])->map(fn ($s) => count($s['interros'] ?? []))->max() ?? 0);
    $devoirCols = max(2, collect($report['subjects'])->map(fn ($s) => count($s['devoirs'] ?? []))->max() ?? 0);
    $rankOf = fn ($r) => $r ? $r.($r == 1 ? 'er' : 'e') : '—';
    // En-tête réglé par l'école : App\Support\ReportCardHeader
    $h = $header ?? \App\Support\ReportCardHeader::for(null, $report['school']);
    // Options de l'éditeur de bulletin (valeurs par défaut = mise en page historique)
    $h += ['font' => 'DejaVu Sans, Arial, sans-serif', 'border' => 'none', 'align' => 'center', 'logo_px' => 72, 'divider' => false, 'title_style' => 'band',
        'student_layout' => 'plain', 'student_fields' => ['matricule', 'class', 'birth_date', 'term'], 'photo_position' => 'right', 'photo_w' => 66, 'photo_h' => 82, 'watermark' => null];
    $infoCells = [
        'matricule' => ['Matricule', $report['student']['matricule']],
        'class' => ['Classe', $report['student']['class_name'].' ('.$report['class_size'].' élèves)'],
        'birth_date' => ['Né(e) le', $report['student']['birth_date'] ? \Illuminate\Support\Carbon::parse($report['student']['birth_date'])->format('d/m/Y') : '—'],
        'term' => ['Période', $report['term']],
        'year' => ['Année scolaire', $report['academic_year']],
    ];
    $infoRows = collect($h['student_fields'])->filter(fn ($k) => isset($infoCells[$k]))->map(fn ($k) => $infoCells[$k])->chunk(2);
    $photoLeft = $h['photo_position'] === 'left';
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
    .center { text-align: center; }
    table.grades th, table.grades td { padding: 3px 4px; }
    table.grades th { font-size: 9px; }
    .strong { font-weight: bold; }
    .appreciation { font-size: 9.5px; }
    .low { color: #A12B2B; font-weight: bold; }
    .boxes { display: table; width: 100%; margin-top: 12px; border-spacing: 8px 0; }
    .box { display: table-cell; border: 1px solid #D6D6D0; padding: 8px; width: 33%; }
    .big { font-size: 18px; font-weight: bold; }
    .line { border-top: 1px solid #999; margin-top: 44px; width: 80%; }

    /* En-tête réglé par l'école (Paramètres › Bulletins) */
    table.hdr, table.hdr td, table.who, table.who td, table.who-grid td, table.sign, table.sign td { border: 0; padding: 0; margin: 0; }
    table.hdr { margin: 0; }
    .doc-font { font-family: {!! $h['font'] !!}; }
    .hdr-side { width: {{ $h['logo_px'] + 8 }}px; vertical-align: middle; }
    .hdr-side-right { text-align: right; }
    .hdr-logo { max-width: {{ $h['logo_px'] }}px; max-height: {{ $h['logo_px'] }}px; }
    .hdr-logo-center { display: block; margin: 3px {{ $h['align'] === 'left' ? '0' : 'auto' }}; max-height: {{ (int) round($h['logo_px'] * 0.75) }}px; }
    .hdr-mid { text-align: {{ $h['align'] }}; vertical-align: middle; padding: 0 8px; }
    .official { font-size: 9px; text-transform: uppercase; letter-spacing: 0.3px; color: #4F5560; }
    .official-first { font-weight: bold; color: #15171C; }
    .official-rule { width: 60px; margin: 4px {{ $h['align'] === 'left' ? '0' : 'auto' }} 6px; border-top: 1px solid {{ $h['color'] }}; }
    .hdr-divider { margin-top: 8px; border-top: 2px solid {{ $h['color'] }}; }
    .school { font-size: 15px; font-weight: bold; text-transform: uppercase; color: {{ $h['color'] }}; margin-top: 2px; }
    .detail { font-size: 9.5px; color: #4F5560; }
    .band { margin-top: 8px; padding: 6px 10px; background: {{ $h['color'] }}; color: #FFFFFF; }
    .band-title { font-size: 13px; font-weight: bold; text-transform: uppercase; }
    .band-term { float: right; font-size: 10px; padding-top: 2px; }
    .band-line { background: none; color: {{ $h['color'] }}; padding: 5px 0; border-bottom: 2px solid {{ $h['color'] }}; }
    .band-plain { background: none; color: #15171C; padding: 5px 0; border-top: 1px solid #D6D6D0; border-bottom: 1px solid #D6D6D0; }
    table.who-card { border: 1px solid #D6D6D0; background: #F8F8F6; }
    table.who-card td.who-main, table.who-card td.photo-cell { padding: 7px 10px; }
    td.photo-cell-left { text-align: left; }
    .page-border { position: fixed; top: -8mm; left: -8mm; right: -8mm; bottom: -6mm; border: {{ $h['border'] === 'double' ? '4px double' : '1px solid' }} {{ $h['color'] }}; }
    .watermark { position: fixed; top: 28%; z-index: -1; }
    .watermark-text { position: fixed; top: 42%; left: 0; width: 100%; text-align: center; font-size: 64px; font-weight: bold; color: {{ $h['color'] }}; text-transform: uppercase; z-index: -1; }
    table.who { margin-top: 8px; }
    table.who > tbody > tr > td, table.who > tr > td { vertical-align: top; }
    .who-name { font-size: 13px; font-weight: bold; margin-bottom: 4px; }
    table.who-grid { margin-top: 0; width: auto; }
    table.who-grid td { padding: 1px 14px 1px 0; }
    .photo-cell { width: {{ $h['photo_w'] + 6 }}px; text-align: right; }
    .photo { width: {{ $h['photo_w'] }}px; height: {{ $h['photo_h'] }}px; border: 1px solid #D6D6D0; }
    .photo-empty { display: inline-block; background: #F1F1EE; color: #8A8F98; font-size: 8px; text-align: center; height: {{ max(20, $h['photo_h'] - 36) }}px; padding-top: 36px; }
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
@if ($h['border'] !== 'none')<div class="page-border"></div>@endif
@if ($h['watermark'])
    @if ($h['watermark']['type'] === 'image')
        <img src="{{ $h['watermark']['src'] }}" class="watermark" style="width: {{ $h['watermark']['width'] }}%; left: {{ (100 - $h['watermark']['width']) / 2 }}%; opacity: {{ $h['watermark']['opacity'] }};" alt="">
    @else
        <div class="watermark-text" style="opacity: {{ $h['watermark']['opacity'] }};">{{ $h['watermark']['text'] }}</div>
    @endif
@endif
<table class="hdr doc-font">
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
@if ($h['divider'])<div class="hdr-divider"></div>@endif

<div class="band band-{{ $h['title_style'] }} doc-font">
    <span class="band-title">{{ $h['title'] }}</span>
    <span class="band-term">{{ $report['term'] }} · Année scolaire {{ $report['academic_year'] }}</span>
</div>

<table class="who doc-font {{ $h['student_layout'] === 'card' ? 'who-card' : '' }}">
    <tr>
        @if ($photoLeft && ($h['photo'] || ($h['show_photo'] && $preview)))
            <td class="photo-cell photo-cell-left">@if ($h['photo'])<img src="{{ $h['photo'] }}" class="photo" alt="">@else<div class="photo photo-empty">Photo de l’élève</div>@endif</td>
        @endif
        <td class="who-main">
            <div class="who-name">{{ $report['student']['full_name'] }}</div>
            <table class="who-grid">
                @foreach ($infoRows as $row)
                    <tr>@foreach ($row as [$label, $value])<td class="muted">{{ $label }}</td><td>{{ $value }}</td>@endforeach</tr>
                @endforeach
            </table>
        </td>
        @if (! $photoLeft && ($h['photo'] || ($h['show_photo'] && $preview)))
            <td class="photo-cell">@if ($h['photo'])<img src="{{ $h['photo'] }}" class="photo" alt="">@else<div class="photo photo-empty">Photo de l’élève</div>@endif</td>
        @endif
    </tr>
</table>

<table class="grades">
    <thead>
        <tr>
            <th rowspan="2">Matière</th>
            <th rowspan="2" class="num">Coef.</th>
            <th colspan="{{ $interroCols + 1 }}" class="center">Interrogations</th>
            <th colspan="{{ $devoirCols }}" class="center">Devoirs</th>
            <th rowspan="2" class="num">Moy. /20</th>
            <th rowspan="2" class="num">Moy. × coef.</th>
            @if ($report['show_rank'])<th rowspan="2" class="num">Rang</th>@endif
            <th rowspan="2">Appréciation</th>
        </tr>
        <tr>
            @for ($i = 1; $i <= $interroCols; $i++)<th class="num">I{{ $i }}</th>@endfor
            <th class="num">Moy.</th>
            @for ($i = 1; $i <= $devoirCols; $i++)<th class="num">D{{ $i }}</th>@endfor
        </tr>
    </thead>
    <tbody>
    @foreach ($report['subjects'] as $s)
        <tr>
            <td>{{ $s['name'] }}</td>
            <td class="num">{{ $note($s['coefficient']) }}</td>
            @for ($i = 0; $i < $interroCols; $i++)<td class="num">{{ isset($s['interros'][$i]) ? $note($s['interros'][$i]) : '—' }}</td>@endfor
            <td class="num">{{ $fmt($s['interro_average'] ?? null) }}</td>
            @for ($i = 0; $i < $devoirCols; $i++)<td class="num">{{ isset($s['devoirs'][$i]) ? $note($s['devoirs'][$i]) : '—' }}</td>@endfor
            <td class="num strong {{ ($s['average'] ?? 20) < 10 ? 'low' : '' }}">{{ $fmt($s['average']) }}</td>
            <td class="num">{{ $s['average'] === null ? '—' : $fmt($s['average'] * $s['coefficient']) }}</td>
            @if ($report['show_rank'])<td class="num">{{ $rankOf($s['rank'] ?? null) }}</td>@endif
            <td class="appreciation">{{ $s['appreciation'] }}</td>
        </tr>
    @endforeach
        <tr>
            <th>Total</th>
            <th class="num">{{ $note($totalCoef) }}</th>
            <th colspan="{{ $interroCols + 2 + $devoirCols }}"></th>
            <th class="num">{{ $fmt($totalPoints) }}</th>
            <th colspan="{{ $report['show_rank'] ? 2 : 1 }}"></th>
        </tr>
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
