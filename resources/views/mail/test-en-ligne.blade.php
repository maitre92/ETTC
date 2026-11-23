{{-- Résultat du test en ligne : au candidat (niveau provisoire, s'inscrire) ou à l'équipe (à écouter / confirmer). $test, $kind. --}}
@php $hasOral = collect($test->online_answers ?? [])->contains(fn ($a) => isset($a['recording'])); @endphp
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Test de niveau en ligne</title></head>
<body style="margin:0;padding:24px;background:#f4f8fc;font-family:Arial,Helvetica,sans-serif;color:#10183f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden">
        <tr><td style="background:#045eb5;color:#ffffff;padding:20px 24px">
            @include('mail._logo')
            <div style="font-size:15px;margin-top:6px">Test de niveau en ligne — {{ $test->number }}</div>
        </td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.6">
            @if ($kind === 'equipe')
                <p style="margin-top:0"><strong>{{ $test->name }}</strong> a passé le test en ligne{{ $test->phone ? ' ('.$test->phone.')' : '' }} : <strong>{{ $test->score }}</strong>, niveau provisoire <strong>{{ $test->niveau?->name ?? '—' }}</strong>.</p>
                @if ($hasOral)<p>Son expression orale est enregistrée : écoutez-la (ou confiez-la à un formateur) et confirmez le niveau.</p>@endif
                <p style="margin:24px 0 0"><a href="{{ route('tests.show', $test) }}" style="display:inline-block;background:#f96809;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Voir le test</a></p>
            @else
                <p style="margin-top:0">Bonjour {{ $test->name }},</p>
                <p>Merci d'avoir passé notre test de niveau en ligne. Votre score : <strong>{{ $test->score }}</strong>.</p>
                <p style="font-size:20px;font-weight:bold;color:#045eb5;margin:8px 0">Niveau conseillé : {{ $test->niveau?->name ?? 'à déterminer' }}</p>
                @if ($hasOral)<p>Votre expression orale va être écoutée par un formateur, qui confirmera votre niveau.</p>@endif
                @if ($test->niveau)
                    <p style="margin:24px 0 0"><a href="{{ route('site.inscription.create', ['niveau' => $test->niveau->slug]) }}" style="display:inline-block;background:#f96809;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">M'inscrire au niveau {{ $test->niveau->name }}</a></p>
                @endif
                @if ($site->get('contact.phone_1'))<p style="font-size:13px;color:#66748a">Une question ? {{ $site->get('contact.phone_1') }}.</p>@endif
            @endif
        </td></tr>
    </table>
</body>
</html>
