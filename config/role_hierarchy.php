<?php

/*
|--------------------------------------------------------------------------
| Hiérarchie des rôles (CLAUDE.md §4.3, cahier §7.1)
|--------------------------------------------------------------------------
|
| Source unique des niveaux et du plafond de rôle. Les Policies passent par
| App\Enums\RoleName, jamais par des comparaisons de chaînes dispersées.
| Personne n'agit sur un compte de niveau supérieur ou égal au sien, sauf le
| superadmin.
|
*/

return [

    'levels' => [
        'superadmin' => 100,
        'administrateur' => 80,
        // Secrétaire (`commercial`) juste après l'administrateur (demande du centre, 2026-11-18).
        'commercial' => 60,
        'gestionnaire' => 50,
        'formateur' => 30,
    ],

    // Plafond de rôle : rôles qu'un rôle peut créer et gérer.
    'manages' => [
        'superadmin' => ['superadmin', 'administrateur', 'gestionnaire', 'commercial', 'formateur'],
        'administrateur' => ['gestionnaire', 'commercial', 'formateur'],
        'gestionnaire' => [],
        'commercial' => [],
        'formateur' => [],
    ],

];
