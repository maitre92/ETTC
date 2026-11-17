@extends('layouts.admin')

@section('title', 'Tests de niveau')

{{-- Suivi des tests de niveau (cahier §3.4, §5) : candidats à planifier, planifiés, passés, annulés, frais à encaisser ;
     inscription d'un candidat au centre ; présentation du test sur le site. Zone [data-crud] actualisée en AJAX. --}}
@section('content')
    @php
        $open = $tests->filter(fn ($t) => $t->status === \App\Enums\TestStatus::Demande)->count();
        $unpaid = $tests->filter(fn ($t) => ! $t->isPaid() && $t->status !== \App\Enums\TestStatus::Annule)->count();
        $today = $tests->filter(fn ($t) => $t->scheduled_at?->isToday() && $t->status === \App\Enums\TestStatus::Planifie)->count();
    @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Formations</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Tests de niveau</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div data-crud="tests" data-crud-columns="5">
        <span data-crud-status hidden>{{ session('status') }}</span>
        <div class="row g-3 mb-3">
            @foreach ([['À planifier', $open, 'bx-time-five', 'danger'], ['Aujourd\'hui', $today, 'bx-calendar-check', 'primary'], ['Frais à encaisser', $unpaid, 'bx-wallet', 'warning'], ['Frais du test', \App\Models\Formule::money($fee), 'bx-purchase-tag', 'success']] as [$cardLabel, $cardValue, $cardIcon, $cardColor])
                <div class="col-6 col-xl-3">
                    <div class="card radius-10 mb-0 h-100">
                        <div class="card-body d-flex align-items-center gap-3">
                            <span class="widgets-icons-2 rounded-circle bg-{{ $cardColor }}-subtle text-{{ $cardColor }} d-inline-flex align-items-center justify-content-center" style="width:48px;height:48px;font-size:1.5rem"><i class='bx {{ $cardIcon }}'></i></span>
                            <div><p class="mb-0 text-secondary small">{{ $cardLabel }}</p><h5 class="mb-0">{{ $cardValue }}</h5></div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header card-header-brand d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h6 class="mb-0 text-white"><i class='bx bx-target-lock me-2'></i>CANDIDATS AU TEST DE NIVEAU</h6>
                @can('create', \App\Models\TestNiveau::class)
                    <button type="button" class="btn btn-light btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#create-test"><i class='bx bx-plus'></i> Inscrire un candidat</button>
                @endcan
            </div>
            <div class="card-body">
                <div class="nv-chips mb-3" id="tests-filter">
                    <button type="button" class="nv-chip active" data-token="">Tous <b>{{ $tests->count() }}</b></button>
                    @foreach (\App\Enums\TestStatus::cases() as $status)
                        <button type="button" class="nv-chip" data-token="statut-{{ $status->value }}">{{ $status->label() }} <b>{{ $tests->where('status', $status)->count() }}</b></button>
                    @endforeach
                    <button type="button" class="nv-chip" data-token="impaye">Frais à encaisser <b>{{ $unpaid }}</b></button>
                </div>
                <div class="table-responsive">
                    <table class="table" id="tests-table">
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>CANDIDAT</th>
                                <th>TEST</th>
                                <th>FRAIS</th>
                                <th>RÉSULTAT</th>
                                <th>STATUT</th>
                                <th width="8%">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($tests as $test)
                                <tr class="{{ $test->status === \App\Enums\TestStatus::Demande ? 'fw-semibold' : '' }}">
                                    <td data-order="{{ $test->id }}">{{ $test->number }}<div class="small text-muted fw-normal">{{ $test->created_at->format('d/m/Y') }} · {{ $test->source === 'site' ? 'site' : 'centre' }}</div></td>
                                    <td class="cell-wrap text-break">{{ $test->name }}<div class="small text-muted fw-normal">{{ $test->contactLine() }}</div></td>
                                    <td class="fw-normal" data-order="{{ $test->scheduled_at?->timestamp ?? 0 }}">
                                        @if ($test->scheduled_at)
                                            {{ $test->scheduled_at->format('d/m/Y H:i') }}
                                        @else
                                            <span class="text-muted">{{ $test->preferred_slot ?: 'À fixer' }}</span>
                                        @endif
                                        <div class="small text-muted">{{ $test->test_mode->label() }}</div>
                                    </td>
                                    <td class="fw-normal">
                                        {{ \App\Models\Formule::money($test->fee) }}
                                        <div>@if ($test->isPaid())<span class="badge bg-success-subtle">Payés</span>@else<span class="badge bg-warning-subtle">À encaisser</span>@endif</div>
                                    </td>
                                    <td class="fw-normal">{{ $test->niveau?->name ?? '—' }}@if ($test->score)<div class="small text-muted">{{ $test->score }}</div>@endif</td>
                                    <td>
                                        <span class="d-none">statut-{{ $test->status->value }}{{ ! $test->isPaid() && $test->status !== \App\Enums\TestStatus::Annule ? ' impaye' : '' }}</span>
                                        <span class="badge {{ $test->status->badge() }}">{{ $test->status->label() }}</span>
                                    </td>
                                    <td><a href="{{ route('tests.show', $test) }}" class="btn btn-primary btn-sm" title="Traiter"><i class='bx bx-show'></i></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @can('tests.configure')
            <div class="card">
                <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-detail me-2'></i>PRÉSENTATION DU TEST SUR LE SITE</h6></div>
                <div class="card-body">
                    <p class="text-muted small">Format, durée, déroulement et communication des résultats : à définir par le centre (cahier §3.4). Affiché sur la page « Test de niveau ». Les frais ({{ \App\Models\Formule::money($fee) }}) se règlent dans <a href="{{ route('formations.formules.index') }}">Formules et tarifs</a>.</p>
                    <form method="POST" action="{{ route('tests.presentation') }}" data-crud-form data-no-guard>
                        @csrf
                        @method('PUT')
                        <textarea class="form-control font-monospace small mb-2" name="presentation" rows="7" maxlength="5000" required aria-label="Présentation du test">{{ $presentation }}</textarea>
                        <div class="form-text mb-2">Markdown : <code>## Titre</code>, <code>**gras**</code>, <code>- liste</code>.</div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class='bx bx-save me-1'></i>Enregistrer</button>
                    </form>
                </div>
            </div>
        @endcan

        @can('create', \App\Models\TestNiveau::class)
            <div class="modal fade" id="create-test" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                    <div class="modal-content">
                        <form method="POST" action="{{ route('tests.store') }}" data-crud-form data-no-guard>
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title"><i class='bx bx-target-lock me-2'></i>Inscrire un candidat au test</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                            </div>
                            <div class="modal-body p-4">
                                <p class="text-muted small">Pour un candidat venu au centre ou qui a appelé. Frais figés à {{ \App\Models\Formule::money($fee) }}.</p>
                                <div class="row g-3">
                                    <div class="col-md-6"><label class="form-label fw-semibold" for="t-name">Nom complet <span class="text-danger">*</span></label><input class="form-control" id="t-name" name="name" maxlength="150" required></div>
                                    <div class="col-md-6"><label class="form-label fw-semibold" for="t-mode">Mode du test <span class="text-danger">*</span></label>
                                        <select class="form-select" id="t-mode" name="test_mode" required>@foreach (\App\Enums\TestMode::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                                    <div class="col-md-6"><label class="form-label fw-semibold" for="t-phone">Téléphone</label><input class="form-control" id="t-phone" name="phone" maxlength="30" placeholder="75 22 91 25"></div>
                                    <div class="col-md-6"><label class="form-label fw-semibold" for="t-email">E-mail</label><input type="email" class="form-control" id="t-email" name="email" maxlength="190"></div>
                                    <div class="col-12"><label class="form-label fw-semibold" for="t-slot">Disponibilités</label><input class="form-control" id="t-slot" name="preferred_slot" maxlength="200" placeholder="Samedi matin, en semaine après 17 h…"></div>
                                    <div class="col-12"><label class="form-label fw-semibold" for="t-message">Remarques</label><textarea class="form-control" id="t-message" name="message" rows="2" maxlength="2000"></textarea></div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                <button type="submit" class="btn btn-primary">Enregistrer</button>
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
    <script>CrudAjax.init(document.querySelector('[data-crud="tests"]'));</script>
@endpush
