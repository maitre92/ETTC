{{-- Annonce du centre à un apprenant. $annonce, $learnerName. --}}
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"><title>{{ $annonce->title }}</title></head>
<body style="margin:0;padding:24px;background:#f4f8fc;font-family:Arial,Helvetica,sans-serif;color:#10183f">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:620px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden">
        <tr><td style="background:#045eb5;color:#ffffff;padding:20px 24px">
            <div style="font-size:22px;font-weight:bold">{{ $site->get('company.short_name') }}<span style="color:#f9d232">.</span></div>
            <div style="font-size:15px;margin-top:6px">Annonce — {{ $annonce->title }}</div>
        </td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.6">
            <p style="margin-top:0">Bonjour {{ $learnerName }},</p>
            <div>{!! nl2br(e($annonce->body)) !!}</div>
            <p style="margin:24px 0 0"><a href="{{ route('client.calendrier') }}" style="display:inline-block;background:#f96809;color:#ffffff;text-decoration:none;font-weight:bold;padding:12px 20px;border-radius:8px">Voir mon espace</a></p>
        </td></tr>
    </table>
</body>
</html>
