{{-- Bandeau animé des promotions en cours (toutes les pages du site), sous l'en-tête : défile en boucle, s'arrête au
     survol, se ferme (le temps de la visite). Rien si aucune promotion n'est affichée. --}}
@php $tickerPromos = \App\Models\Promotion::forSite(); @endphp
@if ($tickerPromos->isNotEmpty() && ! request()->routeIs('client.*'))
    <div class="promo-ticker" data-promo-ticker role="region" aria-label="Promotions en cours">
        <div class="promo-ticker-track">
            @foreach (range(1, $tickerPromos->count() < 3 ? 3 : 2) as $copy)
                @foreach ($tickerPromos as $promo)
                    <a href="{{ $promo->signupUrl() }}" class="promo-ticker-item" @if ($copy > 1) aria-hidden="true" tabindex="-1" @endif>
                        <i class='bx bxs-offer' aria-hidden="true"></i>
                        <strong>{{ $promo->badge() }}</strong> {{ $promo->name }}@if ($promo->formule) · {{ $promo->formule->name }}@endif
                        @if ($promo->deadline())<span class="promo-ticker-deadline">{{ $promo->deadline() }}</span>@endif
                        @if ($promo->code)<span class="promo-ticker-code">code {{ $promo->code }}</span>@endif
                        <span class="promo-ticker-cta">J'en profite <i class='bx bx-right-arrow-alt' aria-hidden="true"></i></span>
                    </a>
                @endforeach
            @endforeach
        </div>
        <button type="button" class="promo-ticker-close" data-promo-ticker-close aria-label="Masquer les promotions"><i class='bx bx-x' aria-hidden="true"></i></button>
    </div>
@endif
