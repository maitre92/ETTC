<?php

/*
| Manifeste du module « paiements » (cahier ETTC §3.8, §5 « Gestion financière ») : en ligne par mobile money (déclaré
| par l'apprenant, vérifié par l'équipe), en présentiel au centre (enregistré par l'équipe), reçus numérotés, impayés,
| annulations et remboursements motivés. Aucun paiement ne se supprime.
*/

return [
    'label' => 'Paiements',

    'permissions' => [
        'view' => 'Voir les paiements, les reçus et les impayés',
        'record' => 'Enregistrer un paiement reçu au centre',
        'verify' => 'Confirmer ou rejeter un paiement mobile money déclaré par un apprenant',
        'refund' => 'Annuler ou rembourser un paiement confirmé',
        'configure' => 'Régler les numéros mobile money et les consignes de paiement',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['view', 'record', 'verify', 'refund', 'configure'], 'optional' => []],
        'commercial' => ['defaults' => ['view', 'record', 'verify'], 'optional' => ['refund']],
        'gestionnaire' => ['defaults' => [], 'optional' => ['view']],
    ],

    'navigation' => [
        ['label' => 'Paiements', 'icon' => 'bx bx-wallet', 'route' => 'paiements.index', 'permission' => 'paiements.view', 'active' => 'paiements.*', 'order' => 20],
    ],
];
