{{-- Reçu de paiement A5 (dompdf, DejaVu Sans : accents et FCFA). $paiement, $inscription, $site, $logo, $qr, $verifyUrl. --}}
@php $money = fn (int $amount) => number_format($amount, 0, ',', ' ').' FCFA'; @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #10183f; }
        .muted { color: #5d6b82; }
        table.rows { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.rows td { padding: 6px 0; border-bottom: 1px solid #dfe7f1; vertical-align: top; }
        table.rows td.l { color: #5d6b82; width: 38%; }
        .amount { font-size: 17px; font-weight: bold; color: #045eb5; }
        .status { font-weight: bold; color: #257e34; }
        .status.off { color: #b42318; }
        .foot { width: 100%; margin-top: 14px; }
        .foot td { vertical-align: top; }
        .sign { height: 46px; border-bottom: 1px dotted #5d6b82; width: 150px; }
        .small { font-size: 8px; }
    </style>
</head>
<body>
    @include('pdf._entete', ['title' => 'REÇU DE PAIEMENT', 'subtitle' => 'N° '.$paiement->number, 'logoSize' => 72])

    <table class="rows">
        <tr><td class="l">Reçu de</td><td><strong>{{ $inscription->name }}</strong>@if ($inscription->phone) — {{ $inscription->phone }}@endif</td></tr>
        <tr><td class="l">Inscription</td><td>{{ $inscription->number }} — {{ $inscription->niveau?->name ? $inscription->niveau->name.', ' : '' }}{{ $inscription->formule_name }}, {{ mb_strtolower($inscription->mode->label()) }}</td></tr>
        <tr><td class="l">Nature</td><td>{{ $paiement->nature->label() }}@if ($paiement->period) — {{ $paiement->period }}@endif</td></tr>
        <tr><td class="l">Montant payé</td><td class="amount">{{ $money($paiement->amount) }}</td></tr>
        <tr><td class="l">Date du paiement</td><td>{{ $paiement->paid_at->format('d/m/Y') }}</td></tr>
        <tr><td class="l">Mode de paiement</td><td>{{ $paiement->method->label() }}@if ($paiement->reference) — transaction {{ $paiement->reference }}@endif</td></tr>
        <tr><td class="l">Statut</td><td class="status {{ $paiement->status === \App\Enums\PaymentStatus::Confirme ? '' : 'off' }}">{{ $paiement->status->label() }}@if ($paiement->cancel_reason) ({{ $paiement->cancel_reason }})@endif</td></tr>
        <tr><td class="l">Formation</td><td>Total {{ $money($inscription->total_amount) }} · payé {{ $money($inscription->amount_paid) }} · <strong>reste {{ $money($inscription->balance()) }}</strong></td></tr>
    </table>

    <table class="foot">
        <tr>
            <td>
                <div class="muted">{{ $paiement->source === 'en_ligne' ? 'Paiement en ligne confirmé par l\'opérateur.' : 'Encaissé par : '.($paiement->recorder?->displayName() ?? $paiement->confirmer?->displayName() ?? $site->get('company.short_name')) }}</div>
                <div class="sign"></div>
                <div class="muted small">Cachet et signature</div>
            </td>
            <td style="text-align:right">
                <img src="{{ $qr }}" width="88" height="88" alt="">
                <div class="muted small">Scannez pour lire ce reçu</div>
            </td>
        </tr>
    </table>
    <p class="muted small">Reçu délivré le {{ ($paiement->confirmed_at ?? $paiement->paid_at)->format('d/m/Y à H:i') }}. Le reste à payer est celui connu à la date d'impression ({{ now()->format('d/m/Y') }}).</p>
</body>
</html>
