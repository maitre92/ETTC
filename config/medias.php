<?php

/*
|--------------------------------------------------------------------------
| Bibliothèque de médias (cahier §7, §9, §10)
|--------------------------------------------------------------------------
|
| Images seulement : type réel vérifié (pas seulement l'extension), taille et dimensions limitées, nom de stockage
| généré, image redimensionnée (largeur max.) et vignette ; les métadonnées (EXIF, position GPS) disparaissent au
| ré-encodage.
|
*/

return [

    'mimes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'],

    'max_kilobytes' => (int) env('MEDIA_MAX_KB', 5120),

    // Au-delà, l'image est refusée (protection mémoire du serveur pendant le traitement).
    'max_dimension' => 7000,

    'max_width' => 1600,

    'thumb_width' => 480,

    'quality' => 82,

    // Dossiers proposés pour ranger la bibliothèque (cahier §7 « organiser les images ») ; saisie libre aussi acceptée.
    'folders' => ['Formations', 'Actualités', 'Témoignages', 'Excursions', 'Site'],

    // Colonnes des contenus qui désignent une image : [table, colonne]. Une image utilisée ne se supprime pas.
    'references' => [
        ['articles', 'cover_media_id'], ['articles', 'seo_media_id'],
        ['categories', 'media_id'],
        ['temoignages', 'photo_media_id'],
    ],

];
