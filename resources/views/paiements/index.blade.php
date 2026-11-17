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
            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-wallet me-2'></i>PAIEMENTS ET REÇUS</h6></div>
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
                                        <a href="{{ route('paiements.show', $paiement) }}" class="btn btn-primary btn-sm" title="{{ $paiement->status === PaymentStatus::AVerifier ? 'Vérifier' : 'Voir' }}"><i class='bx {{ $paiement->status === PaymentStatus::AVerifier ? 'bx-search-alt' : 'bx-show' }}'></i></a>
                                        @if ($paiement->status->hasReceipt())<a href="{{ route('paiements.receipt', $paiement) }}" class="btn btn-primary btn-sm" title="Reçu" target="_blank" rel="noopener"><i class='bx bx-receipt'></i></a>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-time-five me-2'></i>IMPAYÉS — INSCRIPTIONS AVEC UN RESTE À PAYER</h6></div>
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
                                        <td class="fw-bold text-danger">{{ $money($inscription->balance()) }}</td>
                                        <td class="text-nowrap">
                                            @can('create', \App\Models\Paiement::class)
                                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#pay-{{ $inscription->id }}" title="Enregistrer un paiement"><i class='bx bx-plus'></i></button>
                                            @endcan
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
                <div class="modal fade" id="pay-{{ $inscription->id }}" tabindex="-1" aria-hidden="true" data-crud-owner="paiements">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header"><h5 class="modal-title">Paiement au centre — {{ $inscription->name }} ({{ $inscription->number }})</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                            <div class="modal-body">@include('paiements._record-form', ['inscription' => $inscription, 'formId' => 'pay-form-'.$inscription->id, 'back' => 'paiements'])</div>
                        </div>
                    </div>
                </div>
            @endforeach
        @endcan

        @can('paiements.configure')
            <div class="card">
                <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-mobile-alt me-2'></i>MOYENS DE PAIEMENT EN LIGNE (MOBILE MONEY)</h6></div>
                <div class="card-body">
                    <p class="small text-muted">Numéros marchands sur lesquels les apprenants envoient leur paiement en ligne. Un numéro vide : ce moyen n'est pas proposé. Les formations en présentiel se règlent au centre.</p>
                    <form method="POST" action="{{ route('paiements.settings') }}" class="row g-2" data-crud-form data-no-guard>
                        @csrf @method('PUT')
                        @foreach (PaymentMethod::mobileMoney() as $method)
                            <div class="col-md-4"><label class="form-label fw-semibold" for="s-{{ $method->value }}">{{ $method->label() }}</label><input class="form-control" id="s-{{ $method->value }}" name="{{ $method->value }}" maxlength="60" value="{{ $settings[$method->settingKey()] }}" placeholder="Ex. 75 22 91 25 (ETTC)"></div>
                        @endforeach
                        <div class="col-12"><label class="form-label fw-semibold" for="s-instructions">Consignes affichées à l'apprenant</label><textarea class="form-control" id="s-instructions" name="instructions" rows="2" maxlength="1000">{{ $settings['paiement.instructions'] }}</textarea></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm"><i class='bx bx-save me-1'></i>Enregistrer</button></div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud-ajax.js') }}?v={{ filemtime(public_path('assets/js/crud-ajax.js')) }}"></script>
    <script>CrudAjax.init(document.querySelector('[data-crud="paiements"]'));</script>
@endpush
