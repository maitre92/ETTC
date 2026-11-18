{{-- En-tête commun à TOUS les PDF (ticket thermique, reçu A5, rapport financier), modèle du ticket : logo centré, sigle,
     nom du centre, ville · téléphone, trait pointillé. Styles en ligne (indépendants de la page). $site, $logo,
     $title (ex. « REÇU DE PAIEMENT »), $subtitle (ex. « N° REC-2026-0001 »), $logoSize (64 par défaut). --}}
@php $logoSize = $logoSize ?? 64; @endphp
<div style="text-align: center">
    @if ($logo)<img src="{{ $logo }}" width="{{ $logoSize }}" height="{{ $logoSize }}" alt="" style="margin-bottom: 3px">@endif
    <div style="font-weight: bold; font-size: 1.25em; text-transform: uppercase">{{ $site->get('company.short_name') }}</div>
    <div>{{ $site->get('company.name') }}</div>
    <div>{{ $site->get('contact.city') }}@if ($site->get('contact.phone_1')) · {{ $site->get('contact.phone_1') }}@endif</div>
    <div style="border-top: 1px dashed #000; margin: 6px 0"></div>
    @if (! empty($title))<div><strong>{{ $title }}</strong></div>@endif
    @if (! empty($subtitle))<div>{{ $subtitle }}</div>@endif
</div>
<div style="border-top: 1px dashed #000; margin: 6px 0"></div>
