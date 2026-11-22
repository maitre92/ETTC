{{-- Fiche de rémunération d'un formateur pour un mois. $formateur, $period, $month, $data, $payouts, $rates, $site, $logo. --}}
@php
    $money = fn (int $amount) => number_format($amount, 0, ',', ' ').' FCFA';
    $kinds = ['personnalisee' => 'Personnalisée', 'groupe' => 'En groupe'];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 12mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #10183f; }
        h2 { font-size: 11px; color: #045eb5; margin: 14px 0 6px; text-transform: uppercase; }
        .muted { color: #5d6b82; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #045eb5; color: #fff; text-align: left; padding: 4px 5px; font-size: 8.5px; }
        td { padding: 4px 5px; border-bottom: 1px solid #dfe7f1; }
        .r { text-align: right; }
        .kpi td { border: 1px solid #dfe7f1; padding: 6px; width: 33%; }
        .kpi strong { display: block; font-size: 12px; }
        .sign td { border: 0; padding-top: 30px; width: 50%; }
    </style>
</head>
<body>
    @include('pdf._entete', ['title' => 'FICHE DE RÉMUNÉRATION', 'subtitle' => $formateur->name.' · '.ucfirst($month->translatedFormat('F Y')).' · éditée le '.now()->format('d/m/Y'), 'logoSize' => 72])

    <table class="kpi">
        <tr>
            <td><span class="muted">Montant dû</span><strong>{{ $money($data['due']) }}</strong></td>
            <td><span class="muted">Déjà versé</span><strong>{{ $money($data['paid']) }}</strong></td>
            <td><span class="muted">Reste à verser</span><strong>{{ $money($data['remaining']) }}</strong></td>
        </tr>
    </table>
    <p class="muted">Formation personnalisée : {{ $rates['personnalisee'] }} de la mensualité ; formation en groupe : {{ $rates['groupe'] }} du montant. Calcul sur les frais de formation encaissés dans le mois, hors frais d'inscription.</p>

    <h2>Détail</h2>
    <table>
        <tr><th>Date</th><th>Apprenant</th><th>Formation</th><th class="r">Encaissé</th><th class="r">Taux</th><th class="r">Part</th></tr>
        @forelse ($data['lines'] as $line)
            <tr><td>{{ $line['paiement']->paid_at->format('d/m/Y') }}</td><td>{{ $line['inscription']->name }}</td><td>{{ $kinds[$line['kind']] }}{{ $line['inscription']->groupe ? ' · '.$line['inscription']->groupe->name : '' }}</td><td class="r">{{ $money($line['base']) }}</td><td class="r">{{ $rates[$line['kind']] }}</td><td class="r">{{ $money($line['share']) }}</td></tr>
        @empty
            <tr><td colspan="6" class="muted">Aucun paiement ce mois-ci.</td></tr>
        @endforelse
    </table>

    @if ($payouts->isNotEmpty())
        <h2>Versements</h2>
        <table>
            <tr><th>N°</th><th>Date</th><th>Moyen</th><th>Référence</th><th class="r">Montant</th></tr>
            @foreach ($payouts as $v)
                <tr><td>{{ $v->number }}</td><td>{{ $v->paid_on->format('d/m/Y') }}</td><td>{{ $v->method->shortLabel() }}</td><td>{{ $v->reference }}</td><td class="r">{{ $money($v->amount) }}</td></tr>
            @endforeach
        </table>
    @endif

    <table class="sign"><tr><td>Le formateur</td><td class="r">Pour {{ $site->get('company.short_name') }}</td></tr></table>
</body>
</html>
