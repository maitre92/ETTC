@extends('layouts.admin')

@section('title', 'Niveaux')

@section('content')
    @include('admin._crud', [
        'title' => 'Niveaux',
        'section' => 'Formations',
        'tabs' => 'formations._tabs',
        'heading' => 'NIVEAUX DE FORMATION',
        'icon' => 'bx bx-book-open',
        'intro' => 'Chaque niveau publié a sa fiche sur le site (objectifs, compétences visées, prérequis, formules et tarifs). Appellations et nombre de niveaux : fixés par le centre.',
        'items' => $niveaux,
        'model' => \App\Models\Niveau::class,
        'routePrefix' => 'formations.niveaux',
        'idField' => 'niveau_id',
        'deleteTitle' => 'Supprimer ce niveau ?',
        'newLabel' => 'Nouveau niveau',
        'labelOf' => fn ($n) => $n->name,
        'fields' => [
            ['name' => 'name', 'label' => 'Nom du niveau', 'required' => true, 'max' => 100, 'placeholder' => 'Débutant', 'col' => 'col-md-8'],
            ['name' => 'summary', 'label' => 'Résumé', 'type' => 'textarea', 'required' => true, 'max' => 300, 'help' => 'Deux lignes au plus : affiché sur la carte du niveau.'],
            ['name' => 'objectives', 'label' => 'Objectifs pédagogiques', 'type' => 'textarea', 'max' => 3000, 'help' => 'Un objectif par ligne.'],
            ['name' => 'skills', 'label' => 'Compétences visées', 'type' => 'textarea', 'max' => 3000, 'help' => 'Une compétence par ligne.'],
            ['name' => 'prerequisites', 'label' => 'Prérequis', 'type' => 'textarea', 'max' => 2000, 'help' => 'Un prérequis par ligne ; vide = aucun.'],
            ['name' => 'min_score', 'label' => 'Score minimal au test en ligne (%)', 'type' => 'number', 'col' => 'col-md-4', 'help' => 'Ex. 0 (débutant), 40, 70 : le candidat reçoit le plus haut niveau atteint.'],
            ['name' => 'is_published', 'label' => 'Publié sur le site', 'type' => 'switch', 'default' => true, 'col' => 'col-md-8'],
        ],
        'columns' => [
            ['label' => 'NIVEAU', 'value' => fn ($n) => '<div class="fw-semibold">'.e($n->name).'</div><div class="small text-muted">'.e(\Illuminate\Support\Str::limit($n->summary, 110)).'</div>'],
            ['label' => 'TEST EN LIGNE', 'value' => fn ($n) => $n->min_score === null ? '<span class="text-muted small">—</span>' : 'à partir de '.e($n->min_score).' %'],
        ],
    ])
@endsection
