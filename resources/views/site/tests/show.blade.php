@extends('layouts.site')

@section('whatsapp_message', 'Bonjour, je souhaite passer le test de niveau d\'anglais.')

{{-- Test de niveau (cahier §3.4) : présentation et conditions (Tests de niveau → Présentation, dans l'administration),
     test gratuit (décision du centre), déroulement, demande ou réservation envoyée en AJAX. --}}
@section('content')
    @include('site.partials.page-hero', ['title' => 'Test de niveau', 'intro' => 'Avant votre formation, un test évalue vos connaissances et vous oriente vers le niveau qui vous correspond.'])

    <section class="ettc-section">
        <div class="container">
            <div class="ettc-test-layout">
                <div>
                    <div class="ettc-side-card ettc-tone-yellow ettc-test-card mb-4">
                        <i class='bx bx-clipboard' aria-hidden="true"></i>
                        <span><strong>Un test gratuit</strong><small>Le test de niveau ne coûte rien : seule l'inscription à une formation est payante.</small></span>
                        <span class="ettc-test-price">Gratuit</span>
                    </div>
                    {{-- Test EN LIGNE, instantané (2026-11-23) : tout visiteur, résultat aussitôt. --}}
                    @if ($online)
                        <div class="online-test-card mb-4" id="en-ligne">
                            <h2 class="h4 mb-1"><i class='bx bx-laptop' aria-hidden="true"></i> Passer le test en ligne maintenant</h2>
                            <p class="mb-3">{{ $onlineCount }} questions à choix multiples{{ $onlineSpeaking ? ' et '.$onlineSpeaking.' sujet(s) à l\'oral (vous vous enregistrez au micro)' : '' }} · {{ $onlineMinutes }} minutes · <strong>résultat immédiat</strong>.</p>
                            @if ($errors->hasAny(['name', 'phone', 'email', 'consent']) && old('online_start'))
                                <div class="alert alert-danger small">{{ $errors->first() }}</div>
                            @endif
                            <form method="POST" action="{{ route('site.tests.online.start') }}" class="row g-2">
                                @csrf
                                <input type="hidden" name="online_start" value="1">
                                <div class="col-md-6"><label class="form-label" for="ol-name">Nom complet</label><input class="form-control" id="ol-name" name="name" maxlength="150" required autocomplete="name" value="{{ old('online_start') ? old('name') : auth('client')->user()?->name }}"></div>
                                <div class="col-md-6"><label class="form-label" for="ol-phone">Téléphone</label><input type="tel" class="form-control" id="ol-phone" name="phone" maxlength="30" required autocomplete="tel" placeholder="75 22 91 25" value="{{ old('online_start') ? old('phone') : auth('client')->user()?->phone }}"></div>
                                <div class="col-12"><label class="form-label" for="ol-email">E-mail (facultatif, pour recevoir votre résultat)</label><input type="email" class="form-control" id="ol-email" name="email" maxlength="190" autocomplete="email" value="{{ old('online_start') ? old('email') : auth('client')->user()?->email }}"></div>
                                <div class="col-12 form-check ms-1"><input class="form-check-input" type="checkbox" id="ol-consent" name="consent" value="1" required><label class="form-check-label small" for="ol-consent">J'accepte que le centre conserve mon résultat pour m'orienter (<a href="{{ route('site.privacy') }}" target="_blank" rel="noopener">confidentialité</a>).</label></div>
                                <div class="hp-field" aria-hidden="true"><label for="ol-site-web">Ne pas remplir ce champ</label><input type="text" id="ol-site-web" name="{{ \App\Support\SpamGuard::HONEYPOT }}" tabindex="-1" autocomplete="off"></div>
                                <input type="hidden" name="{{ \App\Support\SpamGuard::TOKEN }}" value="{{ \App\Support\SpamGuard::token() }}">
                                <div class="col-12"><button type="submit" class="ettc-btn ettc-btn-orange"><i class='bx bx-play-circle' aria-hidden="true"></i> Commencer le test</button></div>
                            </form>
                        </div>
                    @endif
                    <div class="prose ettc-test-presentation">{!! \App\Support\Markdown::toHtml($site->get('test.presentation')) !!}</div>
                    <h2 class="h5 text-ink mt-4">Comment ça se passe&nbsp;?</h2>
                    <ol class="ettc-steps">
                        <li><strong>Vous passez le test en ligne</strong> tout de suite (résultat immédiat), <strong>ou vous le réservez</strong> au centre ou à distance avec un formateur.</li>
                        <li><strong>Nous fixons la date</strong> ensemble ; vous recevez une convocation.</li>
                        <li><strong>Vous passez le test</strong> au centre ou à distance.</li>
                        <li><strong>Vous découvrez votre niveau</strong> et la formule qui vous convient.</li>
                    </ol>
                    @if (Route::has('site.formations.index'))
                        <a href="{{ route('site.formations.index') }}" class="ettc-btn ettc-btn-blue"><i class='bx bxs-graduation' aria-hidden="true"></i> Voir les formations et tarifs</a>
                    @endif
                </div>
                <div id="reserver">
                    <h2 class="h4 text-ink mb-3">Réserver mon test</h2>
                    @component('site.demandes._form', ['action' => route('site.tests.store'), 'submit' => 'Réserver mon test'])
                        @php $me = auth('client')->user(); @endphp
                        <div class="col-12">@include('site.demandes._field', ['name' => 'name', 'label' => 'Nom complet', 'required' => true, 'max' => 150, 'autocomplete' => 'name', 'value' => $me?->name])</div>
                        <div class="col-md-6">@include('site.demandes._field', ['name' => 'phone', 'label' => 'Téléphone', 'type' => 'tel', 'max' => 30, 'autocomplete' => 'tel', 'placeholder' => '75 22 91 25', 'value' => $me?->phone])</div>
                        <div class="col-md-6">@include('site.demandes._field', ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'max' => 190, 'autocomplete' => 'email', 'placeholder' => 'vous@exemple.com', 'value' => $me?->email])</div>
                        <div class="col-12"><p class="form-text mt-0"><i class='bx bx-info-circle' aria-hidden="true"></i> Indiquez au moins un moyen de vous joindre : téléphone ou e-mail.</p></div>
                        <div class="col-md-6">@include('site.demandes._field', ['name' => 'test_mode', 'label' => 'Je préfère passer le test', 'type' => 'select', 'required' => true, 'options' => \App\Enums\TestMode::options(), 'value' => old('test_mode', 'centre')])</div>
                        <div class="col-md-6">@include('site.demandes._field', ['name' => 'preferred_slot', 'label' => 'Mes disponibilités', 'max' => 200, 'placeholder' => 'Samedi matin, après 17 h…'])</div>
                        <div class="col-12">@include('site.demandes._field', ['name' => 'message', 'label' => 'Message (facultatif)', 'type' => 'textarea', 'max' => 2000, 'rows' => 3, 'placeholder' => 'Votre objectif : études, travail, voyage…'])</div>
                    @endcomponent
                </div>
            </div>
        </div>
    </section>
@endsection
