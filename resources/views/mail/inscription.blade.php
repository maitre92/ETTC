{{-- E-mails d'inscription (styles en ligne : clients de messagerie). $inscription, $kind : equipe | recapitulatif. --}}
@php
    $phone = $site->get('contact.phone_1');
    $rows = [
        ['Référence', $inscription->number],
        ['Niveau', $inscription->niveau?->name ?? 'À déterminer par le test de niveau (gratuit)'],
        ['Formule', $inscription->formule_name.' ('.$inscription->duration_months.' mois)'],
        ['Mode', $inscription->mode->label()],
        ['Tarif', $inscription->priceLabel()],
        ['Total de la formation', \App\Models\Formule::money($inscription->total_amount)],
        ['Paiement', $inscription->payment_choice->label()],
    ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>{{ $kind === 'equipe' ? 'Nouvelle inscription' : 'Récapitulatif de votre inscription' }}</title></head>
<body style="margin:0;padding:24px;background:#f4f8fc;font-family:Arial,Helvetica,sans-serif;color:#10183f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden">
        <tr><td style="background:#045eb5;color:#ffffff;padding:20px 24px">
            <div style="font-size:22px;font-weight:bold">{{ $site->get('company.short_name') }}<span style="color:#f9d232">.</span></div>
            <div style="font-size:15px;margin-top:6px">{{ $kind === 'equipe' ? 'Nouvelle inscription' : 'Récapitulatif de votre inscription' }} — {{ $inscription->number }}</div>
        </td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.6">
            @if ($kind === 'equipe')
                <p style="margin-top:0"><strong>{{ $inscription->name }}</strong> s'est inscrit(e) sur le site.@if ($inscription->contactLine()) Contact : {{ $inscription->contactLine() }}.@endif</p>
            @else
                <p style="margin-top:0">Bonjour {{ $inscription->name }},</p>
                <p>Merci pour votre inscription. Nous avons bien reçu votre demande ; le centre vérifie votre dossier et vous recontacte pour la suite{{ $inscription->niveau ? '' : ', à commencer par le test de niveau (gratuit)' }}.</p>
            @endif

            <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;margin-top:8px;border-collapse:collapse">
                @foreach ($rows as [$label, $value])
                    <tr><td style="color:#66748a;width:180px;border-bottom:1px solid #eef2f7">{{ $label }}</td><td style="border-bottom:1px solid #eef2f7"><strong>{{ $value }}</strong></td></tr>
                @endforeach
            </table>
            <p style="font-size:13px;color:#66748a;margin:10px 0 0">Tarif retenu au moment de votre inscription.</p>

            @if ($kind === 'equipe')
                <p style="margin:24px 0 0"><a href="{{ route('inscriptions.show', $inscription) }}" style="display:inline-block;background:#f96809;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Traiter l'inscription</a></p>
            @else
                <p style="margin:24px 0 0;font-size:14px">
                    @if ($inscription->payment_choice === \App\Enums\PaymentChoice::AuCentre)
                        Le paiement se fait au centre ; votre inscription sera confirmée après l'encaissement.
                    @else
                        Le centre vous indiquera comment régler en ligne (Orange Money, Wave ou carte).
                    @endif
                    @if ($phone) Une question ? Appelez-nous au <strong>{{ $phone }}</strong> en rappelant la référence {{ $inscription->number }}.@endif
                </p>
                <p style="margin:24px 0 0;font-size:12px;color:#66748a">Vous recevez cet e-mail parce que vous vous êtes inscrit(e) à une formation de {{ $site->get('company.name') }}. Si ce n'est pas vous, ignorez-le simplement.</p>
            @endif
        </td></tr>
    </table>
</body>
</html>
