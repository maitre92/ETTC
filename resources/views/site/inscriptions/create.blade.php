@extends('layouts.site')

@section('whatsapp_message', 'Bonjour, je souhaite m\'inscrire à une formation d\'anglais.')

{{-- Inscription en ligne (cahier §3.5) en 4 étapes : niveau → formule et mode → informations personnelles → paiement et
     confirmation. Assistant animé et récapitulatif calculé en direct (ettc.js, [data-ettc-wizard]) ; sans JavaScript,
     tout le formulaire est visible et s'envoie normalement. Tarifs lus dans la base (module Formations). --}}
@section('content')
    @include('site.partials.page-hero', ['title' => 'Inscription à une formation', 'crumb' => 'Inscription', 'intro' => 'Choisissez votre niveau, votre formule et votre mode : le tarif s\'affiche aussitôt. Le test de niveau est gratuit.'])

    @php
        $me = auth('client')->user();
        $steps = ['Niveau', 'Formule', 'Vos informations', 'Paiement'];
        $presetNiveau = old('niveau_id', $preset['niveau']);
    @endphp

    <section class="ettc-section">
        <div class="container">
            @if (session('demande_sent'))
                <div class="form-success" role="status" tabindex="-1">
                    <span class="form-success-icon"><i class='bx bx-check' aria-hidden="true"></i></span>
                    <h2 class="h4">{{ session('demande_sent')['title'] }}</h2>
                    <p class="mb-0">{{ session('demande_sent')['message'] }}</p>
                </div>
            @elseif ($formules->isEmpty())
                <div class="form-success"><h2 class="h4">Inscriptions bientôt ouvertes</h2><p class="mb-0">Les formules seront publiées très prochainement. Contactez-nous pour en savoir plus.</p></div>
            @else
                <form method="POST" action="{{ route('site.inscription.store') }}" class="ettc-wizard" data-ajax-form data-ettc-wizard novalidate>
                    @csrf
                    <ol class="ettc-wizard-steps" aria-hidden="true">
                        @foreach ($steps as $stepLabel)
                            <li class="{{ $loop->first ? 'is-current' : '' }}"><span>{{ $loop->iteration }}</span>{{ $stepLabel }}</li>
                        @endforeach
                    </ol>
                    <div class="alert alert-danger" data-error-summary tabindex="-1" @if (! $errors->any()) hidden @endif role="alert">
                        <i class='bx bx-error-circle' aria-hidden="true"></i> Votre inscription n'a pas été envoyée : vérifiez les champs signalés. Vos informations sont conservées.
                    </div>

                    {{-- 1. Niveau --}}
                    <fieldset class="ettc-step" data-step>
                        <legend class="h4 text-ink">1. Votre niveau</legend>
                        <p class="text-muted">Vous ne le connaissez pas ? Choisissez « À déterminer » : le centre vous fait passer le test de niveau, gratuitement.</p>
                        <div class="ettc-choices">
                            @foreach ($niveaux as $niveau)
                                <label class="ettc-choice form-check">
                                    <input class="form-check-input" type="radio" name="niveau_id" value="{{ $niveau->id }}" data-label="{{ $niveau->name }}" @checked((string) $presetNiveau === (string) $niveau->id)>
                                    <strong>{{ $niveau->name }}</strong>
                                    <small>{{ $niveau->summary }}</small>
                                </label>
                            @endforeach
                            <label class="ettc-choice ettc-choice-soft form-check">
                                <input class="form-check-input" type="radio" name="niveau_id" value="" data-label="À déterminer par le test (gratuit)" @checked($presetNiveau === null || $presetNiveau === '')>
                                <strong>À déterminer</strong>
                                <small>Je passe d'abord le test de niveau gratuit.</small>
                            </label>
                        </div>
                    </fieldset>

                    {{-- 2. Formule et mode --}}
                    <fieldset class="ettc-step" data-step>
                        <legend class="h4 text-ink">2. Votre formule et votre mode</legend>
                        <div class="ettc-choices">
                            @foreach ($formules as $formule)
                                <label class="ettc-choice form-check">
                                    <input class="form-check-input" type="radio" name="formule_id" value="{{ $formule->id }}" required
                                           data-label="{{ $formule->name }}" data-months="{{ $formule->duration_months }}" data-billing="{{ $formule->billing->value }}"
                                           data-price-en_ligne="{{ $formule->price_online }}" data-price-presentiel="{{ $formule->price_onsite }}"
                                           @checked((string) old('formule_id', $preset['formule']) === (string) $formule->id)>
                                    <strong>{{ $formule->name }}</strong>
                                    @foreach ($formule->prices() as [$modeLabel, $amount])
                                        <small>{{ $modeLabel }} : <b>{{ \App\Models\Formule::money($amount) }}</b> {{ $formule->billing->suffix() }}</small>
                                    @endforeach
                                </label>
                            @endforeach
                        </div>
                        <p class="fw-semibold mt-3 mb-2 text-ink">Mode de formation</p>
                        <div class="ettc-choices ettc-choices-2">
                            @foreach (\App\Enums\FormationMode::cases() as $mode)
                                <label class="ettc-choice form-check">
                                    <input class="form-check-input" type="radio" name="mode" value="{{ $mode->value }}" required data-label="{{ $mode->label() }}" @checked(old('mode', $preset['mode']) === $mode->value)>
                                    <strong><i class='bx {{ $mode === \App\Enums\FormationMode::EnLigne ? 'bx-video' : 'bx-building-house' }}' aria-hidden="true"></i> {{ $mode->label() }}</strong>
                                    <small>{{ $mode === \App\Enums\FormationMode::EnLigne ? 'Séances en direct avec un formateur sur Google Meet.' : 'Cours au centre, à Ségou.' }}</small>
                                </label>
                            @endforeach
                        </div>
                        <div class="ettc-price-live" data-price-live aria-live="polite" hidden></div>
                    </fieldset>

                    {{-- 3. Informations personnelles --}}
                    <fieldset class="ettc-step" data-step>
                        <legend class="h4 text-ink">3. Vos informations</legend>
                        <div class="row g-3">
                            <div class="col-md-8">@include('site.demandes._field', ['name' => 'name', 'label' => 'Nom et prénom', 'required' => true, 'max' => 150, 'autocomplete' => 'name', 'value' => $me?->name])</div>
                            <div class="col-md-4">@include('site.demandes._field', ['name' => 'gender', 'label' => 'Sexe', 'type' => 'select', 'options' => \App\Enums\Gender::options(), 'value' => $me?->gender?->value])</div>
                            <div class="col-md-4">@include('site.demandes._field', ['name' => 'birth_date', 'label' => 'Date de naissance', 'type' => 'date', 'value' => $me?->birth_date?->format('Y-m-d')])</div>
                            <div class="col-md-4">@include('site.demandes._field', ['name' => 'phone', 'label' => 'Téléphone', 'type' => 'tel', 'required' => true, 'max' => 30, 'autocomplete' => 'tel', 'placeholder' => '75 22 91 25', 'value' => $me?->phone])</div>
                            <div class="col-md-4">@include('site.demandes._field', ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'max' => 190, 'autocomplete' => 'email', 'placeholder' => 'vous@exemple.com', 'value' => $me?->email, 'help' => 'Pour recevoir votre récapitulatif.'])</div>
                            <div class="col-md-8">@include('site.demandes._field', ['name' => 'address', 'label' => 'Adresse de résidence', 'max' => 300, 'autocomplete' => 'street-address', 'value' => $me?->address])</div>
                            <div class="col-md-4">@include('site.demandes._field', ['name' => 'city', 'label' => 'Ville', 'max' => 100, 'autocomplete' => 'address-level2', 'value' => $me?->city ?? 'Ségou'])</div>
                            <div class="col-12">@include('site.demandes._field', ['name' => 'message', 'label' => 'Informations utiles (facultatif)', 'type' => 'textarea', 'max' => 2000, 'rows' => 3, 'placeholder' => 'Vos disponibilités, votre objectif (études, travail, voyage)…'])</div>
                        </div>
                    </fieldset>

                    {{-- 4. Paiement et confirmation --}}
                    <fieldset class="ettc-step" data-step>
                        <legend class="h4 text-ink">4. Paiement et confirmation</legend>
                        <p class="text-muted">Formation <strong>en présentiel</strong> : paiement au centre. Formation <strong>en ligne</strong> : paiement par <strong>mobile money</strong>. Votre inscription est confirmée dès que le centre a reçu le paiement, et un reçu vous est délivré.</p>
                        <div class="ettc-choices ettc-choices-2">
                            @foreach (\App\Enums\PaymentChoice::cases() as $choice)
                                <label class="ettc-choice form-check">
                                    <input class="form-check-input" type="radio" name="payment_choice" value="{{ $choice->value }}" required data-label="{{ $choice->label() }}" data-payment-for="{{ $choice === \App\Enums\PaymentChoice::AuCentre ? 'presentiel' : 'en_ligne' }}" @checked(old('payment_choice', ($preset['mode'] ?? null) === 'en_ligne' ? 'en_ligne' : 'au_centre') === $choice->value)>
                                    <strong><i class='bx {{ $choice === \App\Enums\PaymentChoice::AuCentre ? 'bx-store' : 'bx-mobile-alt' }}' aria-hidden="true"></i> {{ $choice === \App\Enums\PaymentChoice::AuCentre ? 'Payer au centre' : 'Payer en ligne' }}</strong>
                                    <small>{{ $choice === \App\Enums\PaymentChoice::AuCentre ? 'Vous réglez directement au centre, à l\'accueil.' : 'Par mobile money (Orange Money, Wave, Moov Money) : vous indiquez ensuite la référence de la transaction dans votre espace.' }}</small>
                                </label>
                            @endforeach
                        </div>
                        <div class="ettc-recap" data-recap hidden>
                            <h2 class="h5 text-ink">Récapitulatif</h2>
                            <dl></dl>
                        </div>
                        <div class="row g-3 mt-1">
                            @include('site.demandes._footer', ['submit' => 'Confirmer mon inscription'])
                        </div>
                    </fieldset>

                    <div class="ettc-wizard-nav" data-wizard-nav hidden>
                        <button type="button" class="ettc-btn ettc-btn-light" data-wizard-prev><i class='bx bx-left-arrow-alt' aria-hidden="true"></i> Précédent</button>
                        <button type="button" class="ettc-btn ettc-btn-blue" data-wizard-next>Suivant <i class='bx bx-right-arrow-alt' aria-hidden="true"></i></button>
                    </div>
                </form>
            @endif
        </div>
    </section>
@endsection
