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
                                    <td><span class="tag {{ $inscription->status->isPending() ? 'tag-info' : ($inscription->status === \App\Enums\InscriptionStatus::Annulee ? 'tag-muted' : 'tag-success') }}">{{ $inscription->status->label() }}</span></td>
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
