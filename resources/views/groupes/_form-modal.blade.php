{{-- Création ou modification d'un groupe ($groupe ou null, $modalId, $action, $method, $niveaux). Le lien Google Meet est
     demandé pour un groupe en ligne, la salle pour un groupe en présentiel (data-show-when). --}}
@php
    $g = $groupe;
    // Formateurs : comptes actifs au rôle « Formateur » (Utilisateurs) ; le groupe apparaît dans leur espace.
    $formateurs = \App\Models\User::role(\App\Enums\RoleName::Formateur->value)->active()->orderBy('name')->get();
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true" aria-labelledby="{{ $modalId }}-title">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}" data-crud-form data-no-guard>
                @csrf
                @if ($method !== 'POST') @method($method) @endif
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $modalId }}-title"><i class='bx bx-group me-2'></i>{{ $g ? 'Modifier le groupe' : 'Nouveau groupe' }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-semibold" for="{{ $modalId }}-name">Nom du groupe <span class="text-danger">*</span></label><input class="form-control form-control-lg" id="{{ $modalId }}-name" name="name" maxlength="120" required value="{{ $g?->name }}" placeholder="Débutants — soir — janvier 2027"></div>
                        <div class="col-md-6">
                            <span class="form-label fw-semibold d-block">Mode <span class="text-danger">*</span></span>
                            <div class="btn-group w-100" role="group" aria-label="Mode">
                                @foreach (\App\Enums\FormationMode::cases() as $mode)
                                    <input type="radio" class="btn-check" name="mode" id="{{ $modalId }}-mode-{{ $mode->value }}" value="{{ $mode->value }}" @checked(($g?->mode ?? \App\Enums\FormationMode::EnLigne) === $mode)>
                                    <label class="btn btn-outline-primary btn-lg" for="{{ $modalId }}-mode-{{ $mode->value }}">{{ $mode->label() }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12" data-show-when="mode:en_ligne"><label class="form-label fw-semibold" for="{{ $modalId }}-meet">Lien Google Meet <span class="text-danger">*</span></label><input class="form-control" id="{{ $modalId }}-meet" name="meet_url" maxlength="300" value="{{ $g?->meet_url }}" placeholder="meet.google.com/abc-defg-hij"><div class="form-text">Le même lien sert à toutes les séances du groupe (modifiable pour une séance).</div></div>
                        <div class="col-12" data-show-when="mode:presentiel"><label class="form-label fw-semibold" for="{{ $modalId }}-loc">Salle</label><input class="form-control" id="{{ $modalId }}-loc" name="location" maxlength="200" value="{{ $g?->location }}" placeholder="Salle 1"></div>
                        <div class="col-md-6"><label class="form-label fw-semibold" for="{{ $modalId }}-niveau">Niveau</label><select class="form-select" id="{{ $modalId }}-niveau" name="niveau_id"><option value="">Tous niveaux</option>@foreach ($niveaux as $niveau)<option value="{{ $niveau->id }}" @selected($g?->niveau_id === $niveau->id)>{{ $niveau->name }}</option>@endforeach</select></div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="{{ $modalId }}-capacity">Places (capacité)</label>
                            <input type="number" class="form-control" id="{{ $modalId }}-capacity" name="capacity" min="1" max="500" step="1" value="{{ $g?->capacity }}" placeholder="15">
                            <div class="form-text">Vide : sans limite. Un groupe plein n'accepte plus d'apprenants.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold" for="{{ $modalId }}-formateur">Formateur</label>
                            @if ($formateurs->isNotEmpty())
                                <select class="form-select" id="{{ $modalId }}-formateur" name="formateur_id">
                                    <option value="">— À choisir —</option>
                                    @foreach ($formateurs as $formateur)
                                        <option value="{{ $formateur->id }}" @selected($g?->formateur_id === $formateur->id)>{{ $formateur->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Il retrouve ce groupe dans son espace (séances, supports, discussion).</div>
                            @else
                                <input class="form-control" id="{{ $modalId }}-teacher" name="teacher" maxlength="120" value="{{ $g?->teacher }}">
                                <div class="form-text">Pour lui donner son espace : Utilisateurs → nouveau compte, rôle « Formateur ».</div>
                            @endif
                        </div>
                        <div class="col-12">
                            <details @if ($g?->schedule || $g?->notes) open @endif>
                                <summary class="small text-primary" style="cursor:pointer">Plus d'informations (horaire, dates, notes) — facultatif</summary>
                                <div class="row g-2 mt-1">
                                    <div class="col-md-6"><label class="form-label small" for="{{ $modalId }}-schedule">Horaire habituel</label><input class="form-control" id="{{ $modalId }}-schedule" name="schedule" maxlength="200" value="{{ $g?->schedule }}" placeholder="Lundi et mercredi, 18h–19h30"></div>
                                    <div class="col-md-3"><label class="form-label small" for="{{ $modalId }}-start">Début</label><input type="date" class="form-control" id="{{ $modalId }}-start" name="starts_on" value="{{ $g?->starts_on?->format('Y-m-d') }}"></div>
                                    <div class="col-md-3"><label class="form-label small" for="{{ $modalId }}-end">Fin</label><input type="date" class="form-control" id="{{ $modalId }}-end" name="ends_on" value="{{ $g?->ends_on?->format('Y-m-d') }}"></div>
                                    <div class="col-12"><label class="form-label small" for="{{ $modalId }}-notes">Informations pratiques (vues par les apprenants)</label><textarea class="form-control" id="{{ $modalId }}-notes" name="notes" rows="2" maxlength="3000">{{ $g?->notes }}</textarea></div>
                                    @if ($g)
                                        <div class="col-12"><div class="form-check form-switch"><input type="hidden" name="is_active" value="0"><input class="form-check-input" type="checkbox" id="{{ $modalId }}-active" name="is_active" value="1" @checked($g->is_active)><label class="form-check-label" for="{{ $modalId }}-active">Groupe actif</label></div></div>
                                    @endif
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="submit" class="btn btn-primary btn-lg"><i class='bx bx-save me-1'></i>{{ $g ? 'Enregistrer' : 'Créer le groupe' }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
