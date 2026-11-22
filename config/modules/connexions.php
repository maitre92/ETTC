<?php

/*
| Manifeste du module « connexions » (demande du centre, 2026-11-22) : qui est en ligne, date et heure de connexion et
| dernière activité — équipe (l'administrateur : ses subalternes et lui-même ; le superadmin : tous) et apprenants.
*/

return [
    'label' => 'Connexions',

    'permissions' => [
        'view' => 'Voir qui est en ligne (équipe et apprenants), l\'heure de connexion et la dernière activité',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['view'], 'optional' => []],
    ],

    'navigation' => [
        ['label' => 'Qui est en ligne', 'icon' => 'bx bx-wifi', 'route' => 'connexions.index', 'permission' => 'connexions.view', 'active' => 'connexions.*', 'order' => 94],
    ],
];
