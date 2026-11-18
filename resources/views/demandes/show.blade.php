@extends('layouts.admin')

@section('title', 'Demande '.$demande->reference())

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Demandes et apprenants</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('demandes.index') }}">Demandes</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $demande->reference() }}</li>
                </ol>
            </nav>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
            @if ($demande->email)
                <a href="mailto:{{ $demande->email }}?subject={{ rawurlencode('Votre demande '.$demande->reference()) }}" class="btn btn-primary btn-sm"><i class='bx bx-envelope me-1'></i>Répondre par e-mail</a>
            @endif
            @if ($demande->phone && \App\Support\PhoneNumber::normalize($demande->phone))
                <a href="tel:{{ \App\Support\PhoneNumber::normalize($demande->phone) }}" class="btn btn-outline-primary btn-sm"><i class='bx bx-phone me-1'></i>Appeler</a>
            @endif
        </div>
    </div>
    <hr />

    @if ($demande->notification_error)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
            <i class='bx bx-error fs-4'></i>
            <div class="flex-grow-1"><strong>Notification e-mail non envoyée.</strong> {{ $demande->notification_error }}</div>
            @can('update', $demande)
                <form method="POST" action="{{ route('demandes.notify', $demande) }}">@csrf<button class="btn btn-sm btn-warning" type="submit"><i class='bx bx-refresh me-1'></i>Réessayer</button></form>
            @endcan
        </div>
    @endif

    <div class="row">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="mb-0 text-white"><i class='{{ $demande->type->icon() }} me-2'></i>{{ mb_strtoupper($demande->need_type?->label() ?? $demande->type->label()) }}</h6>
                    <span class="badge {{ $demande->status->badge() }}">{{ $demande->status->label() }}</span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="nv-panel h-100">
                                <div class="nv-panel-title">Demandeur</div>
                                <dl class="nv-dl">
                                    <dt>Nom</dt><dd>{{ $demande->name }}</dd>
                                    <dt>Organisation</dt><dd>{{ $demande->organization ?? '—' }}</dd>
                                    <dt>E-mail</dt><dd class="text-break">{{ $demande->email ?? '—' }}</dd>
                                    <dt>Téléphone</dt><dd>{{ $demande->phone ?? '—' }}</dd>
                                </dl>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="nv-panel h-100">
                                <div class="nv-panel-title">Demande</div>
                                <dl class="nv-dl">
                                    <dt>Reçue le</dt><dd>{{ $demande->created_at->format('d/m/Y à H:i') }}</dd>
                                    @if ($demande->subject_label)
                                        <dt>Concerne</dt>
                                        <dd>
                                            {{ $demande->subject_label }}
                                        </dd>
                                    @endif
                                    @if ($demande->need_type)<dt>Besoin</dt><dd>{{ $demande->need_type->label() }}</dd>@endif
                                    @if ($demande->languagesLabel())<dt>Langues</dt><dd>{{ $demande->languagesLabel() }}</dd>@endif
                                    @if ($demande->document_type)<dt>Document</dt><dd>{{ $demande->document_type }}</dd>@endif
                                    @if ($demande->participants)<dt>Participants</dt><dd>{{ $demande->participants }}</dd>@endif
                                    @if ($demande->budget)<dt>Budget</dt><dd>{{ $demande->budget }}</dd>@endif
                                    @if ($demande->deadline)<dt>Délai</dt><dd>{{ $demande->deadline }}</dd>@endif
                                    @if ($demande->availability)<dt>Disponibilités</dt><dd>{{ $demande->availability }}</dd>@endif
                                    <dt>Consentement</dt><dd>{{ $demande->consent_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                    @if ($demande->subject)
                        <h6 class="mt-4">{{ $demande->subject }}</h6>
                    @endif
                    @if ($demande->message)
                        <div class="nv-panel mt-3 text-break">{!! nl2br(e($demande->message)) !!}</div>
                    @endif
                    @if ($demande->attachment_path)
                        <a href="{{ route('demandes.attachment', $demande) }}" class="btn btn-outline-primary btn-sm mt-3"><i class='bx bx-paperclip me-1'></i>{{ $demande->attachment_name }} ({{ \Illuminate\Support\Number::fileSize((int) $demande->attachment_size, precision: 1) }})</a>
                    @endif
                    @if ($demande->source_url)
                        <p class="small text-muted mt-3 mb-0 text-break">Envoyée depuis : {{ $demande->source_url }}</p>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header card-header-brand d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 text-white"><i class='bx bx-note me-2'></i>NOTES INTERNES <span class="badge bg-light text-dark ms-1" id="notes-count">{{ $demande->notes->count() }}</span></h6>
                </div>
                <div class="card-body">
                    <p class="small text-muted">Visibles uniquement par l'équipe : échanges avec le prospect, relances, informations utiles.</p>
                    @can('update', $demande)
                        <form method="POST" action="{{ route('demandes.notes.store', $demande) }}" id="note-form" class="mb-3">
                            @csrf
                            <label for="note-body" class="visually-hidden">Nouvelle note</label>
                            <textarea class="form-control @error('body') is-invalid @enderror" id="note-body" name="body" rows="3" maxlength="3000" placeholder="Ajouter une note…" required></textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="d-flex justify-content-end mt-2">
                                <button type="submit" class="btn btn-primary btn-sm"><i class='bx bx-plus me-1'></i>Ajouter la note</button>
                            </div>
                        </form>
                    @endcan
                    <div id="notes-list">
                        @forelse ($demande->notes as $note)
                            @include('demandes._note', ['note' => $note])
                        @empty
                            <p class="text-muted small mb-0" id="notes-empty">Aucune note pour l'instant.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            @if ($demande->acceptsProposal())
                @can('update', $demande)
                    {{-- Proposition chiffrée (cahier §3.7, §3.9, §3.10) : un montant, un texte, envoyés au demandeur qui accepte d'un clic. --}}
                    <div class="card border-primary">
                        <div class="card-header card-header-brand d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-white"><i class='bx bx-purchase-tag me-2'></i>PROPOSITION</h6>
                            @if ($demande->accepted_at)<span class="badge bg-success">Acceptée le {{ $demande->accepted_at->format('d/m/Y') }}</span>@elseif ($demande->proposal_sent_at)<span class="badge bg-info-subtle">Envoyée le {{ $demande->proposal_sent_at->format('d/m/Y') }}</span>@endif
                        </div>
                        <div class="card-body">
                            @if ($demande->accepted_at)
                                <p class="fs-4 fw-bold text-success mb-1">{{ \App\Models\Formule::money((int) $demande->proposal_amount) }}</p>
                                <p class="small">{!! nl2br(e($demande->proposal_text)) !!}</p>
                                @if ($demande->need_type === \App\Enums\NeedType::FormationPersonnalisee)
                                    @if ($demande->inscription)
                                        <a href="{{ route('inscriptions.show', $demande->inscription) }}" class="btn btn-primary w-100"><i class='bx bx-show me-1'></i>Voir l'inscription {{ $demande->inscription->number }}</a>
                                    @elseif (auth()->user()->can('inscriptions.create'))
                                        <p class="small mb-2">Dernière étape : créer son inscription au tarif accepté, puis encaisser.</p>
                                        <form method="POST" action="{{ route('demandes.inscribe', $demande) }}" class="d-flex gap-2">
                                            @csrf
                                            <select class="form-select" name="mode" aria-label="Mode de formation">@foreach (\App\Enums\FormationMode::options() as $value => $label)<option value="{{ $value }}" @selected($value === 'presentiel')>{{ $label }}</option>@endforeach</select>
                                            <button type="submit" class="btn btn-success text-nowrap"><i class='bx bx-user-check me-1'></i>Inscrire</button>
                                        </form>
                                    @endif
                                @endif
                            @else
                                <form method="POST" action="{{ route('demandes.proposal', $demande) }}">
                                    @csrf
                                    <label class="form-label fw-semibold" for="p-amount">Montant (FCFA)</label>
                                    <input class="form-control form-control-lg @error('proposal_amount') is-invalid @enderror" id="p-amount" name="proposal_amount" type="number" min="1" step="1" required value="{{ old('proposal_amount', $demande->proposal_amount) }}">
                                    @error('proposal_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <label class="form-label fw-semibold mt-2" for="p-text">Ce que comprend la proposition</label>
                                    <textarea class="form-control @error('proposal_text') is-invalid @enderror" id="p-text" name="proposal_text" rows="4" maxlength="3000" required placeholder="{{ match ($demande->need_type) { \App\Enums\NeedType::Traduction => 'Traduction de 3 pages, livrée sous 5 jours…', \App\Enums\NeedType::FormationPersonnalisee => '12 séances d\'1 h 30, 2 fois par semaine, en ligne…', \App\Enums\NeedType::Excursion => 'Voyage du … au …, transport, hébergement, activités…', default => 'Détail de la proposition…' } }}">{{ old('proposal_text', $demande->proposal_text) }}</textarea>
                                    @error('proposal_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <button type="submit" class="btn btn-success btn-lg w-100 mt-3"><i class='bx bx-send me-1'></i>{{ $demande->proposal_sent_at ? 'Renvoyer la proposition' : 'Envoyer la proposition' }}</button>
                                    <p class="small text-muted mt-2 mb-0">{{ $demande->email ? 'Le demandeur la reçoit par e-mail et l\'accepte d\'un clic.' : 'Pas d\'e-mail : après l\'envoi, transmettez-lui le lien par WhatsApp.' }}</p>
                                </form>
                                @if ($demande->proposal_token && ($wa = app(\App\Support\SiteSettings::class)->whatsappTo($demande->phone, 'Bonjour '.$demande->name.', voici notre proposition : '.route('site.proposition.show', $demande->proposal_token))))
                                    <a href="{{ $wa }}" class="btn btn-outline-success w-100 mt-2" target="_blank" rel="noopener"><i class='bx bxl-whatsapp me-1'></i>Envoyer le lien par WhatsApp</a>
                                @endif
                            @endif
                        </div>
                    </div>
                @endcan
            @endif
            @can('update', $demande)
                <div class="card">
                    <div class="card-header card-header-brand">
                        <h6 class="mb-0 text-white"><i class='bx bx-transfer-alt me-2'></i>TRAITEMENT</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('demandes.status', $demande) }}">
                            @csrf
                            @method('PUT')
                            <label for="status" class="form-label fw-semibold">Statut</label>
                            <select class="form-select mb-2" id="status" name="status">
                                @foreach (\App\Enums\DemandeStatus::cases() as $case)
                                    <option value="{{ $case->value }}" @selected($demande->status === $case) title="{{ $case->meaning() }}">{{ $case->label() }}</option>
                                @endforeach
                            </select>
                            <div id="closed-reason-group" class="{{ $demande->status === \App\Enums\DemandeStatus::Perdue ? '' : 'd-none' }}">
                                <label for="closed_reason" class="form-label fw-semibold">Motif (facultatif)</label>
                                <textarea class="form-control mb-2" id="closed_reason" name="closed_reason" rows="2" maxlength="500" placeholder="Budget insuffisant, sans réponse, doublon…">{{ $demande->closed_reason }}</textarea>
                            </div>
                            <p class="small text-muted" id="status-meaning">{{ $demande->status->meaning() }}</p>
                            <button type="submit" class="btn btn-primary w-100"><i class='bx bx-check me-1'></i>Mettre à jour le statut</button>
                        </form>
                        <hr>
                        <form method="POST" action="{{ route('demandes.assign', $demande) }}">
                            @csrf
                            @method('PUT')
                            <label for="assigned_to" class="form-label fw-semibold">Suivie par</label>
                            <select class="form-select single-select mb-2" id="assigned_to" name="assigned_to">
                                <option value="">— Personne —</option>
                                @foreach ($assignees as $user)
                                    <option value="{{ $user->id }}" @selected($demande->assigned_to === $user->id)>{{ $user->displayName() }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-outline-primary w-100">Attribuer</button>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="card">
                <div class="card-header card-header-brand">
                    <h6 class="mb-0 text-white"><i class='bx bx-history me-2'></i>HISTORIQUE</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled small mb-0">
                        @foreach ($history as $log)
                            <li class="mb-2 pb-2 border-bottom">
                                <div class="text-muted">{{ $log->created_at->format('d/m/Y H:i') }} · {{ $log->isBySuperadmin() && ! auth()->user()->isSuperadmin() ? config('brand.system_actor') : ($log->user_name ?? 'Site public') }}</div>
                                <div>{{ $log->description }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            @can('delete', $demande)
                <form method="POST" action="{{ route('demandes.destroy', $demande) }}" class="js-confirm-delete" data-title="Supprimer cette demande ?" data-name="La demande {{ $demande->reference() }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger w-100"><i class='bx bx-trash me-1'></i>Supprimer (spam, doublon)</button>
                </form>
            @endcan
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const meanings = @json(collect(\App\Enums\DemandeStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->meaning()]));
            const status = document.getElementById('status');
            if (status) {
                status.addEventListener('change', () => {
                    document.getElementById('closed-reason-group').classList.toggle('d-none', status.value !== 'perdue');
                    document.getElementById('status-meaning').textContent = meanings[status.value] || '';
                });
            }

            // Note interne ajoutée sans rechargement (AJAX) ; sans JavaScript, le formulaire s'envoie normalement.
            const form = document.getElementById('note-form');
            if (!form) return;
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                const body = form.querySelector('textarea');
                if (!body.value.trim()) { body.focus(); return; }
                const button = form.querySelector('button[type=submit]');
                button.disabled = true;
                $.ajax({ url: form.action, method: 'POST', data: $(form).serialize(), headers: { Accept: 'application/json' } })
                    .done((data) => {
                        document.getElementById('notes-empty')?.remove();
                        document.getElementById('notes-list').insertAdjacentHTML('afterbegin', data.html);
                        document.getElementById('notes-count').textContent = data.count;
                        body.value = '';
                        Swal.fire({ icon: 'success', text: 'Note ajoutée.', timer: 1500, showConfirmButton: false });
                    })
                    .fail((xhr) => Swal.fire({ icon: 'error', text: xhr.responseJSON?.message || 'La note n\'a pas été enregistrée.' }))
                    .always(() => { button.disabled = false; });
            });
        })();
    </script>
    @include('partials.confirm-delete')
@endpush
