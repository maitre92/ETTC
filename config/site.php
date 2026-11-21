<?php

/*
|--------------------------------------------------------------------------
| Site public d'ETTC
|--------------------------------------------------------------------------
|
| `defaults` : valeur de chaque paramètre du site tant qu'il n'a pas été modifié dans l'administration
| (Pages et paramètres, App\Support\SiteSettings). Textes de départ à remplacer par ceux du centre
| (cahier ETTC §9.1, §12) ; aucune mention « à compléter » ne doit apparaître sur le site public. Ce sont des contenus publics destinés au site, pas des secrets.
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
        // Présentation du test de niveau (Tests de niveau → Présentation sur le site), modifiable par le centre.
        // Le test est GRATUIT (décision du centre, 2026-11-17) : seule l'inscription à une formation est payante.
        'test.presentation' => "## Pourquoi un test de niveau ?\n\nChaque nouvel apprenant passe un test avant de commencer : il évalue ses connaissances en anglais et permet de l'orienter vers le niveau qui lui correspond (débutant, intermédiaire ou avancé).\n\n## Déroulement\n\n- Le test est **gratuit**.\n- Il se passe **au centre** ou **à distance**, selon votre choix.\n- Le centre vous communique ensuite votre niveau et la formule conseillée.",
        // Services du centre (Pages et paramètres → Traduction / Formation personnalisée / Excursion au Ghana).
        'traduction.presentation' => "## Nos prestations\n\nLe centre traduit vos documents du français vers l'anglais et de l'anglais vers le français : documents administratifs, académiques, commerciaux, techniques et correspondances.\n\n## Comment ça se passe ?\n\n1. Vous décrivez votre besoin et joignez le document.\n2. Nous l'étudions et vous envoyons une estimation du prix et du délai.\n3. Vous acceptez la proposition d'un clic, puis nous traduisons.\n\n## Confidentialité\n\nVos documents restent privés : ils ne sont consultés que par l'équipe chargée de la traduction.",
        'personnalisee.presentation' => "## Une formation construite pour vous\n\nVos objectifs (études, travail, voyage, entretien…), votre niveau, votre rythme : nous adaptons le contenu et l'accompagnement à votre besoin.\n\n## Comment ça se passe ?\n\n1. Vous décrivez vos objectifs.\n2. Nous échangeons avec vous lors d'un entretien.\n3. Vous recevez une proposition chiffrée et l'acceptez d'un clic.\n4. Votre formation commence.",
        'excursion.presentation' => "## Pratiquer l'anglais en situation réelle\n\nL'excursion au Ghana est une immersion linguistique et culturelle : parler anglais au quotidien, découvrir un pays anglophone voisin et sa culture, en groupe et accompagné par le centre.",
        'excursion.dates' => '',
        'excursion.price' => '',
        'excursion.included' => "Accompagnement par le centre\nActivités de pratique de l'anglais\nVisites culturelles",
        'excursion.program' => 'Visites, échanges avec des locuteurs anglophones, activités de groupe et découverte culturelle. Le programme détaillé est communiqué aux participants.',
        'excursion.conditions' => "Les conditions de participation, les documents de voyage requis et les modalités d'annulation sont communiqués avec la confirmation de réservation.",
        'excursion.booking' => 'Remplissez le formulaire : le centre vous recontacte pour confirmer votre place et vous indiquer les modalités de paiement.',

        // Moyens de paiement (Paiements → Moyens de paiement, décision du centre 2026-11-18) : en ligne par mobile money,
        // en présentiel au centre. Numéros marchands vides = moyen non proposé à l'apprenant (à fournir par le centre).
        // Frais d'inscription (demande du centre, 2026-11-21) : payés une fois, avec la première échéance ; 0 = aucun.
        'formation.registration_fee' => '5000',
        // Commission du développeur (cahier §8, Finances → Réglages) : taux en %, formations personnalisées incluses ou non.
        'finance.commission_rate' => '20',
        'finance.include_personnalisee' => '0',
        'paiement.orange_money' => '',
        'paiement.wave' => '',
        'paiement.moov_money' => '',
        // Qui reçoit les paiements à vérifier (vide : l'adresse des alertes, SITE_NOTIFICATION_EMAIL ou e-mail de contact).
        'paiement.notify_email' => '',
        // QR marchands (image fournie par l'opérateur, à scanner avec son application) : identifiant d'une image.
        'paiement.orange_money_qr' => '',
        'paiement.wave_qr' => '',
        'paiement.moov_money_qr' => '',
        'paiement.instructions' => 'Envoyez le montant au numéro du centre, puis indiquez ici la référence de la transaction (reçue par SMS). Le centre vérifie le paiement et vous délivre un reçu.',
        'home.why_title' => "Pourquoi nous choisir\u{00A0}?",
        // Une ligne par atout : icône Boxicons | texte
        'home.values' => "bxs-award|Formateurs expérimentés\nbx-laptop|Méthodes modernes et interactives\nbxs-check-shield|Suivi et encadrement personnalisés\nbxs-group|Attestation de formation reconnue",
        // Images (identifiant d'une image de la bibliothèque) ; vide = visuel fourni avec le site.
        'site.logo' => '',
        'home.hero_image' => '',

        // À propos (cahier §1, §2, §3.1) : textes de départ tirés du cahier, à remplacer par les textes officiels du
        // centre dans Pages et paramètres → À propos. Jamais de mention « À compléter » visible du public.
        'about.presentation' => "English Training and Translation Center (ETTC) est un centre de formation linguistique et de traduction basé à Ségou (Mali). Il propose des formations d'anglais par niveau, en présentiel ou en ligne, des prestations de traduction professionnelle et des activités linguistiques comme l'excursion au Ghana.",
        'about.mission' => 'Rendre l\'anglais accessible à tous ceux qui en ont besoin pour leurs études, leur emploi, leurs affaires et leurs échanges internationaux, grâce à des formations pratiques et encadrées.',
        'about.vision' => 'Être à Ségou et au-delà une référence de la formation en anglais et de la traduction professionnelle, en présentiel comme à distance.',
        'about.values' => "Pédagogie pratique et interactive\nSuivi personnalisé de chaque apprenant\nQualité et rigueur des traductions\nOuverture culturelle",
        'about.method' => "Passer le test de niveau\nChoisir sa formule (niveau, mode, durée)\nSuivre des séances interactives avec un formateur\nPratiquer et progresser",

        // Pied de page
        'footer.text' => 'Formations d\'anglais par niveau, test de niveau, traduction professionnelle et activités linguistiques.',

        // Pages légales (Markdown) : à adapter à l'identité juridique du centre.
        // Forme juridique, immatriculation, directeur de la publication et hébergeur : à ajouter dès qu'ils sont connus.
        'legal.mentions' => "## Éditeur du site\n\nEnglish Training and Translation Center (ETTC), Ségou, Mali. Pour nous joindre : voir la page Contact.\n\n## Conception et réalisation\n\nSite conçu et développé par Computer Service BARRY. Tous droits réservés.\n\n## Propriété intellectuelle\n\nLes contenus de ce site (textes, images, logos) sont protégés. Toute reproduction sans autorisation est interdite.",
        'legal.privacy' => "## Données collectées\n\nLes formulaires du site collectent uniquement les informations nécessaires pour vous répondre et organiser votre formation : nom, coordonnées, message et, le cas échéant, le document que vous envoyez.\n\n## Utilisation\n\nCes données servent exclusivement à traiter votre demande ou votre inscription et à vous recontacter. Elles ne sont ni vendues ni cédées à des tiers. Les documents transmis pour traduction restent confidentiels.\n\n## Conservation\n\nVos données sont conservées le temps nécessaire au traitement de votre demande et au suivi de votre formation.\n\n## Vos droits\n\nVous pouvez demander l'accès, la rectification ou la suppression de vos données en écrivant à l'adresse e-mail indiquée sur la page Contact.",

        // Référencement
        'seo.default_description' => 'English Training and Translation Center (ETTC), Ségou : formations d\'anglais par niveau en présentiel ou en ligne, test de niveau, traduction professionnelle et excursion au Ghana.',
        'seo.google_site_verification' => '',

        // Anti-spam des formulaires (App\Support\SpamGuard) : un domaine ou une expression par ligne.
        'antispam.blocked' => '',
    ],

];
