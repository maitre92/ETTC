{{-- Carte d'une formule (maquette : en-tête bleu, coches vertes, prix rouge). Tarifs lus dans la base (module
     Formations) : un seul mode proposé = un grand prix ; deux modes = une ligne par mode. $formule ; $niveau facultatif
     (présélectionné dans l'inscription). --}}
@php
    $prices = $formule->prices();
@endphp
<article class="ettc-formula">
    <h3><i class='bx bx-calendar' aria-hidden="true"></i> {{ $formule->name }}</h3>
    @if ($formule->pointList())
        <ul>
            @foreach ($formule->pointList() as $point)
                <li><i class='bx bx-check' aria-hidden="true"></i>{{ $point }}</li>
            @endforeach
        </ul>
    @endif
    @if (count($prices) === 1)
        <p class="ettc-price">
            {{ \App\Models\Formule::money($prices[0][1]) }}
            @if ($formule->billing === \App\Enums\Billing::Mensuel)<span class="ettc-price-unit">/ mois</span>@else<small>(paiement unique)</small>@endif
            <small class="ettc-price-mode">{{ $prices[0][0] }}</small>
        </p>
    @else
        <dl class="ettc-prices">
            @foreach ($prices as [$mode, $amount])
                <div>
                    <dt>{{ $mode }}</dt>
                    <dd>{{ \App\Models\Formule::money($amount) }} @if ($formule->billing === \App\Enums\Billing::Mensuel)<span class="ettc-price-unit">/ mois</span>@endif</dd>
                </div>
            @endforeach
        </dl>
        @if ($formule->billing === \App\Enums\Billing::Unique)
            <p class="ettc-price-note">(paiement unique)</p>
        @endif
    @endif
    <a href="{{ route('site.inscription.create', array_filter(['formule' => $formule->slug, 'niveau' => isset($niveau) ? $niveau->slug : null])) }}" class="ettc-formula-cta">Choisir cette formule <i class='bx bx-right-arrow-alt' aria-hidden="true"></i></a>
</article>
