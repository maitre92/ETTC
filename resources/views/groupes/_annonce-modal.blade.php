{{-- Fenêtre « Annonce aux apprenants » ($modalId, $groupes pour le choix des destinataires, $groupe pour un groupe
     imposé, $owner = zone data-crud). Prévenance par e-mail cochée d'office. --}}
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true" aria-labelledby="{{ $modalId }}-title">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('groupes.annonces.store') }}" data-crud-form data-no-guard>
                @csrf
                @if ($groupe ?? null)<input type="hidden" name="back" value="{{ $groupe->id }}">@endif
                <div class="modal-header"><h5 class="modal-title" id="{{ $modalId }}-title"><i class='bx bx-megaphone me-1'></i>Annonce {{ ($groupe ?? null) ? 'au groupe '.$groupe->name : 'aux apprenants' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label fw-semibold" for="{{ $modalId }}-title-input">Titre</label><input class="form-control form-control-lg" id="{{ $modalId }}-title-input" name="title" maxlength="150" required placeholder="Ex. Pas de cours lundi (jour férié)"></div>
                        <div class="col-12"><label class="form-label fw-semibold" for="{{ $modalId }}-body">Message</label><textarea class="form-control" id="{{ $modalId }}-body" name="body" rows="4" maxlength="3000" required></textarea></div>
                        @if ($groupe ?? null)
                            <input type="hidden" name="groupe_id" value="{{ $groupe->id }}">
                        @else
                            <div class="col-md-7"><label class="form-label fw-semibold" for="{{ $modalId }}-groupe">Pour qui ?</label><select class="form-select" id="{{ $modalId }}-groupe" name="groupe_id"><option value="">Tous les apprenants</option>@foreach ($groupes as $choice)<option value="{{ $choice->id }}">Groupe {{ $choice->name }}</option>@endforeach</select></div>
                        @endif
                        <div class="col-md-5"><label class="form-label small" for="{{ $modalId }}-expires">Affichée jusqu'au (facultatif)</label><input type="date" class="form-control" id="{{ $modalId }}-expires" name="expires_on" min="{{ today()->format('Y-m-d') }}"></div>
                        <div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" id="{{ $modalId }}-email" name="email" value="1" checked><label class="form-check-label" for="{{ $modalId }}-email">Prévenir aussi par e-mail</label></div></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button><button type="submit" class="btn btn-success btn-lg"><i class='bx bx-send me-1'></i>Publier</button></div>
            </form>
        </div>
    </div>
</div>
