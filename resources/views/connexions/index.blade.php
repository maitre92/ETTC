@extends('layouts.admin')

@section('title', 'Qui est en ligne')

{{-- Qui est en ligne (demande du centre, 2026-11-22) : équipe et apprenants — en ligne ou non, connexion (date et heure),
     dernière activité. Rafraîchie toute seule chaque minute. --}}
@section('content')
    @php
        $state = function ($person) {
            if (\App\Http\Controllers\Web\Admin\ConnexionController::isOnline($person)) {
                return ['En ligne', 'bg-success', 'Connecté(e) depuis '.($person->last_login_at?->format('H:i') ?? '—')];
            }
            if (! $person->last_seen_at) {
                return ['Jamais connecté(e)', 'bg-secondary-subtle', ''];
            }

            return ['Hors ligne', 'bg-light text-dark', 'Vu(e) '.$person->last_seen_at->diffForHumans()];
        };
    @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Administration</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Qui est en ligne</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div class="row g-3 mb-3">
        <div class="col-sm-6"><div class="card mb-0 border-start border-4 border-success"><div class="card-body"><p class="small text-muted mb-1">Équipe en ligne</p><h4 class="mb-0">{{ $onlineTeam }} / {{ $team->count() }}</h4></div></div></div>
        <div class="col-sm-6"><div class="card mb-0 border-start border-4 border-primary"><div class="card-body"><p class="small text-muted mb-1">Apprenants en ligne</p><h4 class="mb-0">{{ $onlineClients }}</h4></div></div></div>
    </div>
    <p class="small text-muted"><i class='bx bx-info-circle'></i> « En ligne » : actif sur le site depuis moins de {{ \App\Http\Middleware\TrackLastSeen::ONLINE_MINUTES }} minutes. La page se met à jour toute seule chaque minute. Le détail de chaque action est dans le Journal d'activité.</p>

    <div class="card">
        <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-group me-2'></i>ÉQUIPE</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>NOM</th><th>RÔLE</th><th>ÉTAT</th><th>DERNIÈRE CONNEXION</th><th>DERNIÈRE ACTIVITÉ</th></tr></thead>
                    <tbody>
                        @foreach ($team as $member)
                            @php [$label, $badge, $hint] = $state($member); @endphp
                            <tr>
                                <td class="fw-semibold">{{ $member->name }}@if ($member->id === auth()->id()) <span class="small text-muted">(vous)</span>@endif</td>
                                <td class="small">{{ $member->roleName()?->label() ?? '—' }}</td>
                                <td><span class="badge {{ $badge }}">{{ $label }}</span>@if ($hint)<div class="small text-muted">{{ $hint }}</div>@endif</td>
                                <td class="text-nowrap">{{ $member->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="text-nowrap">{{ $member->last_seen_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header card-header-brand d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h6 class="mb-0 text-white"><i class='bx bx-user me-2'></i>APPRENANTS (ESPACE APPRENANT)</h6>
            <form method="GET" action="{{ route('connexions.index') }}" class="d-flex gap-1">
                <input type="search" class="form-control form-control-sm" name="q" value="{{ $search }}" placeholder="Nom, téléphone, e-mail" aria-label="Rechercher un apprenant">
                <button type="submit" class="btn btn-light btn-sm"><i class='bx bx-search'></i></button>
            </form>
        </div>
        <div class="card-body">
            @if ($clients->isEmpty())
                <p class="text-muted mb-0">Aucun apprenant ne s'est encore connecté à son espace{{ $search !== '' ? ' (pour cette recherche)' : '' }}.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>APPRENANT</th><th>ÉTAT</th><th>DERNIÈRE CONNEXION</th><th>DERNIÈRE ACTIVITÉ</th></tr></thead>
                        <tbody>
                            @foreach ($clients as $client)
                                @php [$label, $badge, $hint] = $state($client); @endphp
                                <tr>
                                    <td><span class="fw-semibold">{{ $client->name }}</span><div class="small text-muted">{{ collect([$client->phone, $client->email])->filter()->implode(' · ') }}</div></td>
                                    <td><span class="badge {{ $badge }}">{{ $label }}</span>@if ($hint)<div class="small text-muted">{{ $hint }}</div>@endif</td>
                                    <td class="text-nowrap">{{ $client->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="text-nowrap">{{ $client->last_seen_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2 mb-0">Les 100 dernières connexions{{ $search !== '' ? ' correspondant à la recherche' : '' }}.</p>
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>setTimeout(function () { if (!document.querySelector('input[name="q"]:focus')) { window.location.reload(); } }, 60000);</script>
@endpush
