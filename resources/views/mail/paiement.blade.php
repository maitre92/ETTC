{{-- E-mails de paiement (styles en ligne : clients de messagerie). $paiement, $kind : recu | equipe. --}}
@php
    $inscription = $paiement->inscription;
    $rows = [
        [$kind === 'recu' ? 'Reçu n°' : 'Référence', $paiement->number],
        ['Apprenant', $inscription->name],
        ['Inscription', $inscription->number.' — '.$inscription->formule_name.', '.mb_strtolower($inscription->mode->label())],
        ['Nature', $paiement->nature->label()],
        ['Montant', $paiement->money()],
        ['Moyen', $paiement->method->label()],
    ];
    if ($paiement->reference) { $rows[] = ['Transaction', $paiement->reference]; }
    if ($paiement->period) { $rows[] = ['Période', $paiement->period]; }
    $rows[] = ['Date', $paiement->paid_at->format('d/m/Y')];
    $rows[] = ['Reste à payer', \App\Models\Formule::money($inscription->balance())];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>{{ $kind === 'recu' ? 'Reçu de paiement' : 'Paiement à vérifier' }}</title></head>
<body style="margin:0;padding:24px;background:#f4f8fc;font-family:Arial,Helvetica,sans-serif;color:#10183f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden">
        <tr><td style="background:#045eb5;color:#ffffff;padding:20px 24px">
            <div style="font-size:22px;font-weight:bold">{{ $site->get('company.short_name') }}<span style="color:#f9d232">.</span></div>
            <div style="font-size:15px;margin-top:6px">{{ $kind === 'recu' ? 'Reçu de paiement' : 'Paiement mobile money à vérifier' }} — {{ $paiement->number }}</div>
        </td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.6">
            @if ($kind === 'equipe')
                <p style="margin-top:0"><strong>{{ $inscription->name }}</strong> déclare avoir payé {{ $paiement->money() }} par {{ $paiement->method->label() }}. Vérifiez la transaction sur le compte marchand avant de confirmer.</p>
            @else
                <p style="margin-top:0">Bonjour {{ $inscription->name }},</p>
                <p>Nous confirmons la réception de votre paiement. Merci ! Voici votre reçu ; il reste disponible dans votre espace apprenant.</p>
            @endif
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:12px">
                @foreach ($rows as [$label, $value])
                    <tr><td style="padding:8px 0;border-bottom:1px solid #e6edf5;color:#66748a;width:40%">{{ $label }}</td><td style="padding:8px 0;border-bottom:1px solid #e6edf5;font-weight:bold">{{ $value }}</td></tr>
                @endforeach
            </table>
            @if ($kind === 'equipe')
                <p style="margin:24px 0 0"><a href="{{ route('paiements.show', $paiement) }}" style="display:inline-block;background:#f96809;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Vérifier le paiement</a></p>
            @else
                <p style="margin:24px 0 0;font-size:12px;color:#66748a">Reçu délivré par {{ $site->get('company.name') }}. Conservez-le : il fait foi de votre paiement.</p>
            @endif
        </td></tr>
    </table>
</body>
</html>
