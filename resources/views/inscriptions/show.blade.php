@extends('layouts.admin')

@section('title', 'Inscription '.$inscription->number)

{{-- Fiche d'une inscription (cahier §3.5, §5) : apprenant, formation et tarif FIGÉ, étapes (validation du dossier, début et
     fin de formation, annulation), session et notes internes, historique. Formulaires en AJAX (crud-ajax.js). --}}
@use('App\Enums\InscriptionStatus')

@section('content')
    @php
        $status = $inscription->status;
        $field = fn (string $name) => $errors->has($name) ? 'is-invalid' : '';
    @endphp
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Formations</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item"><a href="{{ route('inscriptions.index') }}">Inscriptions</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $inscription->number }}</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    <div data-crud="inscription-{{ $inscription->id }}">
        <span data-crud-status hidden>{{ session('status') }}</span>
        @include('paiements._last-receipt')
        @if ($errors->has('status'))
            <div class="alert alert-warning">{{ $errors->first('status') }}</div>
        @endif

        {{-- Étapes du dossier --}}
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    @foreach ([InscriptionStatus::AValider, InscriptionStatus::Validee, InscriptionStatus::EnCours, InscriptionStatus::Terminee] as $step)
                        @php $reached = $status !== InscriptionStatus::Annulee && array_search($status, InscriptionStatus::cases(), true) >= array_search($step, InscriptionStatus::cases(), true); @endphp
                        <span class="badge {{ $status === $step ? $step->badge().' fs-6' : ($reached ? 'bg-success-subtle' : 'bg-light text-muted') }} px-3 py-2">{{ $step->label() }}</span>
                        @unless ($loop->last)<i class='bx bx-chevron-right text-muted'></i>@endunless
                    @endforeach
                    @if ($status === InscriptionStatus::Annulee)
                        <span class="badge bg-secondary-subtle fs-6 px-3 py-2 ms-2">Annulée</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-5">
                <div class="card">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-user me-2'></i>{{ mb_strtoupper($inscription->name) }}</h6></div>
                    <div class="card-body">
                        <dl class="nv-dl mb-0">
                            <dt>Numéro</dt><dd>{{ $inscription->number }} <span class="text-muted small">({{ $inscription->source === 'site' ? 'inscription en ligne' : 'inscrit au centre' }}, le {{ $inscription->created_at->format('d/m/Y H:i') }})</span></dd>
                            @if ($inscription->phone)<dt>Téléphone</dt><dd><a href="tel:{{ $inscription->phone }}">{{ $inscription->phone }}</a>@if ($wa = $inscription->whatsappMessageUrl()) <a href="{{ $wa }}" class="btn btn-success btn-sm py-0 ms-1" target="_blank" rel="noopener" title="Message WhatsApp déjà rédigé"><i class='bx bxl-whatsapp'></i> Écrire</a>@endif</dd>@endif
                            @if ($inscription->email)<dt>E-mail</dt><dd><a href="mailto:{{ $inscription->email }}">{{ $inscription->email }}</a></dd>@endif
                            @if ($inscription->birth_date)<dt>Naissance</dt><dd>{{ $inscription->birth_date->format('d/m/Y') }} <span class="text-muted small">({{ $inscription->birth_date->age }} ans)</span></dd>@endif
                            @if ($inscription->gender)<dt>Sexe</dt><dd>{{ $inscription->gender->label() }}</dd>@endif
                            @if ($inscription->address || $inscription->city)<dt>Adresse</dt><dd>{{ collect([$inscription->address, $inscription->city])->filter()->implode(', ') }}</dd>@endif
                            @if ($inscription->client)<dt>Compte apprenant</dt><dd>{{ $inscription->client->name }}</dd>@endif
                            @if ($inscription->testNiveau)<dt>Test de niveau</dt><dd><a href="{{ route('tests.show', $inscription->testNiveau) }}">{{ $inscription->testNiveau->number }}</a> · {{ $inscription->testNiveau->niveau?->name }}</dd>@endif
                            @if ($inscription->message)<dt>Message</dt><dd class="text-break">{!! nl2br(e($inscription->message)) !!}</dd>@endif
                            @if ($status === InscriptionStatus::Annulee)<dt>Annulation</dt><dd>{{ $inscription->cancel_reason }}</dd>@endif
                        </dl>
                        @if ($inscription->notification_error)
                            <div class="alert alert-warning small mt-3 mb-0"><i class='bx bx-error me-1'></i>L'e-mail à l'équipe n'est pas parti : {{ $inscription->notification_error }}</div>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-book-open me-2'></i>FORMATION ET TARIF</h6></div>
                    <div class="card-body">
                        <dl class="nv-dl mb-0">
                            <dt>Niveau</dt><dd>{{ $inscription->niveau?->name ?? 'À déterminer (test de niveau gratuit)' }}</dd>
                            <dt>Formule</dt><dd>{{ $inscription->formule_name }} — {{ $inscription->duration_months }} mois</dd>
                            <dt>Mode</dt><dd>{{ $inscription->mode->label() }}</dd>
                            <dt>Tarif figé</dt><dd>{{ $inscription->priceLabel() }}</dd>
                            <dt>Total</dt><dd class="fs-5 fw-bold text-primary">{{ \App\Models\Formule::money($inscription->total_amount) }}</dd>
                            <dt>Paiement choisi</dt><dd>{{ $inscription->payment_choice->label() }}</dd>
                            @if ($inscription->session)<dt>Session</dt><dd>{{ $inscription->session }}</dd>@endif
                            @if ($inscription->validated_at)<dt>Validé le</dt><dd>{{ $inscription->validated_at->format('d/m/Y') }}@if ($inscription->validator) par {{ $inscription->validator->displayName() }}@endif</dd>@endif
                            @if ($inscription->started_at)<dt>Commencée le</dt><dd>{{ $inscription->started_at->format('d/m/Y') }}</dd>@endif
                            @if ($inscription->finished_at)<dt>Terminée le</dt><dd>{{ $inscription->finished_at->format('d/m/Y') }}</dd>@endif
                        </dl>
                        <p class="small text-muted mt-3 mb-0"><i class='bx bx-info-circle me-1'></i>Tarif retenu au moment de l'inscription : un changement de tarif ne le modifie pas.</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                @php
                    $open = in_array($status, [InscriptionStatus::AValider, InscriptionStatus::Validee, InscriptionStatus::EnCours], true);
                    $canPay = $open && $inscription->balance() > 0 && auth()->user()->can('create', \App\Models\Paiement::class);
                    $canValidate = auth()->user()->can('validate', $inscription);
                    $money = fn (int $amount) => \App\Models\Formule::money($amount);
                @endphp

                {{-- Une seule action principale, expliquée en une phrase (écran pensé pour un non-informaticien). --}}
                @if ($open)
                    <div class="card border-primary">
                        <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-bulb me-2'></i>QUE FAIRE MAINTENANT ?</h6></div>
                        <div class="card-body">
                            @if ($canPay)
                                <p class="mb-3">{{ $inscription->amount_paid > 0 ? 'Il reste '.$money($inscription->balance()).' à payer.' : 'L\'apprenant n\'a pas encore payé.' }} Quand il paie, cliquez sur « Encaisser » : le reçu est créé et l'inscription est validée toute seule.</p>
                                <button type="button" class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#pay-inscription"><i class='bx bx-money me-1'></i>Encaisser un paiement</button>
                            @elseif ($status === InscriptionStatus::EnCours && $canValidate)
                                <p class="mb-3">La formation est payée et en cours. À la fin de la formation, cliquez sur « Terminer ».</p>
                                <form method="POST" action="{{ route('inscriptions.finish', $inscription) }}" data-crud-form data-no-guard>@csrf @method('PUT')<button type="submit" class="btn btn-success btn-lg"><i class='bx bx-flag me-1'></i>Terminer la formation</button></form>
                            @else
                                <p class="mb-0">Rien à faire pour l'instant.</p>
                            @endif
                        </div>
                    </div>
                @endif

                @can('viewAny', \App\Models\Paiement::class)
                    <div class="card">
                        <div class="card-header card-header-brand d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 text-white"><i class='bx bx-wallet me-2'></i>PAIEMENTS</h6>
                            <span class="badge {{ $inscription->paymentBadge() }}">{{ $inscription->paymentState() }}</span>
                        </div>
                        <div class="card-body">
                            @php $percent = $inscription->total_amount > 0 ? min(100, (int) round($inscription->amount_paid * 100 / $inscription->total_amount)) : 0; @endphp
                            <div class="d-flex flex-wrap justify-content-between gap-2 small mb-1"><span>Payé : <strong>{{ $money($inscription->amount_paid) }}</strong> sur {{ $money($inscription->total_amount) }}</span><span>Reste : <strong class="{{ $inscription->balance() > 0 ? 'text-danger' : 'text-success' }}">{{ $money($inscription->balance()) }}</strong></span></div>
                            <div class="progress mb-2" style="height:8px" role="progressbar" aria-label="Part payée" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: {{ $percent }}%"></div></div>
                            @forelse ($inscription->paiements as $paiement)
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 py-2 border-bottom small">
                                    <span><a href="{{ route('paiements.show', $paiement) }}">{{ $paiement->number }}</a> · {{ $paiement->paid_at->format('d/m/Y') }} · {{ $paiement->method->label() }}</span>
                                    <span class="d-flex flex-wrap align-items-center gap-1"><strong>{{ $paiement->money() }}</strong> <span class="badge {{ $paiement->status->badge() }}">{{ $paiement->status->label() }}</span>
                                        @if ($paiement->status->hasReceipt())@include('paiements._receipt-buttons', ['receiptRoute' => 'paiements.receipt', 'paiement' => $paiement, 'btn' => 'btn btn-outline-primary btn-sm py-0'])@endif
                                    </span>
                                </div>
                            @empty
                                <p class="small text-muted mb-0 mt-2">Aucun paiement pour l'instant.</p>
                            @endforelse
                        </div>
                    </div>
                @endcan

                @if ($open && (auth()->user()->can('organize', $inscription) || auth()->user()->can('cancel', $inscription) || $canValidate))
                    <div class="card">
                        <details @if ($errors->hasAny(['session', 'internal_notes', 'cancel_reason'])) open @endif>
                            <summary class="card-body py-3 fw-semibold text-primary" style="cursor:pointer"><i class='bx bx-dots-horizontal-rounded me-1'></i>Autres actions (session, notes, annulation…)</summary>
                            <div class="card-body pt-0">
                                @if ($canValidate)
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        @if ($status === InscriptionStatus::AValider)
                                            <form method="POST" action="{{ route('inscriptions.validate', $inscription) }}" data-crud-form data-no-guard>@csrf @method('PUT')<button type="submit" class="btn btn-outline-success btn-sm"><i class='bx bx-check me-1'></i>Valider sans paiement</button></form>
                                        @endif
                                        @if (in_array($status, [InscriptionStatus::AValider, InscriptionStatus::Validee], true))
                                            <form method="POST" action="{{ route('inscriptions.start', $inscription) }}" data-crud-form data-no-guard>@csrf @method('PUT')<button type="submit" class="btn btn-outline-primary btn-sm"><i class='bx bx-play me-1'></i>Démarrer sans attendre le paiement</button></form>
                                        @endif
                                        @if ($status === InscriptionStatus::EnCours && $canPay)
                                            <form method="POST" action="{{ route('inscriptions.finish', $inscription) }}" data-crud-form data-no-guard>@csrf @method('PUT')<button type="submit" class="btn btn-outline-success btn-sm"><i class='bx bx-flag me-1'></i>Terminer la formation</button></form>
                                        @endif
                                    </div>
                                @endif
                                @can('organize', $inscription)
                                    <form method="POST" action="{{ route('inscriptions.organize', $inscription) }}" class="row g-2 mb-3" data-crud-form data-no-guard>
                                        @csrf
                                        @method('PUT')
                                        <div class="col-md-5">
                                            <label class="form-label fw-semibold" for="o-session">Session</label>
                                            <input class="form-control {{ $field('session') }}" id="o-session" name="session" maxlength="100" value="{{ $inscription->session }}" list="sessions-list" placeholder="Janvier 2027">
                                            <datalist id="sessions-list">@foreach ($sessions as $session)<option value="{{ $session }}"></option>@endforeach</datalist>
                                        </div>
                                        <div class="col-md-7"><label class="form-label fw-semibold" for="o-notes">Notes internes</label><textarea class="form-control" id="o-notes" name="internal_notes" rows="2" maxlength="3000">{{ $inscription->internal_notes }}</textarea></div>
                                        <div class="col-12"><button type="submit" class="btn btn-primary btn-sm"><i class='bx bx-save me-1'></i>Enregistrer</button></div>
                                    </form>
                                @endcan
                                @can('cancel', $inscription)
                                    <form method="POST" action="{{ route('inscriptions.cancel', $inscription) }}" class="row g-2 align-items-end" data-crud-form data-no-guard>
                                        @csrf
                                        @method('PUT')
                                        <div class="col-md-8"><label class="form-label fw-semibold" for="c-reason">Annuler l'inscription : motif</label><input class="form-control {{ $field('cancel_reason') }}" id="c-reason" name="cancel_reason" maxlength="300" required placeholder="L'apprenant renonce, changement de formule…"></div>
                                        <div class="col-md-4"><button type="submit" class="btn btn-outline-danger w-100"><i class='bx bx-x me-1'></i>Annuler l'inscription</button></div>
                                    </form>
                                @endcan
                            </div>
                        </details>
                    </div>
                @endif

                @if ($canPay)
                    @include('paiements._record-modal', ['inscription' => $inscription, 'modalId' => 'pay-inscription', 'owner' => 'inscription-'.$inscription->id])
                @endif

                <div class="card">
                    <div class="card-header card-header-brand d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-white"><i class='bx bx-history me-2'></i>HISTORIQUE</h6>
                        @can('delete', $inscription)
                            <form method="POST" action="{{ route('inscriptions.destroy', $inscription) }}" class="js-confirm-delete" data-crud-form data-no-guard data-title="Supprimer cette inscription ?" data-name="L'inscription {{ $inscription->number }}">
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
    <script>CrudAjax.init(document.querySelector('[data-crud="inscription-{{ $inscription->id }}"]'));</script>
    @include('partials.confirm-delete')
@endpush
