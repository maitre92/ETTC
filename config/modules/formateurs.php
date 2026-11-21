<?php

/*
| Manifeste du module « formateurs » (demande du centre, 2026-11-21) : chaque formateur a son espace — SES groupes
| seulement (Séances et groupes → formateur du groupe) : apprenants, séances (lien Google Meet), supports, annonces et
| discussion avec ses apprenants. Il ne voit ni les paiements, ni les autres groupes.
*/

return [
    'label' => 'Espace formateur',

    'permissions' => [
        'space' => 'Accéder à son espace formateur : ses groupes, ses séances, ses supports, la discussion avec ses apprenants',
    ],

    'reserved' => [],

    'roles' => [
        'formateur' => ['defaults' => ['space'], 'optional' => []],
    ],

    'navigation' => [
        ['label' => 'Mes groupes', 'icon' => 'bx bx-chalkboard', 'route' => 'formateur.index', 'permission' => 'formateurs.space', 'active' => 'formateur.*', 'order' => 1],
    ],
];
