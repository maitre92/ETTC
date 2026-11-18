{{-- Calendrier des séances ($kind = planifiees) ou annulation ($kind = annulee). $groupe, $inscription, $seances. --}}
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>{{ $kind === 'annulee' ? 'Séance annulée' : 'Vos séances' }}</title></head>
<body style="margin:0;padding:24px;background:#f4f8fc;font-family:Arial,Helvetica,sans-serif;color:#10183f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden">
        <tr><td style="background:#045eb5;color:#ffffff;padding:20px 24px">
            <div style="font-size:22px;font-weight:bold">{{ $site->get('company.short_name') }}<span style="color:#f9d232">.</span></div>
            <div style="font-size:15px;margin-top:6px">{{ $kind === 'annulee' ? 'Séance annulée' : 'Vos séances' }} — {{ $groupe->name }}</div>
        </td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.6">
            <p style="margin-top:0">Bonjour {{ $inscription->name }},</p>
            @if ($kind === 'annulee')
                <p>La séance suivante est <strong>annulée</strong>@if ($seances->first()->cancel_reason) ({{ $seances->first()->cancel_reason }})@endif :</p>
            @else
                <p>Voici vos prochaines séances{{ $groupe->teacher ? ' avec '.$groupe->teacher : '' }} :</p>
            @endif
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse">
                @foreach ($seances->take(20) as $seance)
                    <tr><td style="padding:8px 0;border-bottom:1px solid #e6edf5">{{ ucfirst($seance->when()) }}@if ($seance->title) — {{ $seance->title }}@endif</td></tr>
                @endforeach
            </table>
            @if ($seances->count() > 20)<p style="font-size:13px;color:#66748a">… et {{ $seances->count() - 20 }} autres séances, visibles dans votre espace.</p>@endif
            @if ($kind !== 'annulee')
                <p>{{ $groupe->mode === \App\Enums\FormationMode::EnLigne ? 'Les cours ont lieu sur Google Meet : le bouton « Rejoindre » s\'active dans votre espace 15 minutes avant chaque séance.' : 'Les cours ont lieu au centre'.($groupe->location ? ' ('.$groupe->location.')' : '').'.' }}</p>
            @endif
            <p style="margin:24px 0 0"><a href="{{ route('client.calendrier') }}" style="display:inline-block;background:#f96809;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Voir mon calendrier</a></p>
        </td></tr>
    </table>
</body>
</html>
