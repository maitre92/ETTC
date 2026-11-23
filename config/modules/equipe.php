<?php

/*
| Manifeste du module « equipe » (demande du centre, 2026-11-23) : l'administrateur donne ou retire des permissions à
| SES subalternes (secrétaire, gestionnaire, formateurs) — seulement des droits qu'il a lui-même, jamais un droit
| réservé au superadmin. Les écrans Rôles / Permissions / Attribution du superadmin restent inchangés.
*/

return [
    'label' => 'Permissions de l\'équipe',

    'permissions' => [
        'permissions' => 'Donner ou retirer des permissions à ses subalternes (parmi ses propres droits)',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['permissions'], 'optional' => []],
    ],

    'navigation' => [
        ['label' => 'Permissions de l\'équipe', 'icon' => 'bx bx-key', 'route' => 'equipe.permissions', 'permission' => 'equipe.permissions', 'active' => 'equipe.*', 'order' => 93],
    ],
];
