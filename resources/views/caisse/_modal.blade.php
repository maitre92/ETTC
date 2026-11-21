{{-- Saisie d'un mouvement de caisse : $type (entree, sortie, transfert), $title, $icon, $color. Peu de champs obligatoires :
     catégorie, libellé, montant, moyen, date ; le reste est facultatif. --}}
@php
    $id = 'mvt-'.$type;
    $placeholder = ['entree' => 'Ex. Traduction d\'un diplôme — M. Coulibaly', 'sortie' => 'Ex. Facture d\'électricité de novembre', 'transfert' => 'Ex. Dépôt des espèces de la semaine à la banque'][$type];
@endphp
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-hidden="true" aria-labelledby="{{ $id }}-title">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('caisse.store', request()->only('periode', 'du', 'au', 'moyen')) }}" enctype="multipart/form-data" data-crud-form data-no-guard>
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $id }}-title"><i class='bx {{ $icon }} me-1'></i>{{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        @if ($type === 'transfert')
                            <input type="hidden" name="category" value="transfert">
                        @else
                            <div class="col-12">
                                <label class="form-label fw-semibold" for="{{ $id }}-category">Catégorie <span class="text-danger">*</span></label>
                                <select class="form-select form-select-lg" id="{{ $id }}-category" name="category" required>
                                    <option value="">— Choisir —</option>
                                    @foreach (\App\Enums\CashCategory::optionsFor($type) as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                </select>
                            </div>
                        @endif
                        <div class="col-12"><label class="form-label fw-semibold" for="{{ $id }}-label">Libellé <span class="text-danger">*</span></label><input class="form-control" id="{{ $id }}-label" name="label" maxlength="200" required placeholder="{{ $placeholder }}"></div>
                        <div class="col-sm-6"><label class="form-label fw-semibold" for="{{ $id }}-amount">Montant (FCFA) <span class="text-danger">*</span></label><input type="number" class="form-control form-control-lg" id="{{ $id }}-amount" name="amount" min="1" step="1" required></div>
                        <div class="col-sm-6"><label class="form-label fw-semibold" for="{{ $id }}-date">Date <span class="text-danger">*</span></label><input type="date" class="form-control form-control-lg" id="{{ $id }}-date" name="occurred_on" max="{{ now()->format('Y-m-d') }}" value="{{ now()->format('Y-m-d') }}" required></div>
                        <div class="{{ $type === 'transfert' ? 'col-sm-6' : 'col-12' }}">
                            <label class="form-label fw-semibold" for="{{ $id }}-method">{{ ['entree' => 'Reçu en', 'sortie' => 'Payé en', 'transfert' => 'Depuis'][$type] }} <span class="text-danger">*</span></label>
                            @include('paiements._method-select', ['id' => $id.'-method', 'name' => 'method', 'selected' => 'especes'])
                        </div>
                        @if ($type === 'transfert')
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold" for="{{ $id }}-to">Vers <span class="text-danger">*</span></label>
                                @include('paiements._method-select', ['id' => $id.'-to', 'name' => 'to_method', 'selected' => 'banque'])
                            </div>
                        @endif
                        <div class="col-12">
                            <details>
                                <summary class="small text-primary" style="cursor:pointer">Plus d'informations (référence, {{ $type === 'sortie' ? 'bénéficiaire, facture' : 'payeur, pièce' }}) — facultatif</summary>
                                <div class="row g-2 mt-1">
                                    <div class="col-sm-6"><label class="form-label small" for="{{ $id }}-ref">Référence</label><input class="form-control" id="{{ $id }}-ref" name="reference" maxlength="100" placeholder="N° de facture, de chèque, SMS…"></div>
                                    <div class="col-sm-6"><label class="form-label small" for="{{ $id }}-party">{{ $type === 'sortie' ? 'Bénéficiaire' : 'Payeur' }}</label><input class="form-control" id="{{ $id }}-party" name="party" maxlength="150"></div>
                                    <div class="col-12"><label class="form-label small" for="{{ $id }}-proof">Justificatif (photo de la facture, du reçu… 5 Mo)</label><input type="file" class="form-control" id="{{ $id }}-proof" name="proof" accept="image/png,image/jpeg,image/webp,application/pdf"></div>
                                    <div class="col-12"><label class="form-label small" for="{{ $id }}-notes">Remarque</label><input class="form-control" id="{{ $id }}-notes" name="notes" maxlength="1000"></div>
                                </div>
                            </details>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-{{ $color }} btn-lg"><i class='bx bx-check me-1'></i>Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>
