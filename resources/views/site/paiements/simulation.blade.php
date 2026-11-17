@extends('layouts.site')

{{-- Opérateur de paiement SIMULÉ (développement et démonstration, jamais en production) : remplace la page d'Orange Money
     / Moov / Wave du prestataire pour tester tout le parcours automatique. --}}
@section('content')
    @include('site.partials.page-hero', ['title' => 'Paiement simulé', 'crumb' => 'Paiement', 'intro' => 'Page de test : en production, l\'apprenant est sur la page sécurisée de l\'opérateur (Orange Money, Moov Money, Wave).'])
    <section class="section">
        <div class="container" style="max-width: 560px">
            <div class="detail-panel text-center">
                <p class="tag tag-info mb-3">Mode test — aucun argent réel</p>
                <h2 class="h4">{{ $paiement->money() }}</h2>
                <p class="text-muted">{{ $paiement->inscription->name }} · {{ $paiement->number }}</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center mt-3">
                    <form method="POST" action="{{ URL::signedRoute('site.paiement-simule.decide', ['transaction' => $transaction, 'accept' => 1]) }}">@csrf<button type="submit" class="btn btn-accent btn-lg"><i class='bx bx-check' aria-hidden="true"></i> Payer</button></form>
                    <form method="POST" action="{{ URL::signedRoute('site.paiement-simule.decide', ['transaction' => $transaction, 'accept' => 0]) }}">@csrf<button type="submit" class="btn btn-outline-secondary btn-lg">Refuser</button></form>
                </div>
            </div>
        </div>
    </section>
@endsection
