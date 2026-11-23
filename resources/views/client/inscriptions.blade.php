@extends('layouts.site')

{{-- Inscriptions de l'apprenant (cahier §4 « retrouver ses inscriptions et leur statut ») : formation, tarif retenu à
     l'inscription, session, statut. --}}
@section('content')
    @component('client._layout', ['title' => 'Mes inscriptions'])
        <div class="detail-panel">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0">Mes inscriptions</h2>
                <a href="{{ route('site.inscription.create') }}" class="btn btn-accent btn-sm">Nouvelle inscription</a>
            </div>
            @if ($inscriptions->isEmpty())
                <p class="text-muted mb-0">Aucune inscription pour l'instant. Choisissez votre formation : le test de niveau est gratuit.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th scope="col">Référence</th><th scope="col">Formation</th><th scope="col">Tarif</th><th scope="col">Statut</th></tr></thead>
                        <tbody>
                            @foreach ($inscriptions as $inscription)
                                <tr>
                                    <td class="fw-semibold">{{ $inscription->number }}<br><small class="text-muted">{{ $inscription->created_at->format('d/m/Y') }}</small></td>
                                    <td>{{ $inscription->niveau?->name ?? 'Niveau à déterminer' }}<br><small class="text-muted">{{ $inscription->formule_name }} · {{ $inscription->mode->label() }}@if ($inscription->session) · session {{ $inscription->session }}@endif</small></td>
                                    <td>{{ \App\Models\Formule::money($inscription->total_amount) }}<br><small class="text-muted">{{ $inscription->priceLabel() }}</small></td>
                                    <td><span class="tag {{ $inscription->status->isPending() ? 'tag-info' : ($inscription->status === \App\Enums\InscriptionStatus::Annulee ? 'tag-muted' : 'tag-success') }}">{{ $inscription->status->label() }}</span>@if ($inscription->status !== \App\Enums\InscriptionStatus::Annulee)<br><a href="{{ route('client.paiements') }}" class="small">{{ $inscription->paymentState() }}@if ($inscription->balance() > 0) · reste {{ \App\Models\Formule::money($inscription->balance()) }}@endif</a>@endif
                                        @if ($inscription->attestation_number)
                                            {{-- Attestation : téléchargée (formation en ligne) ou à retirer au centre (présentiel). --}}
                                            <div class="mt-2">
                                                @if ($inscription->mode === \App\Enums\FormationMode::EnLigne)
                                                    <a href="{{ route('client.inscriptions.attestation', $inscription) }}" class="btn btn-sm btn-accent"><i class='bx bx-award' aria-hidden="true"></i> Télécharger mon attestation</a>
                                                @else
                                                    <span class="tag tag-success"><i class='bx bx-award' aria-hidden="true"></i> Attestation prête : à retirer au centre</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">{{ $inscriptions->links() }}</div>
            @endif
        </div>
    @endcomponent
@endsection
