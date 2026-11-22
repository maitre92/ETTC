@extends('layouts.admin')

@section('title', 'Promotions')

@section('content')
    @include('admin._crud', [
        'title' => 'Promotions',
        'section' => 'Formations',
        'tabs' => 'formations._tabs',
        'heading' => 'PROMOTIONS',
        'icon' => 'bxs-offer bx',
        'intro' => 'Une promotion réduit les frais de formation (jamais les frais d\'inscription). Chaque promotion a un code (le vôtre, ou généré à partir du nom) affiché sur le bandeau et le diaporama du site : la réduction s\'applique quand l\'apprenant le saisit à l\'inscription (déjà rempli s\'il clique sur « J\'en profite »), ou l\'accueil au centre. Un code qui ne concerne pas la formule choisie est refusé avec un message clair. Une inscription garde la réduction obtenue. Les promotions en cours défilent sur le site (bandeau et diaporama), avec leur fiche si vous en ajoutez une.',
        'items' => $promotions,
        'model' => \App\Models\Promotion::class,
        'routePrefix' => 'formations.promotions',
        'idField' => 'promotion_id',
        'deleteTitle' => 'Supprimer cette promotion ?',
        'newLabel' => 'Nouvelle promotion',
        'labelOf' => fn ($p) => $p->name,
        'statusField' => 'is_active',
        'statusLabels' => ['Active', 'Arrêtée'],
        'fields' => [
            ['name' => 'name', 'label' => 'Nom de la promotion', 'required' => true, 'max' => 150, 'placeholder' => 'Promotion de la rentrée', 'col' => 'col-md-8', 'help' => 'Affiché à l\'apprenant.'],
            ['name' => 'code', 'label' => 'Code promo', 'max' => 40, 'placeholder' => 'RENTREE2027', 'col' => 'col-md-4', 'help' => 'Vide : généré à partir du nom (ex. FINANNEE26).'],
            ['name' => 'kind', 'label' => 'Type de réduction', 'type' => 'select', 'required' => true, 'options' => \App\Models\Promotion::KINDS, 'default' => 'percent', 'col' => 'col-md-6'],
            ['name' => 'value', 'label' => 'Réduction', 'type' => 'number', 'required' => true, 'placeholder' => '20', 'col' => 'col-md-6', 'help' => '20 = -20 % ; ou un montant en FCFA retiré du total de la formation.'],
            ['name' => 'formule_id', 'label' => 'Formule', 'type' => 'select', 'options' => $formules->pluck('name', 'id')->all(), 'col' => 'col-md-6', 'help' => '« Aucun » : toutes les formules.'],
            ['name' => 'mode', 'label' => 'Mode', 'type' => 'select', 'options' => collect(\App\Enums\FormationMode::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all(), 'col' => 'col-md-6', 'help' => '« Aucun » : en ligne et en présentiel.'],
            ['name' => 'starts_on', 'label' => 'Du (facultatif)', 'type' => 'date', 'col' => 'col-md-4'],
            ['name' => 'ends_on', 'label' => 'Au (facultatif)', 'type' => 'date', 'col' => 'col-md-4'],
            ['name' => 'max_uses', 'label' => 'Nombre d\'inscriptions max.', 'type' => 'number', 'col' => 'col-md-4', 'help' => 'Vide : sans limite.'],
            ['name' => 'description', 'label' => 'Phrase d\'accroche (facultatif)', 'type' => 'textarea', 'max' => 300, 'placeholder' => 'Inscrivez-vous avant la rentrée et économisez sur votre formation !', 'help' => 'Affichée sur le site, dans le diaporama des promotions.'],
            ['name' => 'affiche', 'label' => 'Fiche (affiche) — facultatif', 'type' => 'image', 'relation' => 'media', 'help' => 'Image de la promotion (JPEG, PNG ou WebP, 5 Mo) : montrée dans le diaporama, agrandie au toucher.'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'switch', 'default' => true, 'col' => 'col-md-6'],
            ['name' => 'show_on_site', 'label' => 'Afficher sur le site (bandeau et diaporama)', 'type' => 'switch', 'default' => true, 'col' => 'col-md-6'],
        ],
        'columns' => [
            ['label' => 'PROMOTION', 'value' => fn ($p) => '<div class="d-flex align-items-center gap-2">'.($p->media ? '<img src="'.e($p->media->thumbUrl()).'" alt="" style="height:40px;border-radius:4px">' : '').'<div><div class="fw-semibold">'.e($p->name).'</div><div class="small text-muted">'.e($p->targetLabel()).($p->show_on_site ? '' : ' · <span class="text-warning">non affichée sur le site</span>').'</div></div></div>'],
            ['label' => 'RÉDUCTION', 'value' => fn ($p) => '<span class="badge bg-success-subtle fs-6">'.e($p->badge()).'</span>'],
            ['label' => 'CODE', 'value' => fn ($p) => '<code class="fs-6">'.e($p->code).'</code>'],
            ['label' => 'UTILISÉE', 'value' => fn ($p) => e($p->uses_count).($p->max_uses ? ' / '.e($p->max_uses) : '').' fois'.($p->isUpcoming() ? '<div class="small text-info">À venir : annoncée « Bientôt » sur le site</div>' : ($p->is_active && ! $p->isRunning() ? '<div class="small text-warning">Terminée ou épuisée</div>' : ''))],
        ],
    ])
@endsection
