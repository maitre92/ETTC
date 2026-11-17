{{-- Fenêtre d'inscription par l'équipe : au centre, ou depuis un test passé ($test : identité et niveau repris du
     test). $modalId, $action, $title, $niveaux, $formules, $test (ou null). Envoi AJAX (crud-ajax.js). --}}
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}" data-crud-form data-no-guard>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class='bx bx-user-check me-2'></i>{{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Le tarif en vigueur de la formule et du mode choisis est figé sur l'inscription.</p>
                    <div class="row g-3">
                        @if ($test)
                            <div class="col-12"><div class="alert alert-info small mb-0"><strong>{{ $test->name }}</strong> — niveau attribué : <strong>{{ $test->niveau?->name ?? '—' }}</strong>@if ($test->contactLine()) · {{ $test->contactLine() }}@endif</div></div>
                            @unless ($test->phone)
                                <div class="col-md-6"><label class="form-label fw-semibold" for="{{ $modalId }}-phone">Téléphone <span class="text-danger">*</span></label><input class="form-control" id="{{ $modalId }}-phone" name="phone" maxlength="30" required></div>
                            @endunless
                        @else
                            <div class="col-md-8"><label class="form-label fw-semibold" for="{{ $modalId }}-name">Nom et prénom <span class="text-danger">*</span></label><input class="form-control" id="{{ $modalId }}-name" name="name" maxlength="150" required></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="{{ $modalId }}-gender">Sexe</label><select class="form-select" id="{{ $modalId }}-gender" name="gender"><option value="">—</option>@foreach (\App\Enums\Gender::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="{{ $modalId }}-phone">Téléphone <span class="text-danger">*</span></label><input class="form-control" id="{{ $modalId }}-phone" name="phone" maxlength="30" required placeholder="75 22 91 25"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="{{ $modalId }}-email">E-mail</label><input type="email" class="form-control" id="{{ $modalId }}-email" name="email" maxlength="190"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="{{ $modalId }}-birth">Date de naissance</label><input type="date" class="form-control" id="{{ $modalId }}-birth" name="birth_date"></div>
                            <div class="col-md-8"><label class="form-label fw-semibold" for="{{ $modalId }}-address">Adresse</label><input class="form-control" id="{{ $modalId }}-address" name="address" maxlength="300"></div>
                            <div class="col-md-4"><label class="form-label fw-semibold" for="{{ $modalId }}-city">Ville</label><input class="form-control" id="{{ $modalId }}-city" name="city" maxlength="100" value="Ségou"></div>
                            <div class="col-md-6"><label class="form-label fw-semibold" for="{{ $modalId }}-niveau">Niveau</label><select class="form-select" id="{{ $modalId }}-niveau" name="niveau_id"><option value="">À déterminer (test de niveau)</option>@foreach ($niveaux->where('is_published', true) as $niveau)<option value="{{ $niveau->id }}">{{ $niveau->name }}</option>@endforeach</select></div>
                        @endif
                        <div class="col-md-6"><label class="form-label fw-semibold" for="{{ $modalId }}-formule">Formule <span class="text-danger">*</span></label>
                            <select class="form-select" id="{{ $modalId }}-formule" name="formule_id" required><option value="">— Choisir —</option>
                                @foreach ($formules as $formule)
                                    <option value="{{ $formule->id }}">{{ $formule->name }} — @foreach ($formule->prices() as [$modeLabel, $amount]){{ $modeLabel }} {{ \App\Models\Formule::money($amount) }}@if (! $loop->last) / @endif @endforeach</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label fw-semibold" for="{{ $modalId }}-mode">Mode <span class="text-danger">*</span></label><select class="form-select" id="{{ $modalId }}-mode" name="mode" required>@foreach (\App\Enums\FormationMode::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label fw-semibold" for="{{ $modalId }}-pay">Paiement <span class="text-danger">*</span></label><select class="form-select" id="{{ $modalId }}-pay" name="payment_choice" required>@foreach (\App\Enums\PaymentChoice::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                        <div class="col-12"><label class="form-label fw-semibold" for="{{ $modalId }}-message">Remarques</label><textarea class="form-control" id="{{ $modalId }}-message" name="message" rows="2" maxlength="2000"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Inscrire</button>
                </div>
            </form>
        </div>
    </div>
</div>
