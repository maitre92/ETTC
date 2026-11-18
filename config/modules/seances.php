<?php

/*
| Manifeste du module « seances » (cahier ETTC §3.6, §5 « Gestion des séances à distance ») : groupes d'apprenants,
| programmation des séances (une ou une série), liens Google Meet, supports pédagogiques.
*/

return [
    'label' => 'Séances',

    'permissions' => [
        'view' => 'Voir les groupes, les séances et les supports',
        'manage' => 'Créer les groupes, y placer les apprenants, programmer et annuler les séances',
        'supports' => 'Ajouter ou retirer des supports pédagogiques',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['view', 'manage', 'supports'], 'optional' => []],
        'gestionnaire' => ['defaults' => ['view', 'manage', 'supports'], 'optional' => []],
        'commercial' => ['defaults' => ['view'], 'optional' => ['manage', 'supports']],
    ],

    'navigation' => [
        ['label' => 'Séances et groupes', 'icon' => 'bx bx-calendar', 'route' => 'groupes.index', 'permission' => 'seances.view', 'active' => 'groupes.*', 'order' => 14],
    ],
];
