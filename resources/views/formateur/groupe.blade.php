@extends('layouts.admin')

@section('title', 'Groupe '.$groupe->name)

{{-- Un groupe dans l'espace formateur : prochaine séance (Meet), séances (ajout, annulation), apprenants (appel,
     WhatsApp), discussion avec les apprenants, supports. Formulaires en AJAX (crud-ajax.js). --}}
@section('content')
    @php
        $online = $groupe->mode === \App\Enums\FormationMode::EnLigne;
        $next = $upcoming->firstWhere('is_cancelled', false);
        $site = app(\App\Support\SiteSettings::class);
    @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Espace formateur</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('formateur.index') }}">Mes groupes</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $groupe->name }}</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div data-crud="formateur-groupe-{{ $groupe->id }}">
        <span data-crud-status hidden>{{ session('status') }}</span>

        <div class="card border-primary">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <div class="flex-grow-1">
                    <h5 class="mb-1">{{ $groupe->name }}</h5>
                    <div class="small text-muted">{{ $groupe->mode->label() }} · {{ $groupe->niveau?->name ?? 'Tous niveaux' }} · {{ $groupe->members->count() }} apprenant(s)@if ($groupe->schedule) · {{ $groupe->schedule }}@endif @if (! $online && $groupe->location) · {{ $groupe->location }}@endif</div>
                    <div class="mt-2">{!! $next ? 'Prochaine séance : <strong>'.e(ucfirst($next->when())).'</strong>'.($next->title ? ' — '.e($next->title) : '') : '<span class="text-danger">Aucune séance à venir.</span>' !!}</div>
                </div>
                @if ($next && $next->joinUrl())
                    <a href="{{ $next->joinUrl() }}" class="btn btn-success btn-lg" target="_blank" rel="noopener"><i class='bx bx-video me-1'></i>Ouvrir Google Meet</a>
                @endif
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-7">
                @include('groupes._discussion', [
                    'groupe' => $groupe,
                    'postUrl' => route('formateur.groupes.messages.store', $groupe),
                    'deleteUrl' => fn ($msg) => route('formateur.groupes.messages.destroy', [$groupe, $msg]),
                ])

                <div class="card" id="seances">
                    <div class="card-header card-header-brand d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h6 class="mb-0 text-white"><i class='bx bx-calendar me-2'></i>SÉANCES À VENIR</h6>
                        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#add-seance"><i class='bx bx-plus'></i> Ajouter une séance</button>
                    </div>
                    <div class="card-body">
                        @forelse ($upcoming as $seance)
                            <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
                                <span class="flex-grow-1 {{ $seance->is_cancelled ? 'text-decoration-line-through text-muted' : '' }}"><strong>{{ ucfirst($seance->when()) }}</strong>@if ($seance->title) — {{ $seance->title }}@endif</span>
                                @if ($seance->is_cancelled)
                                    <span class="badge bg-secondary-subtle">Annulée{{ $seance->cancel_reason ? ' : '.$seance->cancel_reason : '' }}</span>
                                @else
                                    @if ($seance->joinUrl())<a href="{{ $seance->joinUrl() }}" class="btn btn-outline-success btn-sm" target="_blank" rel="noopener" title="Ouvrir Google Meet"><i class='bx bx-video'></i></a>@endif
                                    <details class="text-end">
                                        <summary class="btn btn-outline-danger btn-sm">Annuler</summary>
                                        <form method="POST" action="{{ route('formateur.groupes.seances.cancel', [$groupe, $seance]) }}" class="d-flex gap-1 mt-1" data-crud-form data-no-guard>
                                            @csrf @method('PUT')
                                            <input class="form-control form-control-sm" name="reason" maxlength="300" required placeholder="Motif (envoyé aux apprenants)" aria-label="Motif de l'annulation">
                                            <button type="submit" class="btn btn-danger btn-sm">OK</button>
                                        </form>
                                    </details>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">Aucune séance programmée. Ajoutez-en une, ou demandez au centre de programmer toute la série.</p>
                        @endforelse
                        @if ($past->isNotEmpty())
                            <details class="mt-3">
                                <summary class="small text-primary" style="cursor:pointer">Séances passées</summary>
                                <ul class="small text-muted mt-2 mb-0">@foreach ($past as $seance)<li class="{{ $seance->is_cancelled ? 'text-decoration-line-through' : '' }}">{{ ucfirst($seance->when()) }}@if ($seance->title) — {{ $seance->title }}@endif</li>@endforeach</ul>
                            </details>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-user me-2'></i>MES APPRENANTS ({{ $groupe->members->count() }})</h6></div>
                    <div class="card-body">
                        @forelse ($groupe->members as $member)
                            <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
                                <span class="flex-grow-1">{{ $member->name }}</span>
                                @if ($member->phone)
                                    <a href="tel:{{ $member->phone }}" class="btn btn-light btn-sm" title="Appeler {{ $member->name }}"><i class='bx bx-phone'></i></a>
                                    @if ($wa = $site->whatsappTo($member->phone, 'Bonjour '.$member->name.', '))<a href="{{ $wa }}" class="btn btn-light btn-sm" target="_blank" rel="noopener" title="WhatsApp"><i class='bx bxl-whatsapp'></i></a>@endif
                                @endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">Aucun apprenant pour l'instant : le centre les place dans le groupe.</p>
                        @endforelse
                    </div>
                </div>

                <div class="card" id="supports">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-folder-open me-2'></i>SUPPORTS DE COURS</h6></div>
                    <div class="card-body">
                        @forelse ($groupe->supports as $support)
                            <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom">
                                <i class='bx {{ $support->icon() }} fs-4 text-primary'></i>
                                <span class="flex-grow-1">{{ $support->title }} <span class="small text-muted">{{ $support->kindLabel() }}@if ($support->sizeLabel()) · {{ $support->sizeLabel() }}@endif</span></span>
                                @if ($support->url)
                                    <a href="{{ $support->url }}" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener"><i class='bx bx-link-external'></i></a>
                                @else
                                    <a href="{{ route('formateur.groupes.supports.download', [$groupe, $support]) }}" class="btn btn-outline-primary btn-sm"><i class='bx bx-download'></i></a>
                                @endif
                                <form method="POST" action="{{ route('formateur.groupes.supports.destroy', [$groupe, $support]) }}" data-crud-form data-no-guard data-confirm="Retirer le support « {{ $support->title }} » ?">@csrf @method('DELETE')<button type="submit" class="btn btn-light btn-sm" title="Retirer"><i class='bx bx-trash'></i></button></form>
                            </div>
                        @empty
                            <p class="text-muted">Aucun support pour l'instant.</p>
                        @endforelse
                        <form method="POST" action="{{ route('formateur.groupes.supports.store', $groupe) }}" enctype="multipart/form-data" class="row g-2 mt-2" data-crud-form data-no-guard>
                            @csrf
                            <div class="col-12"><label class="form-label small fw-semibold" for="s-title">Titre</label><input class="form-control" id="s-title" name="title" maxlength="200" required placeholder="Leçon 1 — vocabulaire"></div>
                            <div class="col-md-6"><label class="form-label small fw-semibold" for="s-file">Fichier (20 Mo max)</label><input class="form-control" type="file" id="s-file" name="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.odt,.txt,.jpg,.jpeg,.png,.mp3,.m4a,.wav,.ogg,.mp4,.webm"></div>
                            <div class="col-md-6"><label class="form-label small fw-semibold" for="s-url">… ou un lien</label><input class="form-control" id="s-url" name="url" maxlength="500" placeholder="youtu.be/… ou lien Drive"></div>
                            <div class="col-12"><button type="submit" class="btn btn-primary btn-sm"><i class='bx bx-upload me-1'></i>Ajouter le support</button> <span class="small text-muted">Visible aussitôt par vos apprenants.</span></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="add-seance" tabindex="-1" aria-hidden="true" aria-labelledby="add-seance-title">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('formateur.groupes.seances.store', $groupe) }}" data-crud-form data-no-guard>
                        @csrf
                        <div class="modal-header"><h5 class="modal-title" id="add-seance-title"><i class='bx bx-plus me-1'></i>Ajouter une séance</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-7"><label class="form-label fw-semibold" for="as-at">Date et heure</label><input type="datetime-local" class="form-control" id="as-at" name="starts_at" required value="{{ now()->addDay()->setTime(18, 0)->format('Y-m-d\TH:i') }}"></div>
                                <div class="col-5"><label class="form-label fw-semibold" for="as-duration">Durée</label><select class="form-select" id="as-duration" name="duration_minutes">@foreach ([60 => '1 h', 90 => '1 h 30', 120 => '2 h', 180 => '3 h'] as $minutes => $label)<option value="{{ $minutes }}" @selected($minutes === 90)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-12"><label class="form-label small" for="as-title">Thème</label><input class="form-control" id="as-title" name="title" maxlength="200" placeholder="Révision, leçon 3…"></div>
                                @if ($online)<div class="col-12"><label class="form-label small" for="as-meet">Autre lien Google Meet (sinon celui du groupe)</label><input class="form-control" id="as-meet" name="meet_url" maxlength="300"></div>@endif
                            </div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button><button type="submit" class="btn btn-success"><i class='bx bx-check me-1'></i>Ajouter</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud-ajax.js') }}?v={{ filemtime(public_path('assets/js/crud-ajax.js')) }}"></script>
    <script>CrudAjax.init(document.querySelector('[data-crud="formateur-groupe-{{ $groupe->id }}"]'));</script>
@endpush
