@extends('layouts.admin')

@section('title', 'Mes groupes')

{{-- Espace formateur : séances du jour (bouton Meet), mes groupes (nouveaux messages), derniers messages des apprenants,
     séances des 7 prochains jours. --}}
@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Espace formateur</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Mes groupes</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

    <div class="card">
        <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-time-five me-2'></i>MES SÉANCES D'AUJOURD'HUI</h6></div>
        <div class="card-body">
            @forelse ($today as $seance)
                <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom {{ $seance->is_cancelled ? 'text-decoration-line-through text-muted' : '' }}">
                    <strong class="me-2 fs-5">{{ $seance->starts_at->format('H\hi') }}</strong>
                    <a href="{{ route('formateur.groupes.show', $seance->groupe) }}" class="flex-grow-1">{{ $seance->groupe->name }}@if ($seance->title) — {{ $seance->title }}@endif</a>
                    @if (! $seance->is_cancelled && $seance->joinUrl())<a href="{{ $seance->joinUrl() }}" class="btn btn-success" target="_blank" rel="noopener"><i class='bx bx-video me-1'></i>Ouvrir Google Meet</a>@endif
                </div>
            @empty
                <p class="text-muted mb-0">Pas de séance aujourd'hui.@if ($week->isNotEmpty()) Prochaine : {{ $week->first()->groupe->name }}, {{ $week->first()->when() }}.@endif</p>
            @endforelse
        </div>
    </div>

    <div class="card">
        <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-chalkboard me-2'></i>MES GROUPES</h6></div>
        <div class="card-body">
            @if ($groupes->isEmpty())
                <p class="text-muted mb-0">Aucun groupe ne vous est encore confié. Le centre vous attribue vos groupes (Séances et groupes → formateur) : ils apparaîtront ici.</p>
            @else
                <div class="row g-3">
                    @foreach ($groupes as $groupe)
                        @php $next = $groupe->nextSeance(); @endphp
                        <div class="col-md-6 col-xl-4">
                            <a href="{{ route('formateur.groupes.show', $groupe) }}" class="card h-100 mb-0 text-reset text-decoration-none border {{ $groupe->is_active ? '' : 'opacity-50' }}">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between gap-2">
                                        <h6 class="mb-1">{{ $groupe->name }}</h6>
                                        <span class="badge {{ $groupe->mode === \App\Enums\FormationMode::EnLigne ? 'bg-primary-subtle' : 'bg-success-subtle' }} align-self-start">{{ $groupe->mode->label() }}</span>
                                    </div>
                                    <p class="small text-muted mb-2">{{ $groupe->niveau?->name ?? 'Tous niveaux' }}</p>
                                    <p class="mb-1"><i class='bx bx-user'></i> {{ $groupe->members_count }} apprenant(s)</p>
                                    <p class="small mb-1"><i class='bx bx-calendar'></i> {{ $next ? $next->when() : 'Aucune séance programmée' }}</p>
                                    @if ($unread[$groupe->id] ?? 0)
                                        <span class="badge bg-danger"><i class='bx bx-message-rounded-dots'></i> {{ $unread[$groupe->id] }} nouveau(x) message(s)</span>
                                    @endif
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($personal->isNotEmpty())
        <div class="card">
            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-user-pin me-2'></i>MES FORMATIONS PERSONNALISÉES</h6></div>
            <div class="card-body">
                @foreach ($personal as $learner)
                    <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
                        <span class="flex-grow-1"><strong>{{ $learner->name }}</strong> <span class="small text-muted">· {{ $learner->mode->label() }}{{ $learner->niveau ? ' · '.$learner->niveau->name : '' }}</span></span>
                        @if ($learner->phone)
                            <a href="tel:{{ $learner->phone }}" class="btn btn-light btn-sm" title="Appeler"><i class='bx bx-phone'></i></a>
                            @if ($wa = app(\App\Support\SiteSettings::class)->whatsappTo($learner->phone, 'Bonjour '.$learner->name.', '))<a href="{{ $wa }}" class="btn btn-light btn-sm" target="_blank" rel="noopener" title="WhatsApp"><i class='bx bxl-whatsapp'></i></a>@endif
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Ma rémunération : ce mois-ci et le mois dernier (formation personnalisée 1/3, en groupe 20 %). --}}
    <div class="card">
        <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-wallet me-2'></i>MA RÉMUNÉRATION</h6></div>
        <div class="card-body">
            <div class="row g-3">
                @foreach ([[now(), $pay], [now()->subMonthNoOverflow(), $payLastMonth]] as [$payMonth, $payData])
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <div class="small text-muted mb-1">{{ ucfirst($payMonth->translatedFormat('F Y')) }}</div>
                            <div class="d-flex flex-wrap gap-3">
                                <div><div class="small text-muted">Gagné</div><strong>{{ \App\Models\Formule::money($payData['due']) }}</strong></div>
                                <div><div class="small text-muted">Reçu</div><strong class="text-success">{{ \App\Models\Formule::money($payData['paid']) }}</strong></div>
                                <div><div class="small text-muted">Reste</div><strong class="{{ $payData['remaining'] ? 'text-danger' : '' }}">{{ \App\Models\Formule::money($payData['remaining']) }}</strong></div>
                            </div>
                            <div class="small text-muted mt-1">{{ $payData['lines']->count() }} paiement(s) d'apprenants encaissé(s)</div>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="small text-muted mt-2 mb-0">Formation personnalisée : un tiers de la mensualité ; formation en groupe : 20 % du montant ; sur ce que vos apprenants ont payé (hors frais d'inscription).</p>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-conversation me-2'></i>DERNIERS MESSAGES DE MES APPRENANTS</h6></div>
                <div class="card-body">
                    @forelse ($latest as $msg)
                        <a href="{{ route('formateur.groupes.show', $msg->groupe).'#discussion' }}" class="d-block py-2 border-bottom text-reset text-decoration-none">
                            <div class="small text-muted">{{ $msg->authorName() }} · {{ $msg->groupe->name }} · {{ $msg->created_at->format('d/m H:i') }}</div>
                            <div>{{ \Illuminate\Support\Str::limit($msg->body, 120) }}</div>
                        </a>
                    @empty
                        <p class="text-muted mb-0">Aucun message pour l'instant.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-calendar-week me-2'></i>MES 7 PROCHAINS JOURS</h6></div>
                <div class="card-body">
                    @forelse ($week as $seance)
                        <div class="py-2 border-bottom"><strong>{{ $seance->when() }}</strong><div class="small text-muted">{{ $seance->groupe->name }}@if ($seance->title) — {{ $seance->title }}@endif</div></div>
                    @empty
                        <p class="text-muted mb-0">Aucune séance prévue.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
