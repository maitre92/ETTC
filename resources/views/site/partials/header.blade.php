{{-- En-tête d'après la maquette validée (English Training/maquette.jpeg, 2026-11-17) : logo, nom sur deux lignes et
     slogan ; à droite ville, téléphone et réseaux ; barre de menu bleue (Accueil souligné en jaune, « Formations »
     déroulant, « Mon espace » jaune). Les pages dédiées (traduction, excursion, test de niveau) viendront avec leurs
     modules : en attendant, les entrées mènent à la page des formations et au formulaire de demande prérempli. --}}
@php
    $phones = array_values(array_filter([$site->get('contact.phone_1'), $site->get('contact.phone_2')]));
    $tel = \App\Support\PhoneNumber::normalize($phones[0] ?? null);
    $logoUrl = $site->imageUrl('site.logo', 'assets/site/img/logo-ettc.png');
    $clientUser = auth('client')->user();
    $services = Route::has('site.services') ? route('site.services') : null;
    $demande = fn (string $type) => Route::has('site.devis') ? route('site.devis', ['type' => $type]) : null;
    // [libellé, adresse, motif de route active]
    $menu = array_filter([
        ['À propos', Route::has('site.about') ? route('site.about') : null, 'site.about'],
        'formations',
        ['Traduction', $demande('traduction'), null],
        ['Excursion au Ghana', $demande('excursion'), null],
        ['Test de niveau', route('site.tests.show'), 'site.tests.*'],
        ['Actualités', Route::has('site.actualites.index') ? route('site.actualites.index') : null, 'site.actualites.*'],
        ['Contact', Route::has('site.contact') ? route('site.contact') : null, 'site.contact*'],
    ], fn ($item) => $item === 'formations' || $item[1] !== null);
    $formations = array_filter([
        ['Nos formations par niveau', route('site.formations.index')],
        ['Formules et tarifs', route('site.formations.index').'#tarifs'],
        ['Réserver mon test de niveau', route('site.tests.show').'#reserver'],
        ['Formation personnalisée', $demande('formation_personnalisee')],
        ['Tous nos services', $services],
    ], fn ($item) => $item[1] !== null);
@endphp
<header class="ettc-header">
    <div class="container ettc-brandbar">
        <a class="ettc-brand" href="{{ route('site.home') }}" aria-label="{{ $site->get('company.name') }} — accueil">
            <img src="{{ $logoUrl }}" alt="" width="110" height="96" class="ettc-brand-logo">
            <span class="ettc-brand-text">
                <strong>English Training and<br>Translation Center</strong>
                <small>{{ $site->get('company.slogan') }}</small>
            </span>
        </a>
        <div class="ettc-contactbar">
            @if ($site->get('contact.city'))
                <span class="ettc-contact-item"><i class='bx bxs-map' aria-hidden="true"></i>{{ $site->get('contact.city') }}</span>
            @endif
            @if ($phones)
                <span class="ettc-contact-item"><i class='bx bxs-phone' aria-hidden="true"></i>@if ($tel)<a href="tel:{{ $tel }}">{{ $phones[0] }}</a>@else{{ $phones[0] }}@endif</span>
            @endif
            <button type="button" class="ettc-search-btn" data-search-open aria-label="Rechercher sur le site (touche /)" aria-haspopup="dialog"><i class='bx bx-search' aria-hidden="true"></i></button>
            @if ($site->socialLinks())
                <span class="ettc-social">
                    @foreach ($site->socialLinks() as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="social-{{ $network }}" aria-label="{{ ucfirst($network) }}"><i class='bx bxl-{{ $network }}' aria-hidden="true"></i></a>
                    @endforeach
                </span>
            @endif
        </div>
        <button class="ettc-menu-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Ouvrir le menu">
            <i class='bx bx-menu' aria-hidden="true"></i>
        </button>
    </div>

    <nav class="ettc-nav" aria-label="Navigation principale">
        <div class="container">
            <div class="collapse ettc-nav-collapse" id="mainNav">
                <ul class="ettc-menu">
                    <li><a href="{{ route('site.home') }}" class="{{ request()->routeIs('site.home') ? 'active' : '' }}" @if (request()->routeIs('site.home')) aria-current="page" @endif><i class='bx bxs-home' aria-hidden="true"></i> Accueil</a></li>
                    @foreach ($menu as $item)
                        @if ($item === 'formations')
                            @if ($formations)
                                <li class="dropdown">
                                    {{-- Bouton et non lien : Bootstrap 5.0 lit le href d'un déclencheur comme un sélecteur. --}}
                                    <button type="button" class="dropdown-toggle {{ request()->routeIs('site.services', 'site.formations.*') ? 'active' : '' }}" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">Formations</button>
                                    <ul class="dropdown-menu">
                                        @foreach ($formations as [$subLabel, $subUrl])
                                            <li><a class="dropdown-item" href="{{ $subUrl }}">{{ $subLabel }}</a></li>
                                        @endforeach
                                    </ul>
                                </li>
                            @endif
                        @else
                            @php
                                [$label, $url, $pattern] = $item;
                                $active = $pattern && request()->routeIs($pattern);
                            @endphp
                            <li><a href="{{ $url }}" class="{{ $active ? 'active' : '' }}" @if ($active) aria-current="page" @endif>{{ $label }}</a></li>
                        @endif
                    @endforeach
                </ul>
                <div class="ettc-nav-tools">
                    <a href="{{ route('site.search') }}" class="ettc-search-link" data-search-open><i class='bx bx-search' aria-hidden="true"></i> Rechercher</a>
                    @if (Route::has('client.home'))
                        <a href="{{ route('client.home') }}" class="ettc-account" title="{{ $clientUser ? 'Bonjour '.\Illuminate\Support\Str::before($clientUser->name, ' ') : 'Se connecter / S\'inscrire' }}">
                            <i class='bx bxs-user' aria-hidden="true"></i> Mon espace
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </nav>
</header>

{{-- Recherche en direct (site.js) : suggestions pendant la saisie, Entrée = page de résultats. --}}
<div class="search-overlay" id="search-overlay" role="dialog" aria-modal="true" aria-label="Rechercher sur le site" hidden data-suggest-url="{{ route('site.search.suggest') }}">
    <div class="search-box">
        <form method="GET" action="{{ route('site.search') }}" role="search">
            <i class='bx bx-search' aria-hidden="true"></i>
            <label for="search-overlay-input" class="visually-hidden">Rechercher</label>
            <input type="search" id="search-overlay-input" name="q" placeholder="Formation, traduction, actualité…" autocomplete="off" aria-controls="search-suggestions">
            <button type="button" class="search-close" data-search-close aria-label="Fermer la recherche"><i class='bx bx-x' aria-hidden="true"></i></button>
        </form>
        <div class="search-suggestions" id="search-suggestions" aria-live="polite"></div>
    </div>
</div>
