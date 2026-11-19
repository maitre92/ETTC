@extends('layouts.admin')

@section('title', 'Inscriptions')

{{-- Inscriptions (cahier §3.5, §5) : suivi des dossiers en attente, organisation par niveau, formule, mode ou session,
     inscription au centre. Zone [data-crud] actualisée en AJAX (filtres et recherche conservés). --}}
@use('App\Enums\InscriptionStatus')

@section('content')
    @php
        $count = fn (InscriptionStatus $s) => $inscriptions->where('status', $s)->count();
        $engaged = $inscriptions->filter(fn ($i) => $i->status !== InscriptionStatus::Annulee)->sum('total_amount');
        $canRecord = auth()->user()->can('create', \App\Models\Paiement::class);
        $payable = fn ($i) => $canRecord && $i->balance() > 0 && in_array($i->status, [InscriptionStatus::AValider, InscriptionStatus::Validee, InscriptionStatus::EnCours], true);
    @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Formations</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Inscriptions</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div data-crud="inscriptions" data-crud-columns="5">
        <span data-crud-status hidden>{{ session('status') }}</span>
        @include('paiements._last-receipt')
        <div class="row g-3 mb-3">
            @foreach ([['À valider', $count(InscriptionStatus::AValider), 'bx-folder-open', 'danger'], ['En attente de paiement', $count(InscriptionStatus::Validee), 'bx-time-five', 'warning'], ['Confirmées', $count(InscriptionStatus::EnCours), 'bx-book-reader', 'primary'], ['Montant des inscriptions', \App\Models\Formule::money($engaged), 'bx-purchase-tag', 'success']] as [$cardLabel, $cardValue, $cardIcon, $cardColor])
                <div class="col-6 col-xl-3">
                    <div class="card radius-10 mb-0 h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="rounded-circle bg-{{ $cardColor }}-subtle text-{{ $cardColor }} d-inline-flex align-items-center justify-content-center" style="width:48px;height:48px;font-size:1.5rem"><i class='bx {{ $cardIcon }}'></i></span>
                            <div><p class="mb-0 text-secondary small">{{ $cardLabel }}</p><h5 class="mb-0">{{ $cardValue }}</h5></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="mb-0 text-white"><i class='bx bx-user-check me-2'></i>INSCRIPTIONS AUX FORMATIONS</h6>
                @can('create', \App\Models\Inscription::class)
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#create-inscription"><i class='bx bx-plus'></i> Inscrire au centre</button>
                @endcan
            </div>
            <div class="card-body">
                <div class="nv-chips mb-3" id="inscriptions-filter">
                    <button type="button" class="nv-chip active" data-token="">Toutes <b>{{ $inscriptions->count() }}</b></button>
                    @foreach (InscriptionStatus::cases() as $status)
                        <button type="button" class="nv-chip" data-token="statut-{{ $status->value }}">{{ $status->label() }} <b>{{ $count($status) }}</b></button>
                    @endforeach
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6 col-lg-3"><select class="form-select form-select-sm" data-crud-filter-column="2" aria-label="Filtrer par niveau"><option value="">Tous les niveaux</option>@foreach ($niveaux as $niveau)<option value="{{ $niveau->name }}">{{ $niveau->name }}</option>@endforeach<option value="À déterminer">Niveau à déterminer</option></select></div>
                    <div class="col-sm-6 col-lg-3"><select class="form-select form-select-sm" data-crud-filter-column="2" aria-label="Filtrer par formule"><option value="">Toutes les formules</option>@foreach ($inscriptions->pluck('formule_name')->unique()->sort() as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach</select></div>
                    <div class="col-sm-6 col-lg-3"><select class="form-select form-select-sm" data-crud-filter-column="2" aria-label="Filtrer par mode"><option value="">Tous les modes</option>@foreach (\App\Enums\FormationMode::cases() as $mode)<option value="{{ $mode->label() }}">{{ $mode->label() }}</option>@endforeach</select></div>
                    <div class="col-sm-6 col-lg-3"><select class="form-select form-select-sm" data-crud-filter-column="3" aria-label="Filtrer par session"><option value="">Toutes les sessions</option>@foreach ($sessions as $session)<option value="{{ $session }}">{{ $session }}</option>@endforeach</select></div>
                </div>
                <div class="table-responsive">
                    <table class="table" id="inscriptions-table">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>APPRENANT</th>
                                <th>FORMATION</th>
                                <th>SESSION</th>
                                <th>MONTANT</th>
                                <th>STATUT</th>
                                <th width="12%">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($inscriptions as $inscription)
                                <tr class="{{ $inscription->status === InscriptionStatus::AValider ? 'fw-semibold' : '' }}">
                                    <td data-order="{{ $inscription->id }}">{{ $inscription->number }}<div class="small text-muted fw-normal">{{ $inscription->created_at->format('d/m/Y') }} · {{ $inscription->source === 'site' ? 'site' : 'centre' }}</div></td>
                                    <td class="cell-wrap text-break">{{ $inscription->name }}<div class="small text-muted fw-normal">{{ $inscription->contactLine() }}</div></td>
                                    <td class="fw-normal">{{ $inscription->niveau?->name ?? 'À déterminer' }}<div class="small text-muted">{{ $inscription->formule_name }} · {{ $inscription->mode->label() }}</div></td>
                                    <td class="fw-normal">{{ $inscription->session ?? '—' }}</td>
                                    <td class="fw-normal" data-order="{{ $inscription->total_amount }}">{{ \App\Models\Formule::money($inscription->total_amount) }}<div class="small text-muted">{{ $inscription->priceLabel() }}</div></td>
                                    <td>
                                        <span class="d-none">statut-{{ $inscription->status->value }}</span>
                                        <span class="badge {{ $inscription->status->badge() }}">{{ $inscription->status->label() }}</span>
                                        @if ($inscription->status !== InscriptionStatus::Annulee)<div class="small text-muted fw-normal mt-1">{{ $inscription->paymentState() }}</div>@endif
                                    </td>
                                    <td class="text-nowrap">
                                        @if ($payable($inscription))
                                            <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#pay-{{ $inscription->id }}"><i class='bx bx-money'></i><span class="ms-1">Encaisser</span></button>
                                        @endif
                                        <a href="{{ route('inscriptions.show', $inscription) }}" class="btn btn-primary btn-sm" title="Ouvrir le dossier"><i class='bx bx-show'></i></a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @foreach ($inscriptions->filter($payable) as $inscription)
            @include('paiements._record-modal', ['inscription' => $inscription, 'modalId' => 'pay-'.$inscription->id, 'owner' => 'inscriptions', 'back' => 'inscriptions'])
        @endforeach

        @can('create', \App\Models\Inscription::class)
            @include('inscriptions._form-modal', ['modalId' => 'create-inscription', 'action' => route('inscriptions.store'), 'title' => 'Inscrire un apprenant au centre', 'niveaux' => $niveaux, 'formules' => $formules, 'test' => null])
        @endcan
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/crud-ajax.js') }}?v={{ filemtime(public_path('assets/js/crud-ajax.js')) }}"></script>
    <script src="{{ asset('assets/js/inscription-form.js') }}?v={{ filemtime(public_path('assets/js/inscription-form.js')) }}"></script>
    <script>CrudAjax.init(document.querySelector('[data-crud="inscriptions"]'));</script>
@endpush
