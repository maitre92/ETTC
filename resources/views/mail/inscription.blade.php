{{-- E-mails d'inscription (styles en ligne : clients de messagerie). $inscription, $kind : equipe | recapitulatif. --}}
@php
    $phone = $site->get('contact.phone_1');
    // Formation en ligne : numéros et QR mobile money du centre (null sinon).
    $payment = $kind !== 'equipe' ? app(\App\Services\Paiements\PaiementService::class)->onlinePaymentInfo($inscription) : null;
    $rows = [
        ['Référence', $inscription->number],
        ['Niveau', $inscription->niveau?->name ?? 'À déterminer par le test de niveau (gratuit)'],
        ['Formule', $inscription->formule_name.' ('.$inscription->duration_months.' mois)'],
        ['Mode', $inscription->mode->label()],
        ['Tarif', $inscription->priceLabel()],
    ];
    // Détail figé : formation, promotion, frais d'inscription, total ; puis le premier paiement attendu.
    foreach ($inscription->priceLines() as [$lineLabel, $lineAmount]) {
        $rows[] = [$lineLabel, ($lineAmount < 0 ? '-' : '').\App\Models\Formule::money(abs($lineAmount))];
    }
    $rows[] = ['Premier paiement', \App\Models\Formule::money($inscription->nextInstallment())];
    $rows[] = ['Paiement', $inscription->payment_choice->label()];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>{{ $kind === 'equipe' ? 'Nouvelle inscription' : 'Récapitulatif de votre inscription' }}</title></head>
<body style="margin:0;padding:24px;background:#f4f8fc;font-family:Arial,Helvetica,sans-serif;color:#10183f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden">
        <tr><td style="background:#045eb5;color:#ffffff;padding:20px 24px">
            @include('mail._logo')
            <div style="font-size:15px;margin-top:6px">{{ $kind === 'equipe' ? 'Nouvelle inscription' : 'Récapitulatif de votre inscription' }} — {{ $inscription->number }}</div>
        </td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.6">
            @if ($kind === 'equipe')
                <p style="margin-top:0"><strong>{{ $inscription->name }}</strong> s'est inscrit(e) sur le site.@if ($inscription->contactLine()) Contact : {{ $inscription->contactLine() }}.@endif</p>
                @if ($declared ?? null)
                    {{-- Paiement déclaré à l'inscription : à vérifier sur le compte marchand, puis « Confirmer ». --}}
                    <div style="margin:16px 0;padding:16px;border-radius:10px;background:#fdf3d1">
                        <p style="margin:0 0 8px;font-weight:bold;font-size:16px">Paiement déclaré à vérifier</p>
                        <p style="margin:0">Montant : <strong>{{ $declared->money() }}</strong> · {{ $declared->method->label() }}<br>Référence de la transaction : <strong>{{ $declared->reference }}</strong>@if ($declared->payer_phone)<br>Payé depuis : {{ $declared->payer_phone }}@endif @if ($declared->proof_path)<br><strong>Capture du paiement jointe à cet e-mail.</strong>@endif</p>
                        <p style="margin:12px 0 0;font-size:14px">Vérifiez que cette transaction est bien arrivée sur le compte {{ $declared->method->label() }} du centre, puis cliquez sur « Confirmer » : l'apprenant reçoit alors son reçu par e-mail.</p>
                        <p style="margin:14px 0 0"><a href="{{ route('paiements.show', $declared) }}" style="display:inline-block;background:#257e34;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Vérifier le paiement</a></p>
                    </div>
                @endif
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

            {{-- Formation en ligne : comment payer par mobile money (numéros et QR du centre). --}}
            @if ($kind !== 'equipe' && $payment)
                <div style="margin:20px 0 0;padding:16px;border-radius:10px;background:#fdf3d1">
                    <p style="margin:0 0 8px;font-weight:bold;font-size:16px">Payer maintenant par mobile money</p>
                    <p style="margin:0 0 12px;font-size:14px">{{ $payment['instructions'] }}</p>
                    <table role="presentation" cellpadding="0" cellspacing="0" style="width:100%"><tr>
                        @foreach ($payment['methods'] as $method)
                            <td style="padding:6px;text-align:center;vertical-align:top;background:#ffffff;border-radius:8px">
                                <div style="font-weight:bold">{{ $method['label'] }}</div>
                                @if ($method['number'] !== '')<div style="font-size:16px">{{ $method['number'] }}</div>@endif
                                @if ($method['qr'])
                                    {{-- QR joint à l'e-mail (pièce intégrée) : s'affiche même si le site n'est pas joignable (local). --}}
                                    @php
                                        $qrSrc = ($method['qr_path'] ?? null) && isset($message) && method_exists($message, 'embed') ? $message->embed($method['qr_path']) : $method['qr'];
                                    @endphp
                                    <img src="{{ $qrSrc }}" alt="QR {{ $method['label'] }}" width="130" height="130" style="display:block;margin:6px auto 0">
                                @endif
                            </td>
                        @endforeach
                    </tr></table>
                    {{-- Paiement par code marchand : les étapes, numérotées. --}}
                    @foreach ($payment['methods'] as $method)
                        @if (! empty($method['ussd']))
                            <p style="margin:14px 0 6px;font-weight:bold">Payer par code marchand {{ $method['label'] }}@if ($method['ussd']['merchant']) (code marchand {{ $method['ussd']['merchant'] }})@endif</p>
                            <ol style="margin:0;padding-left:20px;font-size:14px">
                                @foreach ($method['ussd']['steps'] as $step)
                                    <li style="margin-bottom:6px"><strong>{{ $step['title'] }}</strong> — {{ $step['text'] }}@if ($step['code']) <span style="font-family:monospace;font-size:15px;background:#ffffff;padding:2px 6px;border-radius:4px">{{ $step['code'] }}</span>@endif</li>
                                @endforeach
                            </ol>
                        @endif
                    @endforeach
                    @if ($payment['whatsapp'])<p style="margin:14px 0 0;font-size:14px">Après le paiement, envoyez-nous la référence du SMS : <a href="{{ $payment['whatsapp'] }}" style="color:#045eb5;font-weight:bold">sur WhatsApp</a>@if ($payment['account']), ou déclarez-la dans votre <a href="{{ $payment['account'] }}" style="color:#045eb5;font-weight:bold">espace apprenant</a> (créé avec cette adresse e-mail)@endif.</p>@endif
                </div>
            @endif

            @if ($kind === 'equipe')
                <p style="margin:24px 0 0"><a href="{{ route('inscriptions.show', $inscription) }}" style="display:inline-block;background:#f96809;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Traiter l'inscription</a></p>
            @else
                <p style="margin:24px 0 0;font-size:14px">
                    @if ($inscription->payment_choice === \App\Enums\PaymentChoice::AuCentre)
                        Vous pouvez payer au centre, à l'accueil{{ $payment ? ', ou en ligne par mobile money (voir ci-dessus)' : '' }} ; votre inscription est confirmée après le paiement et un reçu vous est délivré.
                    @elseif (! $payment)
                        Le paiement se fait par mobile money (Orange Money, Wave ou Moov Money) : le centre vous communique son numéro. Gardez le SMS de confirmation de votre paiement : sa référence le prouve.
                    @else
                        Le centre vérifie votre paiement et vous délivre un reçu.
                    @endif
                    @if ($phone) Une question ? Appelez-nous au <strong>{{ $phone }}</strong> en rappelant la référence {{ $inscription->number }}.@endif
                </p>
                <p style="margin:24px 0 0;font-size:12px;color:#66748a">Vous recevez cet e-mail parce que vous vous êtes inscrit(e) à une formation de {{ $site->get('company.name') }}. Si ce n'est pas vous, ignorez-le simplement.</p>
            @endif
        </td></tr>
    </table>
</body>
</html>
