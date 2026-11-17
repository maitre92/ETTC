@extends('layouts.site')

{{-- Vérification publique d'un reçu (QR code) : numéro, montant, date, statut. Rien d'autre sur l'apprenant que son nom. --}}
@section('content')
    @include('site.partials.page-hero', ['title' => 'Vérification d\'un reçu', 'crumb' => 'Reçu', 'intro' => 'Cette page confirme qu\'un reçu a bien été délivré par le centre.'])
    <section class="section">
        <div class="container" style="max-width: 620px">
            <div class="detail-panel text-center">
                @if ($paiement)
                    <span class="form-success-icon mx-auto"><i class="bx bx-check" aria-hidden="true"></i></span>
                    <h2 class="h4 mt-2">Reçu authentique</h2>
                    <dl class="row text-start mt-3 mb-0">
                        <dt class="col-5">Reçu n°</dt><dd class="col-7">{{ $paiement->number }}</dd>
                        <dt class="col-5">Apprenant</dt><dd class="col-7">{{ $paiement->inscription->name }}</dd>
                        <dt class="col-5">Montant</dt><dd class="col-7"><strong>{{ $paiement->money() }}</strong></dd>
                        <dt class="col-5">Date</dt><dd class="col-7">{{ $paiement->paid_at->format('d/m/Y') }}</dd>
                        <dt class="col-5">Statut</dt><dd class="col-7">{{ $paiement->status->label() }}</dd>
                    </dl>
                @else
                    <h2 class="h4">Reçu introuvable</h2>
                    <p class="mb-0">Le reçu n° {{ $number }} n'a pas pu être vérifié. Contactez le centre au {{ $site->get('contact.phone_1') }}.</p>
                @endif
            </div>
        </div>
    </section>
@endsection
