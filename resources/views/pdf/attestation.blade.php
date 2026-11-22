{{-- « Certificate of Training » : le modèle fourni par le centre (English Training/Attestation.jpeg, 2026-11-22) en fond,
     à l'identique (en-tête, coordonnées, bandeau, textes fixes, signature et cachet du directeur général), parties
     variables effacées (resources/images/attestation-fond.jpg) puis réécrites aux mêmes places : nom, formation, mode et
     période, durée, QR, référence, lieu et date de délivrance. Page aux proportions du modèle (1280 × 853).
     Signataire (titre, nom), signature et sceau : écran Attestations (AttestationAssets, mêmes positions que l'aperçu).
     $inscription, $background, $training, $period, $duration, $mode, $city, $issued, $qr, $duplicate, $signature,
     $signatureFit, $stamp, $stampFit, $signer, $signerTitle. --}}
@php
    $name = mb_strtoupper($inscription->name);
    $nameSize = min(33, round(33 * 14 / max(14, mb_strlen($name)), 1));
    $trainingText = mb_strtoupper($training);
    $trainingSize = min(18, round(18 * 29 / max(29, mb_strlen($trainingText)), 1));
    $top = fn (float $centerMm, float $sizePt) => round($centerMm - 0.45 * $sizePt * 0.3528, 2);
    $titleSize = min(10.6, round(10.6 * 20 / max(20, mb_strlen($signerTitle)), 1));
    $signerSize = min(11, round(11 * 20 / max(20, mb_strlen($signer)), 1));
    $mm = fn (float $px) => round($px * 297 / 1280, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; }
        .page { position: relative; width: 297mm; height: 197.9mm; overflow: hidden; }
        .bg { position: absolute; top: 0; left: 0; width: 297mm; height: 197.9mm; }
        .t { position: absolute; left: 0; width: 297mm; text-align: center; line-height: 1; white-space: nowrap; font-family: 'DejaVu Serif', serif; }
        .name { font-weight: bold; color: #031a3e; }
        .training { font-weight: bold; color: #03183e; }
        .line { font-style: italic; color: #0a0a12; font-size: 12.4pt; }
        .line b { font-weight: bold; }
        .issued { position: absolute; left: 79.6mm; width: 90mm; text-align: center; line-height: 1; white-space: nowrap; font-family: 'DejaVu Serif', serif; font-style: italic; font-size: 11.3pt; color: #0b0f45; }
        .qr { position: absolute; left: 33.1mm; top: 156.6mm; width: 23mm; height: 23mm; }
        .ref { position: absolute; left: 16.4mm; width: 60mm; text-align: center; line-height: 1; font-family: 'DejaVu Sans', sans-serif; font-weight: bold; font-size: 8.6pt; color: #1f3f8f; }
        .signer { position: absolute; left: {{ $mm(828) }}mm; width: {{ $mm(224) }}mm; text-align: center; line-height: 1; white-space: nowrap; font-family: 'DejaVu Serif', serif; font-weight: bold; color: #101018; }
        .rule { position: absolute; left: {{ $mm(828) }}mm; top: {{ $mm(707) }}mm; width: {{ $mm(224) }}mm; height: 0.35mm; background: #101018; }
        .cover { position: absolute; left: {{ $mm(1125 - 77) }}mm; top: {{ $mm(746 - 77) }}mm; width: {{ $mm(154) }}mm; height: {{ $mm(154) }}mm; border-radius: {{ $mm(77) }}mm; background: rgb(245, 248, 252); }
        .dup { position: absolute; top: 88mm; left: 0; width: 297mm; text-align: center; font-family: helvetica, sans-serif; font-weight: bold; font-size: 60pt; color: #d4351c; opacity: .14; transform: rotate(-12deg); }
    </style>
</head>
<body>
<div class="page">
    <img src="{{ $background }}" alt="" class="bg">

    <div class="t name" style="top: {{ $top(77.3, $nameSize) }}mm; font-size: {{ $nameSize }}pt">{{ $name }}</div>
    <div class="t training" style="top: {{ $top(102.8, $trainingSize) }}mm; font-size: {{ $trainingSize }}pt">{{ $trainingText }}</div>
    <div class="t line" style="top: {{ $top(121.8, 12.4) }}mm">The training was conducted {{ $mode }} <b>{{ $period }}</b>,</div>
    <div class="t line" style="top: {{ $top(127.8, 12.4) }}mm">with a total duration of <b>{{ $duration }}</b>.</div>

    <img src="{{ $qr }}" alt="" class="qr">
    <div class="ref" style="top: {{ $top(184.9, 8.6) }}mm">{{ $inscription->attestation_number }}</div>
    <div class="issued" style="top: {{ $top(171.7, 11.3) }}mm">Issued in {{ $city }}, on {{ $issued }}.</div>

    {{-- Signataire, signature, sceau (un sceau déposé recouvre celui du modèle). --}}
    <div class="signer" style="top: {{ $top($mm(703) - 2.6, $titleSize) }}mm; font-size: {{ $titleSize }}pt">{{ $signerTitle }}</div>
    <div class="rule"></div>
    @if ($signature && $signatureFit)
        <img src="{{ $signature }}" alt="" style="position: absolute; left: {{ $mm(862) + $signatureFit['dx'] }}mm; top: {{ $mm(714) + $signatureFit['dy'] }}mm; width: {{ $signatureFit['w'] }}mm; height: {{ $signatureFit['h'] }}mm">
    @endif
    @if ($signer !== '')<div class="signer" style="top: {{ $top($mm(798) - 2.4, $signerSize) }}mm; font-size: {{ $signerSize }}pt">{{ $signer }}</div>@endif
    @if ($stamp && $stampFit)
        <div class="cover"></div>
        <img src="{{ $stamp }}" alt="" style="position: absolute; left: {{ $mm(1055) + $stampFit['dx'] }}mm; top: {{ $mm(676) + $stampFit['dy'] }}mm; width: {{ $stampFit['w'] }}mm; height: {{ $stampFit['h'] }}mm">
    @endif

    @if ($duplicate)<div class="dup">DUPLICATE</div>@endif
</div>
</body>
</html>
