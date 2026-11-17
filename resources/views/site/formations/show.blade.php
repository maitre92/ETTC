@extends('layouts.site')

@section('whatsapp_message', 'Bonjour, je souhaite des informations sur la formation d\'anglais niveau '.$niveau->name.'.')

{{-- Fiche d'un niveau (cahier §3.2) : objectifs pédagogiques, compétences visées, prérequis, les deux options de durée,
     tarifs selon le mode de formation et modalités d'inscription. --}}
@section('content')
    @include('site.partials.page-hero', [
        'title' => 'Anglais — niveau '.$niveau->name,
        'crumb' => $niveau->name,
        'crumbs' => [['Formations', route('site.formations.index')]],
        'intro' => $niveau->summary,
    ])

    @php
        $inscription = route('site.inscription.create', ['niveau' => $niveau->slug]);
        $blocks = [
            ['objectives', 'Objectifs pédagogiques', 'bx-target-lock', 'blue'],
            ['skills', 'Compétences visées', 'bx-medal', 'green'],
            ['prerequisites', 'Prérequis', 'bx-list-check', 'yellow'],
        ];
    @endphp

    <section class="ettc-section">
        <div class="container">
            <div class="ettc-level-blocks">
                @foreach ($blocks as [$field, $blockTitle, $blockIcon, $tone])
                    @if ($niveau->lines($field) || $field === 'prerequisites')
                        <div class="ettc-level-block ettc-tone-{{ $tone }}">
                            <h2><i class='bx {{ $blockIcon }}' aria-hidden="true"></i> {{ $blockTitle }}</h2>
                            @if ($niveau->lines($field))
                                <ul>
                                    @foreach ($niveau->lines($field) as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="mb-0">Aucun prérequis : le test de niveau vous oriente.</p>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    @if ($formules->isNotEmpty())
        <section class="ettc-section pt-0" id="tarifs">
            <div class="container">
                <h2 class="ettc-title"><span>Durées et tarifs</span></h2>
                <div class="ettc-formula-list">
                    @foreach ($formules as $formule)
                        @include('site.formations._formule', ['formule' => $formule])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="ettc-section pt-0">
        <div class="container">
            <div class="ettc-steps-wrap">
                <div class="ettc-side-card ettc-tone-yellow ettc-test-card">
                    <i class='bx bx-clipboard' aria-hidden="true"></i>
                    <span><strong>Modalités d'inscription</strong><small>Test de niveau gratuit, puis choix de la formule et du mode ; paiement en ligne ou au centre.</small></span>
                    <span class="ettc-test-price">Test gratuit</span>
                </div>
                <div class="ettc-hero-actions align-self-center">
                    <a href="{{ $inscription }}" class="ettc-btn ettc-btn-orange"><i class='bx bxs-user-plus' aria-hidden="true"></i> S'inscrire maintenant</a>
                    <a href="{{ route('site.formations.index') }}" class="ettc-btn ettc-btn-blue"><i class='bx bxs-graduation' aria-hidden="true"></i> Tous les niveaux</a>
                </div>
            </div>
            @if ($autres->isNotEmpty())
                <p class="mt-4 mb-0">Autres niveaux :
                    @foreach ($autres as $autre)
                        <a href="{{ route('site.formations.show', $autre->slug) }}">{{ $autre->name }}</a>@if (! $loop->last), @endif
                    @endforeach
                </p>
            @endif
        </div>
    </section>
@endsection
