@extends('layouts.admin')

@section('title', 'Paiement '.$paiement->number)

{{-- Fiche d'un paiement (cahier §3.8) : détail, vérification d'une déclaration mobile money (confirmer / rejeter),
     annulation ou remboursement motivés, reçu, historique. Formulaires en AJAX (crud-ajax.js). --}}
@use('App\Enums\PaymentStatus')

@section('content')
    @php $inscription = $paiement->inscription; @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Finances</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('paiements.index') }}">Paiements</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $paiement->number }}</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div data-crud="paiement-{{ $paiement->id }}">
        <span data-crud-status hidden>{{ session('status') }}</span>
        @if ($errors->has('status') || $errors->has('amount'))
            <div class="alert alert-warning">{{ $errors->first('status') ?: $errors->first('amount') }}</div>
        @endif

        <div class="row g-3">
            <div class="col-xl-6">
                <div class="card">
                    <div class="card-header card-header-brand d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-white"><i class='bx bx-wallet me-2'></i>PAIEMENT {{ $paiement->number }}</h6>
                        <span class="badge {{ $paiement->status->badge() }} fs-6">{{ $paiement->status->label() }}</span>
                    </div>
                    <div class="card-body">
                        <dl class="nv-dl mb-0">
                            <dt>Montant</dt><dd class="fs-4 fw-bold text-primary">{{ $paiement->money() }}</dd>
                            <dt>Nature</dt><dd>{{ $paiement->nature->label() }}@if ($paiement->period) — {{ $paiement->period }}@endif</dd>
                            <dt>Moyen</dt><dd>{{ $paiement->method->label() }}</dd>
                            @if ($paiement->reference)<dt>Transaction</dt><dd class="font-monospace">{{ $paiement->reference }}</dd>@endif
                            @if ($paiement->payer_phone)<dt>Payé depuis</dt><dd>{{ $paiement->payer_phone }}</dd>@endif
                            <dt>Date</dt><dd>{{ $paiement->paid_at->format('d/m/Y') }}</dd>
                            <dt>Origine</dt><dd>{{ $paiement->source === 'apprenant' ? 'Déclaré par l\'apprenant (paiement en ligne)' : 'Enregistré au centre'.($paiement->recorder ? ' par '.$paiement->recorder->displayName() : '') }}</dd>
                            @if ($paiement->confirmed_at)<dt>Confirmé le</dt><dd>{{ $paiement->confirmed_at->format('d/m/Y H:i') }}@if ($paiement->confirmer) par {{ $paiement->confirmer->displayName() }}@endif</dd>@endif
                            @if ($paiement->cancel_reason)<dt>Motif</dt><dd>{{ $paiement->cancel_reason }}</dd>@endif
                            @if ($paiement->notes)<dt>Remarque</dt><dd>{{ $paiement->notes }}</dd>@endif
                        </dl>
                        @if ($paiement->status->hasReceipt())
                            <a href="{{ route('paiements.receipt', $paiement) }}" class="btn btn-primary btn-sm mt-3" target="_blank" rel="noopener"><i class='bx bx-receipt me-1'></i>Voir le reçu</a>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-user me-2'></i>INSCRIPTION</h6></div>
                    <div class="card-body">
                        <dl class="nv-dl mb-0">
                            <dt>Apprenant</dt><dd>{{ $inscription->name }} <span class="text-muted small">{{ $inscription->contactLine() }}</span></dd>
                            <dt>Inscription</dt><dd><a href="{{ route('inscriptions.show', $inscription) }}">{{ $inscription->number }}</a> · {{ $inscription->formule_name }}, {{ mb_strtolower($inscription->mode->label()) }}</dd>
                            <dt>Total</dt><dd>{{ \App\Models\Formule::money($inscription->total_amount) }}</dd>
                            <dt>Payé</dt><dd>{{ \App\Models\Formule::money($inscription->amount_paid) }} <span class="badge {{ $inscription->paymentBadge() }}">{{ $inscription->paymentState() }}</span></dd>
                            <dt>Reste</dt><dd class="fw-bold">{{ \App\Models\Formule::money($inscription->balance()) }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                @if ($paiement->status === PaymentStatus::AVerifier)
                    @can('verify', $paiement)
                        <div class="card border-warning">
                            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-search-alt me-2'></i>VÉRIFIER LE PAIEMENT</h6></div>
                            <div class="card-body">
                                <p class="mb-3">Recherchez la transaction <strong class="font-monospace">{{ $paiement->reference }}</strong> de <strong>{{ $paiement->money() }}</strong> sur le compte <strong>{{ $paiement->method->label() }}</strong> du centre. Confirmez seulement si l'argent est bien arrivé.</p>
                                <form method="POST" action="{{ route('paiements.confirm', $paiement) }}" class="mb-3" data-crud-form data-no-guard>@csrf @method('PUT')<button type="submit" class="btn btn-success"><i class='bx bx-check-circle me-1'></i>Argent reçu : confirmer et envoyer le reçu</button></form>
                                <form method="POST" action="{{ route('paiements.reject', $paiement) }}" class="row g-2 align-items-end" data-crud-form data-no-guard>
                                    @csrf @method('PUT')
                                    <div class="col-md-8"><label class="form-label fw-semibold" for="r-reason">Rejeter : motif (visible par l'apprenant)</label><input class="form-control @error('reason') is-invalid @enderror" id="r-reason" name="reason" maxlength="300" required placeholder="Transaction introuvable, montant différent…">@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                    <div class="col-md-4"><button type="submit" class="btn btn-outline-danger w-100"><i class='bx bx-x me-1'></i>Rejeter</button></div>
                                </form>
                            </div>
                        </div>
                    @endcan
                @endif

                @if ($paiement->status === PaymentStatus::Confirme)
                    @can('refund', $paiement)
                        <div class="card">
                            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-undo me-2'></i>ANNULER OU REMBOURSER</h6></div>
                            <div class="card-body">
                                <p class="small text-muted">« Annuler » : paiement enregistré par erreur, aucun argent reçu. « Rembourser » : l'argent a été rendu à l'apprenant. Dans les deux cas, le montant payé de l'inscription diminue ; le reçu reste consultable avec son nouveau statut.</p>
                                <form method="POST" action="{{ route('paiements.refund', $paiement) }}" class="row g-2 align-items-end" data-crud-form data-no-guard id="refund-form">
                                    @csrf @method('PUT')
                                    <div class="col-12"><label class="form-label fw-semibold" for="c-reason">Motif</label><input class="form-control @error('reason') is-invalid @enderror" id="c-reason" name="reason" maxlength="300" required>@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                                    <div class="col-sm-6"><button type="submit" class="btn btn-outline-primary w-100"><i class='bx bx-money-withdraw me-1'></i>Rembourser</button></div>
                                    <div class="col-sm-6"><button type="submit" class="btn btn-outline-danger w-100" formaction="{{ route('paiements.cancel', $paiement) }}"><i class='bx bx-x me-1'></i>Annuler (erreur)</button></div>
                                </form>
                            </div>
                        </div>
                    @endcan
                @endif

                <div class="card">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-history me-2'></i>HISTORIQUE</h6></div>
                    <div class="card-body">
                        <ul class="list-unstyled small mb-0">
                            @forelse ($history as $log)
                                <li class="mb-2 pb-2 border-bottom">
                                    <div class="text-muted">{{ $log->created_at->format('d/m/Y H:i') }} · {{ $log->isBySuperadmin() && ! auth()->user()->isSuperadmin() ? config('brand.system_actor') : ($log->user_name ?? 'Apprenant') }}</div>
                                    <div>{{ $log->description }}</div>
                                </li>
                            @empty
                                <li class="text-muted">Aucune action enregistrée.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud-ajax.js') }}?v={{ filemtime(public_path('assets/js/crud-ajax.js')) }}"></script>
    <script>CrudAjax.init(document.querySelector('[data-crud="paiement-{{ $paiement->id }}"]'));</script>
@endpush
