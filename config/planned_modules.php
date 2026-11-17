<?php

/*
|--------------------------------------------------------------------------
| Modules prévus, NON implémentés (D25)
|--------------------------------------------------------------------------
|
| Modules du cahier des charges ETTC (version 2.1) encore à construire. Chacun disparaît de cette liste (et du menu
| « Modules à venir ») dès que son manifeste config/modules/<clé>.php existe : la clé ici doit donc être celle du
| futur manifeste.
|
| `roles` : rôles qui verront ce module (superadmin et administrateur voient tout). `cahier` : où le cahier en
| parle. `open` : décisions à prendre avec le centre avant de le construire (cahier §12).
|
*/

return [
    'formations' => [
        'label' => 'Formations et tarifs', 'icon' => 'bx bx-book-open', 'order' => 100, 'roles' => ['gestionnaire'],
        'summary' => 'Catalogue organisé par niveaux : objectifs, compétences visées, prérequis, deux options de durée (2 ou 3 mois) et tarifs mensuels par mode (en ligne, en présentiel), modifiables depuis l\'administration.',
        'cahier' => '§3.2, §3.3, §5 « Gestion des formations et des tarifs »',
        'open' => ['Appellations exactes et nombre définitif des niveaux.', 'Confirmation que les tarifs sont exprimés par mois (en ligne : 100 000 / 50 000 FCFA ; présentiel : 50 000 / 20 000 FCFA).'],
    ],
    'tests' => [
        'label' => 'Tests de niveau', 'icon' => 'bx bx-target-lock', 'order' => 110, 'roles' => ['gestionnaire', 'commercial'],
        'summary' => 'Présentation du test, demande ou réservation, paiement des frais (5 000 FCFA, hors commission), résultat et niveau attribué, orientation vers la formule adaptée.',
        'cahier' => '§3.4, §5 « Gestion des tests de niveau »',
        'open' => ['Contenu, format, durée et mode d\'administration du test.', 'Modalités de communication des résultats.'],
    ],
    'inscriptions' => [
        'label' => 'Apprenants et inscriptions', 'icon' => 'bx bx-user-check', 'order' => 120, 'roles' => ['gestionnaire', 'commercial'],
        'summary' => 'Inscription en ligne (niveau, mode, durée, informations personnelles, mode de paiement, récapitulatif) ou au centre ; validation des dossiers, suivi des inscriptions en attente, organisation par niveau, formule ou session.',
        'cahier' => '§3.5, §4, §5 « Gestion des apprenants et des inscriptions »',
        'open' => ['Informations exactes à demander à l\'inscription.', 'Notion de session ou de groupe.'],
    ],
    'paiements' => [
        'label' => 'Paiements et reçus', 'icon' => 'bx bx-wallet', 'order' => 130, 'roles' => ['commercial'],
        'summary' => 'Paiement en ligne (Orange Money, Wave, carte) confirmé par notification serveur, paiement au centre enregistré par un utilisateur autorisé, reçu numérique numéroté, frais de test et de formation séparés.',
        'cahier' => '§3.8',
        'open' => ['Moyens de paiement réellement disponibles et comptes marchands.', 'Règles d\'annulation et de remboursement.'],
    ],
    'seances' => [
        'label' => 'Séances à distance', 'icon' => 'bx bx-video', 'order' => 140, 'roles' => ['gestionnaire'],
        'summary' => 'Programmation des séances (apprenant ou groupe), lien Google Meet communiqué aux apprenants concernés, supports pédagogiques complémentaires (vidéos, audios, documents).',
        'cahier' => '§3.6, §5 « Gestion des séances à distance »',
        'open' => ['Modalités de programmation des cours et de transmission des liens Google Meet.'],
    ],
    'formations_personnalisees' => [
        'label' => 'Formations personnalisées', 'icon' => 'bx bx-customize', 'order' => 150, 'roles' => ['commercial'],
        'summary' => 'Demande avec description des objectifs, entretien proposé par le centre, proposition et tarif enregistrés, validation par le demandeur avant inscription.',
        'cahier' => '§3.7',
        'open' => ['Modalités pratiques de l\'entretien préalable.'],
    ],
    'traductions' => [
        'label' => 'Traductions', 'icon' => 'bx bx-transfer-alt', 'order' => 160, 'roles' => ['commercial'],
        'summary' => 'Demande de traduction (langues source et cible, type de document, délai, coordonnées, document joint protégé), estimation, tarif communiqué et suivi du statut du dossier.',
        'cahier' => '§3.9, §5 « Gestion des traductions et des excursions »',
        'open' => ['Formats de fichiers acceptés, taille maximale et conditions de confidentialité.'],
    ],
    'excursions' => [
        'label' => 'Excursion au Ghana', 'icon' => 'bx bx-world', 'order' => 170, 'roles' => ['gestionnaire', 'commercial'],
        'summary' => 'Fiche de l\'excursion (objectifs, dates, programme, conditions, tarif, prestations incluses, réservation) et suivi des demandes de renseignements ou de réservation.',
        'cahier' => '§3.10',
        'open' => ['Dates, programme, tarif, documents requis et conditions d\'annulation.', 'Inclusion ou non dans le calcul de la commission.'],
    ],
    'finances' => [
        'label' => 'Finances et commission', 'icon' => 'bx bx-line-chart', 'order' => 180, 'roles' => [],
        'summary' => 'Recettes par période, impayés, remboursements et annulations ; frais de test et de formation séparés ; part du centre (80 %), commission du développeur (20 % des frais mensuels de formation encaissés), commissions versées et restant à payer ; rapports téléchargeables.',
        'cahier' => '§5 « Gestion financière », « Statistiques », §8',
        'open' => ['Traitement des paiements partiels, renouvellements, remboursements et fraudes (protocole de partenariat).', 'Périodicité du calcul et du versement de la commission.'],
    ],
];
