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
                            @if ($inscription->phone)<dt>Téléphone</dt><dd><a href="tel:{{ $inscription->phone }}">{{ $inscription->phone }}</a></dd>@endif
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
                        <p class="small text-muted mt-3 mb-0"><i class='bx bx-info-circle me-1'></i>Tarif retenu au moment de l'inscription : un changement de tarif ne le modifie pas. L'encaissement et le reçu seront gérés par le module Paiements.</p>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                @can('validate', $inscription)
                    @if (in_array($status, [InscriptionStatus::AValider, InscriptionStatus::Validee, InscriptionStatus::EnCours], true))
                        <div class="card">
                            <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-check-double me-2'></i>SUITE DU DOSSIER</h6></div>
                            <div class="card-body d-flex flex-wrap gap-2">
                                @if ($status === InscriptionStatus::AValider)
                                    <form method="POST" action="{{ route('inscriptions.validate', $inscription) }}" data-crud-form data-no-guard>@csrf @method('PUT')<button type="submit" class="btn btn-success"><i class='bx bx-check me-1'></i>Valider le dossier</button></form>
                                @endif
                                @if (in_array($status, [InscriptionStatus::AValider, InscriptionStatus::Validee], true))
                                    <form method="POST" action="{{ route('inscriptions.start', $inscription) }}" data-crud-form data-no-guard>@csrf @method('PUT')<button type="submit" class="btn btn-primary"><i class='bx bx-play me-1'></i>Démarrer la formation</button></form>
                                @endif
                                @if ($status === InscriptionStatus::EnCours)
                                    <form method="POST" action="{{ route('inscriptions.finish', $inscription) }}" data-crud-form data-no-guard>@csrf @method('PUT')<button type="submit" class="btn btn-success"><i class='bx bx-flag me-1'></i>Terminer la formation</button></form>
                                @endif
                                <p class="small text-muted w-100 mb-0 mt-1">« Valider le dossier » : informations vérifiées, l'inscription attend le paiement. « Démarrer » : le paiement est réglé et les séances commencent.</p>
                            </div>
                        </div>
                    @endif
                @endcan

                @can('organize', $inscription)
                    <div class="card">
                        <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-group me-2'></i>SESSION ET NOTES</h6></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('inscriptions.organize', $inscription) }}" class="row g-2" data-crud-form data-no-guard>
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
                        </div>
                    </div>
                @endcan

                @can('cancel', $inscription)
                    @if (in_array($status, [InscriptionStatus::AValider, InscriptionStatus::Validee, InscriptionStatus::EnCours], true))
                        <div class="card">
                            <div class="card-body">
                                <form method="POST" action="{{ route('inscriptions.cancel', $inscription) }}" class="row g-2 align-items-end" data-crud-form data-no-guard>
                                    @csrf
                                    @method('PUT')
                                    <div class="col-md-8"><label class="form-label fw-semibold" for="c-reason">Annuler l'inscription : motif</label><input class="form-control {{ $field('cancel_reason') }}" id="c-reason" name="cancel_reason" maxlength="300" required placeholder="L'apprenant renonce, changement de formule…"></div>
                                    <div class="col-md-4"><button type="submit" class="btn btn-outline-danger w-100"><i class='bx bx-x me-1'></i>Annuler</button></div>
                                </form>
                            </div>
                        </div>
                    @endif
                @endcan

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
