{{-- E-mails du test de niveau (styles en ligne : clients de messagerie). $test, $kind : equipe | accuse | convocation. --}}
@php
    $phone = $site->get('contact.phone_1');
    $titles = ['equipe' => 'Nouvelle demande de test de niveau', 'accuse' => 'Demande de test bien reçue', 'convocation' => 'Convocation à votre test de niveau'];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>{{ $titles[$kind] }}</title></head>
<body style="margin:0;padding:24px;background:#f4f8fc;font-family:Arial,Helvetica,sans-serif;color:#10183f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden">
        <tr><td style="background:#045eb5;color:#ffffff;padding:20px 24px">
            <div style="font-size:22px;font-weight:bold">{{ $site->get('company.short_name') }}<span style="color:#f9d232">.</span></div>
            <div style="font-size:15px;margin-top:6px">{{ $titles[$kind] }} — {{ $test->number }}</div>
        </td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.6">
            @if ($kind === 'equipe')
                <p style="margin-top:0">Un candidat demande à passer le test de niveau.</p>
            @elseif ($kind === 'accuse')
                <p style="margin-top:0">Bonjour {{ $test->name }},</p>
                <p>Nous avons bien reçu votre demande de test de niveau, enregistrée sous la référence <strong>{{ $test->number }}</strong>. Nous vous recontactons rapidement pour fixer la date du test.</p>
            @else
                <p style="margin-top:0">Bonjour {{ $test->name }},</p>
                <p>Votre test de niveau est fixé au <strong>{{ $test->scheduled_at?->translatedFormat('l j F Y \à H\hi') }}</strong> ({{ $test->test_mode->label() }}).</p>
            @endif

            <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;margin-top:8px">
                @if ($kind === 'equipe')
                    <tr><td style="color:#66748a;width:160px">Candidat</td><td>{{ $test->name }}</td></tr>
                    @if ($test->phone)<tr><td style="color:#66748a">Téléphone</td><td>{{ $test->phone }}</td></tr>@endif
                    @if ($test->email)<tr><td style="color:#66748a">E-mail</td><td>{{ $test->email }}</td></tr>@endif
                @endif
                <tr><td style="color:#66748a;width:160px">Mode souhaité</td><td>{{ $test->test_mode->label() }}</td></tr>
                @if ($test->preferred_slot && $kind !== 'convocation')<tr><td style="color:#66748a">Disponibilités</td><td>{{ $test->preferred_slot }}</td></tr>@endif
                @if ($kind === 'convocation' && $test->location)<tr><td style="color:#66748a">{{ $test->test_mode === \App\Enums\TestMode::Distance ? 'Connexion' : 'Lieu' }}</td><td>{{ $test->location }}</td></tr>@endif
                <tr><td style="color:#66748a">Coût</td><td><strong>Gratuit</strong></td></tr>
            </table>
            @if ($kind === 'equipe' && $test->message)
                <div style="margin-top:12px;padding:14px;background:#f4f8fc;border-radius:8px;font-size:14px;line-height:1.6">{!! nl2br(e($test->message)) !!}</div>
            @endif

            @if ($kind === 'equipe')
                <p style="margin:24px 0 0"><a href="{{ route('tests.show', $test) }}" style="display:inline-block;background:#f96809;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Planifier le test</a></p>
            @elseif ($phone)
                <p style="margin:24px 0 0;font-size:14px">Une question ? Appelez-nous au <strong>{{ $phone }}</strong> en rappelant la référence {{ $test->number }}.</p>
            @endif
            @if ($kind !== 'equipe')
                <p style="margin:24px 0 0;font-size:12px;color:#66748a">Vous recevez cet e-mail parce que vous avez demandé un test de niveau à {{ $site->get('company.name') }}. Si ce n'est pas vous, ignorez-le simplement.</p>
            @endif
        </td></tr>
    </table>
</body>
</html>
