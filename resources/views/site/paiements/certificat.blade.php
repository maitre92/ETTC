@extends('layouts.site')

{{-- Vérification publique d'une attestation (QR du certificat) : rien d'autre que le nom, la formation, la période et la
     référence. --}}
@section('content')
    @include('site.partials.page-hero', ['title' => 'Vérification d\'une attestation', 'crumb' => 'Attestation', 'intro' => 'Cette page confirme qu\'une attestation a bien été délivrée par le centre.'])
    <section class="section">
        <div class="container" style="max-width: 620px">
            <div class="detail-panel text-center">
                @if ($inscription)
                    <span class="form-success-icon mx-auto"><i class="bx bx-check" aria-hidden="true"></i></span>
                    <h2 class="h4 mt-2">Attestation authentique</h2>
                    <dl class="row text-start mt-3 mb-0">
                        <dt class="col-5">Référence</dt><dd class="col-7">{{ $inscription->attestation_number }}</dd>
                        <dt class="col-5">Titulaire</dt><dd class="col-7"><strong>{{ $inscription->name }}</strong></dd>
                        <dt class="col-5">Formation</dt><dd class="col-7">{{ $training }}</dd>
                        <dt class="col-5">Période</dt><dd class="col-7">{{ ucfirst($period) }}</dd>
                        <dt class="col-5">Délivrée le</dt><dd class="col-7">{{ $inscription->attestation_issued_at->format('d/m/Y') }}</dd>
                    </dl>
                @else
                    <h2 class="h4">Attestation introuvable</h2>
                    <p class="mb-0">Cette attestation n'a pas pu être vérifiée. Contactez le centre au {{ $site->get('contact.phone_1') }}.</p>
                @endif
            </div>
        </div>
    </section>
@endsection
