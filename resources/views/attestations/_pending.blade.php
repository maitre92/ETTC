{{-- Résultat du traitement (avant / après) et bouton « Enregistrer », affichés après l'aperçu. $kind. --}}
<div class="mt-3" data-pending hidden>
    <div class="row g-2 align-items-center">
        <div class="col-6 text-center"><div class="small text-muted mb-1">Image déposée</div><img alt="Image d'origine" class="img-fluid border rounded" style="max-height:140px" data-original></div>
        <div class="col-6 text-center"><div class="small text-muted mb-1">Après traitement</div><div class="att-checker p-2"><img alt="Image traitée, prête pour l'attestation" class="img-fluid" style="max-height:124px" data-processed></div></div>
    </div>
    <form method="POST" action="{{ route('attestations.save', $kind) }}" class="d-flex flex-wrap gap-2 mt-3">
        @csrf
        <button type="submit" class="btn btn-success"><i class='bx bx-check me-1'></i>Enregistrer {{ $kind === 'stamp' ? 'ce sceau' : 'cette signature' }}</button>
        <span class="small text-muted align-self-center">Regardez l'aperçu à droite : c'est le rendu des prochaines attestations.</span>
    </form>
</div>
<div class="alert alert-danger small mt-2 mb-0" data-error hidden></div>
