<?php

/*
| Manifeste du module « finances » (cahier ETTC §5 « Gestion financière », « Statistiques », §8) : recettes par période,
| frais de test et de formation séparés, part du centre, commission du développeur, versements, rapports.
*/

return [
    'label' => 'Finances',

    'permissions' => [
        'view' => 'Voir les recettes, la commission et les statistiques',
        'export' => 'Télécharger les rapports financiers (PDF, Excel)',
        'payout' => 'Enregistrer un versement de la commission',
        'configure' => 'Régler le taux et le périmètre de la commission (fixés par le contrat : superadmin)',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['view', 'export', 'payout'], 'optional' => []],
        'commercial' => ['defaults' => [], 'optional' => ['view', 'export']],
        'gestionnaire' => ['defaults' => [], 'optional' => ['view']],
    ],

    'navigation' => [
        ['label' => 'Finances et commission', 'icon' => 'bx bx-line-chart', 'route' => 'finances.index', 'permission' => 'finances.view', 'active' => 'finances.*', 'order' => 21],
    ],
];
