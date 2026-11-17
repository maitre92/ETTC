{{-- Pied de page d'après la maquette validée (English Training/maquette.jpeg, 2026-11-17) : logo et nom, liens utiles,
     informations (ville, téléphone, e-mail), réseaux sociaux ; barre du bas avec le copyright et les pages légales. --}}
@php
    $links = array_filter([
        ['À propos de nous', Route::has('site.about') ? route('site.about') : null],
        ['Nos formations', route('site.formations.index')],
        ['Tarifs et formules', route('site.formations.index').'#tarifs'],
        ['Actualités', Route::has('site.actualites.index') ? route('site.actualites.index') : null],
        ['Contact', Route::has('site.contact') ? route('site.contact') : null],
    ], fn ($item) => $item[1] !== null);
    $phones = array_values(array_filter([$site->get('contact.phone_1'), $site->get('contact.phone_2')]));
    $tel = \App\Support\PhoneNumber::normalize($phones[0] ?? null);
@endphp
<footer class="ettc-footer">
    <div class="container ettc-footer-grid">
        <a class="ettc-footer-brand" href="{{ route('site.home') }}">
            <img src="{{ $site->imageUrl('site.logo', 'assets/site/img/logo-ettc.png') }}" alt="" width="92" height="80" loading="lazy">
            <strong>English Training and<br>Translation Center<br>({{ $site->get('company.short_name') }})</strong>
        </a>
        <div>
            <h2>Liens utiles</h2>
            <ul class="ettc-footer-links">
                @foreach ($links as [$label, $url])
                    <li><a href="{{ $url }}">{{ $label }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2>Informations</h2>
            <ul class="ettc-footer-info">
                @if ($site->get('contact.address') || $site->get('contact.city'))
                    <li><i class='bx bxs-map' aria-hidden="true"></i><span>{!! nl2br(e($site->get('contact.address') ?: $site->get('contact.city'))) !!}</span></li>
                @endif
                @if ($phones)
                    <li><i class='bx bxs-phone' aria-hidden="true"></i>@if ($tel)<a href="tel:{{ $tel }}">{{ implode(' / ', $phones) }}</a>@else<span>{{ implode(' / ', $phones) }}</span>@endif</li>
                @endif
                @if ($site->get('contact.email'))
                    <li><i class='bx bxs-envelope' aria-hidden="true"></i><a href="mailto:{{ $site->get('contact.email') }}">{{ $site->get('contact.email') }}</a></li>
                @endif
                @if ($site->get('contact.hours'))
                    <li><i class='bx bxs-time-five' aria-hidden="true"></i><span>{!! nl2br(e($site->get('contact.hours'))) !!}</span></li>
                @endif
            </ul>
        </div>
        <div>
            <h2>Suivez-nous</h2>
            <div class="ettc-footer-social">
                @foreach ($site->socialLinks() as $network => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="social-{{ $network }}" aria-label="{{ ucfirst($network) }}"><i class='bx bxl-{{ $network }}' aria-hidden="true"></i></a>
                @endforeach
                @if ($site->whatsappLink())
                    <a href="{{ $site->whatsappLink('Bonjour,') }}" target="_blank" rel="noopener" class="social-whatsapp" aria-label="WhatsApp"><i class='bx bxl-whatsapp' aria-hidden="true"></i></a>
                @endif
            </div>
        </div>
    </div>
    <div class="ettc-footer-bottom">
        <div class="container">
            {{-- Droits réservés au développeur, Computer Service BARRY (demande du développeur, 2026-11-17 ; propriété du
                 code : cahier §12, à formaliser dans le protocole de partenariat). --}}
            <span>&copy; {{ date('Y') }} Computer Service BARRY. Tous droits réservés. Site de {{ $site->get('company.name') }} ({{ $site->get('company.short_name') }}).</span>
            <nav aria-label="Informations légales">
                <a href="{{ route('site.privacy') }}">Confidentialité</a>
                <a href="{{ route('site.legal') }}">Mentions légales</a>
                <a href="#pwa-install" data-pwa-open hidden><i class='bx bx-download' aria-hidden="true"></i> Installer l'application</a>
            </nav>
        </div>
    </div>
</footer>
