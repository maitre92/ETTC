@extends('layouts.site')

{{-- Proposition chiffrée reçue par lien personnel (cahier §3.7 « validation de l'offre par le demandeur ») : montant,
     détail, bouton « J'accepte » (une seule fois). --}}
@section('content')
    @include('site.partials.page-hero', ['title' => 'Votre proposition', 'crumb' => 'Proposition', 'intro' => ($demande->need_type?->label() ?? 'Demande').' — '.$demande->name])

    <section class="section">
        <div class="container" style="max-width: 680px">
            <div class="detail-panel">
                @if (session('accepted') || $demande->accepted_at)
                    <div class="form-success mb-4" role="status">
                        <span class="form-success-icon"><i class="bx bx-check" aria-hidden="true"></i></span>
                        <h2 class="h4">Proposition acceptée</h2>
                        <p class="mb-0">Merci ! Le centre est prévenu et vous recontacte pour la suite.</p>
                    </div>
                @endif
                <p class="small text-muted mb-1">Montant proposé</p>
                <p class="display-6 fw-bold text-primary mb-3">{{ number_format((int) $demande->proposal_amount, 0, ',', ' ') }} FCFA</p>
                <div class="mb-4">{!! nl2br(e($demande->proposal_text)) !!}</div>
                @unless ($demande->accepted_at)
                    <form method="POST" action="{{ route('site.proposition.accept', $demande->proposal_token) }}">
                        @csrf
                        <button type="submit" class="btn btn-accent btn-lg w-100"><i class='bx bx-check-circle' aria-hidden="true"></i> J'accepte cette proposition</button>
                    </form>
                    <p class="small text-muted mt-3 mb-0">Une question avant d'accepter ? Appelez-nous au {{ $site->get('contact.phone_1') }}@if ($site->whatsappLink()) ou <a href="{{ $site->whatsappLink('Bonjour, une question sur ma proposition ('.$demande->reference().').') }}" target="_blank" rel="noopener">écrivez-nous sur WhatsApp</a>@endif.</p>
                @endunless
            </div>
        </div>
    </section>
@endsection
