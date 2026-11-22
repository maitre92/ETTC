@extends('layouts.admin')

@section('title', 'Caisse')

{{-- Caisse (demande du centre, 2026-11-21) : soldes par moyen, période, entrées / sorties / résultat, journal (paiements
     des apprenants automatiques, revenus, dépenses, transferts), saisie en modale, annulation motivée, rapports. --}}
@section('content')
    @php
        $money = fn (int $amount) => \App\Models\Formule::money($amount);
        $query = fn (array $extra = []) => array_filter(['periode' => $preset, 'du' => $preset === 'dates' ? $from->format('Y-m-d') : null, 'au' => $preset === 'dates' ? $to->format('Y-m-d') : null, 'moyen' => $family] + $extra);
        $sources = ['paiement' => ['Paiement apprenant', 'bg-primary-subtle'], 'remboursement' => ['Remboursement', 'bg-warning-subtle'], 'commission' => ['Commission', 'bg-info-subtle'], 'formateur' => ['Formateur', 'bg-secondary-subtle'], 'manuel' => ['Saisi', 'bg-light text-dark']];
        $canRecord = auth()->user()->can('caisse.record');
        $canCancel = auth()->user()->can('caisse.cancel');
    @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Finances</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Caisse</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div data-crud="caisse">
        <span data-crud-status hidden>{{ session('status') }}</span>

        {{-- Soldes (aujourd'hui) --}}
        <div class="row g-3 mb-1">
            @foreach ([['especes', 'bx-money', 'success'], ['mobile_money', 'bx-mobile-alt', 'warning'], ['banque', 'bx-building-house', 'info']] as [$key, $icon, $color])
                <div class="col-sm-6 col-xl-3">
                    <div class="card radius-10 border-start border-4 border-{{ $color }} mb-0 h-100">
                        <div class="card-body">
                            <p class="mb-1 text-muted small"><i class='bx {{ $icon }}'></i> {{ \App\Enums\PaymentMethod::FAMILIES[$key] }}</p>
                            <h4 class="mb-0 {{ $balances[$key] < 0 ? 'text-danger' : '' }}">{{ $money($balances[$key]) }}</h4>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="col-sm-6 col-xl-3">
                <div class="card radius-10 bg-primary text-white mb-0 h-100">
                    <div class="card-body">
                        <p class="mb-1 small text-white-50">Solde total aujourd'hui</p>
                        <h4 class="mb-0 text-white">{{ $money($balances['total']) }}</h4>
                    </div>
                </div>
            </div>
        </div>
        @if (min($balances['especes'], $balances['mobile_money'], $balances['banque']) < 0)
            <p class="small text-danger mt-2 mb-0"><i class='bx bx-error-circle'></i> Un solde négatif signifie qu'il manque une entrée : enregistrez le <strong>solde d'ouverture</strong> (l'argent déjà en caisse au départ) comme revenu.</p>
        @endif

        {{-- Actions --}}
        @if ($canRecord)
            <div class="d-flex flex-wrap gap-2 my-3">
                <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#mvt-entree"><i class='bx bx-plus-circle me-1'></i>Revenu</button>
                <button type="button" class="btn btn-danger btn-lg" data-bs-toggle="modal" data-bs-target="#mvt-sortie"><i class='bx bx-minus-circle me-1'></i>Dépense</button>
                <button type="button" class="btn btn-outline-primary btn-lg" data-bs-toggle="modal" data-bs-target="#mvt-transfert"><i class='bx bx-transfer-alt me-1'></i>Transfert</button>
                <span class="small text-muted align-self-center">Les paiements des apprenants entrent tout seuls dans la caisse.</span>
            </div>
        @endif

        {{-- Période --}}
        <div class="card">
            <div class="card-body d-flex flex-wrap align-items-center gap-2">
                <div class="nv-chips">
                    @foreach ($presets as $key => $label)
                        <a href="{{ route('caisse.index', array_filter(['periode' => $key, 'moyen' => $family])) }}" class="nv-chip {{ $preset === $key ? 'active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('caisse.index') }}" class="d-flex flex-wrap align-items-center gap-1">
                    <input type="hidden" name="periode" value="dates">
                    <label class="small text-muted" for="c-du">Du</label><input type="date" class="form-control form-control-sm" id="c-du" name="du" value="{{ $from->format('Y-m-d') }}" style="width:auto">
                    <label class="small text-muted" for="c-au">au</label><input type="date" class="form-control form-control-sm" id="c-au" name="au" value="{{ $to->format('Y-m-d') }}" style="width:auto">
                    <select class="form-select form-select-sm" name="moyen" style="width:auto" aria-label="Moyen">
                        <option value="">Tous les moyens</option>
                        @foreach (\App\Enums\PaymentMethod::FAMILIES as $key => $label)<option value="{{ $key }}" @selected($family === $key)>{{ $label }}</option>@endforeach
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Voir</button>
                </form>
                @can('caisse.export')
                    <span class="ms-auto d-flex gap-1">
                        <a href="{{ route('caisse.report', $query()) }}" class="btn btn-outline-primary btn-sm"><i class='bx bxs-file-pdf'></i> PDF</a>
                        <a href="{{ route('caisse.export', $query()) }}" class="btn btn-outline-success btn-sm"><i class='bx bx-spreadsheet'></i> Excel</a>
                    </span>
                @endcan
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4"><div class="card mb-0"><div class="card-body"><p class="small text-muted mb-1">Entrées · {{ $from->format('d/m') }} → {{ $to->format('d/m/Y') }}</p><h5 class="mb-0 text-success">+ {{ $money($totals['in']) }}</h5></div></div></div>
            <div class="col-md-4"><div class="card mb-0"><div class="card-body"><p class="small text-muted mb-1">Sorties</p><h5 class="mb-0 text-danger">− {{ $money($totals['out']) }}</h5></div></div></div>
            <div class="col-md-4"><div class="card mb-0"><div class="card-body"><p class="small text-muted mb-1">Résultat de la période</p><h5 class="mb-0 {{ $totals['result'] < 0 ? 'text-danger' : 'text-primary' }}">{{ $money($totals['result']) }}</h5></div></div></div>
        </div>

        <div class="row g-3">
            <div class="col-xl-9">
                <div class="card">
                    <div class="card-header card-header-brand d-flex justify-content-between align-items-center"><h6 class="mb-0 text-white"><i class='bx bx-list-ul me-2'></i>JOURNAL DE CAISSE</h6><span class="badge bg-light text-dark">{{ $journal->count() }} mouvement(s)</span></div>
                    <div class="card-body">
                        @if ($journal->isEmpty())
                            <p class="text-muted mb-0">Aucun mouvement sur cette période.</p>
                        @else
                            <div class="table-responsive">
                                <table class="table align-middle" id="caisse-table">
                                    <thead><tr><th>DATE</th><th>LIBELLÉ</th><th>MOYEN</th><th class="text-end">ENTRÉE</th><th class="text-end">SORTIE</th><th></th></tr></thead>
                                    <tbody>
                                        @foreach ($journal as $r)
                                            <tr class="{{ $r['cancelled'] ? 'text-muted' : '' }}">
                                                <td class="text-nowrap">{{ $r['date']->format('d/m/Y') }}<div class="small text-muted">{{ $r['number'] }}</div></td>
                                                <td class="cell-wrap {{ $r['cancelled'] ? 'text-decoration-line-through' : '' }}">
                                                    {{ $r['label'] }}
                                                    <div class="small"><span class="badge {{ $sources[$r['source']][1] }}">{{ $sources[$r['source']][0] }}</span> <span class="text-muted">{{ $r['category'] }}</span>
                                                        @if ($r['source'] === 'manuel' && $r['model']->reference) <span class="text-muted">· réf. {{ $r['model']->reference }}</span>@endif
                                                        @if ($r['source'] === 'manuel' && $r['model']->recorder) <span class="text-muted">· par {{ $r['model']->recorder->name }}</span>@endif
                                                    </div>
                                                    @if ($r['cancelled'])<div class="small text-danger text-decoration-none">Annulé : {{ $r['model']->cancel_reason }}</div>@endif
                                                </td>
                                                <td class="small text-nowrap">{{ $r['method']->shortLabel() }}@if ($r['to_method']) <i class='bx bx-right-arrow-alt'></i> {{ $r['to_method']->shortLabel() }}@endif</td>
                                                <td class="text-end text-nowrap text-success fw-semibold">{{ $r['in'] ? '+ '.$money($r['in']) : '' }}{{ $r['transfer'] ? $money($r['transfer']) : '' }}</td>
                                                <td class="text-end text-nowrap text-danger fw-semibold">{{ $r['out'] ? '− '.$money($r['out']) : '' }}</td>
                                                <td class="text-end text-nowrap">
                                                    @if ($r['source'] === 'paiement' || $r['source'] === 'remboursement')
                                                        <a href="{{ route('paiements.show', $r['model']) }}" class="btn btn-light btn-sm" title="Voir le paiement"><i class='bx bx-show'></i></a>
                                                    @elseif ($r['source'] === 'formateur' && auth()->user()->can('remunerations.view'))
                                                        <a href="{{ route('remunerations.index', ['mois' => $r['model']->period]) }}" class="btn btn-light btn-sm" title="Voir la rémunération"><i class='bx bx-show'></i></a>
                                                    @elseif ($r['source'] === 'manuel')
                                                        @if ($r['model']->proof_path)<a href="{{ route('caisse.proof', $r['model']) }}" class="btn btn-light btn-sm" target="_blank" rel="noopener" title="Justificatif"><i class='bx bx-paperclip'></i></a>@endif
                                                        @if ($canCancel && ! $r['cancelled'])
                                                            <details class="d-inline-block text-start">
                                                                <summary class="btn btn-outline-danger btn-sm" title="Annuler"><i class='bx bx-x'></i></summary>
                                                                <form method="POST" action="{{ route('caisse.cancel', $r['model']) }}" class="d-flex gap-1 mt-1" data-crud-form data-no-guard>
                                                                    @csrf @method('PUT')
                                                                    <input class="form-control form-control-sm" name="reason" maxlength="300" required placeholder="Motif de l'annulation" aria-label="Motif de l'annulation">
                                                                    <button type="submit" class="btn btn-danger btn-sm">OK</button>
                                                                </form>
                                                            </details>
                                                        @endif
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-pie-chart-alt me-2'></i>PAR CATÉGORIE</h6></div>
                    <div class="card-body">
                        @forelse ($totals['by_category'] as $category => $sum)
                            <div class="d-flex justify-content-between gap-2 py-1 border-bottom small">
                                <span>{{ $category }}</span>
                                <span class="text-nowrap fw-semibold {{ $sum['out'] ? 'text-danger' : 'text-success' }}">{{ $sum['in'] ? '+ '.$money($sum['in']) : '' }}{{ $sum['out'] ? '− '.$money($sum['out']) : '' }}</span>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">Rien sur cette période.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        @if ($canRecord)
            @foreach (['entree' => ['Nouveau revenu', 'bx-plus-circle', 'success'], 'sortie' => ['Nouvelle dépense', 'bx-minus-circle', 'danger'], 'transfert' => ['Transfert entre caisses', 'bx-transfer-alt', 'primary']] as $type => [$title, $icon, $color])
                @include('caisse._modal', ['type' => $type, 'title' => $title, 'icon' => $icon, 'color' => $color])
            @endforeach
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud-ajax.js') }}?v={{ filemtime(public_path('assets/js/crud-ajax.js')) }}"></script>
    <script>CrudAjax.init(document.querySelector('[data-crud="caisse"]'));</script>
@endpush
