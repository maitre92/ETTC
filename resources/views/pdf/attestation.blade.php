{{-- « Certificate of Training » : le modèle fourni par le centre (English Training/Attestation.jpeg, 2026-11-22) en fond,
     à l'identique (en-tête, coordonnées, bandeau, textes fixes, signature et cachet du directeur général), parties
     variables effacées (resources/images/attestation-fond.jpg) puis réécrites aux mêmes places : nom, formation, mode et
     période, durée, QR, référence, lieu et date de délivrance. Page aux proportions du modèle (1280 × 853).
     Signataire (titre, nom), signature et sceau : écran Attestations (AttestationAssets, mêmes positions que l'aperçu).
     Une page par attestation ($pages : AttestationService::pageData ; un groupe = plusieurs pages). Par page :
     $inscription, $background, $training, $period, $duration, $mode, $city, $issued, $qr, $duplicate, $signature,
     $signatureFit, $stamp, $stampFit, $signer, $signerTitle. --}}
@php $mm = fn (float $px) => round($px * 297 / 1280, 2); @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; }
        .page { position: relative; width: 297mm; height: 197.9mm; overflow: hidden; }
        .page.break { page-break-after: always; }
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
        .contact { position: absolute; left: {{ $mm(963) }}mm; width: 62mm; line-height: 1; white-space: nowrap; font-family: 'DejaVu Sans', sans-serif; font-weight: bold; color: #141d45; }
        .dup { position: absolute; top: 88mm; left: 0; width: 297mm; text-align: center; font-family: helvetica, sans-serif; font-weight: bold; font-size: 60pt; color: #d4351c; opacity: .14; transform: rotate(-12deg); }
    </style>
</head>
<body>
@foreach ($pages as $page)
    @include('pdf._attestation-page', $page + ['last' => $loop->last])
@endforeach
</body>
</html>
