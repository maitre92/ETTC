<?php

/*
| Manifeste du module « tests » (cahier ETTC §3.4, §5 « Gestion des tests de niveau ») : demandes et réservations,
| planification, encaissement des frais (hors commission), résultat et niveau attribué, suivi des candidats en attente.
| Une permission par geste métier, pour confier chaque tâche à la bonne personne (accueil, pédagogie, caisse).
*/

return [
    'label' => 'Tests de niveau',

    'permissions' => [
        'view' => 'Voir les tests de niveau et les candidats',
        'create' => 'Inscrire un candidat au test (au centre ou par téléphone)',
        'schedule' => 'Planifier, reporter ou annuler un test',
        'payment' => 'Encaisser les frais du test',
        'result' => 'Enregistrer le résultat et le niveau attribué',
        'configure' => 'Modifier la présentation du test sur le site',
        'delete' => 'Supprimer un test',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['view', 'create', 'schedule', 'payment', 'result', 'configure', 'delete'], 'optional' => []],
        'gestionnaire' => ['defaults' => ['view', 'create', 'schedule', 'result', 'configure'], 'optional' => ['payment', 'delete']],
        'commercial' => ['defaults' => ['view', 'create', 'schedule', 'payment'], 'optional' => ['result']],
    ],

    'navigation' => [
        ['label' => 'Tests de niveau', 'icon' => 'bx bx-target-lock', 'route' => 'tests.index', 'permission' => 'tests.view', 'active' => 'tests.*', 'order' => 12],
    ],
];
