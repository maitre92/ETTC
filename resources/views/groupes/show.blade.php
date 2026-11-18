@extends('layouts.admin')

@section('title', 'Groupe '.$groupe->name)

{{-- Fiche d'un groupe (cahier §3.6, §5) : « Que faire maintenant ? », apprenants (proposés automatiquement), séances
     (une ou une série d'un coup, annulation avec prévenance), supports pédagogiques. Formulaires en AJAX. --}}
@section('content')
    @php
        $online = $groupe->mode === \App\Enums\FormationMode::EnLigne;
        $canManage = auth()->user()->can('update', $groupe);
        $canSupports = auth()->user()->can('manageSupports', $groupe);
        $next = $upcoming->firstWhere('is_cancelled', false);
        $days = [1 => 'Lun', 2 => 'Mar', 3 => 'Mer', 4 => 'Jeu', 5 => 'Ven', 6 => 'Sam', 7 => 'Dim'];
    @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Formations</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('groupes.index') }}">Séances et groupes</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $groupe->name }}</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div data-crud="groupe-{{ $groupe->id }}">
        <span data-crud-status hidden>{{ session('status') }}</span>

        <div class="row g-3">
            <div class="col-xl-5">
                <div class="card">
                    <div class="card-header card-header-brand d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-white"><i class='bx bx-group me-2'></i>{{ mb_strtoupper($groupe->name) }}</h6>
                        @if ($canManage)<button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#edit-groupe"><i class='bx bx-edit'></i> Modifier</button>@endif
                    </div>
                    <div class="card-body">
                        <dl class="nv-dl mb-0">
                            <dt>Mode</dt><dd>{{ $groupe->mode->label() }}@unless ($groupe->is_active) <span class="badge bg-secondary-subtle">Inactif</span>@endunless</dd>
                            <dt>Niveau</dt><dd>{{ $groupe->niveau?->name ?? 'Tous niveaux' }}</dd>
                            @if ($groupe->teacher)<dt>Formateur</dt><dd>{{ $groupe->teacher }}</dd>@endif
                            @if ($online && $groupe->meet_url)<dt>Google Meet</dt><dd class="text-break"><a href="{{ $groupe->meet_url }}" target="_blank" rel="noopener">{{ $groupe->meet_url }}</a></dd>@endif
                            @if (! $online && $groupe->location)<dt>Salle</dt><dd>{{ $groupe->location }}</dd>@endif
                            @if ($groupe->schedule)<dt>Horaire</dt><dd>{{ $groupe->schedule }}</dd>@endif
                            @if ($groupe->starts_on)<dt>Période</dt><dd>{{ $groupe->starts_on->format('d/m/Y') }}@if ($groupe->ends_on) → {{ $groupe->ends_on->format('d/m/Y') }}@endif</dd>@endif
                            @if ($groupe->notes)<dt>Infos pratiques</dt><dd>{!! nl2br(e($groupe->notes)) !!}</dd>@endif
                        </dl>
                    </div>
                </div>

                {{-- Apprenants --}}
                <div class="card">
                    <div class="card-header card-header-brand d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-white"><i class='bx bx-user me-2'></i>APPRENANTS ({{ $groupe->members->count() }})</h6>
                        @if ($canManage && $candidates->isNotEmpty())<button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#add-members"><i class='bx bx-plus'></i> Ajouter</button>@endif
                    </div>
                    <div class="card-body">
                        @forelse ($groupe->members as $member)
                            <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
                                <a href="{{ route('inscriptions.show', $member) }}" class="flex-grow-1">{{ $member->name }}</a>
                                <span class="badge {{ $member->paymentBadge() }}">{{ $member->paymentState() }}</span>
                                @if ($canManage)
                                    <form method="POST" action="{{ route('groupes.members.remove', [$groupe, $member]) }}" data-crud-form data-no-guard data-confirm="Retirer {{ $member->name }} de ce groupe ?">@csrf @method('DELETE')<button type="submit" class="btn btn-light btn-sm" title="Retirer du groupe"><i class='bx bx-x'></i></button></form>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">Aucun apprenant dans ce groupe.@if ($candidates->isNotEmpty()) {{ $candidates->count() }} inscrit(s) correspondent : cliquez sur « Ajouter ».@endif</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                {{-- Que faire maintenant ? --}}
                <div class="card border-primary">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-bulb me-2'></i>QUE FAIRE MAINTENANT ?</h6></div>
                    <div class="card-body">
                        @if ($groupe->members->isEmpty() && $canManage)
                            <p class="mb-3">Le groupe est vide. Ajoutez ses apprenants : le site vous propose ceux qui correspondent ({{ $groupe->mode->label() }}{{ $groupe->niveau ? ', '.$groupe->niveau->name : '' }}).</p>
                            <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#add-members" @disabled($candidates->isEmpty())><i class='bx bx-user-plus me-1'></i>Ajouter des apprenants</button>
                            @if ($candidates->isEmpty())<p class="small text-muted mt-2 mb-0">Aucun inscrit ne correspond pour l'instant.</p>@endif
                        @elseif (! $next && $canManage)
                            <p class="mb-3">Aucune séance à venir. Programmez toutes les séances d'un coup : jours, heure et période. Les apprenants reçoivent leur calendrier par e-mail.</p>
                            <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#plan-series"><i class='bx bx-calendar-plus me-1'></i>Programmer les séances</button>
                        @elseif ($next)
                            <p class="mb-3">Prochaine séance : <strong>{{ ucfirst($next->when()) }}</strong>@if ($next->title) — {{ $next->title }}@endif.</p>
                            @if ($next->joinUrl())<a href="{{ $next->joinUrl() }}" class="btn btn-success btn-lg" target="_blank" rel="noopener"><i class='bx bx-video me-1'></i>Ouvrir Google Meet</a>@endif
                        @else
                            <p class="mb-0">Rien à faire pour l'instant.</p>
                        @endif
                    </div>
                </div>

                {{-- Séances --}}
                <div class="card">
                    <div class="card-header card-header-brand d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="mb-0 text-white"><i class='bx bx-calendar me-2'></i>SÉANCES À VENIR ({{ $upcoming->where('is_cancelled', false)->count() }})</h6>
                        @if ($canManage)
                            <span class="d-flex gap-1">
                                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#plan-series"><i class='bx bx-calendar-plus'></i> Série</button>
                                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#add-seance"><i class='bx bx-plus'></i> Une séance</button>
                            </span>
                        @endif
                    </div>
                    <div class="card-body">
                        @forelse ($upcoming as $seance)
                            @if ($loop->index === 8)
                                <details><summary class="small text-primary py-2" style="cursor:pointer">Voir les {{ $upcoming->count() - 8 }} autres séances</summary>
                            @endif
                            <div class="py-2 border-bottom">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="flex-grow-1 {{ $seance->is_cancelled ? 'text-decoration-line-through text-muted' : '' }}"><strong>{{ ucfirst($seance->when()) }}</strong>@if ($seance->title) — {{ $seance->title }}@endif</span>
                                    @if ($seance->is_cancelled)
                                        <span class="badge bg-secondary-subtle">Annulée{{ $seance->cancel_reason ? ' : '.$seance->cancel_reason : '' }}</span>
                                    @elseif ($canManage)
                                        <details class="text-end">
                                            <summary class="btn btn-outline-danger btn-sm">Annuler</summary>
                                            <form method="POST" action="{{ route('groupes.seances.cancel', [$groupe, $seance]) }}" class="d-flex gap-1 mt-1" data-crud-form data-no-guard>
                                                @csrf @method('PUT')
                                                <input class="form-control form-control-sm" name="reason" maxlength="300" required placeholder="Motif (envoyé aux apprenants)" aria-label="Motif de l'annulation">
                                                <button type="submit" class="btn btn-danger btn-sm">OK</button>
                                            </form>
                                        </details>
                                    @endif
                                </div>
                            </div>
                            @if ($loop->last && $loop->count > 8)
                                </details>
                            @endif
                        @empty
                            <p class="text-muted mb-0">Aucune séance programmée.</p>
                        @endforelse
                        @if ($past->isNotEmpty())
                            <details class="mt-3">
                                <summary class="small text-primary" style="cursor:pointer">Séances passées ({{ $past->count() }} dernières)</summary>
                                <ul class="small text-muted mt-2 mb-0">@foreach ($past as $seance)<li class="{{ $seance->is_cancelled ? 'text-decoration-line-through' : '' }}">{{ ucfirst($seance->when()) }}@if ($seance->title) — {{ $seance->title }}@endif</li>@endforeach</ul>
                            </details>
                        @endif
                    </div>
                </div>

                {{-- Supports --}}
                <div class="card">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-folder-open me-2'></i>SUPPORTS PÉDAGOGIQUES</h6></div>
                    <div class="card-body">
                        @forelse ($groupe->supports as $support)
                            <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
                                <i class='bx {{ $support->icon() }} fs-4 text-primary'></i>
                                <span class="flex-grow-1">{{ $support->title }} <span class="small text-muted">{{ $support->kindLabel() }}@if ($support->sizeLabel()) · {{ $support->sizeLabel() }}@endif</span></span>
                                @if ($support->url)
                                    <a href="{{ $support->url }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener"><i class='bx bx-link-external'></i></a>
                                @else
                                    <a href="{{ route('groupes.supports.download', [$groupe, $support]) }}" class="btn btn-outline-primary btn-sm"><i class='bx bx-download'></i></a>
                                @endif
                                @if ($canSupports)
                                    <form method="POST" action="{{ route('groupes.supports.destroy', [$groupe, $support]) }}" data-crud-form data-no-guard data-confirm="Retirer le support « {{ $support->title }} » ?">@csrf @method('DELETE')<button type="submit" class="btn btn-light btn-sm" title="Retirer"><i class='bx bx-trash'></i></button></form>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted">Aucun support pour l'instant.</p>
                        @endforelse
                        @if ($canSupports)
                            <form method="POST" action="{{ route('groupes.supports.store', $groupe) }}" enctype="multipart/form-data" class="row g-2 mt-2" data-crud-form data-no-guard>
                                @csrf
                                <div class="col-md-4"><label class="form-label small fw-semibold" for="s-title">Titre</label><input class="form-control" id="s-title" name="title" maxlength="200" required placeholder="Leçon 1 — vocabulaire"></div>
                                <div class="col-md-4"><label class="form-label small fw-semibold" for="s-file">Fichier (20 Mo max)</label><input class="form-control" type="file" id="s-file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.odt,.txt,.jpg,.jpeg,.png,.mp3,.m4a,.wav,.ogg,.mp4,.webm"></div>
                                <div class="col-md-4"><label class="form-label small fw-semibold" for="s-url">… ou un lien</label><input class="form-control" id="s-url" name="url" maxlength="500" placeholder="youtu.be/… ou lien Drive"></div>
                                <div class="col-12"><button type="submit" class="btn btn-primary btn-sm"><i class='bx bx-upload me-1'></i>Ajouter le support</button> <span class="small text-muted">Visible aussitôt par les apprenants du groupe.</span></div>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <details>
                        <summary class="card-body py-3 fw-semibold text-primary" style="cursor:pointer"><i class='bx bx-history me-1'></i>Historique</summary>
                        <div class="card-body pt-0">
                            <ul class="list-unstyled small mb-0">
                                @forelse ($history as $log)
                                    <li class="mb-2 pb-2 border-bottom"><div class="text-muted">{{ $log->created_at->format('d/m/Y H:i') }} · {{ $log->isBySuperadmin() && ! auth()->user()->isSuperadmin() ? config('brand.system_actor') : ($log->user_name ?? '—') }}</div><div>{{ $log->description }}</div></li>
                                @empty
                                    <li class="text-muted">Aucune action enregistrée.</li>
                                @endforelse
                            </ul>
                        </div>
                    </details>
                </div>
            </div>
        </div>

        @if ($canManage)
            @include('groupes._form-modal', ['groupe' => $groupe, 'modalId' => 'edit-groupe', 'action' => route('groupes.update', $groupe), 'method' => 'PUT', 'niveaux' => $niveaux])

            <div class="modal fade" id="add-members" tabindex="-1" aria-hidden="true" aria-labelledby="add-members-title">
                <div class="modal-dialog modal-dialog-scrollable">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('groupes.members.add', $groupe) }}" data-crud-form data-no-guard>
                            @csrf
                            <div class="modal-header"><h5 class="modal-title" id="add-members-title"><i class='bx bx-user-plus me-1'></i>Ajouter des apprenants</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                            <div class="modal-body">
                                <p class="small text-muted">Inscrits sans groupe, {{ mb_strtolower($groupe->mode->label()) }}{{ $groupe->niveau ? ', niveau '.$groupe->niveau->name.' (ou à déterminer)' : '' }}. Tous sont cochés : décochez ceux à ne pas ajouter.</p>
                                @forelse ($candidates as $candidate)
                                    <div class="form-check py-1">
                                        <input class="form-check-input" type="checkbox" name="inscriptions[]" value="{{ $candidate->id }}" id="cand-{{ $candidate->id }}" checked>
                                        <label class="form-check-label" for="cand-{{ $candidate->id }}">{{ $candidate->name }} <span class="small text-muted">— {{ $candidate->niveau?->name ?? 'niveau à déterminer' }} · {{ $candidate->paymentState() }}</span></label>
                                    </div>
                                @empty
                                    <p class="mb-0">Aucun inscrit ne correspond pour l'instant.</p>
                                @endforelse
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button><button type="submit" class="btn btn-success btn-lg" @disabled($candidates->isEmpty())><i class='bx bx-check me-1'></i>Ajouter au groupe</button></div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="plan-series" tabindex="-1" aria-hidden="true" aria-labelledby="plan-series-title">
                <div class="modal-dialog modal-dialog-scrollable">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('groupes.series', $groupe) }}" data-crud-form data-no-guard>
                            @csrf
                            <div class="modal-header"><h5 class="modal-title" id="plan-series-title"><i class='bx bx-calendar-plus me-1'></i>Programmer les séances</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <span class="form-label fw-semibold d-block">Jours de cours</span>
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach ($days as $number => $label)
                                                <input type="checkbox" class="btn-check" name="days[]" value="{{ $number }}" id="day-{{ $number }}" @checked(in_array($number, [1, 3], true))>
                                                <label class="btn btn-outline-primary" for="day-{{ $number }}">{{ $label }}</label>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="col-6"><label class="form-label fw-semibold" for="ps-time">Heure</label><input type="time" class="form-control form-control-lg" id="ps-time" name="time" value="18:00" required></div>
                                    <div class="col-6"><label class="form-label fw-semibold" for="ps-duration">Durée</label><select class="form-select form-select-lg" id="ps-duration" name="duration_minutes">@foreach ([60 => '1 h', 90 => '1 h 30', 120 => '2 h', 180 => '3 h'] as $minutes => $label)<option value="{{ $minutes }}" @selected($minutes === 90)>{{ $label }}</option>@endforeach</select></div>
                                    <div class="col-6"><label class="form-label fw-semibold" for="ps-from">Du</label><input type="date" class="form-control" id="ps-from" name="from" value="{{ ($groupe->starts_on && $groupe->starts_on->isFuture() ? $groupe->starts_on : today())->format('Y-m-d') }}" required></div>
                                    <div class="col-6"><label class="form-label fw-semibold" for="ps-to">Au</label><input type="date" class="form-control" id="ps-to" name="to" value="{{ ($groupe->ends_on ?? today()->addMonths(2))->format('Y-m-d') }}" required></div>
                                    <div class="col-12"><label class="form-label small" for="ps-title">Thème (facultatif)</label><input class="form-control" id="ps-title" name="title" maxlength="200" placeholder="Cours d'anglais"></div>
                                    <p class="small text-muted mb-0">Les dates déjà programmées ne sont pas doublées. Chaque apprenant reçoit son calendrier par e-mail.</p>
                                </div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button><button type="submit" class="btn btn-success btn-lg"><i class='bx bx-check me-1'></i>Programmer</button></div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="add-seance" tabindex="-1" aria-hidden="true" aria-labelledby="add-seance-title">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('groupes.seances.store', $groupe) }}" data-crud-form data-no-guard>
                            @csrf
                            <div class="modal-header"><h5 class="modal-title" id="add-seance-title"><i class='bx bx-plus me-1'></i>Ajouter une séance</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    <div class="col-7"><label class="form-label fw-semibold" for="as-at">Date et heure</label><input type="datetime-local" class="form-control" id="as-at" name="starts_at" required value="{{ now()->addDay()->setTime(18, 0)->format('Y-m-d\TH:i') }}"></div>
                                    <div class="col-5"><label class="form-label fw-semibold" for="as-duration">Durée</label><select class="form-select" id="as-duration" name="duration_minutes">@foreach ([60 => '1 h', 90 => '1 h 30', 120 => '2 h', 180 => '3 h'] as $minutes => $label)<option value="{{ $minutes }}" @selected($minutes === 90)>{{ $label }}</option>@endforeach</select></div>
                                    <div class="col-12"><label class="form-label small" for="as-title">Thème</label><input class="form-control" id="as-title" name="title" maxlength="200"></div>
                                    @if ($online)<div class="col-12"><label class="form-label small" for="as-meet">Autre lien Google Meet (sinon celui du groupe)</label><input class="form-control" id="as-meet" name="meet_url" maxlength="300"></div>@endif
                                    <div class="col-12"><label class="form-label small" for="as-notes">Informations pratiques</label><input class="form-control" id="as-notes" name="notes" maxlength="1000"></div>
                                </div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button><button type="submit" class="btn btn-success"><i class='bx bx-check me-1'></i>Ajouter</button></div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud-ajax.js') }}?v={{ filemtime(public_path('assets/js/crud-ajax.js')) }}"></script>
    <script>CrudAjax.init(document.querySelector('[data-crud="groupe-{{ $groupe->id }}"]'));</script>
@endpush
