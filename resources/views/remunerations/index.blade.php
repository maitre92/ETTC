@extends('layouts.admin')

@section('title', 'Rémunération des formateurs')

{{-- Rémunération des formateurs (demande du centre, 2026-11-22) : formation personnalisée {1/3} de la mensualité, en groupe
     {20 %} du montant, sur les frais de formation encaissés du mois. Payer, fiche PDF, annulation motivée. --}}
@section('content')
    @php
        $money = fn (int $amount) => \App\Models\Formule::money($amount);
        $canPay = auth()->user()->can('remunerations.payout');
        $prev = $month->subMonthNoOverflow()->format('Y-m');
        $next = $month->addMonthNoOverflow()->format('Y-m');
        $kinds = ['personnalisee' => 'Personnalisée', 'groupe' => 'En groupe'];
    @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Finances</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Rémunération des formateurs</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div data-crud="remunerations">
        <span data-crud-status hidden>{{ session('status') }}</span>

        <div class="card">
            <div class="card-body d-flex flex-wrap align-items-center gap-2">
                <a href="{{ route('remunerations.index', ['mois' => $prev]) }}" class="btn btn-light" aria-label="Mois précédent"><i class='bx bx-chevron-left'></i></a>
                <h5 class="mb-0 mx-2">{{ ucfirst($month->translatedFormat('F Y')) }}</h5>
                @if ($next <= now()->format('Y-m'))<a href="{{ route('remunerations.index', ['mois' => $next]) }}" class="btn btn-light" aria-label="Mois suivant"><i class='bx bx-chevron-right'></i></a>@endif
                <form method="GET" action="{{ route('remunerations.index') }}" class="d-flex gap-1 ms-lg-3">
                    <input type="month" class="form-control form-control-sm" name="mois" value="{{ $period }}" max="{{ now()->format('Y-m') }}" aria-label="Mois">
                    <button type="submit" class="btn btn-primary btn-sm">Voir</button>
                </form>
                <span class="ms-auto small text-muted">Formation personnalisée : <strong>{{ $rates['personnalisee'] }}</strong> de la mensualité · En groupe : <strong>{{ $rates['groupe'] }}</strong> du montant · sur les frais de formation encaissés (hors frais d'inscription).</span>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="card mb-0"><div class="card-body"><p class="small text-muted mb-1">Dû aux formateurs</p><h4 class="mb-0">{{ $money($summary['totals']['due']) }}</h4></div></div></div>
            <div class="col-md-4"><div class="card mb-0"><div class="card-body"><p class="small text-muted mb-1">Déjà versé</p><h4 class="mb-0 text-success">{{ $money($summary['totals']['paid']) }}</h4></div></div></div>
            <div class="col-md-4"><div class="card mb-0"><div class="card-body"><p class="small text-muted mb-1">Reste à verser</p><h4 class="mb-0 {{ $summary['totals']['remaining'] ? 'text-danger' : '' }}">{{ $money($summary['totals']['remaining']) }}</h4></div></div></div>
        </div>

        @if ($summary['unassigned']->isNotEmpty())
            <div class="alert alert-warning">
                <strong><i class='bx bx-error'></i> {{ $summary['unassigned']->count() }} paiement(s) sans formateur</strong> : la part du formateur ne peut pas être calculée. Attribuez un formateur (formation personnalisée : fiche de l'inscription ; en groupe : fiche du groupe).
                <ul class="mb-0 mt-2 small">
                    @foreach ($summary['unassigned']->unique(fn ($l) => $l['inscription']->id) as $line)
                        <li><a href="{{ route('inscriptions.show', $line['inscription']) }}">{{ $line['inscription']->name }}</a> — {{ $kinds[$line['kind']] }}{{ $line['inscription']->groupe ? ' · groupe '.$line['inscription']->groupe->name : '' }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-user-voice me-2'></i>FORMATEURS</h6></div>
            <div class="card-body">
                @forelse ($summary['teachers'] as $t)
                    <div class="border rounded-3 p-3 mb-3">
                        <div class="d-flex flex-wrap align-items-center gap-3">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $t['user']->name }}</h6>
                                <div class="small text-muted">{{ $t['learners'] }} apprenant(s) · encaissé {{ $money($t['base']) }}</div>
                            </div>
                            <div class="text-end"><div class="small text-muted">Dû</div><strong>{{ $money($t['due']) }}</strong></div>
                            <div class="text-end"><div class="small text-muted">Versé</div><strong class="text-success">{{ $money($t['paid']) }}</strong></div>
                            <div class="text-end"><div class="small text-muted">Reste</div><strong class="{{ $t['remaining'] ? 'text-danger' : '' }}">{{ $money($t['remaining']) }}</strong></div>
                            <div class="d-flex gap-1">
                                <a href="{{ route('remunerations.sheet', [$t['user'], 'mois' => $period]) }}" class="btn btn-outline-primary btn-sm" title="Fiche PDF"><i class='bx bxs-file-pdf'></i> Fiche</a>
                                @if ($canPay && $t['remaining'] > 0)
                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#pay-{{ $t['user']->id }}"><i class='bx bx-money'></i> Payer</button>
                                @endif
                            </div>
                        </div>
                        @if ($t['lines']->isNotEmpty())
                            <details class="mt-2">
                                <summary class="small text-primary" style="cursor:pointer">Détail ({{ $t['lines']->count() }} paiement(s))</summary>
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm small mb-0">
                                        <thead><tr><th>Date</th><th>Apprenant</th><th>Formation</th><th class="text-end">Encaissé</th><th class="text-end">Taux</th><th class="text-end">Part</th></tr></thead>
                                        <tbody>
                                            @foreach ($t['lines'] as $line)
                                                <tr>
                                                    <td>{{ $line['paiement']->paid_at->format('d/m/Y') }}</td>
                                                    <td><a href="{{ route('inscriptions.show', $line['inscription']) }}">{{ $line['inscription']->name }}</a></td>
                                                    <td>{{ $kinds[$line['kind']] }}{{ $line['inscription']->groupe ? ' · '.$line['inscription']->groupe->name : '' }}</td>
                                                    <td class="text-end">{{ $money($line['base']) }}</td>
                                                    <td class="text-end">{{ $rates[$line['kind']] }}</td>
                                                    <td class="text-end fw-semibold">{{ $money($line['share']) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        @endif
                    </div>

                    @if ($canPay && $t['remaining'] > 0)
                        <div class="modal fade" id="pay-{{ $t['user']->id }}" tabindex="-1" aria-hidden="true" aria-labelledby="pay-{{ $t['user']->id }}-title">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form method="POST" action="{{ route('remunerations.pay') }}" data-crud-form data-no-guard>
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $t['user']->id }}">
                                        <input type="hidden" name="period" value="{{ $period }}">
                                        <div class="modal-header"><h5 class="modal-title" id="pay-{{ $t['user']->id }}-title"><i class='bx bx-money me-1'></i>Payer {{ $t['user']->name }} — {{ $month->translatedFormat('F Y') }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-sm-6"><label class="form-label fw-semibold" for="pa-{{ $t['user']->id }}">Montant (FCFA)</label><input type="number" class="form-control form-control-lg" id="pa-{{ $t['user']->id }}" name="amount" min="1" max="{{ $t['remaining'] }}" value="{{ $t['remaining'] }}" required><div class="form-text">Reste : {{ $money($t['remaining']) }}</div></div>
                                                <div class="col-sm-6"><label class="form-label fw-semibold" for="pd-{{ $t['user']->id }}">Date</label><input type="date" class="form-control form-control-lg" id="pd-{{ $t['user']->id }}" name="paid_on" max="{{ now()->format('Y-m-d') }}" value="{{ now()->format('Y-m-d') }}" required></div>
                                                <div class="col-sm-6"><label class="form-label fw-semibold" for="pm-{{ $t['user']->id }}">Payé en</label>@include('paiements._method-select', ['id' => 'pm-'.$t['user']->id, 'name' => 'method', 'selected' => 'especes'])</div>
                                                <div class="col-sm-6"><label class="form-label" for="pr-{{ $t['user']->id }}">Référence (facultatif)</label><input class="form-control form-control-lg" id="pr-{{ $t['user']->id }}" name="reference" maxlength="100"></div>
                                            </div>
                                        </div>
                                        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-success btn-lg"><i class='bx bx-check me-1'></i>Enregistrer le versement</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endif
                @empty
                    <p class="text-muted mb-0">Rien à rémunérer ce mois-ci : aucun paiement de formation encaissé pour un apprenant qui a un formateur.</p>
                @endforelse
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-list-check me-2'></i>VERSEMENTS DU MOIS</h6></div>
                    <div class="card-body">
                        @forelse ($payouts as $v)
                            <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-bottom {{ $v->isCancelled() ? 'text-muted' : '' }}">
                                <span class="flex-grow-1 {{ $v->isCancelled() ? 'text-decoration-line-through' : '' }}">{{ $v->paid_on->format('d/m/Y') }} · <strong>{{ $v->formateur->name }}</strong> · {{ $v->method->shortLabel() }}@if ($v->reference) · {{ $v->reference }}@endif <span class="small text-muted">({{ $v->number }}{{ $v->recorder ? ', par '.$v->recorder->name : '' }})</span></span>
                                <strong>{{ $money($v->amount) }}</strong>
                                @if ($v->isCancelled())
                                    <span class="small text-danger w-100">Annulé : {{ $v->cancel_reason }}</span>
                                @elseif ($canPay)
                                    <details class="text-end">
                                        <summary class="btn btn-outline-danger btn-sm" title="Annuler"><i class='bx bx-x'></i></summary>
                                        <form method="POST" action="{{ route('remunerations.cancel', $v) }}" class="d-flex gap-1 mt-1" data-crud-form data-no-guard>@csrf @method('PUT')<input class="form-control form-control-sm" name="reason" maxlength="300" required placeholder="Motif" aria-label="Motif de l'annulation"><button type="submit" class="btn btn-danger btn-sm">OK</button></form>
                                    </details>
                                @endif
                            </div>
                        @empty
                            <p class="text-muted mb-0">Aucun versement pour ce mois.</p>
                        @endforelse
                    </div>
                </div>
            </div>
            @can('remunerations.configure')
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-cog me-2'></i>TAUX</h6></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('remunerations.settings') }}" class="row g-2" data-crud-form data-no-guard>
                                @csrf @method('PUT')
                                <input type="hidden" name="mois" value="{{ $period }}">
                                <div class="col-6"><label class="form-label small fw-semibold" for="r-perso">Formation personnalisée</label><input class="form-control" id="r-perso" name="rate_personnalisee" maxlength="10" value="{{ $settings->get('formateurs.rate_personnalisee') }}" required><div class="form-text">Fraction de la mensualité, ex. 1/3</div></div>
                                <div class="col-6"><label class="form-label small fw-semibold" for="r-groupe">Formation en groupe (%)</label><input class="form-control" id="r-groupe" name="rate_groupe" maxlength="10" value="{{ $settings->get('formateurs.rate_groupe') }}" required><div class="form-text">Pourcentage du montant, ex. 20</div></div>
                                <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">Enregistrer</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            @endcan
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud-ajax.js') }}?v={{ filemtime(public_path('assets/js/crud-ajax.js')) }}"></script>
    <script>CrudAjax.init(document.querySelector('[data-crud="remunerations"]'));</script>
@endpush
