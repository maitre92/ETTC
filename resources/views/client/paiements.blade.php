@extends('layouts.site')

{{-- Paiements de l'apprenant (cahier §4, §3.8) : reste à payer par inscription ; formation en ligne → paiement mobile
     money déclaré ici (référence de la transaction, vérifiée par le centre) ; présentiel → paiement au centre.
     Historique et reçus (imprimables / PDF). --}}
@use('App\Enums\PaymentStatus')
@use('App\Enums\PaymentMethod')
@use('App\Http\Controllers\Client\PaiementController')

@section('content')
    @component('client._layout', ['title' => 'Mes paiements'])
        @php $money = fn (int $amount) => \App\Models\Formule::money($amount); @endphp

        @forelse ($inscriptions as $inscription)
            @php
                $pending = (int) $inscription->paiements->where('status', PaymentStatus::AVerifier)->sum('amount');
                $payable = max(0, $inscription->balance() - $pending);
            @endphp
            <div class="detail-panel mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <h2 class="h5 mb-1">{{ $inscription->niveau?->name ?? 'Formation' }} — {{ $inscription->formule_name }}</h2>
                        <p class="text-muted small mb-0">{{ $inscription->number }} · {{ $inscription->mode->label() }} · {{ $inscription->priceLabel() }}</p>
                    </div>
                    <span class="tag {{ $inscription->balance() === 0 ? 'tag-success' : 'tag-info' }}">{{ $inscription->paymentState() }}</span>
                </div>
                <div class="row g-2 text-center my-3">
                    <div class="col-4"><div class="small text-muted">Total</div><strong>{{ $money($inscription->total_amount) }}</strong></div>
                    <div class="col-4"><div class="small text-muted">Payé</div><strong class="text-success">{{ $money($inscription->amount_paid) }}</strong></div>
                    <div class="col-4"><div class="small text-muted">Reste</div><strong class="{{ $inscription->balance() > 0 ? 'text-danger' : '' }}">{{ $money($inscription->balance()) }}</strong></div>
                </div>

                @if ($pending > 0)
                    <p class="alert alert-info small mb-3"><i class='bx bx-time-five' aria-hidden="true"></i> {{ $money($pending) }} en cours de vérification par le centre.</p>
                @endif

                @if ($payable > 0)
                    @if (! PaiementController::paysOnline($inscription))
                        <p class="mb-0"><i class='bx bx-store' aria-hidden="true"></i> Formation en présentiel : le paiement se fait <strong>au centre</strong>, à l'accueil. Un reçu vous est remis et apparaît ensuite ici.</p>
                    @elseif ($gateway)
                        <h3 class="h6 text-ink"><i class='bx bx-mobile-alt' aria-hidden="true"></i> Payer maintenant par mobile money</h3>
                        <p class="small text-muted">Orange Money, Moov Money ou Wave : vous validez le paiement sur votre téléphone, puis vous revenez ici avec votre reçu. Le paiement est confirmé automatiquement.</p>
                        <form method="POST" action="{{ route('client.paiements.online') }}" class="row g-2 align-items-end">
                            @csrf
                            <input type="hidden" name="inscription_id" value="{{ $inscription->id }}">
                            <div class="col-sm-5">
                                <label for="o-amount-{{ $inscription->id }}" class="form-label required">Montant (FCFA)</label>
                                <input type="number" class="form-control @error('amount') is-invalid @enderror" id="o-amount-{{ $inscription->id }}" name="amount" min="100" max="{{ $payable }}" step="1" required value="{{ min($inscription->nextInstallment(), $payable) }}">
                                @error('amount')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-sm-4">
                                <label for="o-phone-{{ $inscription->id }}" class="form-label">Téléphone</label>
                                <input type="tel" class="form-control" id="o-phone-{{ $inscription->id }}" name="payer_phone" maxlength="30" value="{{ $inscription->phone }}">
                            </div>
                            <div class="col-sm-3"><button type="submit" class="btn btn-accent w-100"><i class='bx bx-lock-alt' aria-hidden="true"></i> Payer</button></div>
                        </form>
                        @if (! empty($numbers))
                            <details class="mt-3">
                                <summary class="small">J'ai déjà envoyé l'argent au numéro du centre</summary>
                                @include('client._declare-form', ['inscription' => $inscription, 'payable' => $payable])
                            </details>
                        @endif
                    @elseif (empty($numbers))
                        <p class="mb-0"><i class='bx bx-mobile-alt' aria-hidden="true"></i> Paiement par mobile money : le centre vous communique son numéro. Une question ? Appelez le {{ $site->get('contact.phone_1') }}.</p>
                    @else
                        <h3 class="h6 text-ink"><i class='bx bx-mobile-alt' aria-hidden="true"></i> Payer par mobile money</h3>
                        @include('client._declare-form', ['inscription' => $inscription, 'payable' => $payable])
                    @endif
                @endif
            </div>
        @empty
            <div class="detail-panel mb-4">
                <p class="text-muted mb-0">Aucune inscription en cours. <a href="{{ route('site.inscription.create') }}">S'inscrire à une formation</a>.</p>
            </div>
        @endforelse

        <div class="detail-panel">
            <h2 class="h5 mb-3">Historique des paiements</h2>
            @if ($paiements->isEmpty())
                <p class="text-muted mb-0">Aucun paiement pour l'instant.</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th scope="col">Reçu</th><th scope="col">Paiement</th><th scope="col">Statut</th><th scope="col"><span class="visually-hidden">Reçu</span></th></tr></thead>
                        <tbody>
                            @foreach ($paiements as $paiement)
                                <tr>
                                    <td class="fw-semibold">{{ $paiement->number }}<br><small class="text-muted">{{ $paiement->paid_at->format('d/m/Y') }}</small></td>
                                    <td><strong>{{ $paiement->money() }}</strong> · {{ $paiement->method->label() }}<br><small class="text-muted">{{ $paiement->nature->label() }}@if ($paiement->period) · {{ $paiement->period }}@endif · {{ $paiement->inscription->number }}</small></td>
                                    <td>
                                        <span class="tag {{ $paiement->status === PaymentStatus::Confirme ? 'tag-success' : ($paiement->status === PaymentStatus::AVerifier ? 'tag-info' : 'tag-muted') }}">{{ $paiement->status->label() }}</span>
                                        @if ($paiement->cancel_reason)<br><small class="text-muted">{{ $paiement->cancel_reason }}</small>@endif
                                    </td>
                                    <td class="text-end">@if ($paiement->status->hasReceipt())<span class="d-inline-flex gap-1">@include('paiements._receipt-buttons', ['receiptRoute' => 'client.paiements.receipt', 'paiement' => $paiement, 'btn' => 'btn btn-sm btn-outline-primary text-nowrap'])</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endcomponent
@endsection
