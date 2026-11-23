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
                        @php $next = $groupe->nextSeance(); $open = route('formateur.groupes.show', $groupe); @endphp
                        <div class="col-md-6 col-xl-4">
                            <div class="card h-100 mb-0 border {{ $groupe->is_active ? '' : 'opacity-50' }}">
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between gap-2">
                                        <h6 class="mb-1"><a href="{{ $open }}" class="text-reset">{{ $groupe->name }}</a></h6>
                                        <span class="badge {{ $groupe->mode === \App\Enums\FormationMode::EnLigne ? 'bg-primary-subtle' : 'bg-success-subtle' }} align-self-start">{{ $groupe->mode->label() }}</span>
                                    </div>
                                    <p class="small text-muted mb-2">{{ $groupe->niveau?->name ?? 'Tous niveaux' }} · {{ $groupe->members_count }} apprenant(s)</p>
                                    <p class="small mb-2"><i class='bx bx-calendar'></i> {{ $next ? $next->when() : 'Aucune séance programmée' }}</p>
                                    @if ($unread[$groupe->id] ?? 0)
                                        <a href="{{ $open }}#discussion" class="badge bg-danger text-decoration-none align-self-start mb-2"><i class='bx bx-message-rounded-dots'></i> {{ $unread[$groupe->id] }} nouveau(x) message(s)</a>
                                    @endif
                                    <div class="d-grid gap-1 mt-auto">
                                        <a href="{{ $open }}#supports" class="btn btn-primary btn-sm"><i class='bx bx-upload me-1'></i>Déposer un support de cours</a>
                                        <div class="d-flex gap-1">
                                            <a href="{{ $open }}#discussion" class="btn btn-outline-primary btn-sm flex-fill"><i class='bx bx-conversation me-1'></i>Écrire au groupe</a>
                                            <a href="{{ $open }}#add-seance" class="btn btn-outline-primary btn-sm flex-fill"><i class='bx bx-calendar-plus me-1'></i>Séance</a>
                                        </div>
                                        <a href="{{ $open }}" class="btn btn-light btn-sm">Ouvrir le groupe <i class='bx bx-right-arrow-alt'></i></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if ($tests->isNotEmpty())
        <div class="card border-warning">
            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-target-lock me-2'></i>MES TESTS DE NIVEAU À FAIRE PASSER</h6></div>
            <div class="card-body">
                <p class="small text-muted">Après le test, indiquez le niveau : le centre inscrit l'apprenant, et ses frais d'inscription vous reviennent.</p>
                @foreach ($tests as $test)
                    <div class="border rounded-3 p-2 mb-2">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div><strong>{{ $test->name }}</strong> <span class="small text-muted">· {{ $test->phone }}</span>
                                <div class="small">@if ($test->isOnline())Test passé en ligne le {{ $test->result_at?->format('d/m/Y') }}@else{{ $test->scheduled_at ? $test->scheduled_at->translatedFormat('l j F à H\hi') : 'Date à fixer' }} · {{ $test->test_mode->label() }}@if ($test->location) · {{ $test->location }}@endif @endif</div>
                            </div>
                            @if ($test->phone)<a href="tel:{{ $test->phone }}" class="btn btn-light btn-sm align-self-start"><i class='bx bx-phone'></i></a>@endif
                        </div>
                        @if ($test->isOnline())
                            <div class="mt-2">@include('tests._online', ['test' => $test, 'recordingUrl' => fn ($id) => route('formateur.tests.recording', [$test, $id])])</div>
                            <p class="small mb-1">Niveau provisoire : <strong>{{ $test->niveau?->name ?? '—' }}</strong> ({{ $test->score }}) — écoutez l'oral puis confirmez ou corrigez :</p>
                        @endif
                        <form method="POST" action="{{ route('formateur.tests.result', $test) }}" class="row g-2 mt-1 align-items-end">
                            @csrf @method('PUT')
                            <div class="col-sm-4"><label class="form-label small mb-0" for="t-niv-{{ $test->id }}">Niveau</label><select class="form-select form-select-sm" id="t-niv-{{ $test->id }}" name="niveau_id" required><option value="">—</option>@foreach ($niveaux as $niveau)<option value="{{ $niveau->id }}" @selected($test->niveau_id === $niveau->id)>{{ $niveau->name }}</option>@endforeach</select></div>
                            <div class="col-sm-3"><label class="form-label small mb-0" for="t-score-{{ $test->id }}">Score</label><input class="form-control form-control-sm" id="t-score-{{ $test->id }}" name="score" maxlength="50" placeholder="ex. 14/20"></div>
                            <div class="col-sm-5"><label class="form-label small mb-0" for="t-notes-{{ $test->id }}">Remarques</label><input class="form-control form-control-sm" id="t-notes-{{ $test->id }}" name="result_notes" maxlength="2000"></div>
                            <div class="col-12"><button type="submit" class="btn btn-success btn-sm"><i class='bx bx-check me-1'></i>Enregistrer le résultat</button></div>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

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
