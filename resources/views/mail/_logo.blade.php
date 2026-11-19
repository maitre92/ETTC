{{-- En-tête commun des e-mails : logo du centre JOINT à l'e-mail (image intégrée : s'affiche même hors ligne ou depuis le
     local) sur une pastille blanche, et le nom du centre. Logo de « Pages et paramètres → Entreprise », sinon celui du site. --}}
@php
    $logoMedia = (int) $site->get('site.logo') ? \App\Models\Media::find((int) $site->get('site.logo')) : null;
    $logoPath = $logoMedia && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoMedia->path)
        ? \Illuminate\Support\Facades\Storage::disk('public')->path($logoMedia->path)
        : public_path('assets/site/img/logo-ettc.png');
    $logoSrc = isset($message) && method_exists($message, 'embed') && is_file($logoPath)
        ? $message->embed($logoPath)
        : $site->imageUrl('site.logo', 'assets/site/img/logo-ettc.png');
@endphp
<table role="presentation" cellpadding="0" cellspacing="0"><tr>
    <td style="background:#ffffff;border-radius:50%;padding:4px;width:56px;height:56px"><img src="{{ $logoSrc }}" alt="{{ $site->get('company.short_name') }}" width="56" height="56" style="display:block;width:56px;height:56px;border-radius:50%"></td>
    <td style="padding-left:12px;color:#ffffff;font-size:14px;line-height:1.3"><strong>{{ $site->get('company.name') }}</strong><br>{{ $site->get('company.short_name') }}</td>
</tr></table>
