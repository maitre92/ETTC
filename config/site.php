<?php

/*
|--------------------------------------------------------------------------
| Site public d'ETTC
|--------------------------------------------------------------------------
|
| `defaults` : valeur de chaque paramètre du site tant qu'il n'a pas été modifié dans l'administration
| (Pages et paramètres, App\Support\SiteSettings). Les textes marqués « À COMPLÉTER » attendent les informations
| du centre (cahier ETTC §9.1, §12). Ce sont des contenus publics destinés au site, pas des secrets.
|
*/

return [

    // Préfixe d'URL de l'administration (cahier §7 « URL dédiée »).
    'admin_path' => env('ADMIN_PATH', 'admin'),

    // Tunnel (ngrok) ou proxy HTTPS devant le site : « * » ou adresses séparées par des virgules ; vide = aucun.
    // Sans cela, les liens des CSS / JS / images sortent en http et le navigateur les bloque (contenu mixte).
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Destinataire des e-mails de notification des demandes (cahier §3.1, §6.4) ; vide = adresse « contact.email ».
    'notification_email' => env('SITE_NOTIFICATION_EMAIL'),

    'defaults' => [
        // Centre (cahier ETTC, page de garde)
        'company.name' => 'English Training and Translation Center',
        'company.short_name' => 'ETTC',
        'company.slogan' => 'Formation • Traduction • Activités linguistiques',
        'company.tagline' => 'Centre de formation en anglais et de traduction professionnelle à Ségou',

        // Coordonnées publiques du centre, reprises de la maquette validée (2026-11-17). WhatsApp = même numéro : À VALIDER.
        'contact.phone_1' => '+223 75 22 91 25',
        'contact.phone_2' => '',
        'contact.whatsapp' => '75 22 91 25',
        'contact.email' => 'contact@ettc-mali.org',
        'contact.city' => 'Ségou, Mali',
        'contact.address' => '',
        'contact.hours' => '',
        'contact.map_url' => '',

        // Réseaux sociaux (cahier §3.1) : vides tant que le centre ne les a pas fournis.
        'social.facebook' => '',
        'social.linkedin' => '',
        'social.instagram' => '',
        'social.tiktok' => '',
        'social.youtube' => '',

        // Accueil : textes et tarifs de la maquette validée (English Training/maquette.jpeg, 2026-11-17).
        // Les formules et leurs tarifs viennent du module Formations (tables `formules`), modifiables à tout moment.
        'home.hero_title' => "Apprenez l'anglais avec\nun encadrement de qualité\u{00A0}!",
        'home.hero_subtitle' => "Formations par niveau • En présentiel ou en ligne\nTraduction professionnelle • Excursion linguistique",
        'home.hero_text' => 'English Training and Translation Center (ETTC) : formations d\'anglais par niveau en présentiel ou en ligne avec des formateurs, test de niveau, traduction professionnelle et excursion linguistique au Ghana.',
        // Frais du test de niveau en FCFA (cahier §3.4, hors commission) : modifiables dans Formations → Formules et tarifs.
        'tarifs.test_niveau' => '5000',
        // Présentation du test de niveau (Tests de niveau → Présentation) : format, durée, déroulement et communication
        // des résultats À DÉFINIR par le centre (cahier §3.4, §12).
        'test.presentation' => "## Pourquoi un test de niveau ?\n\nChaque nouvel apprenant passe un test avant de commencer : il évalue ses connaissances en anglais et permet de l'orienter vers le niveau qui lui correspond (débutant, intermédiaire ou avancé).\n\n## Déroulement\n\n- Le test se passe **au centre** ou **à distance**.\n- À COMPLÉTER par le centre : format (écrit, oral), durée, matériel à prévoir.\n- Le résultat et le niveau attribué vous sont communiqués par le centre, avec la formule conseillée.",
        'home.why_title' => "Pourquoi nous choisir\u{00A0}?",
        // Une ligne par atout : icône Boxicons | texte
        'home.values' => "bxs-award|Formateurs expérimentés\nbx-laptop|Méthodes modernes et interactives\nbxs-check-shield|Suivi et encadrement personnalisés\nbxs-group|Attestation de formation reconnue",
        // Images (identifiant d'une image de la bibliothèque) ; vide = visuel fourni avec le site.
        'site.logo' => '',
        'home.hero_image' => '',

        // À propos (cahier §3.1 : historique, mission, valeurs) : textes officiels à fournir par le centre.
        'about.presentation' => "English Training and Translation Center (ETTC) est un centre de formation linguistique et de traduction basé à Ségou (Mali). Il propose des formations d'anglais par niveau, en présentiel ou en ligne, des prestations de traduction professionnelle et des activités linguistiques comme l'excursion au Ghana.",
        'about.mission' => 'À COMPLÉTER — mission du centre.',
        'about.vision' => 'À COMPLÉTER — vision du centre.',
        'about.values' => 'À COMPLÉTER — valeurs du centre',
        'about.method' => "Passer le test de niveau\nChoisir sa formule (niveau, mode, durée)\nSuivre des séances interactives avec un formateur\nPratiquer et progresser",

        // Pied de page
        'footer.text' => 'Formations d\'anglais par niveau, test de niveau, traduction professionnelle et activités linguistiques.',

        // Pages légales (Markdown) : à adapter à l'identité juridique du centre.
        'legal.mentions' => "## Éditeur du site\n\nEnglish Training and Translation Center (ETTC), Ségou, Mali.\n\nÀ COMPLÉTER : forme juridique, numéro d'immatriculation, adresse, directeur de la publication.\n\n## Hébergement\n\nÀ COMPLÉTER : nom et adresse de l'hébergeur.\n\n## Propriété intellectuelle\n\nLes contenus de ce site (textes, images, logos) sont la propriété d'ETTC ou de leurs auteurs. Toute reproduction sans autorisation est interdite.",
        'legal.privacy' => "## Données collectées\n\nLes formulaires du site collectent uniquement les informations nécessaires pour vous répondre et organiser votre formation : nom, coordonnées, message et, le cas échéant, le document que vous envoyez.\n\n## Utilisation\n\nCes données servent exclusivement à traiter votre demande ou votre inscription et à vous recontacter. Elles ne sont ni vendues ni cédées à des tiers. Les documents transmis pour traduction restent confidentiels.\n\n## Conservation\n\nÀ COMPLÉTER : durée de conservation des données.\n\n## Vos droits\n\nVous pouvez demander l'accès, la rectification ou la suppression de vos données en écrivant à l'adresse e-mail indiquée sur la page Contact.",

        // Référencement
        'seo.default_description' => 'English Training and Translation Center (ETTC), Ségou : formations d\'anglais par niveau en présentiel ou en ligne, test de niveau, traduction professionnelle et excursion au Ghana.',
        'seo.google_site_verification' => '',

        // Anti-spam des formulaires (App\Support\SpamGuard) : un domaine ou une expression par ligne.
        'antispam.blocked' => '',
    ],

];
