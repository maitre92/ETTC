{{-- Fenêtre « Inscrire au centre » (et depuis un test passé : $test, identité et niveau repris). Pensée pour l'accueil :
     1. le TÉLÉPHONE d'abord (une personne déjà connue remplit la fiche toute seule) ; 2. la formation, avec le total en
     direct ; 3. en présentiel, l'inscription et le PAIEMENT se font ensemble (encaissé tout de suite, reçu proposé).
     Le mode de paiement est déduit du mode de formation. $modalId, $action, $title, $niveaux, $formules, $test (ou null).
     Comportements : public/assets/js/inscription-form.js ; envoi AJAX : crud-ajax.js. --}}
@php $canPay = auth()->user()->can('paiements.record'); @endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true" aria-labelledby="{{ $modalId }}-title">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <form method="POST" action="{{ $action }}" data-crud-form data-no-guard data-inscription-form data-lookup="{{ $test ? '' : route('inscriptions.lookup') }}">
                @csrf
                <input type="hidden" name="payment_choice" value="au_centre">
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $modalId }}-title"><i class='bx bx-user-check me-2'></i>{{ $title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12"><h6 class="text-primary mb-0"><span class="badge rounded-pill bg-primary me-1">1</span> L'apprenant</h6></div>
                        @if ($test)
                            <div class="col-12"><div class="alert alert-info small mb-0"><strong>{{ $test->name }}</strong> — niveau attribué : <strong>{{ $test->niveau?->name ?? '—' }}</strong>@if ($test->contactLine()) · {{ $test->contactLine() }}@endif</div></div>
                            @unless ($test->phone)
                                <div class="col-md-6"><label class="form-label fw-semibold" for="{{ $modalId }}-phone">Téléphone <span class="text-danger">*</span></label><input class="form-control" id="{{ $modalId }}-phone" name="phone" maxlength="30" required></div>
                            @endunless
                        @else
                            <div class="col-md-5">
                                <label class="form-label fw-semibold" for="{{ $modalId }}-phone">Téléphone <span class="text-danger">*</span></label>
                                <input class="form-control form-control-lg" id="{{ $modalId }}-phone" name="phone" type="tel" maxlength="30" required placeholder="75 22 91 25" data-lookup-phone autocomplete="off">
                            </div>
                            <div class="col-md-7"><label class="form-label fw-semibold" for="{{ $modalId }}-name">Nom et prénom <span class="text-danger">*</span></label><input class="form-control form-control-lg" id="{{ $modalId }}-name" name="name" maxlength="150" required></div>
                            <div class="col-12" data-lookup-result hidden></div>
                            <div class="col-12">
                                <details>
                                    <summary class="small text-primary" style="cursor:pointer">Plus d'informations (e-mail, sexe, naissance, adresse) — facultatif</summary>
                                    <div class="row g-2 mt-1">
                                        <div class="col-md-6"><label class="form-label small" for="{{ $modalId }}-email">E-mail (pour envoyer le reçu)</label><input type="email" class="form-control" id="{{ $modalId }}-email" name="email" maxlength="190"></div>
                                        <div class="col-md-3"><label class="form-label small" for="{{ $modalId }}-gender">Sexe</label><select class="form-select" id="{{ $modalId }}-gender" name="gender"><option value="">—</option>@foreach (\App\Enums\Gender::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                                        <div class="col-md-3"><label class="form-label small" for="{{ $modalId }}-birth">Naissance</label><input type="date" class="form-control" id="{{ $modalId }}-birth" name="birth_date"></div>
                                        <div class="col-md-8"><label class="form-label small" for="{{ $modalId }}-address">Adresse</label><input class="form-control" id="{{ $modalId }}-address" name="address" maxlength="300"></div>
                                        <div class="col-md-4"><label class="form-label small" for="{{ $modalId }}-city">Ville</label><input class="form-control" id="{{ $modalId }}-city" name="city" maxlength="100" value="Ségou"></div>
                                    </div>
                                </details>
                            </div>
                        @endif

                        <div class="col-12 mt-4"><h6 class="text-primary mb-0"><span class="badge rounded-pill bg-primary me-1">2</span> La formation</h6></div>
                        @unless ($test)
                            <div class="col-md-4"><label class="form-label fw-semibold" for="{{ $modalId }}-niveau">Niveau</label><select class="form-select" id="{{ $modalId }}-niveau" name="niveau_id"><option value="">À déterminer (test gratuit)</option>@foreach ($niveaux->where('is_published', true) as $niveau)<option value="{{ $niveau->id }}">{{ $niveau->name }}</option>@endforeach</select></div>
                        @endunless
                        <div class="{{ $test ? 'col-md-7' : 'col-md-4' }}"><label class="form-label fw-semibold" for="{{ $modalId }}-formule">Formule <span class="text-danger">*</span></label>
                            <select class="form-select" id="{{ $modalId }}-formule" name="formule_id" required>
                                @foreach ($formules as $formule)
                                    <option value="{{ $formule->id }}" data-months="{{ $formule->duration_months }}" data-billing="{{ $formule->billing->value }}" data-price-presentiel="{{ $formule->price_onsite }}" data-price-en_ligne="{{ $formule->price_online }}" @selected($loop->first)>{{ $formule->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="{{ $test ? 'col-md-5' : 'col-md-4' }}">
                            <span class="form-label fw-semibold d-block">Mode <span class="text-danger">*</span></span>
                            <div class="btn-group w-100" role="group" aria-label="Mode de formation">
                                @foreach (\App\Enums\FormationMode::cases() as $mode)
                                    <input type="radio" class="btn-check" name="mode" id="{{ $modalId }}-mode-{{ $mode->value }}" value="{{ $mode->value }}" @checked($mode === \App\Enums\FormationMode::Presentiel) required>
                                    <label class="btn btn-outline-primary" for="{{ $modalId }}-mode-{{ $mode->value }}">{{ $mode->label() }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12"><div class="alert alert-light border mb-0 py-2" data-total aria-live="polite"></div></div>

                        @if ($canPay)
                            <div class="col-12 mt-4"><h6 class="text-primary mb-0"><span class="badge rounded-pill bg-primary me-1">3</span> Le paiement</h6></div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" id="{{ $modalId }}-paynow" name="pay_now" value="1" checked data-pay-now>
                                    <label class="form-check-label fw-semibold" for="{{ $modalId }}-paynow">L'apprenant paie maintenant</label>
                                </div>
                                <div class="form-text" data-pay-hint>En présentiel, l'inscription et le paiement se font ensemble.</div>
                            </div>
                            <div class="col-md-4" data-pay-field><label class="form-label fw-semibold" for="{{ $modalId }}-amount">Montant reçu (FCFA)</label><input class="form-control form-control-lg" id="{{ $modalId }}-amount" name="pay_amount" type="number" min="1" step="1" data-pay-amount></div>
                            <div class="col-md-4" data-pay-field><label class="form-label fw-semibold" for="{{ $modalId }}-method">Payé en</label>
                                <select class="form-select form-select-lg" id="{{ $modalId }}-method" name="pay_method">@foreach (\App\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}">{{ $method->isMobileMoney() ? $method->label() : 'Espèces' }}</option>@endforeach</select>
                            </div>
                            <div class="col-md-4" data-pay-field data-show-when="pay_method:orange_money,wave,moov_money"><label class="form-label fw-semibold" for="{{ $modalId }}-ref">Référence (SMS)</label><input class="form-control form-control-lg" id="{{ $modalId }}-ref" name="pay_reference" maxlength="100"></div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <button type="submit" class="btn btn-success btn-lg" data-submit-label><i class='bx bx-check-circle me-1'></i><span>Inscrire et encaisser</span></button>
                </div>
            </form>
        </div>
    </div>
</div>
