@extends('layouts.admin')

@section('title', 'Promotions')

@section('content')
    @include('admin._crud', [
        'title' => 'Promotions',
        'section' => 'Formations',
        'tabs' => 'formations._tabs',
        'heading' => 'PROMOTIONS',
        'icon' => 'bxs-offer bx',
        'intro' => 'Une promotion réduit les frais de formation (jamais les frais d\'inscription). Sans code : elle s\'applique toute seule pendant sa période. Avec un code : l\'apprenant le saisit à l\'inscription (ou l\'accueil, au centre). Les promotions ne se cumulent pas : la plus avantageuse l\'emporte. Une inscription garde la réduction obtenue.',
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
            ['name' => 'code', 'label' => 'Code promo (facultatif)', 'max' => 40, 'placeholder' => 'RENTREE2027', 'col' => 'col-md-4', 'help' => 'Vide : automatique.'],
            ['name' => 'kind', 'label' => 'Type de réduction', 'type' => 'select', 'required' => true, 'options' => \App\Models\Promotion::KINDS, 'default' => 'percent', 'col' => 'col-md-6'],
            ['name' => 'value', 'label' => 'Réduction', 'type' => 'number', 'required' => true, 'placeholder' => '20', 'col' => 'col-md-6', 'help' => '20 = -20 % ; ou un montant en FCFA retiré du total de la formation.'],
            ['name' => 'formule_id', 'label' => 'Formule', 'type' => 'select', 'options' => $formules->pluck('name', 'id')->all(), 'col' => 'col-md-6', 'help' => '« Aucun » : toutes les formules.'],
            ['name' => 'mode', 'label' => 'Mode', 'type' => 'select', 'options' => collect(\App\Enums\FormationMode::cases())->mapWithKeys(fn ($m) => [$m->value => $m->label()])->all(), 'col' => 'col-md-6', 'help' => '« Aucun » : en ligne et en présentiel.'],
            ['name' => 'starts_on', 'label' => 'Du (facultatif)', 'type' => 'date', 'col' => 'col-md-4'],
            ['name' => 'ends_on', 'label' => 'Au (facultatif)', 'type' => 'date', 'col' => 'col-md-4'],
            ['name' => 'max_uses', 'label' => 'Nombre d\'inscriptions max.', 'type' => 'number', 'col' => 'col-md-4', 'help' => 'Vide : sans limite.'],
            ['name' => 'is_active', 'label' => 'Active', 'type' => 'switch', 'default' => true],
        ],
        'columns' => [
            ['label' => 'PROMOTION', 'value' => fn ($p) => '<div class="fw-semibold">'.e($p->name).'</div><div class="small text-muted">'.e($p->targetLabel()).'</div>'],
            ['label' => 'RÉDUCTION', 'value' => fn ($p) => '<span class="badge bg-success-subtle fs-6">'.e($p->badge()).'</span>'],
            ['label' => 'CODE', 'value' => fn ($p) => $p->code ? '<code class="fs-6">'.e($p->code).'</code>' : '<span class="small text-muted">Automatique</span>'],
            ['label' => 'UTILISÉE', 'value' => fn ($p) => e($p->uses_count).($p->max_uses ? ' / '.e($p->max_uses) : '').' fois'.($p->is_active && ! $p->isRunning() ? '<div class="small text-warning">Hors période ou épuisée</div>' : '')],
        ],
    ])
@endsection
