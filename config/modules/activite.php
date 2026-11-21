<?php

/*
| Manifeste du module « activite » (demande du centre, 2026-11-21) : journal d'activité de l'ÉQUIPE pour
| l'administrateur — chaque action de ses subalternes (secrétaire, gestionnaire, formateur) et les siennes, en lecture
| seule. Le superadmin garde le journal d'audit complet (audit.view, réservé).
*/

return [
    'label' => 'Journal d\'activité',

    'permissions' => [
        'view' => 'Voir les actions de ses subalternes et les siennes',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['view'], 'optional' => []],
    ],

    'navigation' => [
        ['label' => 'Journal d\'activité', 'icon' => 'bx bx-history', 'route' => 'activite.index', 'permission' => 'activite.view', 'active' => 'activite.*', 'order' => 95],
    ],
];
