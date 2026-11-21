{{-- Journal de caisse PDF (demande du centre, 2026-11-21). $journal (ordre chronologique), $totals, $balances, $from, $to, $site. --}}
@php $money = fn (int $amount) => number_format($amount, 0, ',', ' ').' FCFA'; @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #10183f; }
        h2 { font-size: 11px; color: #045eb5; margin: 14px 0 6px; text-transform: uppercase; }
        .muted { color: #5d6b82; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #045eb5; color: #fff; text-align: left; padding: 4px 5px; font-size: 8px; }
        td { padding: 3px 5px; border-bottom: 1px solid #dfe7f1; vertical-align: top; }
        .r { text-align: right; white-space: nowrap; }
        .kpi td { border: 1px solid #dfe7f1; padding: 6px; width: 25%; }
        .kpi strong { display: block; font-size: 11.5px; }
        .x td { color: #9aa5b5; text-decoration: line-through; }
        .tot td { font-weight: bold; border-top: 2px solid #045eb5; }
    </style>
</head>
<body>
    @include('pdf._entete', ['title' => 'JOURNAL DE CAISSE', 'subtitle' => 'Du '.$from->format('d/m/Y').' au '.$to->format('d/m/Y').' · édité le '.now()->format('d/m/Y à H:i'), 'logoSize' => 72])

    <table class="kpi">
        <tr>
            <td><span class="muted">Entrées de la période</span><strong>{{ $money($totals['in']) }}</strong></td>
            <td><span class="muted">Sorties de la période</span><strong>{{ $money($totals['out']) }}</strong></td>
            <td><span class="muted">Résultat</span><strong>{{ $money($totals['result']) }}</strong></td>
            <td><span class="muted">Solde au {{ $to->format('d/m/Y') }}</span><strong>{{ $money($balances['total']) }}</strong></td>
        </tr>
        <tr>
            @foreach (\App\Enums\PaymentMethod::FAMILIES as $key => $label)
                <td><span class="muted">{{ $label }}</span><strong>{{ $money($balances[$key]) }}</strong></td>
            @endforeach
            <td></td>
        </tr>
    </table>

    <h2>Mouvements</h2>
    <table>
        <tr><th>Date</th><th>N°</th><th>Libellé</th><th>Catégorie</th><th>Moyen</th><th class="r">Entrée</th><th class="r">Sortie</th></tr>
        @forelse ($journal as $r)
            <tr class="{{ $r['cancelled'] ? 'x' : '' }}">
                <td>{{ $r['date']->format('d/m/Y') }}</td><td>{{ $r['number'] }}</td><td>{{ $r['label'] }}{{ $r['cancelled'] ? ' (annulé)' : '' }}</td><td>{{ $r['category'] }}</td>
                <td>{{ $r['method']->shortLabel() }}{{ $r['to_method'] ? ' → '.$r['to_method']->shortLabel() : '' }}</td>
                <td class="r">{{ $r['in'] ? $money($r['in']) : ($r['transfer'] ? $money($r['transfer']) : '') }}</td><td class="r">{{ $r['out'] ? $money($r['out']) : ($r['transfer'] ? $money($r['transfer']) : '') }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Aucun mouvement sur la période.</td></tr>
        @endforelse
        <tr class="tot"><td colspan="5">Total (hors transferts et mouvements annulés)</td><td class="r">{{ $money($totals['in']) }}</td><td class="r">{{ $money($totals['out']) }}</td></tr>
    </table>

    @if ($totals['by_category'])
        <h2>Par catégorie</h2>
        <table>
            <tr><th>Catégorie</th><th class="r">Entrées</th><th class="r">Sorties</th></tr>
            @foreach ($totals['by_category'] as $category => $sum)
                <tr><td>{{ $category }}</td><td class="r">{{ $sum['in'] ? $money($sum['in']) : '' }}</td><td class="r">{{ $sum['out'] ? $money($sum['out']) : '' }}</td></tr>
            @endforeach
        </table>
    @endif
</body>
</html>
