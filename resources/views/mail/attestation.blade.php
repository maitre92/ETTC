{{-- Attestation délivrée : jointe (formation en ligne) ou à retirer au centre (présentiel). $inscription, $remote. --}}
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Votre attestation</title></head>
<body style="margin:0;padding:24px;background:#f4f8fc;font-family:Arial,Helvetica,sans-serif;color:#10183f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden">
        <tr><td style="background:#045eb5;color:#ffffff;padding:20px 24px">
            @include('mail._logo')
            <div style="font-size:15px;margin-top:6px">Attestation de fin de formation — {{ $inscription->attestation_number }}</div>
        </td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.6">
            <p style="margin-top:0">Bonjour {{ $inscription->name }},</p>
            <p>Félicitations : vous avez terminé votre formation ({{ $inscription->summary() }}).</p>
            @if ($remote)
                <p><strong>Votre attestation est jointe à cet e-mail</strong> (PDF, à imprimer ou à conserver). Elle reste disponible dans votre espace apprenant, rubrique « Mes inscriptions ».</p>
                @if (\Illuminate\Support\Facades\Route::has('client.inscriptions'))
                    <p style="margin:24px 0 0"><a href="{{ route('client.inscriptions') }}" style="display:inline-block;background:#f96809;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Mon espace apprenant</a></p>
                @endif
            @else
                <p><strong>Votre attestation est prête</strong> : vous pouvez la retirer au centre, à l'accueil{{ $site->get('contact.address') ? ' ('.$site->get('contact.address').')' : '' }}.@if ($site->get('contact.hours')) Horaires : {{ str_replace("\n", ', ', $site->get('contact.hours')) }}.@endif</p>
            @endif
            <p style="font-size:13px;color:#66748a">Référence de l'attestation : {{ $inscription->attestation_number }}.@if ($site->get('contact.phone_1')) Une question ? {{ $site->get('contact.phone_1') }}.@endif</p>
        </td></tr>
    </table>
</body>
</html>
