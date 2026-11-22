<?php

/*
| Manifeste du module « attestations » (demande du centre, 2026-11-22) : réglages de l'attestation de fin de formation —
| signataire (nom, titre), lieu, signature (déposée ou tracée à l'écran) et sceau, traités pour être lisibles, avec aperçu.
| La délivrance elle-même relève de inscriptions.attestation.
*/

return [
    'label' => 'Attestations',

    'permissions' => [
        'configure' => 'Régler l\'attestation : signataire, signature, sceau',
    ],

    'reserved' => [],

    'roles' => [
        'administrateur' => ['defaults' => ['configure'], 'optional' => []],
    ],

    'navigation' => [
        ['label' => 'Attestations', 'icon' => 'bx bx-award', 'route' => 'attestations.edit', 'permission' => 'attestations.configure', 'active' => 'attestations.*', 'order' => 16],
    ],
];
