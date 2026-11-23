@extends('layouts.admin')

@section('title', 'Attestations')

{{-- Réglages de l'attestation (demande du centre, 2026-11-22), repris sur les reçus A5 et les fiches de rémunération : signataire et lieu ; signature tracée à l'écran ou déposée ;
     sceau déposé, traité pour être lisible (fond détouré, encre renforcée) — aperçu SUR l'attestation avant d'enregistrer.
     Comportements : public/assets/js/attestation-settings.js. --}}
@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Formations</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Attestations</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <p class="small text-muted"><i class='bx bx-info-circle'></i> Le signataire, la signature et le sceau réglés ici figurent sur les <strong>attestations</strong>, les <strong>reçus A5</strong> et les <strong>fiches de rémunération</strong> des formateurs. Par défaut : ceux du modèle du centre.</p>

    <div class="row g-3" data-attestation-settings data-preview-url="{{ route('attestations.preview') }}">
        <div class="col-xl-5">
            {{-- Signataire --}}
            <div class="card">
                <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-user-check me-2'></i>SIGNATAIRE</h6></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('attestations.signer') }}" class="row g-2">
                        @csrf @method('PUT')
                        <div class="col-12"><label class="form-label fw-semibold" for="a-name">Nom (tel qu'il apparaît sous la signature)</label><input class="form-control" id="a-name" name="director_name" maxlength="60" required value="{{ old('director_name', $settings->get('attestation.director_name')) }}"></div>
                        <div class="col-md-7"><label class="form-label fw-semibold" for="a-title">Titre (en anglais)</label><input class="form-control" id="a-title" name="director_title" maxlength="40" required value="{{ old('director_title', $settings->get('attestation.director_title')) }}"><div class="form-text">Ex. The General Director</div></div>
                        <div class="col-md-5"><label class="form-label fw-semibold" for="a-city">Lieu de délivrance</label><input class="form-control" id="a-city" name="city" maxlength="60" required value="{{ old('city', $settings->get('attestation.city')) }}"></div>
                        <div class="col-12"><button type="submit" class="btn btn-primary"><i class='bx bx-save me-1'></i>Enregistrer</button></div>
                    </form>
                </div>
            </div>

            {{-- Signature --}}
            <div class="card" data-asset="signature">
                <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-pen me-2'></i>SIGNATURE</h6></div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="att-current"><img src="{{ $signature }}" alt="Signature actuelle"></div>
                        <div class="small text-muted">{{ $custom['signature'] ? 'Signature enregistrée.' : 'Signature du modèle.' }}
                            @if ($custom['signature'])
                                <form method="POST" action="{{ route('attestations.reset', 'signature') }}" class="mt-1" data-confirm-reset="Revenir à la signature du modèle ?">@csrf @method('DELETE')<button type="submit" class="btn btn-link btn-sm p-0">Revenir à celle du modèle</button></form>
                            @endif
                        </div>
                    </div>
                    <ul class="nav nav-pills nav-sm mb-2" role="tablist">
                        <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="pill" data-bs-target="#sig-draw" role="tab">Signer à l'écran</button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="pill" data-bs-target="#sig-upload" role="tab">Déposer une image</button></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="sig-draw" role="tabpanel">
                            <canvas class="att-pad" width="640" height="240" data-pad aria-label="Zone de signature : signez avec la souris ou le doigt"></canvas>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-light btn-sm" data-pad-clear><i class='bx bx-eraser'></i> Effacer</button>
                                <button type="button" class="btn btn-primary btn-sm" data-pad-use><i class='bx bx-show'></i> Voir sur l'attestation</button>
                            </div>
                            <div class="form-text">Signez avec la souris, le doigt ou un stylet, en grand : la signature est réduite et nettoyée automatiquement.</div>
                        </div>
                        <div class="tab-pane fade" id="sig-upload" role="tabpanel">
                            <input type="file" class="form-control" accept="image/png,image/jpeg,image/webp" data-file aria-label="Image de la signature">
                            <div class="form-text">Photo ou scan d'une signature à l'encre sur papier blanc : le fond est retiré.</div>
                        </div>
                    </div>
                    @include('attestations._pending', ['kind' => 'signature'])
                </div>
            </div>

            {{-- Sceau --}}
            <div class="card" data-asset="stamp">
                <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-certification me-2'></i>SCEAU (CACHET)</h6></div>
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="att-current att-current-stamp">@if ($stamp)<img src="{{ $stamp }}" alt="Sceau actuel">@else<span class="small text-muted text-center">Sceau du modèle</span>@endif</div>
                        <div class="small text-muted">{{ $custom['stamp'] ? 'Sceau enregistré.' : 'Le sceau du modèle est utilisé.' }}
                            @if ($custom['stamp'])
                                <form method="POST" action="{{ route('attestations.reset', 'stamp') }}" class="mt-1" data-confirm-reset="Revenir au sceau du modèle ?">@csrf @method('DELETE')<button type="submit" class="btn btn-link btn-sm p-0">Revenir à celui du modèle</button></form>
                            @endif
                        </div>
                    </div>
                    <label class="form-label fw-semibold" for="stamp-file">Photo ou scan du cachet</label>
                    <input type="file" class="form-control" id="stamp-file" accept="image/png,image/jpeg,image/webp" data-file>
                    <div class="row g-2 mt-1">
                        <div class="col-sm-7">
                            <label class="form-label small" for="stamp-ink">Couleur</label>
                            <select class="form-select form-select-sm" id="stamp-ink" data-ink>
                                @foreach (\App\Services\Attestations\AttestationAssets::INKS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-sm-5 d-flex align-items-end">
                            <div class="form-check mb-1"><input class="form-check-input" type="checkbox" id="stamp-strong" data-strong><label class="form-check-label small" for="stamp-strong">Nettoyage renforcé du fond</label></div>
                        </div>
                    </div>
                    <div class="form-text">Le système retire le fond (papier), fonce et ravive l'encre, recadre le cachet. Changez la couleur ou le nettoyage : l'aperçu se refait aussitôt.</div>
                    @include('attestations._pending', ['kind' => 'stamp'])
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <div class="card">
                <div class="card-header card-header-brand d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="mb-0 text-white"><i class='bx bx-show me-2'></i>APERÇU DE L'ATTESTATION</h6>
                    <a href="{{ route('attestations.sample') }}" class="btn btn-light btn-sm" target="_blank" rel="noopener"><i class='bx bxs-file-pdf'></i> Exemple PDF</a>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2" data-preview-note>Attestation avec les réglages enregistrés.</p>
                    <img src="{{ $preview['zoom'] }}" alt="Zone du signataire, agrandie" class="img-fluid border rounded mb-3" data-preview-zoom>
                    <img src="{{ $preview['full'] }}" alt="Attestation entière" class="img-fluid border rounded" data-preview-full>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <style>
        .att-current { width: 150px; height: 70px; border: 1px dashed #c8d3e3; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #fff; }
        .att-current-stamp { width: 90px; height: 90px; }
        .att-current img { max-width: 100%; max-height: 100%; }
        .att-pad { width: 100%; height: auto; aspect-ratio: 8 / 3; border: 2px dashed #9fb3d1; border-radius: 10px; background: #fff; touch-action: none; cursor: crosshair; }
        .att-checker { background-color: #fff; background-image: linear-gradient(45deg, #e9eef5 25%, transparent 25%), linear-gradient(-45deg, #e9eef5 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #e9eef5 75%), linear-gradient(-45deg, transparent 75%, #e9eef5 75%); background-size: 16px 16px; background-position: 0 0, 0 8px, 8px -8px, -8px 0; border-radius: 8px; }
    </style>
    <script src="{{ asset('assets/js/attestation-settings.js') }}?v={{ filemtime(public_path('assets/js/attestation-settings.js')) }}"></script>
@endpush
