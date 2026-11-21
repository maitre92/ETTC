{{-- Message du formateur à son groupe, envoyé à un apprenant. $groupeMessage, $learnerName. --}}
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>Message de votre formateur</title></head>
<body style="margin:0;padding:24px;background:#f4f8fc;font-family:Arial,Helvetica,sans-serif;color:#10183f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden">
        <tr><td style="background:#045eb5;color:#ffffff;padding:20px 24px">
            @include('mail._logo')
            <div style="font-size:15px;margin-top:6px">Groupe {{ $groupeMessage->groupe->name }}</div>
        </td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.6">
            <p style="margin-top:0">Bonjour {{ $learnerName }},</p>
            <p><strong>{{ $groupeMessage->authorName() }}</strong> a écrit à votre groupe :</p>
            <div style="background:#f4f8fc;border-radius:8px;padding:12px 16px">{!! nl2br(e($groupeMessage->body)) !!}</div>
            <p style="margin:24px 0 0"><a href="{{ route('client.groupes.show', $groupeMessage->groupe_id) }}" style="display:inline-block;background:#f96809;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Lire et répondre</a></p>
        </td></tr>
    </table>
</body>
</html>
