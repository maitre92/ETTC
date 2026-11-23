{{-- Une page d'attestation (pdf/attestation, une ou plusieurs pages) : mêmes variables que la vue d'origine. --}}
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
<div class="page{{ ($last ?? true) ? '' : ' break' }}">
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
