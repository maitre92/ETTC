@extends('layouts.admin')

@section('title', 'Séances et groupes')

{{-- Séances et groupes (cahier §3.6, §5) : séances du jour, groupes (apprenants, prochaine séance), création d'un groupe.
     Zone [data-crud] actualisée en AJAX. --}}
@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Formations</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Séances et groupes</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div data-crud="groupes">
        <span data-crud-status hidden>{{ session('status') }}</span>

        @if ($withoutGroup > 0)
            <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
                <i class='bx bx-bulb fs-4'></i>
                <span class="flex-grow-1"><strong>{{ $withoutGroup }} apprenant(s)</strong> inscrit(s) n'ont pas encore de groupe. Répartissez-les en un clic, ou ouvrez un groupe : ils vous y seront proposés.</span>
                @can('create', \App\Models\Groupe::class)
                    @if ($plan['total'] > 0)
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#repartir"><i class='bx bx-shuffle me-1'></i>Répartir automatiquement</button>
                    @endif
                @endcan
            </div>
        @endif

        <div class="card">
            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-time-five me-2'></i>SÉANCES D'AUJOURD'HUI</h6></div>
            <div class="card-body">
                @forelse ($today as $seance)
                    <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom {{ $seance->is_cancelled ? 'text-decoration-line-through text-muted' : '' }}">
                        <strong class="me-2">{{ $seance->starts_at->format('H\hi') }} – {{ $seance->endsAt()->format('H\hi') }}</strong>
                        <a href="{{ route('groupes.show', $seance->groupe) }}" class="flex-grow-1">{{ $seance->groupe->name }}@if ($seance->title) — {{ $seance->title }}@endif</a>
                        @if (! $seance->is_cancelled && $seance->joinUrl())<a href="{{ $seance->joinUrl() }}" class="btn btn-success btn-sm" target="_blank" rel="noopener"><i class='bx bx-video'></i><span class="ms-1">Ouvrir Meet</span></a>@endif
                    </div>
                @empty
                    <p class="text-muted mb-0">Aucune séance aujourd'hui.</p>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="mb-0 text-white"><i class='bx bx-group me-2'></i>GROUPES</h6>
                @can('create', \App\Models\Groupe::class)
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#create-groupe"><i class='bx bx-plus'></i> Nouveau groupe</button>
                @endcan
            </div>
            <div class="card-body">
                @if ($groupes->isEmpty())
                    <p class="text-muted mb-0">Aucun groupe pour l'instant. Créez le premier : un nom, le mode (en ligne ou au centre) et le lien Google Meet.</p>
                @else
                    <div class="row g-3">
                        @foreach ($groupes as $groupe)
                            @php $next = $groupe->nextSeance(); $open = route('groupes.show', $groupe); @endphp
                            <div class="col-md-6 col-xl-4">
                                <div class="card h-100 mb-0 border {{ $groupe->is_active ? '' : 'opacity-50' }}">
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex justify-content-between gap-2">
                                            <h6 class="mb-1"><a href="{{ $open }}" class="text-reset">{{ $groupe->name }}</a></h6>
                                            <span class="badge {{ $groupe->mode === \App\Enums\FormationMode::EnLigne ? 'bg-primary-subtle' : 'bg-success-subtle' }} align-self-start">{{ $groupe->mode->label() }}</span>
                                        </div>
                                        <p class="small text-muted mb-2">{{ $groupe->niveau?->name ?? 'Tous niveaux' }}@if ($groupe->teacherName()) · {{ $groupe->teacherName() }}@else · <span class="text-warning">formateur à choisir</span>@endif</p>
                                        <p class="mb-1"><i class='bx bx-user'></i> {{ $groupe->members_count }}{{ $groupe->capacity ? ' / '.$groupe->capacity : '' }} apprenant(s)@if ($groupe->capacity && $groupe->members_count >= $groupe->capacity) <span class="badge bg-danger-subtle">Complet</span>@endif</p>
                                        <p class="small mb-2 {{ $next ? '' : 'text-danger' }}"><i class='bx bx-calendar'></i> {{ $next ? 'Prochaine séance : '.$next->when() : 'Aucune séance programmée' }}</p>
                                        @unless ($groupe->is_active)<span class="badge bg-secondary-subtle mb-2 align-self-start">Inactif</span>@endunless
                                        <div class="d-flex flex-wrap gap-1 mt-auto">
                                            <a href="{{ $open }}" class="btn btn-primary btn-sm flex-fill">Ouvrir le groupe</a>
                                            @can('inscriptions.attestation')
                                                <a href="{{ $open }}#attestations" class="btn btn-outline-success btn-sm flex-fill"><i class='bx bx-award'></i> Attestations @if ($groupe->certificates_due ?? 0)<span class="badge bg-success">{{ $groupe->certificates_due }} à délivrer</span>@endif</a>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="mb-0 text-white"><i class='bx bx-megaphone me-2'></i>ANNONCES AUX APPRENANTS</h6>
                @can('create', \App\Models\Groupe::class)
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#annonce"><i class='bx bx-plus'></i> Nouvelle annonce</button>
                @endcan
            </div>
            <div class="card-body">
                @forelse ($annonces as $annonce)
                    <div class="d-flex flex-wrap align-items-start gap-2 py-2 border-bottom {{ $annonce->expires_on && $annonce->expires_on->isPast() ? 'opacity-50' : '' }}">
                        <div class="flex-grow-1">
                            <strong>{{ $annonce->title }}</strong> <span class="badge bg-light text-dark">{{ $annonce->audience() }}</span>
                            <div class="small text-muted">{{ $annonce->created_at->format('d/m/Y') }}@if ($annonce->expires_on) · jusqu'au {{ $annonce->expires_on->format('d/m/Y') }}@endif @if ($annonce->emailed_count) · envoyée à {{ $annonce->emailed_count }} apprenant(s)@endif</div>
                        </div>
                        @can('create', \App\Models\Groupe::class)
                            <form method="POST" action="{{ route('groupes.annonces.destroy', $annonce) }}" data-crud-form data-no-guard data-confirm="Retirer l'annonce « {{ $annonce->title }} » ?">@csrf @method('DELETE')<button type="submit" class="btn btn-light btn-sm" title="Retirer"><i class='bx bx-trash'></i></button></form>
                        @endcan
                    </div>
                @empty
                    <p class="text-muted mb-0">Aucune annonce. Exemple : « Pas de cours lundi (jour férié) », « Nouvelle session en janvier ».</p>
                @endforelse
            </div>
        </div>

        @can('create', \App\Models\Groupe::class)
            @include('groupes._annonce-modal', ['modalId' => 'annonce', 'groupes' => $groupes->where('is_active', true), 'groupe' => null])
            @include('groupes._distribute-modal', ['plan' => $plan])
            @include('groupes._form-modal', ['groupe' => null, 'modalId' => 'create-groupe', 'action' => route('groupes.store'), 'method' => 'POST', 'niveaux' => $niveaux])
        @endcan
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud-ajax.js') }}?v={{ filemtime(public_path('assets/js/crud-ajax.js')) }}"></script>
    <script>CrudAjax.init(document.querySelector('[data-crud="groupes"]'));</script>
@endpush
