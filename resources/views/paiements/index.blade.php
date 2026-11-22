@extends('layouts.admin')

@section('title', 'Paiements')

{{-- Paiements (cahier §3.8, §5 « Gestion financière ») : recettes du mois, paiements mobile money à vérifier, liste de
     tous les paiements (reçus), impayés avec enregistrement d'un paiement, moyens de paiement. Zone [data-crud] en AJAX. --}}
@use('App\Enums\PaymentStatus')
@use('App\Enums\PaymentMethod')

@section('content')
    @php
        $toVerify = $paiements->where('status', PaymentStatus::AVerifier);
        $money = fn (int $amount) => \App\Models\Formule::money($amount);
    @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Finances</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Paiements</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div data-crud="paiements" data-crud-columns="5">
        <span data-crud-status hidden>{{ session('status') }}</span>
        @include('paiements._last-receipt')
        <div class="row g-3 mb-3">
            @foreach ([['Encaissé ce mois', $money($monthTotal), 'bx-wallet', 'success'], ['À vérifier', $toVerify->count().' ('.$money((int) $toVerify->sum('amount')).')', 'bx-search-alt', 'warning'], ['Reste à encaisser', $money((int) $unpaid->sum(fn ($i) => $i->balance())), 'bx-time-five', 'danger'], ['Remboursé ce mois', $money($refundedMonth), 'bx-undo', 'info']] as [$cardLabel, $cardValue, $cardIcon, $cardColor])
                <div class="col-6 col-xl-3">
                    <div class="card radius-10 mb-0 h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="rounded-circle bg-{{ $cardColor }}-subtle text-{{ $cardColor }} d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;font-size:1.5rem"><i class='bx {{ $cardIcon }}'></i></span>
                            <div><p class="mb-0 text-secondary small">{{ $cardLabel }}</p><h5 class="mb-0">{{ $cardValue }}</h5></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($toVerify->isNotEmpty())
            <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
                <i class='bx bx-mobile-alt fs-4'></i>
                <span class="flex-grow-1"><strong>{{ $toVerify->count() }} paiement(s) mobile money</strong> déclaré(s) par des apprenants : vérifiez-les sur le compte marchand avant de les confirmer.</span>
                <button type="button" class="btn btn-sm btn-warning" data-chip-target="statut-a_verifier">Afficher seulement ceux-ci</button>
            </div>
        @endif

        <div class="card">
            <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="mb-0 text-white"><i class='bx bx-wallet me-2'></i>PAIEMENTS ET REÇUS</h6>
                @can('paiements.configure')
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#moyens-paiement"><i class='bx bx-mobile-alt'></i> Moyens de paiement{{ $qrCodes ? ' · '.count($qrCodes).' QR' : '' }}</button>
                @endcan
            </div>
            <div class="card-body">
                <div class="nv-chips mb-3" id="paiements-filter">
                    <button type="button" class="nv-chip active" data-token="">Tous <b>{{ $paiements->count() }}</b></button>
                    @foreach (PaymentStatus::cases() as $status)
                        <button type="button" class="nv-chip" data-token="statut-{{ $status->value }}">{{ $status->label() }} <b>{{ $paiements->where('status', $status)->count() }}</b></button>
                    @endforeach
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6 col-lg-3"><select class="form-select form-select-sm" data-crud-filter-column="3" aria-label="Filtrer par moyen"><option value="">Tous les moyens</option>@foreach (PaymentMethod::cases() as $method)<option value="{{ $method->label() }}">{{ $method->label() }}</option>@endforeach</select></div>
                </div>
                <div class="table-responsive">
                    <table class="table" id="paiements-table">
                        <thead>
                            <tr><th>REÇU</th><th>APPRENANT</th><th>MONTANT</th><th>MOYEN</th><th>DATE</th><th>STATUT</th><th width="8%">ACTIONS</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($paiements as $paiement)
                                <tr class="{{ $paiement->status === PaymentStatus::AVerifier ? 'fw-semibold' : '' }}">
                                    <td data-order="{{ $paiement->id }}">{{ $paiement->number }}<div class="small text-muted fw-normal">{{ $paiement->source === 'apprenant' ? 'en ligne' : 'au centre' }}</div></td>
                                    <td class="cell-wrap text-break">{{ $paiement->inscription->name }}<div class="small text-muted fw-normal">{{ $paiement->inscription->number }}@if ($paiement->period) · {{ $paiement->period }}@endif</div></td>
                                    <td data-order="{{ $paiement->amount }}">{{ $paiement->money() }}</td>
                                    <td class="fw-normal">{{ $paiement->method->label() }}@if ($paiement->reference)<div class="small text-muted font-monospace">{{ $paiement->reference }}</div>@endif</td>
                                    <td class="fw-normal" data-order="{{ $paiement->paid_at->timestamp }}">{{ $paiement->paid_at->format('d/m/Y') }}</td>
                                    <td><span class="d-none">statut-{{ $paiement->status->value }}</span><span class="badge {{ $paiement->status->badge() }}">{{ $paiement->status->label() }}</span></td>
                                    <td class="text-nowrap">
                                        @if ($paiement->status === PaymentStatus::AVerifier)
                                            @can('verify', $paiement)
                                                <form method="POST" action="{{ route('paiements.confirm', $paiement) }}" class="d-inline" data-crud-form data-no-guard data-confirm="Avez-vous bien retrouvé {{ $paiement->money() }} (transaction {{ $paiement->reference }}) sur le compte {{ $paiement->method->label() }} ?">@csrf @method('PUT')<button type="submit" class="btn btn-success btn-sm"><i class='bx bx-check'></i><span class="ms-1">Confirmer</span></button></form>
                                            @endcan
                                        @endif
                                        <a href="{{ route('paiements.show', $paiement) }}" class="btn btn-primary btn-sm" title="Ouvrir"><i class='bx bx-show'></i></a>
                                        @if ($paiement->status->hasReceipt())@include('paiements._receipt-buttons', ['receiptRoute' => 'paiements.receipt', 'paiement' => $paiement, 'btn' => 'btn btn-outline-primary btn-sm'])@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header card-header-brand" id="impayes"><h6 class="mb-0 text-white"><i class='bx bx-time-five me-2'></i>IMPAYÉS — INSCRIPTIONS AVEC UN RESTE À PAYER</h6></div>
            <div class="card-body">
                @if ($unpaid->isEmpty())
                    <p class="text-muted mb-0">Aucun impayé : toutes les inscriptions validées sont réglées.</p>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>INSCRIPTION</th><th>APPRENANT</th><th>TOTAL</th><th>PAYÉ</th><th>RESTE</th><th width="12%">ACTIONS</th></tr></thead>
                            <tbody>
                                @foreach ($unpaid as $inscription)
                                    <tr>
                                        <td>{{ $inscription->number }}<div class="small text-muted">{{ $inscription->status->label() }} · {{ $inscription->mode->label() }}</div></td>
                                        <td class="cell-wrap">{{ $inscription->name }}<div class="small text-muted">{{ $inscription->contactLine() }}</div></td>
                                        <td>{{ $money($inscription->total_amount) }}</td>
                                        <td>{{ $money($inscription->amount_paid) }}</td>
                                        <td class="fw-bold text-danger">{{ $money($inscription->balance()) }}@if ($inscription->pendingAmount() > 0)<div class="small fw-normal text-warning">dont {{ $money($inscription->pendingAmount()) }} en vérification</div>@endif</td>
                                        <td class="text-nowrap">
                                            @if ($inscription->payableNow() > 0)
                                                @can('create', \App\Models\Paiement::class)
                                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#pay-{{ $inscription->id }}"><i class='bx bx-money'></i><span class="ms-1">Encaisser</span></button>
                                                @endcan
                                            @elseif ($pending = $inscription->pendingPayment())
                                                <a href="{{ route('paiements.show', $pending) }}" class="btn btn-warning btn-sm"><i class='bx bx-search-alt'></i><span class="ms-1">Vérifier</span></a>
                                            @endif
                                            @if ($wa = $inscription->whatsappMessageUrl())<a href="{{ $wa }}" class="btn btn-outline-success btn-sm" target="_blank" rel="noopener" title="Rappel WhatsApp déjà rédigé"><i class='bx bxl-whatsapp'></i><span class="ms-1">Relancer</span></a>@endif
                                            <a href="{{ route('inscriptions.show', $inscription) }}" class="btn btn-primary btn-sm" title="Voir l'inscription"><i class='bx bx-show'></i></a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        @can('create', \App\Models\Paiement::class)
            @foreach ($unpaid as $inscription)
                @if ($inscription->payableNow() > 0)
                    @include('paiements._record-modal', ['inscription' => $inscription, 'modalId' => 'pay-'.$inscription->id, 'owner' => 'paiements', 'back' => 'paiements'])
                @endif
            @endforeach
        @endcan

        @can('paiements.configure')
            {{-- Moyens de paiement en ligne : fenêtre ouverte depuis l'en-tête du tableau (accès direct, même avec beaucoup
                 de paiements) — numéros, QR marchands avec aperçu, consignes, état du paiement automatique. --}}
            <div class="modal fade" id="moyens-paiement" tabindex="-1" aria-hidden="true" aria-labelledby="moyens-paiement-title">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('paiements.settings') }}" enctype="multipart/form-data" data-crud-form data-no-guard>
                            @csrf @method('PUT')
                            <div class="modal-header">
                                <h5 class="modal-title" id="moyens-paiement-title"><i class='bx bx-mobile-alt me-1'></i>Moyens de paiement en ligne (mobile money)</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                            </div>
                            <div class="modal-body">
                                <p class="small text-muted">Code marchand, numéro ou QR à scanner sur lesquels les apprenants envoient leur paiement en ligne. Sans aucun des trois, un moyen n'est pas proposé. Les formations en présentiel se règlent au centre.</p>
                                <div class="row g-3">
                                    @foreach (PaymentMethod::mobileMoney() as $method)
                                        <div class="col-md-4">
                                            <div class="border rounded-3 p-3 h-100 text-center">
                                                <div class="fw-bold mb-2">{{ $method->label() }}</div>
                                                <label class="form-label small fw-semibold text-start w-100" for="s-{{ $method->value }}">Numéro marchand</label>
                                                <input class="form-control mb-3" id="s-{{ $method->value }}" name="{{ $method->value }}" maxlength="60" value="{{ $settings[$method->settingKey()] }}" placeholder="Ex. 75 22 91 25 (ETTC)">
                                                <label class="form-label small fw-semibold text-start w-100" for="s-{{ $method->value }}-ussd">Paiement par code marchand</label>
                                                <input class="form-control mb-1 font-monospace" id="s-{{ $method->value }}-ussd" name="{{ $method->value }}_ussd" maxlength="80" value="{{ $settings[$method->ussdSettingKey()] }}" placeholder="#144#8*754830*code secret#">
                                                <div class="form-text text-start mb-3">Formule à composer ; écrivez « code secret » là où l'apprenant tape le sien. Les étapes lui sont expliquées en diaporama.</div>
                                                <div class="small fw-semibold text-start">QR marchand à scanner</div>
                                                <div class="d-flex align-items-center justify-content-center bg-light rounded my-2" style="height:170px">
                                                    <img @if (isset($qrCodes[$method->value])) src="{{ $qrCodes[$method->value] }}" @endif alt="Aperçu du QR {{ $method->label() }}" width="150" height="150" style="object-fit:contain" class="bg-white rounded {{ isset($qrCodes[$method->value]) ? '' : 'd-none' }}" data-qr-preview-img="{{ $method->value }}">
                                                    <span class="small text-muted {{ isset($qrCodes[$method->value]) ? 'd-none' : '' }}" data-qr-preview-empty="{{ $method->value }}"><i class='bx bx-qr fs-1 d-block'></i>Aucun QR</span>
                                                </div>
                                                <input class="form-control form-control-sm" type="file" id="s-{{ $method->value }}-qr" name="{{ $method->value }}_qr" accept="image/png,image/jpeg,image/webp" data-qr-preview="{{ $method->value }}" aria-label="Image du QR {{ $method->label() }}">
                                                @if (isset($qrCodes[$method->value]))
                                                    <div class="form-check small text-start mt-2"><input class="form-check-input" type="checkbox" id="s-{{ $method->value }}-qr-remove" name="{{ $method->value }}_qr_remove" value="1"><label class="form-check-label" for="s-{{ $method->value }}-qr-remove">Retirer ce QR</label></div>
                                                @endif
                                                <div class="form-text text-start">Photo ou capture du QR fourni par {{ $method->label() }}.</div>
                                            </div>
                                        </div>
                                    @endforeach
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold" for="s-notify">Qui reçoit les paiements à vérifier ?</label>
                                        <input type="email" class="form-control" id="s-notify" name="notify_email" maxlength="190" value="{{ $settings['paiement.notify_email'] }}" placeholder="{{ $defaultVerifier ?: 'adresse@exemple.com' }}">
                                        <div class="form-text">Le promoteur ou la personne désignée reçoit un e-mail à chaque paiement en ligne déclaré, avec un bouton pour le vérifier. Vide : {{ $defaultVerifier ? 'l\'adresse des alertes ('.$defaultVerifier.')' : 'l\'adresse des alertes du site' }}.</div>
                                    </div>
                                    <div class="col-12"><label class="form-label fw-semibold" for="s-instructions">Consignes affichées à l'apprenant</label><textarea class="form-control" id="s-instructions" name="instructions" rows="2" maxlength="1000">{{ $settings['paiement.instructions'] }}</textarea></div>
                                </div>

                                <div class="alert {{ $gateway && $gateway->name() !== 'simulation' ? 'alert-success' : 'alert-light border' }} mt-3 mb-0 small">
                                    <strong><i class='bx bx-bolt-circle'></i> Paiement automatique :</strong>
                                    @if ($gateway && $gateway->name() !== 'simulation')
                                        actif avec {{ $gateway->label() }} — l'apprenant paie sur la page sécurisée de l'opérateur, le paiement est confirmé tout seul et le reçu part par e-mail.
                                    @elseif ($gateway)
                                        mode test (simulation).
                                    @else
                                        pas encore activé. Avec un agrégateur agréé par la BCEAO (InTouch, CinetPay…), la confirmation devient automatique : le centre ouvre un compte marchand (statuts, RCCM, NIF, pièce du responsable, justificatif d'adresse, RIB), puis transmet la clé API au développeur. En attendant, l'apprenant déclare sa transaction et vous la confirmez.
                                    @endif
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                <button type="submit" class="btn btn-primary btn-lg"><i class='bx bx-save me-1'></i>Enregistrer</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud-ajax.js') }}?v={{ filemtime(public_path('assets/js/crud-ajax.js')) }}"></script>
    <script>CrudAjax.init(document.querySelector('[data-crud="paiements"]'));</script>
    <script>
        // Aperçu immédiat du QR choisi, avant l'enregistrement (fenêtre « Moyens de paiement »).
        document.addEventListener('change', function (event) {
            var input = event.target.closest('input[data-qr-preview]');
            if (!input || !input.files || !input.files[0]) { return; }
            var key = input.getAttribute('data-qr-preview');
            var img = document.querySelector('[data-qr-preview-img="' + key + '"]');
            var empty = document.querySelector('[data-qr-preview-empty="' + key + '"]');
            var reader = new FileReader();
            reader.onload = function () { img.src = reader.result; img.classList.remove('d-none'); if (empty) { empty.classList.add('d-none'); } };
            reader.readAsDataURL(input.files[0]);
        });
    </script>
@endpush
