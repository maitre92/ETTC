<?php

/*
| Manifeste du module « formations » (cahier ETTC §3.2, §3.3, §5 « Gestion des formations et des tarifs ») : niveaux,
| formules (durées), tarifs par mode de formation et frais du test de niveau. Les tarifs changent à tout moment :
| `update` les modifie, chaque changement est audité. Le commercial consulte les tarifs pour renseigner les visiteurs.
*/

return [
    'label' => 'Formations et tarifs',

    'permissions' => [
        'view' => 'Voir les niveaux, les formules et les tarifs',
        'create' => 'Créer un niveau ou une formule',
        'update' => 'Modifier les niveaux, les formules, les tarifs et les frais du test de niveau',
        'delete' => 'Supprimer un niveau ou une formule',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['view', 'create', 'update', 'delete'], 'optional' => []],
        'gestionnaire' => ['defaults' => ['view', 'create', 'update'], 'optional' => ['delete']],
        'commercial' => ['defaults' => ['view'], 'optional' => ['update']],
    ],

    'navigation' => [
        ['label' => 'Formules et tarifs', 'icon' => 'bx bx-purchase-tag', 'route' => 'formations.formules.index', 'permission' => 'formations.view', 'active' => 'formations.formules.*', 'order' => 10],
        ['label' => 'Niveaux', 'icon' => 'bx bx-book-open', 'route' => 'formations.niveaux.index', 'permission' => 'formations.view', 'active' => 'formations.niveaux.*', 'order' => 11],
    ],
];
