@extends('layouts.site')

{{-- Excursion linguistique et culturelle au Ghana (cahier §3.10) : fiche entièrement modifiable (Pages et paramètres →
     Excursion au Ghana : présentation, dates, tarif, prestations, programme, conditions, réservation) et formulaire de
     réservation ou de renseignement (envoyé comme une demande). --}}
@section('whatsapp_message', 'Bonjour, je souhaite des informations sur l\'excursion au Ghana.')

@section('content')
    @include('site.partials.page-hero', ['title' => 'Excursion au Ghana', 'crumb' => 'Excursion au Ghana', 'intro' => 'Une immersion linguistique et culturelle pour pratiquer l\'anglais en situation réelle.'])

    @php
        $included = $site->lines('excursion.included');
        $dates = $site->get('excursion.dates');
        $price = $site->get('excursion.price');
    @endphp
    <section class="section">
        <div class="container">
            <div class="row g-4 mb-4">
                <div class="col-md-6"><div class="detail-panel h-100"><p class="small text-muted mb-1"><i class='bx bx-calendar' aria-hidden="true"></i> Dates</p><p class="h5 mb-0">{{ $dates !== '' ? $dates : 'Communiquées sur demande' }}</p></div></div>
                <div class="col-md-6"><div class="detail-panel h-100"><p class="small text-muted mb-1"><i class='bx bx-purchase-tag' aria-hidden="true"></i> Tarif</p><p class="h5 mb-0">{{ $price !== '' ? $price : 'Sur demande' }}</p></div></div>
            </div>
            <div class="row g-5">
                <div class="col-lg-6">
                    <div class="prose">{!! \App\Support\Markdown::toHtml($site->get('excursion.presentation')) !!}</div>
                    @if ($included)
                        <h2 class="h4 mt-4">Prestations incluses</h2>
                        <ul class="list-unstyled">@foreach ($included as $item)<li class="mb-1"><i class='bx bx-check-circle text-success' aria-hidden="true"></i> {{ $item }}</li>@endforeach</ul>
                    @endif
                    <h2 class="h4 mt-4">Programme et activités</h2>
                    <div class="prose">{!! \App\Support\Markdown::toHtml($site->get('excursion.program')) !!}</div>
                    <h2 class="h4 mt-4">Conditions de participation</h2>
                    <div class="prose">{!! \App\Support\Markdown::toHtml($site->get('excursion.conditions')) !!}</div>
                    <h2 class="h4 mt-4">Réservation et paiement</h2>
                    <div class="prose">{!! \App\Support\Markdown::toHtml($site->get('excursion.booking')) !!}</div>
                </div>
                <div class="col-lg-6">
                    <h2 class="h4">Réserver ou se renseigner</h2>
                    @component('site.demandes._form', ['action' => route('site.devis.store'), 'submit' => 'Envoyer'])
                        <input type="hidden" name="need_type" value="excursion">
                        @include('site.demandes._identity')
                        <div class="col-md-4">@include('site.demandes._field', ['name' => 'participants', 'label' => 'Participants', 'type' => 'number', 'max' => 3, 'value' => 1])</div>
                        <div class="col-md-8">@include('site.demandes._field', ['name' => 'message', 'label' => 'Vos questions (facultatif)', 'max' => 5000, 'placeholder' => 'Dates, documents de voyage, paiement…'])</div>
                    @endcomponent
                </div>
            </div>
        </div>
    </section>
@endsection
