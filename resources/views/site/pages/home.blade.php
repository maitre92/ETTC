@extends('layouts.site')

{{-- Accueil d'après la maquette validée (English Training/maquette.jpeg, 2026-11-17) : bandeau (titre en italique,
     deux appels à l'action, apprenante, carte du Mali, bulles Hello! / Bonjour !), nos services (4 cartes teintées),
     nos formules de formation (2 mois, 3 mois, formation personnalisée, test de niveau), pourquoi nous choisir et
     témoignages en carrousel. Textes et tarifs : Pages et paramètres → Accueil. Styles : assets/site/css/ettc-theme.css. --}}
@section('content')
    @php
        $services = Route::has('site.services') ? route('site.services') : null;
        $formations = route('site.formations.index');
        $demande = fn (string $type) => Route::has('site.devis') ? route('site.devis', ['type' => $type]) : route('site.contact');
        $inscription = Route::has('client.register') ? route('client.register') : route('site.contact');
        $serviceCards = [
            ['blue', 'bxs-graduation', 'Formations par niveau', $niveauxLabel ?: 'Débutant, Intermédiaire et Avancé', 'toque-livres.svg', 'Toque de diplômé sur une pile de livres', $formations],
            ['green', 'bx-desktop', 'Cours en ligne interactifs', 'Séances en direct via Google Meet', 'ordinateur-visio.svg', 'Ordinateur portable en visioconférence', $services ? $services.'#cours-en-ligne-interactifs' : route('site.home').'#formules'],
            ['yellow', 'bx-transfer-alt', 'Traduction professionnelle', 'Traduction de documents et dossiers (FR ⇄ EN)', 'documents-stylo.svg', 'Documents à traduire et stylo', $demande('traduction')],
            ['green', 'bxs-plane-take-off', 'Excursion au Ghana', 'Immersion linguistique et culturelle', 'arc-ghana.webp', 'Arc de l\'Indépendance à Accra et drapeau du Ghana', $demande('excursion')],
        ];
        $whyColors = ['yellow', 'blue', 'green', 'orange'];
    @endphp

    {{-- ——— Bandeau ——— --}}
    <section class="ettc-hero">
        <div class="container ettc-hero-inner">
            <div class="ettc-hero-text">
                <h1>{!! nl2br(e($site->get('home.hero_title'))) !!}</h1>
                <p class="ettc-hero-subtitle">{!! nl2br(e($site->get('home.hero_subtitle'))) !!}</p>
                <div class="ettc-hero-actions">
                    <a href="{{ $formations }}" class="ettc-btn ettc-btn-blue"><i class='bx bxs-graduation' aria-hidden="true"></i> Découvrir nos formations</a>
                    <a href="{{ $inscription }}" class="ettc-btn ettc-btn-orange"><i class='bx bxs-user-plus' aria-hidden="true"></i> S'inscrire maintenant</a>
                </div>
            </div>
            <div class="ettc-hero-visual" aria-hidden="true">
                <img src="{{ asset('assets/site/img/accueil/arc-ghana.webp') }}" alt="" class="ettc-hero-building" width="640" height="500">
                <img src="{{ asset('assets/site/img/accueil/carte-mali.svg') }}" alt="" class="ettc-hero-map" width="200" height="180">
                <img src="{{ $site->imageUrl('home.hero_image', 'assets/site/img/accueil/apprenante.webp') }}" alt="" class="ettc-hero-person" width="760" height="824" fetchpriority="high">
                <span class="ettc-bubble ettc-bubble-hello">Hello!</span>
                <span class="ettc-bubble ettc-bubble-bonjour">Bonjour&nbsp;!</span>
                <span class="ettc-sparks"><i></i><i></i><i></i></span>
            </div>
        </div>
        <svg class="ettc-hero-waves" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true">
            <path d="M0 70 C 360 10, 760 130, 1440 30 L1440 120 L0 120 Z" fill="#ffffff"/>
            <path d="M0 58 C 380 0, 760 118, 1440 18" fill="none" stroke="#f9d232" stroke-width="10"/>
            <path d="M0 78 C 380 22, 760 140, 1440 42" fill="none" stroke="#1376ba" stroke-width="6"/>
            <path d="M720 96 C 980 108, 1200 70, 1440 46" fill="none" stroke="#4ab248" stroke-width="6"/>
        </svg>
    </section>

    {{-- ——— Nos services ——— --}}
    <section class="ettc-section" id="services">
        <div class="container">
            <h2 class="ettc-title"><span>Nos services</span></h2>
            <div class="ettc-services">
                @foreach ($serviceCards as [$tone, $icon, $cardTitle, $cardText, $image, $imageAlt, $url])
                    <a href="{{ $url }}" class="ettc-service ettc-tone-{{ $tone }}">
                        <span class="ettc-service-head">
                            <span class="ettc-service-icon"><i class='bx {{ $icon }}' aria-hidden="true"></i></span>
                            <span>
                                <strong>{{ $cardTitle }}</strong>
                                <small>{{ $cardText }}</small>
                            </span>
                        </span>
                        <img src="{{ asset('assets/site/img/accueil/'.$image) }}" alt="{{ $imageAlt }}" class="ettc-service-img" loading="lazy">
                        <span class="ettc-service-go" aria-hidden="true"><i class='bx bx-right-arrow-alt'></i></span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ——— Nos formules de formation ——— --}}
    <section class="ettc-section pt-0" id="formules">
        <div class="container">
            <h2 class="ettc-title"><span>Nos formules de formation</span></h2>
            <div class="ettc-formulas">
                {{-- Formules et tarifs : module Formations (modifiables à tout moment dans l'administration). --}}
                @foreach ($formules as $formule)
                    @include('site.formations._formule', ['formule' => $formule])
                @endforeach
                <div class="ettc-formula-side">
                    <a href="{{ $demande('formation_personnalisee') }}" class="ettc-side-card ettc-tone-blue">
                        <i class='bx bxs-group' aria-hidden="true"></i>
                        <span><strong>Formation personnalisée</strong><small>Une formation adaptée à vos besoins avec un entretien préalable.</small></span>
                    </a>
                    <a href="{{ $formations }}#test-de-niveau" class="ettc-side-card ettc-tone-yellow">
                        <i class='bx bx-clipboard' aria-hidden="true"></i>
                        <span><strong>Test de niveau</strong><small>Évaluez votre niveau avant de choisir votre formule.</small></span>
                        <span class="ettc-test-price">{{ \App\Models\Formule::money($testFee) }}</span>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- ——— Pourquoi nous choisir + témoignages ——— --}}
    <section class="ettc-section pt-0">
        <div class="container ettc-why-row">
            <div class="ettc-why">
                <h2 class="ettc-title ettc-title-sm"><span>{{ $site->get('home.why_title') }}</span></h2>
                <div class="ettc-why-grid">
                    @foreach ($site->rows('home.values', 2) as $index => [$icon, $valueText])
                        <div class="ettc-why-item ettc-tone-{{ $whyColors[$index % 4] }}">
                            <i class='bx {{ $icon ?: 'bxs-check-circle' }}' aria-hidden="true"></i>
                            <span>{{ $valueText }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            @if ($temoignages->isNotEmpty())
                <div class="ettc-testimonials" data-ettc-carousel>
                    <h2 class="ettc-title ettc-title-sm"><span>Témoignages</span></h2>
                    <div class="ettc-testimonial-frame">
                        <button type="button" class="ettc-carousel-btn" data-prev aria-label="Témoignage précédent"><i class='bx bx-chevron-left' aria-hidden="true"></i></button>
                        <div class="ettc-testimonial-track" aria-live="polite">
                            @foreach ($temoignages as $temoignage)
                                <figure class="ettc-testimonial {{ $loop->first ? 'is-active' : '' }}" @unless ($loop->first) hidden @endunless>
                                    @if ($temoignage->photo)
                                        <img src="{{ $temoignage->photo->thumbUrl() }}" alt="" width="64" height="64" loading="lazy">
                                    @else
                                        <span class="ettc-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($temoignage->author_name, 0, 1)) }}</span>
                                    @endif
                                    <div>
                                        <blockquote>« {{ \Illuminate\Support\Str::limit($temoignage->content, 160) }} »</blockquote>
                                        <figcaption>{{ $temoignage->author_name }}</figcaption>
                                    </div>
                                </figure>
                            @endforeach
                        </div>
                        <button type="button" class="ettc-carousel-btn" data-next aria-label="Témoignage suivant"><i class='bx bx-chevron-right' aria-hidden="true"></i></button>
                    </div>
                    @if ($temoignages->count() > 1)
                        <div class="ettc-dots">
                            @foreach ($temoignages as $temoignage)
                                <button type="button" class="{{ $loop->first ? 'is-active' : '' }}" data-dot="{{ $loop->index }}" aria-label="Témoignage {{ $loop->iteration }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endsection
