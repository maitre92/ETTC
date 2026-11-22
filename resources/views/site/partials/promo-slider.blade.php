{{-- Diaporama des promotions en cours (accueil, formations, inscription) : fiche (affiche) si elle existe, réduction
     animée, accroche, formule, échéance, code promo à copier, « J'en profite » (inscription avec le code déjà saisi).
     Défile toutes les 6 s, glisse au doigt, s'arrête au survol (ettc.js [data-promo-slider]). $compact (facultatif). --}}
@php $slides = \App\Models\Promotion::forSite(); @endphp
@if ($slides->isNotEmpty())
    <section class="promo-slider-wrap {{ ($compact ?? false) ? 'is-compact' : '' }}" aria-label="Promotions en cours">
        <div class="container">
            <div class="promo-slider" data-promo-slider>
                @foreach ($slides as $promo)
                    <article class="promo-slide {{ $promo->media ? 'has-media' : '' }}" data-promo-slide @if (! $loop->first) hidden @endif>
                        @if ($promo->media)
                            <a href="{{ $promo->media->url() }}" class="promo-slide-media" target="_blank" rel="noopener" title="Agrandir la fiche">
                                <img src="{{ $promo->media->url() }}" alt="Fiche de la promotion « {{ $promo->name }} »" loading="lazy">
                            </a>
                        @endif
                        <div class="promo-slide-body">
                            <span class="promo-slide-badge">{{ $promo->badge() }}</span>
                            <p class="promo-slide-kicker"><i class='bx bxs-offer' aria-hidden="true"></i> Promotion en cours</p>
                            <h2 class="promo-slide-title">{{ $promo->name }}</h2>
                            @if ($promo->description)<p class="promo-slide-text">{{ $promo->description }}</p>@endif
                            <p class="promo-slide-meta">{{ $promo->formule?->name ?? 'Toutes les formules' }}{{ $promo->mode ? ' · '.mb_strtolower($promo->mode->label()) : '' }} · sur les frais de formation</p>
                            @if ($promo->deadline())<p class="promo-slide-deadline"><i class='bx bx-time-five' aria-hidden="true"></i> {{ $promo->deadline() }}</p>@endif
                            <div class="promo-slide-actions">
                                @if ($promo->code)
                                    <span class="promo-slide-code">Code : <strong>{{ $promo->code }}</strong> <button type="button" class="btn btn-sm btn-light" data-copy="{{ $promo->code }}" data-copy-message="Code promo copié : il est déjà saisi si vous cliquez sur « J'en profite »."><i class='bx bx-copy' aria-hidden="true"></i> Copier</button></span>
                                @endif
                                <a href="{{ $promo->signupUrl() }}" class="ettc-btn ettc-btn-orange">J'en profite <i class='bx bx-right-arrow-alt' aria-hidden="true"></i></a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
@endif
