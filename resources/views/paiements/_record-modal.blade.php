{{-- Modale « Encaisser un paiement » ($inscription, $modalId, $owner = zone data-crud à actualiser). --}}
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true" aria-labelledby="{{ $modalId }}-title" data-crud-owner="{{ $owner }}">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $modalId }}-title"><i class='bx bx-money me-1'></i>Encaisser — {{ $inscription->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body">@include('paiements._record-form', ['inscription' => $inscription, 'formId' => $modalId.'-form', 'back' => $back ?? 'inscription'])</div>
        </div>
    </div>
</div>
