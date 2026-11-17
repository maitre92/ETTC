@extends('layouts.site')

@section('whatsapp_message', 'Bonjour, je souhaite des informations sur vos formations d\'anglais.')

{{-- Catalogue des formations (cahier §3.2, §3.3) : niveaux, formules et tarifs par mode (réglés dans l'administration),
     test de niveau préalable, modalités d'inscription. --}}
@section('content')
    @include('site.partials.page-hero', ['title' => 'Nos formations d\'anglais', 'crumb' => 'Formations', 'intro' => 'Des formations organisées par niveau, sur deux ou trois mois, en ligne avec un formateur ou en présentiel au centre.'])

    @php
        $inscription = Route::has('client.register') ? route('client.register') : route('site.contact');
    @endphp

    @if ($niveaux->isNotEmpty())
        <section class="ettc-section" id="niveaux">
            <div class="container">
                <h2 class="ettc-title"><span>Nos niveaux</span></h2>
                <div class="ettc-levels">
                    @foreach ($niveaux as $niveau)
                        <a href="{{ route('site.formations.show', $niveau->slug) }}" class="ettc-level ettc-tone-{{ ['blue', 'green', 'yellow', 'orange'][$loop->index % 4] }}">
                            <span class="ettc-level-num" aria-hidden="true">{{ $loop->iteration }}</span>
                            <strong>{{ $niveau->name }}</strong>
                            <span>{{ $niveau->summary }}</span>
                            <span class="ettc-level-more">Voir la fiche <i class='bx bx-right-arrow-alt' aria-hidden="true"></i></span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="ettc-section pt-0" id="tarifs">
        <div class="container">
            <h2 class="ettc-title"><span>Formules et tarifs</span></h2>
            @if ($formules->isEmpty())
                <p class="text-center text-muted">Les formules et tarifs seront bientôt publiés. Contactez-nous pour en savoir plus.</p>
            @else
                <div class="ettc-formula-list">
                    @foreach ($formules as $formule)
                        @include('site.formations._formule', ['formule' => $formule])
                    @endforeach
                </div>
                <p class="ettc-tariff-note">Tarifs en FCFA. Ils sont identiques pour tous les niveaux ; le niveau est fixé par le test de niveau.</p>
            @endif
        </div>
    </section>

    <section class="ettc-section pt-0" id="test-de-niveau">
        <div class="container">
            <div class="ettc-steps-wrap">
                <div class="ettc-side-card ettc-tone-yellow ettc-test-card">
                    <i class='bx bx-clipboard' aria-hidden="true"></i>
                    <span><strong>Test de niveau</strong><small>Avant de commencer, chaque nouvel apprenant passe un test de niveau : il évalue vos connaissances et vous oriente vers le niveau qui vous correspond.</small></span>
                    <span class="ettc-test-price">{{ \App\Models\Formule::money($testFee) }}</span>
                </div>
                <div>
                    <h2 class="h4 text-ink mb-3">Comment s'inscrire&nbsp;?</h2>
                    <ol class="ettc-steps">
                        <li><strong>Passez le test de niveau</strong> au centre ou à distance.</li>
                        <li><strong>Choisissez votre formule</strong> : durée et mode (en ligne ou en présentiel).</li>
                        <li><strong>Réglez</strong> en ligne ou directement au centre.</li>
                        <li><strong>Commencez vos séances</strong> avec votre formateur.</li>
                    </ol>
                    <div class="ettc-hero-actions">
                        <a href="{{ $inscription }}" class="ettc-btn ettc-btn-orange"><i class='bx bxs-user-plus' aria-hidden="true"></i> S'inscrire maintenant</a>
                        @if (Route::has('site.devis'))
                            <a href="{{ route('site.devis', ['type' => 'formation_personnalisee']) }}" class="ettc-btn ettc-btn-blue"><i class='bx bxs-group' aria-hidden="true"></i> Formation personnalisée</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
