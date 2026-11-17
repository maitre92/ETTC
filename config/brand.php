<?php

/*
|--------------------------------------------------------------------------
| Identité de l'administration
|--------------------------------------------------------------------------
|
| Le nom est affiché en logo 3D bicolore dans la sidebar et sur la page de connexion de l'administration :
| `logo.first` en bleu, `logo.second` en orange. Les coordonnées publiques de l'entreprise (téléphones, e-mail,
| slogan…) ne sont PAS ici : elles se règlent dans l'administration (Pages et paramètres, App\Support\SiteSettings),
| avec leurs valeurs par défaut dans config/site.php.
|
*/

return [

    'name' => env('APP_NAME', 'ETTC'),

    'logo' => ['first' => 'ETTC', 'second' => 'ADMIN'],

    // Mini logo affiché quand la sidebar est repliée.
    'mini' => 'ETTC',

    'tagline' => 'ENGLISH TRAINING AND TRANSLATION CENTER',

    // Mention affichée à la place du nom d'un superadmin pour tous les autres rôles (comptes invisibles).
    'system_actor' => env('BRAND_SYSTEM_ACTOR', 'Administration'),

    /*
    | Bandeau du bas de la page de connexion : les domaines gérés depuis l'administration (cahier §7).
    */
    'login_highlights' => [
        ['icon' => 'bx bx-book-open', 'label' => 'Formations'],
        ['icon' => 'bx bx-user-check', 'label' => 'Apprenants'],
        ['icon' => 'bx bx-transfer-alt', 'label' => 'Traductions'],
        ['icon' => 'bx bx-news', 'label' => 'Actualités'],
        ['icon' => 'bx bx-envelope', 'label' => 'Demandes'],
        ['icon' => 'bx bx-images', 'label' => 'Médias'],
    ],

];
