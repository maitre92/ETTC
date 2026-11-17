{{-- Reçu de paiement imprimable (cahier §3.8 C, §4 « télécharger ses reçus ») : page autonome, « Imprimer / Enregistrer
     en PDF » par le navigateur (aucune bibliothèque PDF, D-G). Partagé par l'administration et l'espace apprenant.
     $paiement, $back (adresse du bouton Retour). --}}
@php
    $site = app(\App\Support\SiteSettings::class);
    $inscription = $paiement->inscription;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Reçu {{ $paiement->number }} — {{ $site->get('company.short_name') }}</title>
    <style>
        :root { --blue: #045eb5; --ink: #10183f; --muted: #5d6b82; --line: #dfe7f1; --orange: #f96809; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef3f9; color: var(--ink); font: 15px/1.5 Arial, Helvetica, sans-serif; }
        .bar { max-width: 760px; margin: 16px auto 0; padding: 0 16px; display: flex; gap: 8px; justify-content: flex-end; }
        .bar a, .bar button { font: inherit; padding: 9px 16px; border-radius: 8px; border: 1px solid var(--blue); background: #fff; color: var(--blue); text-decoration: none; cursor: pointer; }
        .bar button { background: var(--blue); color: #fff; }
        .receipt { max-width: 760px; margin: 16px auto 32px; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 6px 24px rgba(16, 24, 63, .08); }
        .head { display: flex; gap: 16px; align-items: center; justify-content: space-between; padding: 24px; border-bottom: 4px solid var(--orange); }
        .head img { width: 72px; height: 72px; object-fit: contain; }
        .brand { display: flex; gap: 14px; align-items: center; }
        .brand strong { display: block; font-size: 17px; }
        .brand span, .meta span { color: var(--muted); font-size: 13px; }
        .meta { text-align: right; }
        .meta h1 { margin: 0; font-size: 22px; color: var(--blue); letter-spacing: .04em; }
        .body { padding: 24px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 10px 0; border-bottom: 1px solid var(--line); vertical-align: top; }
        td:first-child { color: var(--muted); width: 38%; }
        .amount { font-size: 24px; font-weight: bold; color: var(--blue); }
        .status { display: inline-block; padding: 3px 10px; border-radius: 999px; font-weight: bold; font-size: 13px; background: #ebf7ec; color: #257e34; }
        .status.off { background: #fdecea; color: #b42318; }
        .foot { padding: 16px 24px 24px; color: var(--muted); font-size: 12px; }
        @media (max-width: 560px) { .head { flex-direction: column; align-items: flex-start; } .meta { text-align: left; } td:first-child { width: 45%; } }
        @media print { body { background: #fff; } .bar { display: none; } .receipt { box-shadow: none; margin: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="bar">
        <a href="{{ $back }}">Retour</a>
        <button type="button" onclick="window.print()">Imprimer / PDF</button>
    </div>
    <main class="receipt">
        <div class="head">
            <div class="brand">
                <img src="{{ asset('assets/site/img/logo-ettc.png') }}" alt="">
                <div>
                    <strong>{{ $site->get('company.name') }} ({{ $site->get('company.short_name') }})</strong>
                    <span>{{ collect([$site->get('contact.city'), $site->get('contact.phone_1'), $site->get('contact.email')])->filter()->implode(' · ') }}</span>
                </div>
            </div>
            <div class="meta">
                <h1>REÇU</h1>
                <span>N° {{ $paiement->number }}</span>
            </div>
        </div>
        <div class="body">
            <table>
                <tr><td>Apprenant</td><td><strong>{{ $inscription->name }}</strong>@if ($inscription->phone)<br>{{ $inscription->phone }}@endif</td></tr>
                <tr><td>Inscription</td><td>{{ $inscription->number }} — {{ $inscription->niveau?->name ? $inscription->niveau->name.', ' : '' }}{{ $inscription->formule_name }}, {{ mb_strtolower($inscription->mode->label()) }}</td></tr>
                <tr><td>Nature du paiement</td><td>{{ $paiement->nature->label() }}@if ($paiement->period) — {{ $paiement->period }}@endif</td></tr>
                <tr><td>Montant payé</td><td class="amount">{{ $paiement->money() }}</td></tr>
                <tr><td>Date du paiement</td><td>{{ $paiement->paid_at->format('d/m/Y') }}</td></tr>
                <tr><td>Mode de paiement</td><td>{{ $paiement->method->label() }}@if ($paiement->reference) — transaction {{ $paiement->reference }}@endif</td></tr>
                <tr><td>Statut</td><td><span class="status {{ $paiement->status === \App\Enums\PaymentStatus::Confirme ? '' : 'off' }}">{{ $paiement->status->label() }}</span>@if ($paiement->cancel_reason) <span style="color:var(--muted)">({{ $paiement->cancel_reason }})</span>@endif</td></tr>
                <tr><td>Total de la formation</td><td>{{ \App\Models\Formule::money($inscription->total_amount) }} · payé : {{ \App\Models\Formule::money($inscription->amount_paid) }} · reste : <strong>{{ \App\Models\Formule::money($inscription->balance()) }}</strong></td></tr>
            </table>
        </div>
        <div class="foot">Reçu délivré le {{ ($paiement->confirmed_at ?? $paiement->paid_at)->format('d/m/Y à H:i') }}. Il fait foi du paiement indiqué ; le montant restant est celui connu à la date d'impression.</div>
    </main>
</body>
</html>
