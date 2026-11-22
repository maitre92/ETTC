<?php

/*
| Manifeste du module « remunerations » (demande du centre, 2026-11-22) : rémunération des formateurs — formation
| personnalisée 1/3 de la mensualité, formation en groupe 20 % du montant, sur les frais de formation encaissés ;
| versements par mois (fiche PDF), annulation motivée ; réglage des taux.
*/

return [
    'label' => 'Rémunération des formateurs',

    'permissions' => [
        'view' => 'Voir ce qui est dû à chaque formateur',
        'payout' => 'Enregistrer ou annuler un versement à un formateur',
        'configure' => 'Régler les taux (formation personnalisée, en groupe)',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['view', 'payout', 'configure'], 'optional' => []],
        'commercial' => ['defaults' => [], 'optional' => ['view', 'payout']],
    ],

    'navigation' => [
        ['label' => 'Rémunération des formateurs', 'icon' => 'bx bx-user-voice', 'route' => 'remunerations.index', 'permission' => 'remunerations.view', 'active' => 'remunerations.*', 'order' => 22],
    ],
];
