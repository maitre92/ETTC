@extends('layouts.site')

{{-- Formation personnalisée (cahier §3.7) : présentation (Pages et paramètres → Formation personnalisée) et demande :
     objectifs, disponibilités, coordonnées. Le centre propose un entretien puis une offre chiffrée, acceptée en ligne. --}}
@section('whatsapp_message', 'Bonjour, je souhaite une formation personnalisée.')

@section('content')
    @include('site.partials.page-hero', ['title' => 'Formation personnalisée', 'crumb' => 'Formation personnalisée', 'intro' => 'Décrivez vos objectifs : nous en parlons ensemble, puis vous recevez une proposition adaptée.'])

    <section class="section">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-5">
                    <div class="prose">{!! \App\Support\Markdown::toHtml($site->get('personnalisee.presentation')) !!}</div>
                </div>
                <div class="col-lg-7">
                    @component('site.demandes._form', ['action' => route('site.devis.store'), 'submit' => 'Envoyer ma demande'])
                        <input type="hidden" name="need_type" value="formation_personnalisee">
                        <div class="col-12">@include('site.demandes._field', ['name' => 'message', 'label' => 'Vos objectifs', 'type' => 'textarea', 'required' => true, 'max' => 5000, 'rows' => 5, 'placeholder' => 'Ex. préparer un entretien en anglais dans 2 mois, niveau débutant, cours le soir…'])</div>
                        <div class="col-12">@include('site.demandes._field', ['name' => 'availability', 'label' => 'Vos disponibilités (facultatif)', 'max' => 300, 'placeholder' => 'Ex. en semaine après 17 h, le samedi matin'])</div>
                        @include('site.demandes._identity')
                    @endcomponent
                </div>
            </div>
        </div>
    </section>
@endsection
