@extends('layouts.admin')

@section('title', 'Formules et tarifs')

@section('content')
    @include('admin._crud', [
        'title' => 'Formules et tarifs',
        'section' => 'Formations',
        'tabs' => 'formations._tabs',
        'heading' => 'FORMULES ET TARIFS',
        'icon' => 'bx bx-purchase-tag',
        'intro' => 'Chaque formule (durée) a un tarif par mode de formation. Un tarif modifié est en ligne immédiatement sur l\'accueil et la page des formations, et la modification est enregistrée dans le journal. Laissez un tarif vide si le mode n\'est pas proposé.',
        'items' => $formules,
        'model' => \App\Models\Formule::class,
        'routePrefix' => 'formations.formules',
        'idField' => 'formule_id',
        'deleteTitle' => 'Supprimer cette formule ?',
        'newLabel' => 'Nouvelle formule',
        'labelOf' => fn ($f) => $f->name,
        'fields' => [
            ['name' => 'name', 'label' => 'Nom de la formule', 'required' => true, 'max' => 100, 'placeholder' => 'Formule 2 mois', 'col' => 'col-md-8'],
            ['name' => 'duration_months', 'label' => 'Durée (mois)', 'type' => 'number', 'required' => true, 'default' => 2, 'col' => 'col-md-4'],
            ['name' => 'price_online', 'label' => 'Tarif en ligne (FCFA)', 'placeholder' => '100 000', 'col' => 'col-md-4', 'help' => 'Vide : non proposé en ligne.'],
            ['name' => 'price_onsite', 'label' => 'Tarif en présentiel (FCFA)', 'placeholder' => '50 000', 'col' => 'col-md-4', 'help' => 'Vide : non proposé au centre.'],
            ['name' => 'billing', 'label' => 'Périodicité', 'type' => 'select', 'required' => true, 'options' => \App\Enums\Billing::options(), 'default' => 'mois', 'col' => 'col-md-4'],
            ['name' => 'points', 'label' => 'Points forts', 'type' => 'textarea', 'max' => 1000, 'placeholder' => "Formation intensive\nSuivi personnalisé", 'help' => 'Un point par ligne : coches vertes de la carte sur le site.'],
            ['name' => 'is_published', 'label' => 'Publiée sur le site', 'type' => 'switch', 'default' => true, 'col' => 'col-md-8'],
        ],
        'columns' => [
            ['label' => 'FORMULE', 'value' => fn ($f) => '<div class="fw-semibold">'.e($f->name).'</div><div class="small text-muted">'.e($f->duration_months).' mois · '.e($f->billing->label()).'</div>'],
            ['label' => 'EN LIGNE', 'value' => fn ($f) => $f->price_online === null ? '<span class="text-muted">—</span>' : '<span class="fw-semibold">'.e(\App\Models\Formule::money($f->price_online)).'</span> <span class="small text-muted">'.e($f->billing->suffix()).'</span>'],
            ['label' => 'EN PRÉSENTIEL', 'value' => fn ($f) => $f->price_onsite === null ? '<span class="text-muted">—</span>' : '<span class="fw-semibold">'.e(\App\Models\Formule::money($f->price_onsite)).'</span> <span class="small text-muted">'.e($f->billing->suffix()).'</span>'],
        ],
        'statusLabels' => ['Publiée', 'Masquée'],
    ])

    {{-- Frais d'inscription : payés une fois, avec le premier paiement, au centre comme en ligne. --}}
    <div class="card mt-3">
        <div class="card-header card-header-brand"><h6 class="mb-0 text-white"><i class='bx bx-id-card me-2'></i>FRAIS D'INSCRIPTION</h6></div>
        <div class="card-body">
            <p class="text-muted small">Ajoutés une seule fois à chaque nouvelle inscription (site et centre) et payés avec le premier mois. Mettez 0 pour ne pas en demander. Une inscription déjà faite garde ses frais.</p>
            @can('formations.update')
                <form method="POST" action="{{ route('formations.fee.update') }}" class="row g-2 align-items-end">
                    @csrf
                    @method('PUT')
                    <div class="col-sm-5 col-md-4">
                        <label for="registration_fee" class="form-label fw-semibold">Montant (FCFA)</label>
                        <input type="number" min="0" step="1" class="form-control form-control-lg @error('registration_fee') is-invalid @enderror" id="registration_fee" name="registration_fee" value="{{ old('registration_fee', $fee) }}" required>
                        @error('registration_fee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-auto"><button type="submit" class="btn btn-primary btn-lg"><i class='bx bx-save me-1'></i>Enregistrer</button></div>
                </form>
            @else
                <p class="fs-5 fw-bold mb-0">{{ \App\Models\Formule::money($fee) }}</p>
            @endcan
        </div>
    </div>

@endsection
