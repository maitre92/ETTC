<?php

/*
| Manifeste du module « caisse » (demande du centre, 2026-11-21) : journal des entrées et sorties, soldes par moyen
| (espèces, mobile money, banque), revenus divers, dépenses et transferts saisis à la main, rapports. Les paiements des
| apprenants y entrent automatiquement. Un mouvement ne se supprime jamais : il s'annule avec un motif.
*/

return [
    'label' => 'Caisse',

    'permissions' => [
        'view' => 'Voir la caisse : journal des entrées et sorties, soldes',
        'record' => 'Saisir un revenu, une dépense ou un transfert',
        'cancel' => 'Annuler un mouvement de caisse (avec un motif)',
        'export' => 'Télécharger le journal de caisse (PDF, Excel)',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['view', 'record', 'cancel', 'export'], 'optional' => []],
        'commercial' => ['defaults' => ['view', 'record'], 'optional' => ['cancel', 'export']],
        'gestionnaire' => ['defaults' => [], 'optional' => ['view']],
    ],

    'navigation' => [
        ['label' => 'Caisse', 'icon' => 'bx bx-transfer', 'route' => 'caisse.index', 'permission' => 'caisse.view', 'active' => 'caisse.*', 'order' => 20],
    ],
];
