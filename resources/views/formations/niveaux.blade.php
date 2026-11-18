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
            ['name' => 'sort_order', 'label' => 'Ordre d\'affichage', 'type' => 'number', 'required' => true, 'default' => ((int) $niveaux->max('sort_order')) + 10, 'help' => 'Place sur le site : le plus petit nombre s\'affiche en premier (10, puis 20, puis 30…). Déjà rempli pour mettre le nouvel élément à la fin ; pour le glisser entre 10 et 20, mettez 15.', 'col' => 'col-md-4'],
            ['name' => 'summary', 'label' => 'Résumé', 'type' => 'textarea', 'required' => true, 'max' => 300, 'help' => 'Deux lignes au plus : affiché sur la carte du niveau.'],
            ['name' => 'objectives', 'label' => 'Objectifs pédagogiques', 'type' => 'textarea', 'max' => 3000, 'help' => 'Un objectif par ligne.'],
            ['name' => 'skills', 'label' => 'Compétences visées', 'type' => 'textarea', 'max' => 3000, 'help' => 'Une compétence par ligne.'],
            ['name' => 'prerequisites', 'label' => 'Prérequis', 'type' => 'textarea', 'max' => 2000, 'help' => 'Un prérequis par ligne ; vide = aucun.'],
            ['name' => 'is_published', 'label' => 'Publié sur le site', 'type' => 'switch', 'default' => true, 'col' => 'col-md-8'],
        ],
        'columns' => [
            ['label' => 'NIVEAU', 'value' => fn ($n) => '<div class="fw-semibold">'.e($n->name).'</div><div class="small text-muted">'.e(\Illuminate\Support\Str::limit($n->summary, 110)).'</div>'],
            ['label' => 'ORDRE', 'value' => fn ($n) => e($n->sort_order)],
        ],
    ])
@endsection
