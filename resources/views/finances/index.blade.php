@extends('layouts.admin')

@section('title', 'Finances et commission')

{{-- Tableau de bord financier (cahier §5 « Gestion financière », « Statistiques », §8) : période au choix, frais de
     test et de formation séparés, part du centre, commission du développeur (figée sur chaque paiement), versements et
     reste à payer, mois par mois, statistiques, rapports. Zone [data-crud] : formulaires en AJAX. --}}
@section('content')
    @php
        $money = fn (int $amount) => \App\Models\Formule::money($amount);
        $query = fn (array $extra = []) => array_filter(['periode' => $preset, 'du' => $preset === 'dates' ? $from->format('Y-m-d') : null, 'au' => $preset === 'dates' ? $to->format('Y-m-d') : null] + $extra);
        $presets = ['mois' => 'Ce mois', 'mois-dernier' => 'Mois dernier', 'annee' => 'Cette année', 'tout' => 'Depuis le début'];
    @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Finances</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Finances et commission</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div data-crud="finances">
        <span data-crud-status hidden>{{ session('status') }}</span>

        {{-- Période --}}
        <div class="card">
            <div class="card-body d-flex flex-wrap align-items-center gap-2">
                <div class="nv-chips">
                    @foreach ($presets as $key => $label)
                        <a href="{{ route('finances.index', ['periode' => $key]) }}" class="nv-chip {{ $preset === $key ? 'active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('finances.index') }}" class="d-flex flex-wrap align-items-center gap-1 ms-lg-2">
                    <input type="hidden" name="periode" value="dates">
                    <label class="small text-muted" for="f-du">Du</label><input type="date" class="form-control form-control-sm" id="f-du" name="du" value="{{ $from->format('Y-m-d') }}" style="width:auto">
                    <label class="small text-muted" for="f-au">au</label><input type="date" class="form-control form-control-sm" id="f-au" name="au" value="{{ $to->format('Y-m-d') }}" style="width:auto">
                    <button type="submit" class="btn btn-primary btn-sm">Voir</button>
                </form>
                @can('finances.export')
                    <span class="ms-auto d-flex gap-1">
                        <a href="{{ route('finances.report', $query()) }}" class="btn btn-outline-primary btn-sm"><i class='bx bxs-file-pdf'></i> Rapport PDF</a>
                        <a href="{{ route('finances.export', $query()) }}" class="btn btn-outline-success btn-sm"><i class='bx bx-spreadsheet'></i> Excel</a>
                    </span>
                @endcan
            </div>
        </div>
        <p class="text-muted small mb-3">Période : du <strong>{{ $from->format('d/m/Y') }}</strong> au <strong>{{ $to->format('d/m/Y') }}</strong> — seuls les paiements confirmés comptent (remboursés et annulés exclus).</p>

        {{-- Chiffres clés (cahier §8 : test, formation, centre, commission, versé, reste) --}}
        <div class="row g-3 mb-3">
            @foreach ([
                ['Frais de formation encaissés', $money($totals['formation']), $totals['count'].' paiement(s)', 'bx-wallet', 'primary'],
                ['Part du centre', $money($totals['centre']), round(100 - $rate, 2).' % des frais de formation', 'bx-buildings', 'success'],
                ['Commission du développeur', $money($totals['commission']), $rate.' % des frais de formation', 'bx-code-alt', 'info'],
                ['Commission versée', $money($totals['paid']), 'sur les mois de la période', 'bx-check-circle', 'secondary'],
                ['Commission restant à payer', $money($totals['due']), 'sur la période', 'bx-time-five', $totals['due'] > 0 ? 'warning' : 'success'],
                ['Frais de test', 'Gratuit', 'test de niveau sans frais', 'bx-target-lock', 'secondary'],
            ] as [$cardLabel, $cardValue, $cardSub, $cardIcon, $cardColor])
                <div class="col-6 col-xl-4">
                    <div class="card radius-10 mb-0 h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="rounded-circle bg-{{ $cardColor }}-subtle text-{{ $cardColor }} d-inline-flex align-items-center justify-content-center flex-shrink-0" style="width:48px;height:48px;font-size:1.5rem"><i class='bx {{ $cardIcon }}'></i></span>
                            <div><p class="mb-0 text-secondary small">{{ $cardLabel }}</p><h5 class="mb-0">{{ $cardValue }}</h5><p class="mb-0 small text-muted">{{ $cardSub }}</p></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="alert {{ $overall['due'] > 0 ? 'alert-warning' : 'alert-success' }} d-flex flex-wrap align-items-center gap-2">
            <i class='bx bx-info-circle fs-4'></i>
            <span>Depuis le début : commission {{ $money($overall['commission']) }}, déjà versée {{ $money($overall['paid']) }} — <strong>reste à payer {{ $money(max(0, $overall['due'])) }}</strong>@if ($overall['due'] < 0) (versé en trop : {{ $money(-$overall['due']) }})@endif.</span>
        </div>

        {{-- Mois par mois --}}
        <div class="card">
            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-calendar me-2'></i>MOIS PAR MOIS</h6></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>MOIS</th><th>ENCAISSÉ</th><th>PART DU CENTRE</th><th>COMMISSION</th><th>VERSÉ</th><th>RESTE</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($monthly as $row)
                                <tr>
                                    <td class="fw-semibold">{{ $row['label'] }}</td>
                                    <td>{{ $money($row['formation']) }}</td>
                                    <td>{{ $money($row['centre']) }}</td>
                                    <td>{{ $money($row['commission']) }}</td>
                                    <td>{{ $money($row['paid']) }}</td>
                                    <td class="fw-bold {{ $row['due'] > 0 ? 'text-danger' : 'text-success' }}">{{ $money($row['due']) }}</td>
                                    <td class="text-end">
                                        @if ($row['due'] > 0)
                                            @can('finances.payout')
                                                <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#payout-{{ $row['period'] }}"><i class='bx bx-check'></i><span class="ms-1">Verser</span></button>
                                            @endcan
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-3">
            {{-- Statistiques (cahier §5) --}}
            <div class="col-xl-7">
                <div class="card">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-bar-chart-alt-2 me-2'></i>STATISTIQUES DE LA PÉRIODE</h6></div>
                    <div class="card-body">
                        <div class="row g-2 mb-3 text-center">
                            <div class="col-4"><div class="small text-muted">Inscriptions</div><div class="fs-4 fw-bold">{{ $stats['inscriptions'] }}</div></div>
                            <div class="col-4"><div class="small text-muted">Apprenants en formation</div><div class="fs-4 fw-bold">{{ $stats['learners'] }}</div></div>
                            <div class="col-4"><div class="small text-muted">Reste à encaisser</div><div class="fs-5 fw-bold text-danger">{{ $money($stats['unpaid']) }}</div></div>
                        </div>
                        <div class="row g-3">
                            @foreach ([['Par niveau', $stats['byNiveau']], ['Par formule', $stats['byFormule']], ['Par mode', $stats['byMode']]] as [$statTitle, $statRows])
                                <div class="col-md-4">
                                    <h6 class="small text-uppercase text-muted">{{ $statTitle }}</h6>
                                    @forelse ($statRows as $statLabel => $statCount)
                                        <div class="d-flex justify-content-between border-bottom py-1 small"><span>{{ $statLabel }}</span><strong>{{ $statCount }}</strong></div>
                                    @empty
                                        <p class="small text-muted mb-0">—</p>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                        @if ($totals['pending'] > 0 || $totals['refunded'] > 0)
                            <p class="small text-muted mt-3 mb-0">@if ($totals['pending'] > 0)Paiements mobile money à vérifier : {{ $money($totals['pending']) }}. @endif @if ($totals['refunded'] > 0)Remboursé sur la période : {{ $money($totals['refunded']) }}.@endif</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-history me-2'></i>DERNIERS VERSEMENTS DE COMMISSION</h6></div>
                    <div class="card-body">
                        @forelse ($payouts as $payout)
                            <div class="d-flex flex-wrap justify-content-between gap-2 py-2 border-bottom small">
                                <span>{{ $payout->paid_on->format('d/m/Y') }} · {{ ucfirst(\Carbon\CarbonImmutable::parse($payout->period.'-01')->translatedFormat('F Y')) }} · {{ \App\Services\Caisse\CaisseService::payoutMethod($payout->method)->shortLabel() }}@if ($payout->reference) · {{ $payout->reference }}@endif</span>
                                <strong>{{ $money($payout->amount) }}</strong>
                            </div>
                        @empty
                            <p class="text-muted mb-0">Aucun versement enregistré.</p>
                        @endforelse
                    </div>
                </div>

                @can('finances.configure')
                    <div class="card">
                        <details>
                            <summary class="card-body py-3 fw-semibold text-primary" style="cursor:pointer"><i class='bx bx-cog me-1'></i>Réglages de la commission ({{ $rate }} %)</summary>
                            <div class="card-body pt-0">
                                <form method="POST" action="{{ route('finances.settings') }}" class="row g-2" data-crud-form data-no-guard>
                                    @csrf @method('PUT')
                                    <div class="col-6"><label class="form-label small fw-semibold" for="c-rate">Taux (%)</label><input type="number" class="form-control" id="c-rate" name="rate" min="0" max="100" step="0.01" value="{{ $rate }}" required></div>
                                    <div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" id="c-perso" name="include_personnalisee" value="1" @checked($includesPersonalised)><label class="form-check-label small" for="c-perso">Inclure les formations personnalisées (à convenir entre le centre et le développeur)</label></div></div>
                                    <div class="col-12"><button type="submit" class="btn btn-primary btn-sm"><i class='bx bx-save me-1'></i>Enregistrer</button> <span class="small text-muted">S'applique aux prochains paiements confirmés.</span></div>
                                </form>
                            </div>
                        </details>
                    </div>
                @endcan
            </div>
        </div>

        @can('finances.payout')
            @foreach ($monthly->where('due', '>', 0) as $row)
                <div class="modal fade" id="payout-{{ $row['period'] }}" tabindex="-1" aria-hidden="true" aria-labelledby="payout-{{ $row['period'] }}-title">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form method="POST" action="{{ route('finances.payout') }}" data-crud-form data-no-guard>
                                @csrf
                                <input type="hidden" name="period" value="{{ $row['period'] }}">
                                <div class="modal-header"><h5 class="modal-title" id="payout-{{ $row['period'] }}-title">Commission versée — {{ $row['label'] }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
                                <div class="modal-body">
                                    <div class="row g-3">
                                        <div class="col-6"><label class="form-label fw-semibold" for="po-amount-{{ $row['period'] }}">Montant (FCFA)</label><input type="number" class="form-control form-control-lg" id="po-amount-{{ $row['period'] }}" name="amount" min="1" step="1" required value="{{ $row['due'] }}"></div>
                                        <div class="col-6"><label class="form-label fw-semibold" for="po-date-{{ $row['period'] }}">Versé le</label><input type="date" class="form-control form-control-lg" id="po-date-{{ $row['period'] }}" name="paid_on" max="{{ now()->format('Y-m-d') }}" value="{{ now()->format('Y-m-d') }}" required></div>
                                        <div class="col-6"><label class="form-label small" for="po-method-{{ $row['period'] }}">Moyen</label>@include('paiements._method-select', ['id' => 'po-method-'.$row['period'], 'name' => 'method', 'class' => 'form-select', 'selected' => 'banque'])</div>
                                        <div class="col-6"><label class="form-label small" for="po-ref-{{ $row['period'] }}">Référence</label><input class="form-control" id="po-ref-{{ $row['period'] }}" name="reference" maxlength="100"></div>
                                    </div>
                                </div>
                                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button><button type="submit" class="btn btn-success btn-lg"><i class='bx bx-check me-1'></i>Enregistrer le versement</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        @endcan
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud-ajax.js') }}?v={{ filemtime(public_path('assets/js/crud-ajax.js')) }}"></script>
    <script>CrudAjax.init(document.querySelector('[data-crud="finances"]'));</script>
@endpush
