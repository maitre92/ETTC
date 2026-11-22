<?php

/*
| Manifeste du module « inscriptions » (cahier ETTC §3.5, §4, §5 « Gestion des apprenants et des inscriptions ») :
| consulter, rechercher et filtrer les inscriptions, inscrire au centre, valider les dossiers, suivre les inscriptions en
| attente, organiser par niveau, formule ou session. Le tarif est figé à l'inscription (calcul de la commission, §8).
*/

return [
    'label' => 'Inscriptions',

    'permissions' => [
        'view' => 'Voir les inscriptions et les dossiers des apprenants',
        'create' => 'Inscrire un apprenant au centre (ou à la suite de son test de niveau)',
        'validate' => 'Valider un dossier, démarrer ou terminer une formation',
        'organize' => 'Affecter une session et annoter un dossier',
        'cancel' => 'Annuler une inscription',
        'attestation' => 'Délivrer l\'attestation de fin de formation (et un duplicata)',
        'delete' => 'Supprimer une inscription',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['view', 'create', 'validate', 'organize', 'cancel', 'delete', 'attestation'], 'optional' => []],
        'gestionnaire' => ['defaults' => ['view', 'create', 'validate', 'organize'], 'optional' => ['cancel', 'delete', 'attestation']],
        'commercial' => ['defaults' => ['view', 'create', 'validate', 'organize', 'cancel'], 'optional' => ['attestation']],
    ],

    'navigation' => [
        ['label' => 'Inscriptions', 'icon' => 'bx bx-id-card', 'route' => 'inscriptions.index', 'permission' => 'inscriptions.view', 'active' => 'inscriptions.*', 'order' => 13],
    ],
];
