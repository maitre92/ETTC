@extends('layouts.admin')

@section('title', 'Test '.$test->number)

{{-- Fiche de traitement d'un test de niveau (cahier §3.4) : candidat, planification, frais, résultat et orientation,
     annulation, historique. Chaque formulaire part en AJAX et la fiche se met à jour sans rechargement (crud-ajax.js). --}}
@use('App\Enums\TestStatus')

@section('content')
    @php
        $closed = in_array($test->status, [TestStatus::Passe, TestStatus::Annule], true);
        $field = fn (string $name) => $errors->has($name) ? 'is-invalid' : '';
    @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Formations</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('tests.index') }}">Tests de niveau</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $test->number }}</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div data-crud="test-{{ $test->id }}">
        <span data-crud-status hidden>{{ session('status') }}</span>
        @if ($errors->has('status'))
            <div class="alert alert-warning">{{ $errors->first('status') }}</div>
        @endif

        <div class="row g-3">
            <div class="col-xl-5">
                <div class="card">
                    <div class="card-header card-header-brand d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-white"><i class='bx bx-user me-2'></i>{{ mb_strtoupper($test->name) }}</h6>
                        <span class="badge {{ $test->status->badge() }}">{{ $test->status->label() }}</span>
                    </div>
                    <div class="card-body">
                        <dl class="nv-dl mb-0">
                            <dt>Numéro</dt><dd>{{ $test->number }} <span class="text-muted small">({{ $test->source === 'site' ? 'demande du site' : 'inscrit au centre' }}, le {{ $test->created_at->format('d/m/Y H:i') }})</span></dd>
                            @if ($test->phone)<dt>Téléphone</dt><dd><a href="tel:{{ $test->phone }}">{{ $test->phone }}</a></dd>@endif
                            @if ($test->email)<dt>E-mail</dt><dd><a href="mailto:{{ $test->email }}">{{ $test->email }}</a></dd>@endif
                            @if ($test->client)<dt>Compte apprenant</dt><dd>{{ $test->client->name }}</dd>@endif
                            <dt>Mode</dt><dd>{{ $test->test_mode->label() }}</dd>
                            @if ($test->preferred_slot)<dt>Disponibilités</dt><dd>{{ $test->preferred_slot }}</dd>@endif
                            @if ($test->message)<dt>Message</dt><dd class="text-break">{!! nl2br(e($test->message)) !!}</dd>@endif
                            @if ($test->scheduled_at)<dt>Test prévu</dt><dd class="fw-semibold">{{ $test->scheduled_at->translatedFormat('l j F Y \à H\hi') }}@if ($test->location)<div class="small text-muted fw-normal text-break">{{ $test->location }}</div>@endif</dd>@endif
                            <dt>Frais</dt>
                            <dd>
                                {{ \App\Models\Formule::money($test->fee) }}
                                @if ($test->paid_at)
                                    <span class="badge bg-success-subtle">Payés le {{ $test->paid_at->format('d/m/Y') }}</span>
                                    <div class="small text-muted">{{ $test->payment_method?->label() }}@if ($test->payment_reference) · réf. {{ $test->payment_reference }}@endif @if ($test->paymentRecorder) · par {{ $test->paymentRecorder->displayName() }}@endif</div>
                                @elseif ($test->fee > 0)
                                    <span class="badge bg-warning-subtle">À encaisser</span>
                                @endif
                                <div class="small text-muted">Frais de test : hors commission (cahier §3.4).</div>
                            </dd>
                            @if ($test->status === TestStatus::Annule)<dt>Annulation</dt><dd>{{ $test->cancel_reason }}</dd>@endif
                        </dl>
                        @if ($test->notification_error)
                            <div class="alert alert-warning small mt-3 mb-0"><i class='bx bx-error me-1'></i>L'e-mail à l'équipe n'est pas parti : {{ $test->notification_error }}</div>
                        @endif
                    </div>
                </div>

                @if ($test->niveau)
                    <div class="card border-success">
                        <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-medal me-2'></i>RÉSULTAT ET ORIENTATION</h6></div>
                        <div class="card-body">
                            <p class="mb-1 fs-5">Niveau attribué : <strong>{{ $test->niveau->name }}</strong>@if ($test->score) <span class="text-muted">({{ $test->score }})</span>@endif</p>
                            @if ($test->result_notes)<p class="text-break">{!! nl2br(e($test->result_notes)) !!}</p>@endif
                            <p class="small text-muted">Enregistré le {{ $test->result_at?->format('d/m/Y H:i') }}@if ($test->evaluator) par {{ $test->evaluator->displayName() }}@endif.</p>
                            @if ($formules->isNotEmpty())
                                <p class="fw-semibold mb-2">Formules proposées au candidat :</p>
                                <ul class="small mb-2">
                                    @foreach ($formules as $formule)
                                        <li>{{ $formule->name }} — @foreach ($formule->prices() as [$mode, $amount]){{ $mode }} : {{ \App\Models\Formule::money($amount) }}{{ $formule->billing === \App\Enums\Billing::Mensuel ? ' / mois' : '' }}@if (! $loop->last) · @endif @endforeach</li>
                                    @endforeach
                                </ul>
                            @endif
                            @if ($test->niveau->is_published && Route::has('site.formations.show'))
                                <a href="{{ route('site.formations.show', $test->niveau->slug) }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class='bx bx-link-external me-1'></i>Fiche du niveau à transmettre</a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-xl-7">
                @can('schedule', $test)
                    @unless ($closed)
                        <div class="card">
                            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-calendar me-2'></i>{{ $test->scheduled_at ? 'REPORTER LE TEST' : 'PLANIFIER LE TEST' }}</h6></div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('tests.schedule', $test) }}" class="row g-2" data-crud-form data-no-guard>
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-5"><label class="form-label fw-semibold" for="s-at">Date et heure <span class="text-danger">*</span></label><input type="datetime-local" class="form-control {{ $field('scheduled_at') }}" id="s-at" name="scheduled_at" value="{{ old('scheduled_at', $test->scheduled_at?->format('Y-m-d\TH:i')) }}" required></div>
                                    <div class="col-md-3"><label class="form-label fw-semibold" for="s-mode">Mode</label><select class="form-select" id="s-mode" name="test_mode">@foreach (\App\Enums\TestMode::options() as $value => $label)<option value="{{ $value }}" @selected($test->test_mode->value === $value)>{{ $label }}</option>@endforeach</select></div>
                                    <div class="col-md-4"><label class="form-label fw-semibold" for="s-loc">Lieu ou lien</label><input class="form-control {{ $field('location') }}" id="s-loc" name="location" maxlength="500" value="{{ old('location', $test->location) }}" placeholder="Salle 2 / lien Google Meet"></div>
                                    <div class="col-12 d-flex align-items-center gap-2 mt-2">
                                        <button type="submit" class="btn btn-primary btn-sm"><i class='bx bx-calendar-check me-1'></i>{{ $test->scheduled_at ? 'Reporter' : 'Planifier' }}</button>
                                        <span class="small text-muted">{{ $test->email ? 'Une convocation part par e-mail au candidat.' : 'Pas d\'e-mail : prévenez le candidat par téléphone.' }}</span>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endunless
                @endcan

                @can('payment', $test)
                    @if (! $test->paid_at && $test->fee > 0 && $test->status !== TestStatus::Annule)
                        <div class="card">
                            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-wallet me-2'></i>ENCAISSER LES FRAIS — {{ \App\Models\Formule::money($test->fee) }}</h6></div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('tests.payment', $test) }}" class="row g-2 align-items-end" data-crud-form data-no-guard>
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-5"><label class="form-label fw-semibold" for="p-method">Moyen de paiement <span class="text-danger">*</span></label><select class="form-select" id="p-method" name="payment_method" required>@foreach (\App\Enums\PaymentMethod::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                                    <div class="col-md-4"><label class="form-label fw-semibold" for="p-ref">Référence</label><input class="form-control {{ $field('payment_reference') }}" id="p-ref" name="payment_reference" maxlength="100" placeholder="N° de transaction"></div>
                                    <div class="col-md-3"><button type="submit" class="btn btn-success w-100"><i class='bx bx-check me-1'></i>Encaissé</button></div>
                                </form>
                            </div>
                        </div>
                    @endif
                @endcan

                @can('result', $test)
                    @if ($test->status !== TestStatus::Annule)
                        <div class="card">
                            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-medal me-2'></i>{{ $test->niveau ? 'CORRIGER LE RÉSULTAT' : 'ENREGISTRER LE RÉSULTAT' }}</h6></div>
                            <div class="card-body">
                                @if (! $test->isPaid())
                                    <div class="alert alert-warning small py-2"><i class='bx bx-info-circle me-1'></i>Les frais du test ne sont pas encore encaissés.</div>
                                @endif
                                <form method="POST" action="{{ route('tests.result', $test) }}" class="row g-2" data-crud-form data-no-guard>
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-6"><label class="form-label fw-semibold" for="r-niveau">Niveau attribué <span class="text-danger">*</span></label><select class="form-select {{ $field('niveau_id') }}" id="r-niveau" name="niveau_id" required><option value="">— Choisir —</option>@foreach ($niveaux as $niveau)<option value="{{ $niveau->id }}" @selected($test->niveau_id === $niveau->id)>{{ $niveau->name }}</option>@endforeach</select></div>
                                    <div class="col-md-6"><label class="form-label fw-semibold" for="r-score">Score</label><input class="form-control" id="r-score" name="score" maxlength="50" value="{{ $test->score }}" placeholder="Ex. 34 / 50"></div>
                                    <div class="col-12"><label class="form-label fw-semibold" for="r-notes">Appréciation</label><textarea class="form-control" id="r-notes" name="result_notes" rows="2" maxlength="3000">{{ $test->result_notes }}</textarea></div>
                                    <div class="col-12"><button type="submit" class="btn btn-primary btn-sm mt-1"><i class='bx bx-save me-1'></i>Enregistrer le résultat</button></div>
                                </form>
                            </div>
                        </div>
                    @endif
                @endcan

                @can('schedule', $test)
                    @unless ($closed)
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" action="{{ route('tests.cancel', $test) }}" class="row g-2 align-items-end" data-crud-form data-no-guard>
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-8"><label class="form-label fw-semibold" for="c-reason">Annuler le test : motif</label><input class="form-control {{ $field('cancel_reason') }}" id="c-reason" name="cancel_reason" maxlength="300" placeholder="Le candidat ne souhaite plus passer le test…" required></div>
                                    <div class="col-md-4"><button type="submit" class="btn btn-outline-danger w-100"><i class='bx bx-x me-1'></i>Annuler le test</button></div>
                                </form>
                            </div>
                        </div>
                    @endunless
                @endcan

                <div class="card">
                    <div class="card-header card-header-brand d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-white"><i class='bx bx-history me-2'></i>HISTORIQUE</h6>
                        @can('delete', $test)
                            <form method="POST" action="{{ route('tests.destroy', $test) }}" class="js-confirm-delete" data-crud-form data-no-guard data-title="Supprimer ce test ?" data-name="Le test {{ $test->number }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-light btn-sm" title="Supprimer"><i class='bx bx-trash'></i></button>
                            </form>
                        @endcan
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small mb-0">
                            @forelse ($history as $log)
                                <li class="mb-2 pb-2 border-bottom">
                                    <div class="text-muted">{{ $log->created_at->format('d/m/Y H:i') }} · {{ $log->isBySuperadmin() && ! auth()->user()->isSuperadmin() ? config('brand.system_actor') : ($log->user_name ?? 'Site public') }}</div>
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
    <script>CrudAjax.init(document.querySelector('[data-crud="test-{{ $test->id }}"]'));</script>
    @include('partials.confirm-delete')
@endpush
