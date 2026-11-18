{{-- Ticket de caisse 80 mm (imprimante thermique), comme KalanNet : logo, détail, QR code de vérification. $paiement, $inscription, $site, $logo, $qr. --}}
@php $money = fn (int $amount) => number_format($amount, 0, ',', ' ').' F'; @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #000; margin: 0; }
        .c { text-align: center; }
        .r { text-align: right; }
        .line { border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .title { font-weight: bold; font-size: 11px; text-transform: uppercase; }
        .big { font-weight: bold; font-size: 13px; }
    </style>
</head>
<body>
    @include('pdf._entete', ['title' => 'REÇU DE PAIEMENT', 'subtitle' => 'N° '.$paiement->number])
    <table>
        <tr><td>Date</td><td class="r">{{ $paiement->paid_at->format('d/m/Y') }}</td></tr>
        <tr><td>Apprenant</td><td class="r">{{ $inscription->name }}</td></tr>
        <tr><td>Inscription</td><td class="r">{{ $inscription->number }}</td></tr>
        <tr><td>Formation</td><td class="r">{{ $inscription->formule_name }}</td></tr>
        <tr><td>Nature</td><td class="r">{{ $paiement->nature->label() }}</td></tr>
        @if ($paiement->period)<tr><td>Période</td><td class="r">{{ $paiement->period }}</td></tr>@endif
        <tr><td>Moyen</td><td class="r">{{ $paiement->method->label() }}</td></tr>
        @if ($paiement->reference)<tr><td>Transaction</td><td class="r">{{ $paiement->reference }}</td></tr>@endif
        <tr><td>Statut</td><td class="r">{{ $paiement->status->label() }}</td></tr>
    </table>
    <div class="line"></div>
    <table>
        <tr><td class="big">PAYÉ</td><td class="r big">{{ $money($paiement->amount) }}</td></tr>
        <tr><td>Total formation</td><td class="r">{{ $money($inscription->total_amount) }}</td></tr>
        <tr><td>Reste à payer</td><td class="r"><strong>{{ $money($inscription->balance()) }}</strong></td></tr>
    </table>
    <div class="line"></div>
    <div class="c">
        <img src="{{ $qr }}" width="104" height="104" alt="">
        <div>Scannez pour vérifier ce reçu</div>
    </div>
    <div class="line"></div>
    <div class="c">Merci de votre confiance !<br>{{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
