{{-- Rapport financier PDF (cahier §5 « Générer et télécharger les rapports financiers », §8). $from, $to, $site,
     $totals, $monthly, $stats, $payments. --}}
@php $money = fn (int $amount) => number_format($amount, 0, ',', ' ').' FCFA'; @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #10183f; }
        h1 { font-size: 16px; color: #045eb5; margin: 0 0 2px; }
        h2 { font-size: 11px; color: #045eb5; margin: 16px 0 6px; text-transform: uppercase; }
        .muted { color: #5d6b82; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #045eb5; color: #fff; text-align: left; padding: 4px 5px; font-size: 8.5px; }
        td { padding: 4px 5px; border-bottom: 1px solid #dfe7f1; }
        .r { text-align: right; }
        .kpi td { border: 1px solid #dfe7f1; padding: 6px; width: 33%; }
        .kpi strong { display: block; font-size: 12px; }
        .head { border-bottom: 3px solid #f96809; padding-bottom: 6px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="head">
        <h1>Rapport financier — {{ $site->get('company.short_name') }}</h1>
        <div class="muted">{{ $site->get('company.name') }} · du {{ $from->format('d/m/Y') }} au {{ $to->format('d/m/Y') }} · édité le {{ now()->format('d/m/Y à H:i') }}</div>
    </div>

    <table class="kpi">
        <tr>
            <td><span class="muted">Frais de formation encaissés</span><strong>{{ $money($totals['formation']) }}</strong></td>
            <td><span class="muted">Part du centre</span><strong>{{ $money($totals['centre']) }}</strong></td>
            <td><span class="muted">Commission du développeur</span><strong>{{ $money($totals['commission']) }}</strong></td>
        </tr>
        <tr>
            <td><span class="muted">Frais de test</span><strong>Gratuit</strong></td>
            <td><span class="muted">Commission versée</span><strong>{{ $money($totals['paid']) }}</strong></td>
            <td><span class="muted">Commission restant à payer</span><strong>{{ $money($totals['due']) }}</strong></td>
        </tr>
    </table>
    <p class="muted">Seuls les paiements confirmés comptent ; les paiements remboursés ou annulés sont exclus. Commission calculée sur les frais de formation, au taux en vigueur à la confirmation de chaque paiement.</p>

    <h2>Mois par mois</h2>
    <table>
        <tr><th>Mois</th><th class="r">Encaissé</th><th class="r">Part du centre</th><th class="r">Commission</th><th class="r">Versé</th><th class="r">Reste</th></tr>
        @foreach ($monthly as $row)
            <tr><td>{{ $row['label'] }}</td><td class="r">{{ $money($row['formation']) }}</td><td class="r">{{ $money($row['centre']) }}</td><td class="r">{{ $money($row['commission']) }}</td><td class="r">{{ $money($row['paid']) }}</td><td class="r">{{ $money($row['due']) }}</td></tr>
        @endforeach
    </table>

    <h2>Inscriptions de la période ({{ $stats['inscriptions'] }})</h2>
    <table>
        <tr><th>Par niveau</th><th class="r">Nb</th><th>Par formule</th><th class="r">Nb</th><th>Par mode</th><th class="r">Nb</th></tr>
        @php $rows = max($stats['byNiveau']->count(), $stats['byFormule']->count(), $stats['byMode']->count(), 1); $n = $stats['byNiveau']->toArray(); $f = $stats['byFormule']->toArray(); $m = $stats['byMode']->toArray(); @endphp
        @for ($i = 0; $i < $rows; $i++)
            <tr>
                <td>{{ array_keys($n)[$i] ?? '' }}</td><td class="r">{{ array_values($n)[$i] ?? '' }}</td>
                <td>{{ array_keys($f)[$i] ?? '' }}</td><td class="r">{{ array_values($f)[$i] ?? '' }}</td>
                <td>{{ array_keys($m)[$i] ?? '' }}</td><td class="r">{{ array_values($m)[$i] ?? '' }}</td>
            </tr>
        @endfor
    </table>

    <h2>Détail des paiements ({{ $payments->count() }})</h2>
    <table>
        <tr><th>Reçu</th><th>Date</th><th>Apprenant</th><th>Formation</th><th>Moyen</th><th class="r">Montant</th><th class="r">Commission</th></tr>
        @forelse ($payments as $p)
            <tr><td>{{ $p->number }}</td><td>{{ $p->paid_at->format('d/m/Y') }}</td><td>{{ $p->inscription->name }}</td><td>{{ $p->inscription->formule_name }}</td><td>{{ $p->method->label() }}</td><td class="r">{{ $money($p->amount) }}</td><td class="r">{{ $money($p->commission_amount) }}</td></tr>
        @empty
            <tr><td colspan="7" class="muted">Aucun paiement sur la période.</td></tr>
        @endforelse
    </table>
</body>
</html>
