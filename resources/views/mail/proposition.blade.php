{{-- Proposition chiffrée ($kind = offre, au demandeur, avec le bouton « Voir et accepter ») ou alerte d'acceptation
     ($kind = acceptee, à l'équipe). $demande. --}}
@php $amount = number_format((int) $demande->proposal_amount, 0, ',', ' ').' FCFA'; @endphp
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>{{ $kind === 'acceptee' ? 'Proposition acceptée' : 'Notre proposition' }}</title></head>
<body style="margin:0;padding:24px;background:#f4f8fc;font-family:Arial,Helvetica,sans-serif;color:#10183f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden">
        <tr><td style="background:#045eb5;color:#ffffff;padding:20px 24px">
            @include('mail._logo')
            <div style="font-size:15px;margin-top:6px">{{ $kind === 'acceptee' ? 'Proposition acceptée' : 'Notre proposition' }} — {{ $demande->need_type?->label() ?? 'Demande' }}</div>
        </td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.6">
            @if ($kind === 'acceptee')
                <p style="margin-top:0"><strong>{{ $demande->name }}</strong> a accepté la proposition de <strong>{{ $amount }}</strong> ({{ $demande->reference() }}).@if ($demande->phone) Téléphone : {{ $demande->phone }}.@endif</p>
                <p style="margin:24px 0 0"><a href="{{ route('demandes.show', $demande) }}" style="display:inline-block;background:#f96809;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Ouvrir la demande</a></p>
            @else
                <p style="margin-top:0">Bonjour {{ $demande->name }},</p>
                <p>Merci pour votre demande. Voici notre proposition :</p>
                <p style="font-size:22px;font-weight:bold;color:#045eb5;margin:8px 0">{{ $amount }}</p>
                <div style="background:#f4f8fc;border-radius:8px;padding:12px 16px">{!! nl2br(e($demande->proposal_text)) !!}</div>
                <p>Si elle vous convient, il vous suffit de cliquer sur le bouton ci-dessous pour l'accepter. Le centre vous recontacte ensuite pour la suite.</p>
                <p style="margin:24px 0 0"><a href="{{ route('site.proposition.show', $demande->proposal_token) }}" style="display:inline-block;background:#f96809;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Voir et accepter la proposition</a></p>
                @if ($site->get('contact.phone_1'))<p style="font-size:13px;color:#66748a">Une question ? Appelez-nous au {{ $site->get('contact.phone_1') }}.</p>@endif
            @endif
        </td></tr>
    </table>
</body>
</html>
