# Plateforme ETTC — guide du projet (dépôt `ETTC`)

Ce fichier est la **seule documentation** du dépôt. Il se lit en entier avant de commencer, par le développeur comme par son assistant IA (Claude Code le charge automatiquement). Langue de travail : français. Source officielle des exigences : `docs/cahier_des_charges.docx` (cahier des charges version 2.1, « version finale après confirmation des tarifs », English Training and Translation Center, Ségou).

Le projet est **parti d'une copie du site CSB** (`htdocs/CSB`, Computer Service Barry) le 2026-11-16 : socle, administration, site public, médias, actualités, demandes, espace client. Les modules propres à CSB (logiciels, matériel, réalisations, boutique, stock, tickets, partenaires, newsletter, conseiller d'achat, alertes WhatsApp, notifications push, rôles « Communication » et « Administrateur KalanNet ») ont été retirés. **Le dépôt CSB ne se modifie jamais depuis ce projet.**

## 0. Règles pour l'assistant IA

1. Le cahier des charges ETTC est la source principale. Ce qui n'y est pas est « À VALIDER AVEC LE CENTRE » ; toute idée non prévue est « PROPOSITION TECHNIQUE — À VALIDER » et n'est jamais une exigence. Les points ouverts du cahier (§12) sont listés au §5.
2. Ne crée ni table ni fonctionnalité « parce qu'elle semble logique » : vérifie d'abord le cahier, les décisions (§5) et le modèle de données (§4.2).
3. **L'administration utilise le gabarit hérité** (Gestion_magasin → CSB) : socle (authentification, rôles et permissions, audit, sauvegardes), thème « Semi Bleu », sidebar, modales, DataTables. Le socle ne se modifie qu'après revue. Un module s'ajoute **sans** modifier les fichiers du socle (§6).
4. Le **site public** a son propre gabarit (`layouts/site.blade.php`, `public/assets/site/css/site.css`, `site-fx.css`). Il ne charge jamais le thème de l'administration, et inversement.
5. Aucun mot de passe, secret, ni numéro personnel dans Git. Les coordonnées publiques du centre sont du contenu : défauts dans `config/site.php`, modifiables dans l'administration. Aucune ressource externe (CDN, police Google, carte intégrée) : assets locaux (D15). **Exception prévue par le cahier** : le paiement en ligne (Orange Money, Wave, carte) passera par le prestataire choisi, côté serveur.
6. Toute action sensible est journalisée dans `audit_logs` (cahier §6 « journalisation des opérations sensibles de gestion et de paiement »). Tout modèle métier utilise `LogsActivity`. Les permissions ne se créent pas « à la main » dans la base (§4.4).
7. Tout contenu saisi dans l'administration est affiché **échappé** ; le texte riche passe par `App\Support\Markdown`. Jamais `{!! !!}` sur une saisie non filtrée.
8. Avant de conclure une tâche : `composer test` vert, `vendor/bin/pint` propre, page vérifiée dans le navigateur (Chrome headless, mobile 390 px et ordinateur 1366 px).
9. Un fichier `.md` de plus n'est pas souhaité : mets à jour **ce fichier**.
10. L'assistant ne commite ni ne pousse que si son développeur le demande. Dépôt distant : `git@github.com:maitre92/ETTC.git` (branche `main`), atteint par l'alias SSH `github-maitre92` (`origin` = `git@github-maitre92:maitre92/ETTC.git`) : la clé par défaut de la machine est celle du compte `maitrebarry`, sans droits sur ce dépôt.
11. **Plateforme dynamique, interactive et fluide** (exigence du centre, 2026-11-17) : chaque module se comporte comme une application, pas comme un site statique. Opérations en AJAX (fetch) sans rechargement complet, liste actualisée après chaque ajout / modification / suppression / validation / paiement, erreurs de validation sous les champs sans perdre la saisie, notifications de confirmation ou d'erreur, indicateurs de chargement, double envoi impossible, coupures réseau gérées. Animations discrètes (glissement, apparition, élévation, micro-interactions) aux couleurs d'ETTC, coupées par `prefers-reduced-motion`. Sans JavaScript, tout doit encore fonctionner (amélioration progressive). Outils en place : `public/assets/js/crud-ajax.js` (écrans `admin/_crud` : tout `[data-crud-form]` part en AJAX, la zone `[data-crud]` est remplacée, message relu dans `[data-crud-status]`), `public/assets/site/js/site.js` (`form[data-ajax-form]`, `form[data-ajax-filter]`), `public/assets/site/js/ettc.js`, `ettc-theme.css` (section « Animations au survol et au clic »).

### 0.1 Début de session

1. Lis ce fichier en entier, puis la section du cahier du module à construire.
2. Vérifie l'environnement (§3) : `.env`, les deux bases, `php artisan migrate --seed`, puis `composer test` **vert avant ta première modification**.
3. **Imite l'existant, ne réinvente pas.** Fichiers de référence :

| Besoin | Fichier de référence |
|---|---|
| Manifeste de module (permissions, défauts par rôle, menu) | `config/modules/users.php`, `config/modules/articles.php` |
| Module prévu (fiche « à venir » tant que le manifeste n'existe pas) | `config/planned_modules.php` (clé = nom du futur manifeste) |
| Paramètres modifiables dans l'interface, audités | `app/Support/SiteSettings.php`, `app/Models/AppSetting.php` |
| Page publique (SEO, fil d'Ariane, appel à l'action) | `app/Http/Controllers/Site/PageController.php`, `resources/views/site/pages/about.blade.php`, `resources/views/site/partials/*` |
| Métadonnées d'une page publique | `app/Support/Seo.php` |
| Liste publique filtrée en AJAX, fiche détaillée | `app/Http/Controllers/Site/ActualiteController.php`, `resources/views/site/actualites/*`, `public/assets/site/js/site.js` |
| Écran d'administration simple (liste + modales) | `resources/views/admin/_crud.blade.php` (ex. `prestations/index.blade.php`) |
| Fiche longue sur page dédiée, images, SEO, publication | `ArticleController` (admin), `app/Services/Contenus/ArticleService.php`, `resources/views/articles/*`, `admin/_media-picker`, `admin/_seo-fields` |
| Images (validation, redimensionnement, galeries) | `app/Services/Medias/MediaService.php` |
| Formulaire public en AJAX, anti-spam, pièce jointe privée, notification | `app/Http/Controllers/Site/DemandeController.php`, `app/Http/Requests/Site/DemandeRequest.php`, `app/Services/Demandes/DemandeService.php`, `resources/views/site/demandes/*` |
| Numérotation sans doublon (futurs reçus) | `app/Services/Numbering/SequenceService.php` (table `sequences`) |
| Espace personnel (garde `client`) | `routes/espace-client.php`, `app/Http/Controllers/Client/*`, `app/Http/Controllers/Client/ClientAreaSections.php` |
| Saisie en masse / import Excel | `config/bulk.php`, `app/Services/Bulk/*` |
| Logique métier dans un service | `app/Services/Users/UserProvisioningService.php` |
| Modèle audité / Policy | `app/Models/User.php`, `app/Models/Concerns/LogsActivity.php`, `app/Policies/UserPolicy.php` |
| Tests | `tests/Concerns/BuildsFoundation.php`, `tests/Feature/Users/UserScreenTest.php`, `tests/Architecture/FoundationRulesTest.php` |

4. **Pièges connus** (hérités du gabarit) :
   - `composer test` tourne sur `db_ettc_test`, jamais sur la base de développement.
   - MariaDB 10.4 (XAMPP) : pas de requête JSON (une colonne JSON se stocke en texte et se lit par cast `array`), pas d'`enum` SQL (chaîne + enum PHP). Prévoir la longueur des colonnes pour la plus longue valeur de l'enum (ex. `demandes.need_type` = 40 pour `formation_personnalisee`).
   - `User::roleName()` et non `role()` ; un seul rôle par utilisateur. Les numéros de connexion passent par `App\Support\PhoneNumber::normalize()` (E.164).
   - Pas d'`update()` de masse sans audit (test d'architecture). Pas de permission créée à la main (manifeste + `php artisan permissions:sync`).
   - Blade : ne jamais écrire `@php(...)` en ligne avant un bloc `@php … @endphp` dans la même vue (test d'architecture). Une directive collée à un mot n'est pas compilée (`commande@if (…)`).
   - Aucune URL absolue dans les vues (test d'architecture) : les URL externes (schema.org, wa.me, Google Meet) se construisent en PHP.
   - Les clés de `SiteSettings` contiennent des points (`contact.email`) : ne jamais les lire par `config('site.defaults.contact.email')`. Dans un test, surcharger un défaut par `config(['site.defaults' => [...] + config('site.defaults')])`.
   - Test sur téléphone par tunnel (ngrok) : `TRUSTED_PROXIES=*` dans `.env` puis **redémarrer** `php artisan serve`.
   - Port de développement : **8002** (`php artisan serve --port=8002`) ; 8000 et 8001 servent à d'autres projets (CSB = 8001).
   - Un `@foreach (… as [$title, …])` dans une vue écrase les variables des partiels inclus ensuite (`site.partials.cta` lit `$title`).
   - Les anciennes adresses de contenus sont redirigées par le gestionnaire d'erreurs 404 (`bootstrap/app.php`, `Redirection::follow`).
   - **Cache : jamais d'objet** (modèle, collection) dans `Cache::remember` : Laravel 13 refuse de les désérialiser. Mettre en cache des tableaux.
   - Bootstrap local = **5.0.0** : pas de `Modal.getOrCreateInstance` ; un déclencheur `data-bs-toggle` doit être un `<button>`.
   - Modale `modal-dialog-scrollable` avec un `<form>` autour du corps et du pied : corrigé dans `numerise.css`, ne pas le retirer.
   - Vue d'e-mail : ne jamais passer de variable `$message` ; dans les tests, `$mail->render()`.
   - Les e-mails et l'audit de l'administration prennent l'utilisateur par `Auth::guard('web')` : un apprenant connecté (garde `client`) n'est jamais l'auteur d'une action de l'équipe.
   - Vérifier une page JavaScript : `node --check` et Chrome headless (`google-chrome --headless=new --screenshot`).
   - Le site public garde des préfixes neutres : variables CSS `--site-*`, classes `.site-*`, objet JavaScript `window.SiteUI`.
   - **Bootstrap 5.0 et les menus déroulants** : (1) n'animer un `.dropdown-menu` qu'avec des **transitions** (pas `@keyframes`, qui laissaient une couche figée à demi transparente) et le placer en CSS avec `data-bs-display="static"` (Popper écrit `transform` : une animation de `transform` le déplaçait par-dessus le bouton) ; (2) Bootstrap 5.0 écoute les clics délégués sur `document` **en capture** : pour intercepter un clic avant lui, écouter `window` en capture.
   - **Modales des écrans AJAX** : `crud-ajax.js` les déplace sous `<body>` (dans la page, le fond grisé passait par-dessus et « Enregistrer » ne répondait plus) ; un formulaire de modale retrouve sa zone par `data-crud-owner`.
   - Préfixe de route avec un point (`formations.formules`) : les identifiants HTML de `admin/_crud` utilisent `$domId` (points remplacés par des tirets).
   - Chrome headless se déclare **sans souris** (`hover: none`) : pour tester un survol, lancer Chrome avec `--blink-settings=primaryHoverType=2,availableHoverTypes=2,primaryPointerType=4,availablePointerTypes=4`.

## 1. Le projet

Plateforme web de l'**English Training and Translation Center (ETTC)**, Ségou (Mali) : pas un simple site vitrine, mais un outil de promotion, de commercialisation, d'inscription, de paiement et de gestion des formations linguistiques, des traductions et des activités (excursion au Ghana) — cahier, présentation générale et §2.

| Acteur (cahier) | Rôle technique | Périmètre |
|---|---|---|
| Direction / personnel autorisé | `administrateur` | Gestion complète : paramètres, utilisateurs, contenus, apprenants, finances |
| Gestion pédagogique et contenus | `gestionnaire` | Pages, actualités, médias, formations, tests, séances — PROPOSITION, À VALIDER |
| Accueil / encaissement | `commercial` | Demandes, inscriptions, paiements au centre — PROPOSITION, À VALIDER |
| Développeur (prestataire, commission 20 %) | `superadmin` | Maintenance, rôles, permissions, audit, système |
| Apprenants | garde `client` | Espace personnel (cahier §4) |
| Visiteurs | — | Site public, demandes, inscription en ligne |

Les noms des rôles `gestionnaire` et `commercial` sont hérités ; leur libellé et leur périmètre exacts sont À VALIDER avec le centre (un espace « Formateur » est une évolution, cahier §11).

**Règle financière structurante** (cahier §3.4, §8) : la commission du développeur = **20 % des frais mensuels de formation effectivement encaissés**  ; le **test de niveau est gratuit** (décision du centre, 2026-11-17 : le cahier prévoyait 5 000 FCFA hors commission) ; formations personnalisées, traductions et excursion : hors règle tant que le protocole de partenariat ne les inclut pas. Toute la modélisation des paiements doit permettre un calcul vérifiable : nature du paiement (formation / autre), montant encaissé, remboursements et annulations, tarif et périodicité figés à l'inscription.

## 2. Où en est-on

**Fait — phase 0, reprise du socle CSB** (2026-11-16) :
- Copie des fichiers suivis par Git de CSB (sans historique, `.env`, fichiers téléversés ni sauvegardes), nouveau dépôt Git, bases `db_ettc` / `db_ettc_test`, `APP_URL=http://localhost:8002`.
- Retrait des modules CSB hors cahier ETTC (voir l'introduction) : fichiers, routes, migrations, manifestes, tests, vues, styles et scripts.
- Conservé : socle (connexion par téléphone, rôles, permissions, audit, sauvegardes, système), pages et paramètres, formations et services (`prestations`), témoignages (vidéo et écrit, modération), médias, actualités (catégories : sessions et inscriptions, événements, promotions, résultats, excursions), demandes (contact + demande avec pièce jointe privée : traduction, formation personnalisée, excursion), espace personnel (comptes, demandes, documents), statistiques de fréquentation, recherche, SEO, redirections, saisie en masse, PWA (`public/sw.js`).
- Identité ETTC : `config/site.php` (textes « À COMPLÉTER »), `config/brand.php`. **2026-11-17** : logo officiel (`logo-ettc.png`, tiré de `English Training/logo.docx`) dans l'en-tête, le pied de page, la connexion (affiche `affiche-ettc.svg`), icônes PWA et favicon régénérés depuis le logo ; palette CSB remplacée par celle du logo dans le site, les e-mails, la connexion et les manifestes ; `ettc-theme.css` (voir §7). `logo.svg` et `affiche.svg` (provisoires) ne servent plus.
- Modules du cahier déclarés « à venir » dans `config/planned_modules.php` (menu « Modules à venir » de l'administration).
- 292 tests verts, Pint propre ; accueil vérifié dans Chrome headless (1366 px et 390 px), page de connexion de l'administration.

**Fait — phase 2, Formations et tarifs** (2026-11-17) :
- Manifeste `formations` (view, create, update, delete ; administrateur tout, gestionnaire sans suppression par défaut, commercial en lecture avec `update` en option). Écrans **Formations → Formules et tarifs** et **Niveaux** (`FormationController` admin, `routes/web/formations.php`, routes `formations.formules.*`, `formations.niveaux.*`, `formations.test-fee.update`).
- Tables `niveaux` (fiche : résumé, objectifs, compétences, prérequis) et `formules` (durée, points forts, `price_online` / `price_onsite` en FCFA entiers, vide = mode non proposé, `billing` : `mois` ou `unique`). (Un réglage « frais du test » a existé puis a été retiré : le test est gratuit.) Tout se modifie à tout moment, chaque changement est audité (ancien et nouveau montant).
- Site : `/formations` (niveaux, formules et tarifs, test de niveau, comment s'inscrire) et `/formations/{niveau}` (fiche du cahier §3.2), carte de formule partagée `site/formations/_formule` (un tarif = grand prix, deux modes = une ligne par mode) ; l'accueil lit les mêmes données. Menu « Formations » déroulant (ouvert au survol à la souris, au clic au toucher).
- Contenu initial : niveaux Débutant / Intermédiaire / Avancé (structure suggérée par le cahier, à valider) ; formules 2 et 3 mois aux **tarifs du cahier §3.3** (en ligne 100 000 / 50 000 FCFA par mois, présentiel 50 000 / 20 000 FCFA par mois). La maquette affichait d'autres prix (30 000 FCFA en paiement unique, 20 000 FCFA / mois) : à trancher par le centre, puis à saisir dans l'administration. Un tarif déjà réglé n'est jamais écrasé par le seeder.
- **Écrans `admin/_crud` en AJAX** (`crud-ajax.js`) pour tous les modules qui l'utilisent ; animations du site (`ettc-theme.css`).
- 300 tests verts, Pint propre ; parcours vérifiés dans Chrome headless (modification d'un tarif en AJAX sans rechargement, erreur sous le champ, menu au survol et au toucher, 1366 px et 390 px).

**Fait — phase 3, Tests de niveau** (2026-11-17) :
- **Le test de niveau est GRATUIT** (décision du centre transmise le 2026-11-17 ; le cahier §3.4 prévoyait 5 000 FCFA) : seule l'inscription à une formation est payante. Frais, encaissement du test et réglage « frais du test » retirés (migration `remove_fees_from_tests_niveau_table`) ; le site affiche « Gratuit ».
- Manifeste `tests` : une permission par geste (`view`, `create`, `schedule`, `result`, `configure`, `delete`) ; administrateur tout ; gestionnaire sans suppression par défaut ; commercial (accueil) inscrit et planifie, `result` en option.
- Table `tests_niveau` (numéro `TST-AAAA-NNNN` par `SequenceService`), `TestNiveauService` : demande du site ou inscription au centre → planification / report (convocation e-mail) → résultat (score, niveau attribué, appréciation) ; annulation motivée. Règles : pas de résultat sur un test annulé, pas d'annulation d'un test passé. Une ligne d'audit métier par geste (`tests.*`), journalisation automatique coupée pour ces mises à jour (`withoutActivityLog`).
- Site : `/test-de-niveau` (présentation en Markdown réglée dans l'administration, réglage `test.presentation` ; test gratuit ; déroulement ; réservation en AJAX, anti-spam). Menu « Test de niveau ». E-mails : `TestNiveauDemande` (équipe), `TestNiveauAccuse`, `TestNiveauConvocation` (candidat) ; le résultat n'est pas envoyé automatiquement (modalités à définir par le centre).
- Administration : liste (à planifier, aujourd'hui, passés, filtres par statut), fiche de traitement en AJAX avec orientation (formules du niveau, lien de sa fiche), historique ; tableau de bord (tâches), pastille du menu, cloche, raccourci.
- Espace apprenant : « Mes tests de niveau » (tests liés au compte ou faits avec son e-mail confirmé), bloc du tableau de bord.
- Parcours vérifié dans Chrome (réservation, planification, résultat : 0 rechargement). **Aucun texte « à compléter » sur le site public** : textes de départ définitifs (présentation du test, mission, vision, valeurs, mentions légales, confidentialité), garde-fou `FormationsTest::test_no_public_page_shows_a_placeholder_to_complete`.

**Fait — phase 4, Inscriptions et apprenants** (2026-11-17) :
- Table `inscriptions` (numéro `INS-AAAA-NNNN`), `InscriptionService` : inscription du site (`/inscription`, assistant en 4 étapes `form[data-ettc-wizard]` dans `ettc.js` : niveau ou « à déterminer par le test », formule et mode avec **tarif en direct**, coordonnées, choix du paiement et récapitulatif ; envoi AJAX, anti-spam, `throttle:demandes`) ou au centre, ou depuis un **test passé** (identité et niveau repris). **Tarif figé** à l'inscription (`formule_name`, `duration_months`, `billing`, `unit_price`, `total_amount`) : un changement de tarif ne touche pas les inscriptions faites ; formule masquée ou mode sans prix refusés.
- Statuts : `a_valider` → `validee` (« En attente de paiement ») → `en_cours` → `terminee` ; `annulee` (motif obligatoire). Début de formation confirmé à la main en attendant le module Paiements. Session et notes internes. Audit métier par geste (`inscriptions.*`), sans ligne « Modification » en double.
- Manifeste `inscriptions` (`view`, `create`, `validate`, `organize`, `cancel`, `delete`) ; administrateur tout, gestionnaire sans annulation ni suppression par défaut, commercial (accueil) sans suppression. Liste filtrable (statut, niveau, formule), fiche de traitement en AJAX, historique, tableau de bord (tâches, répartition par niveau), pastille, cloche.
- E-mails `InscriptionRecue` (équipe) et `InscriptionRecapitulatif` (apprenant). Espace apprenant : « Mes inscriptions », bloc du tableau de bord ; profil enrichi (date de naissance, sexe ; adresse « de résidence »).
- 316 tests verts, Pint propre ; assistant vérifié dans Chrome headless (1366 px et 390 px : étape bloquée sans choix, tarif en direct, envoi sans rechargement) et validation d'un dossier en AJAX.

**Fait — phase 5, Paiements et reçus** (2026-11-18) :
- **Décision du centre (2026-11-18)** : en ligne, le paiement se fait par **mobile money** (Orange Money, Wave, Moov Money) ; en présentiel, **au centre** (espèces, ou mobile money au guichet). Pas de carte bancaire. Dans l'assistant d'inscription, le paiement est présélectionné d'après le mode (modifiable).
- Table `paiements` (numéro du reçu `REC-AAAA-NNNN`), `PaiementService` : au centre, l'équipe enregistre le paiement → **Confirmé**, reçu ; en ligne, l'apprenant **déclare** sa transaction (moyen dont le numéro marchand est renseigné, montant, référence unique par moyen) → **À vérifier** ; l'équipe confirme après l'avoir retrouvée sur le compte marchand, ou rejette avec un motif. Le navigateur ne confirme jamais un paiement (cahier §3.8 A) ; prestataire avec notification serveur : comptes marchands À VALIDER.
- Un paiement confirmé met à jour `inscriptions.amount_paid` et fait passer une inscription à valider / en attente de paiement **En cours** (`inscriptions.paid`). Montant plafonné au reste à payer (déclarations en attente comprises). Annulation (erreur) et remboursement (argent rendu) motivés, jamais de suppression. Nature `formation` seulement (test gratuit) ; la colonne garde la distinction test / formation du cahier §3.8 C.
- Administration (manifeste `paiements` : `view`, `record`, `verify`, `refund`, `configure` ; administrateur tout, commercial sans `refund` par défaut ni `configure`, gestionnaire `view` en option) : recettes du mois, à vérifier, reste à encaisser, remboursé ; liste des paiements ; **impayés** avec enregistrement en modale ; fiche (vérification, annulation / remboursement : deux boutons d'un même formulaire, `formaction` géré par `crud-ajax.js`) ; numéros marchands et consignes (réglages `paiement.*`) ; bloc Paiements sur la fiche d'inscription (barre de progression) ; tableau de bord (tâches, section Finances), pastille, cloche.
- **Reçu** imprimable (`paiements/recu.blade.php`, page autonome, « Imprimer / PDF » du navigateur, aucune bibliothèque PDF) : numéro, apprenant, inscription, nature, montant, date, moyen et transaction, statut, total / payé / reste. E-mails `PaiementConfirme` (reçu à l'apprenant) et `PaiementDeclare` (équipe).
- Espace apprenant : « Mes paiements » (total, payé, reste par inscription ; déclaration mobile money en AJAX pour une formation en ligne, « au centre » pour le présentiel ; historique, motif d'un rejet, reçus), bloc du tableau de bord ; état du paiement sur « Mes inscriptions ».

**Fait — administration simplifiée, reçus PDF, paiement automatique prêt** (2026-11-18, demande du centre : « moins de protocole pour un non-informaticien », reçus « thermique et A5 comme KalanNet », paiement mobile money automatique par un agrégateur agréé) :
- **Une action principale par écran** : fiche d'inscription → carte « Que faire maintenant ? » (une phrase, un gros bouton : « Encaisser un paiement » ou « Terminer la formation ») ; actions rares (valider / démarrer sans paiement, session, notes, annulation) sous « Autres actions » (`<details>`), de même pour l'annulation d'un test. Bouton vert **« Encaisser »** sur chaque ligne de la liste des inscriptions et des impayés (modale `paiements._record-modal`) ; formulaire d'encaissement réduit à **montant + moyen** (référence affichée seulement pour le mobile money : `[data-show-when]` dans `crud-ajax.js`, champs masqués désactivés), le reste sous « Plus d'options ». Paiement « À vérifier » confirmé **depuis la liste** : `form[data-confirm]` → question Oui / Non (SweetAlert2) gérée par `crud-ajax.js`.
- **Reçus PDF** comme KalanNet (`barryvdh/laravel-dompdf`, `endroid/qr-code` : décision D-G levée pour les reçus) : `ReceiptService`, `pdf/recu-a5.blade.php` (A5, cachet et signature) et `pdf/recu-thermique.blade.php` (ticket 80 mm, 226,77 × 540 pt) ; `?format=a5|thermique`, `?imprimer=1` = affiché dans l'onglet ; boutons « A5 » / « Ticket » (`paiements._receipt-buttons`). **QR code** vers `/recu/{numéro}/{clé}` (`site.recu.verify`, clé = HMAC du numéro, du montant et de l'inscription avec `APP_KEY`) : « Reçu authentique » ou « introuvable ».
- **Paiement automatique** (cahier §3.8 A) : `Services/Paiements/Gateway` — interface `PaymentGateway` (`initiate`, `fetch`), `CinetPayGateway` (API checkout v2 : `/v2/payment`, `/v2/payment/check`, champs à revérifier avec la documentation remise à l'ouverture du compte), `SimulationGateway` (opérateur simulé, refusé en production), `GatewayManager` (`PAYMENT_GATEWAY`, `CINETPAY_API_KEY`, `CINETPAY_SITE_ID` dans `.env`). Parcours : « Payer » dans « Mes paiements » → paiement **`en_attente`** (colonnes `gateway`, `gateway_transaction_id` unique, `gateway_status`, `gateway_checked_at`) → page de l'opérateur → retour (`client.paiements.return`) et notification serveur (`site.paiements.notify`, `/paiements/notification/{gateway}`, hors CSRF) ne sont que des **signaux** : `PaiementService::synchronize()` relit l'état **chez le prestataire** (verrou, idempotent) ; accepté au bon montant → confirmé + reçu + inscription « En cours » ; montant différent ou trop-perçu → « À vérifier » ; refusé → rejeté. `paiements:sync` (toutes les 5 min) relit les attentes, abandon après 24 h. Sans prestataire actif, la déclaration manuelle reste le parcours.
- Agrégateur : **agrément BCEAO** (zone UEMOA ; c'est la BCEAO, pas la CEDEAO, qui agrée les établissements de paiement au Mali). CinetPay Africa SA (agréée, pilote déjà écrit) ; InTouch (agréée « EP » au Mali). Dossier type : statuts, RCCM, NIF, pièce du responsable, justificatif d'adresse, RIB, site HTTPS avec mentions légales et conditions de remboursement — À VALIDER avec l'agrégateur choisi. Carte « Paiement automatique » sur l'écran Paiements (état, démarches).

**Fait — inscription + paiement en une fois, admin « intelligent », phase 6 Séances** (2026-11-19, demande du centre : « en présentiel, l'inscription et le paiement se font ensemble », « admin plus légère, plus intelligente ») :
- **Fenêtre « Inscrire au centre »** (`inscriptions._form-modal`, `public/assets/js/inscription-form.js`) : 1. le **téléphone** d'abord → `inscriptions.lookup` (`/admin/inscriptions/personne`) retrouve la personne (compte, test, inscription précédente) et remplit la fiche, avec alerte si une inscription est déjà en cours ; 2. formule et mode (boutons), **total en direct** ; `payment_choice` déduit du mode ; 3. « L'apprenant paie maintenant » coché en présentiel : `pay_amount` (une mensualité proposée), `pay_method`, `pay_reference` (mobile money) → inscription + paiement + reçu en un envoi (`InscriptionController::register`, période « Mois 1 »), montant plafonné au total. Après un encaissement, barre **« Imprimer le reçu »** (`paiements._last-receipt`, session `receipt_id`) sur la fiche et les listes. **Message conservé** quand un formulaire AJAX ouvre une autre page : `crud-ajax.js` envoie `X-Crud-From`, le middleware `KeepFlashForCrudNavigation` garde le message pour le vrai chargement (auparavant il était perdu).
- Relance **WhatsApp** rédigée selon le dossier (`Inscription::whatsappMessageUrl()`, `SiteSettings::whatsappTo()`) : bouton « Écrire » sur la fiche, « Relancer » sur les impayés. Test de niveau : date proposée (prochain jour ouvré, 9 h) et derniers lieux utilisés.
- **Phase 6 — Séances** (cahier §3.6, §4, §5), manifeste `seances` (`view`, `manage`, `supports`) ; tables `groupes` (nom, niveau, mode, formateur, `meet_url` ou `location`, horaire, période, notes, actif), `seances` (`starts_at`, durée, thème, `meet_url` facultatif, annulation motivée), `supports` (fichier privé sur le disque `local` ou lien), `inscriptions.groupe_id` (le libellé `session` suit le nom du groupe). `SeanceService` : apprenants **proposés** (même mode, niveau du groupe ou à déterminer, dossier actif, sans groupe, cochés d'office), **série** de séances (jours, heure, durée, période ; pas de doublon ; 120 au plus), séance seule, annulation, supports ; e-mail `SeancesCalendrier` (calendrier ou annulation) à chaque apprenant. Écrans : « Séances et groupes » (séances du jour avec « Ouvrir Meet », groupes en cartes, alerte « apprenants sans groupe »), fiche du groupe avec « Que faire maintenant ? ». Lien collé sans `https://` complété. Espace apprenant : **« Mon calendrier »** (prochaine séance, « Rejoindre » actif 15 min avant, séances, supports téléchargeables, infos pratiques), bloc du tableau de bord. Tableau de bord de l'équipe : « Séances aujourd'hui », « Apprenants sans groupe ».

**Fait — phase 7, formation personnalisée, traduction, excursion au Ghana** (2026-11-19, cahier §3.7, §3.9, §3.10) : suivies comme des **demandes** (pas de module en plus : réception, statuts, notes, pièce jointe privée, alertes déjà en place). Pages publiques `/traduction` (langues source et cible, type de document, délai, document joint), `/formation-personnalisee` (objectifs, disponibilités), `/excursion-ghana` (dates, tarif, prestations, programme, conditions, réservation ; formulaire avec nombre de participants) ; textes dans Pages et paramètres (onglets Traduction, Formation personnalisée, Excursion au Ghana, réglages `traduction.*`, `personnalisee.*`, `excursion.*`) ; menu, accueil et plan du site pointent vers elles. Colonnes `demandes.source_language`, `target_language`, `document_type`, `participants`, `proposal_amount`, `proposal_text`, `proposal_sent_at`, `proposal_token`, `accepted_at`. **Proposition** (`PropositionService`, carte sur la fiche de la demande) : montant + détail → e-mail `PropositionDemande` avec lien `/proposition/{jeton}` (sinon bouton WhatsApp) → le demandeur **accepte d'un clic** (statut « Acceptée », équipe prévenue) ; formation personnalisée acceptée → bouton **« Inscrire »** : inscription au tarif accepté (`formule_id` nul, « Formation personnalisée », paiement unique, `inscriptions.demande_id`), encaissable comme les autres. Statuts renommés pour tous : « Proposition envoyée », « Acceptée ». Liste des demandes filtrable par service.

**À faire** : voir le plan (§10).

## 3. Démarrer

Prérequis : PHP ≥ 8.3 (extensions `gd`, `mbstring`, `fileinfo`), Composer 2, MariaDB 10.4+ ou MySQL 8. Aucun Node (D19).

```sh
composer install
cp .env.example .env
php artisan key:generate
# créer deux bases vides (utf8mb4, utf8mb4_unicode_ci) : db_ettc et db_ettc_test
php artisan migrate --seed      # rôles, permissions, superadmins déclarés dans .env, formations et services initiaux
php artisan storage:link        # images du site (disque public)
php artisan serve --port=8002   # site : http://localhost:8002 — administration : http://localhost:8002/admin
composer test                   # base db_ettc_test, jamais la base de développement
php artisan db:seed --class=DonneesTestSeeder   # facultatif : données FICTIVES (jamais en production)
```

Dans `.env` (jamais dans Git) : `SUPERADMIN_PASSWORD`, `SUPERADMIN_1_NAME` / `_PHONE` ; `ADMIN_PATH` ; `SITE_NOTIFICATION_EMAIL` et la configuration SMTP (`MAIL_*`) ; `PAYMENT_GATEWAY` (`cinetpay`, ou `simulation` en développement), `CINETPAY_API_KEY`, `CINETPAY_SITE_ID` pour le paiement automatique (vides = désactivé).

## 4. Architecture

```
Visiteur  → routes/site.php (site.*) → contrôleurs App\Http\Controllers\Site → vues site/* (layouts.site)
Apprenant → routes/espace-client.php (client.*, garde `client`) → App\Http\Controllers\Client → vues client/*
Équipe    → /admin + routes/web/*.php → session → Middleware (auth, active, password.changed, permission:…)
           → contrôleurs minces → Services (transactions) → Modèles → MariaDB
                                      ↑ ActivityLogger → audit_logs (même transaction)
```

1. La logique vit dans des **Services**, jamais dans les contrôleurs.
2. Autorisation en trois couches : permission (`module.action`) → Policy → règles du service. Le superadmin contourne l'autorisation (`Gate::before`), pas la logique métier ni l'audit.
3. Le site public ne lit que les contenus **publiés** (scope `published()`).
4. Formulaires publics protégés : CSRF, validation serveur, `throttle`, champ piège et délai minimal (`SpamGuard`), domaines et mots bloqués (paramètres → Anti-spam).
5. **Paiements** (à construire) : la confirmation d'un paiement en ligne repose sur la notification serveur du prestataire, jamais sur le retour du navigateur (cahier §3.8 A). Un paiement au centre est saisi par un utilisateur autorisé et audité. Chaque paiement produit un reçu numéroté (`SequenceService`).

### 4.1 Tables du socle (13)

`users` (connexion par téléphone, sans e-mail), `roles`, `permissions` (`source` = `manifest` / `custom`), `model_has_roles` (un seul rôle), `model_has_permissions`, `role_has_permissions`, `audit_logs` (non modifiable), `app_settings`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.

### 4.2 Modèle de données métier (✔ = créée)

Noms de tables en français, colonnes en anglais. Tout en `softDeletes` ; clés étrangères `restrictOnDelete` ou `nullOnDelete`, **jamais de cascade vers les demandes ni vers les paiements**. La liste exacte des tables est vérifiée par `tests/Feature/Database/SchemaTest.php`.

| Table | Colonnes principales | Règles |
|---|---|---|
| `categories` ✔ | `type` (`article`), `name`, `slug`, `description`, `media_id`, `sort_order`, `is_active` | Catégories des actualités |
| `medias` ✔, `mediables` ✔ | image, vignette, `alt`, `folder` ; galeries | JPEG, PNG, WebP, ≤ 5 Mo, redimensionnées ; une image utilisée (`config/medias.php`) ne se supprime pas |
| `prestations` ✔ | `title`, `slug`, `icon`, `summary`, `description`, `sort_order`, `is_published` | Page « Formations et services » ; icônes : `Prestation::ICONS` |
| `temoignages` ✔ | auteur, fonction, organisation, texte, photo, note, vidéo, `source`, modération | Témoignages d'apprenants (cahier §3.1) |
| `articles` ✔ | `title`, `slug`, `excerpt`, `body`, `category_id`, `cover_media_id`, `author_id`, `is_published`, `published_at`, SEO | Actualités (cahier §3.11) |
| `demandes` ✔, `demande_notes` ✔ | `type` (`contact`, `devis`), `need_type` (`traduction`, `formation_personnalisee`, `excursion`, `autre`), coordonnées, message, délai, pièce jointe privée, statut, attribution, notes | Formulaire générique en attendant les modules dédiés (traductions, formations personnalisées, excursion) |
| `clients` ✔, `client_documents` ✔, `client_password_reset_tokens` ✔ | compte (`name`, `email`, `phone`, `password`, `is_active`, `email_verified_at`, `address`, `city`) ; documents privés | Compte **apprenant** (cahier §4) ; `birth_date`, `gender` ajoutés en phase 4 |
| `niveaux` ✔ | `name`, `slug`, `summary`, `objectives`, `skills`, `prerequisites`, `sort_order`, `is_published` | Fiches du catalogue (cahier §3.2) ; une ligne = un élément |
| `formules` ✔ | `name`, `slug`, `duration_months`, `points`, `price_online`, `price_onsite`, `billing` (`mois`, `unique`), `sort_order`, `is_published` | Tarifs par mode (cahier §3.3), modifiables à tout moment et audités ; une inscription devra figer le tarif appliqué |
| `tests_niveau` ✔ | `number`, `status` (`demande`, `planifie`, `passe`, `annule`), `source` (`site`, `centre`), identité, `test_mode` (`centre`, `distance`), `preferred_slot`, `client_id`, `scheduled_at`, `location`, `score`, `niveau_id`, `result_notes`, `result_at`, `result_by`, `cancel_reason` | Test gratuit (décision du centre, 2026-11-17) |
| `inscriptions` ✔ | `number`, `status` (`a_valider`, `validee`, `en_cours`, `terminee`, `annulee`), `source`, `client_id`, `test_niveau_id`, identité (`name`, `birth_date`, `gender`, `phone`, `email`, `address`, `city`), `niveau_id`, `formule_id`, `mode` (`en_ligne`, `presentiel`), `payment_choice`, tarif figé (`formule_name`, `duration_months`, `billing`, `unit_price`, `total_amount`), `session`, `validated_*`, `started_at`, `finished_at`, `cancel_reason`, `internal_notes` | Le tarif ne change jamais après l'inscription |
| `sequences` ✔ | `prefix`, `year`, `last_number` | Numérotation sous verrou (`TST`, futurs reçus `REC-AAAA-NNNN`) |
| `page_vues` ✔ | `day`, `visitor`, `path`, `route`, `source`, `device` | Fréquentation (technique, sans modèle) |
| `paiements` ✔ ; `inscriptions.amount_paid` | `gateway`, `gateway_transaction_id`, `gateway_status`, `gateway_checked_at` (paiement automatique) ; `number` (reçu), `inscription_id`, `client_id`, `nature` (`formation`), `amount`, `method` (`especes`, `orange_money`, `wave`, `moov_money`), `reference`, `payer_phone`, `period`, `status` (`en_attente`, `a_verifier`, `confirme`, `rejete`, `annule`, `rembourse`), `source` (`centre`, `apprenant`, `en_ligne`), `paid_at`, `recorded_by`, `confirmed_*`, `cancel_reason`, `cancelled_*`, `notes` | Jamais supprimé ; seuls les paiements confirmés comptent |
| `groupes` ✔, `seances` ✔, `supports` ✔ ; `inscriptions.groupe_id` | groupe (`name`, `niveau_id`, `mode`, `teacher`, `meet_url`, `location`, `schedule`, `starts_on`, `ends_on`, `notes`, `is_active`) ; séance (`groupe_id`, `starts_at`, `duration_minutes`, `title`, `meet_url`, `notes`, `is_cancelled`, `cancel_reason`) ; support (`groupe_id`, `title`, `kind`, `path` privé ou `url`) | Visibles seulement des apprenants du groupe |
| `redirections` ✔ | `from_path`, `to_path`, `hits` | Anciennes adresses → nouvelles (301) |

Tables à concevoir (PROPOSITION TECHNIQUE — À VALIDER), dans l'ordre du plan : `remboursements`, `commissions` (périodes, montants dus et versés).

### 4.3 Rôles

| Rôle | Niveau | Gère les comptes |
|---|---|---|
| `superadmin` | 100 | tous |
| `administrateur` | 80 | `gestionnaire`, `commercial` |
| `gestionnaire` | 50 | aucun |
| `commercial` | 50 | aucun |

Source unique : `config/role_hierarchy.php` et `App\Enums\RoleName`.

### 4.4 Permissions (deux voies)

Convention `module.action`. **Voie A** : manifeste `config/modules/<module>.php` + `php artisan permissions:sync` (à chaque déploiement). **Voie B** : le superadmin crée des permissions `custom` depuis Paramètres → Permissions. Toutes les routes d'administration portent `permission:module.action`. Réservées au superadmin : `roles.*`, `permissions.*`, `audit.*`, `system.*`.

### 4.5 Audit

`ActivityLogger::log()` pour les actions métier, trait `LogsActivity` pour création / modification / suppression. Les lectures ne sont pas journalisées. Paiements, remboursements, validations d'inscription et résultats de test seront journalisés (cahier §6).

## 5. Décisions

| # | Décision |
|---|---|
| D-G | **Gabarit** : copie de CSB (lui-même issu de Gestion_magasin / VigiMoto) sans ses modules métier. Décisions du socle valables : MariaDB, audit, permissions à deux voies, assets locaux (D15), pas de Vite (D19), connexion de l'équipe par téléphone (D26, À VALIDER avec le centre), superadmins automatiques (D27) |
| D-1 | Administration sous `/admin` (`ADMIN_PATH`), site public à la racine (`site.*`), espace apprenant sous `/espace-client` (`client.*` ; adresse `/mon-espace` : PROPOSITION) |
| D-2 | Contenus riches en Markdown, rendus par `App\Support\Markdown` |
| D-3 | Images sur le disque `public` (nom généré, 1600 px, vignette 480 px) ; pièces jointes (documents à traduire, supports pédagogiques) sur le disque privé `local`, téléchargées seulement par les personnes autorisées (cahier §6 « protection des documents ») |
| D-4 | Notifications e-mail envoyées **après** l'enregistrement : un échec d'envoi ne perd jamais une demande ni une inscription |
| D-5 | Montants en FCFA, entiers (pas de centimes) |
| D-6 | Cours à distance : la plateforme n'héberge pas la visioconférence ; elle stocke et communique les liens Google Meet (cahier §3.6) |
| D-7 | Aucune carte ni vidéo externe intégrée : liens ouverts dans un nouvel onglet |
| D-8 | Site public dynamique (AJAX, animations) dans `public/assets/site/js/site.js`, sans bibliothèque ; sans JavaScript, la page fonctionne ; respecte `prefers-reduced-motion` |
| D-9 | Les modules du cahier sont déclarés dans `config/planned_modules.php` ; un module quitte cette liste dès que son manifeste existe |

### Points ouverts — À VALIDER AVEC LE CENTRE (cahier §12)

Appellations et nombre des niveaux ; tarifs bien exprimés **par mois** (en ligne : 100 000 FCFA/mois sur 2 mois, 50 000 FCFA/mois sur 3 mois ; présentiel : 50 000 FCFA/mois sur 2 mois, 20 000 FCFA/mois sur 3 mois) ; format et durée du test de niveau (gratuit) ; entretien de formation personnalisée ; programmation des cours à distance et transmission des liens Google Meet ; dates, programme, tarif et conditions de l'excursion au Ghana ; moyens de paiement réellement disponibles et comptes marchands ; annulation et remboursement ; périmètre de la commission de 20 % ; hébergement, domaine, SMTP ; propriété du code. Également : logo, couleurs, coordonnées, textes officiels (présentation, mission, valeurs), mentions légales, rôles du personnel.

## 6. Comment ajouter un module

1. Manifeste `config/modules/<module>.php` (permissions, défauts par rôle, `navigation`) puis `php artisan permissions:sync`. Le groupe de la sidebar se déclare dans `partials/sidebar.blade.php` (`$groupOf` : Formations, Finances, Demandes et apprenants, Contenus du site, Administration). L'entrée de `config/planned_modules.php` disparaît d'elle-même du menu ; la supprimer garde le fichier lisible.
2. Migrations (`softDeletes`, index, clés étrangères sans cascade vers les demandes et les paiements) ; tables ajoutées à `tests/Feature/Database/SchemaTest.php` et, si essentielles, à `config/backup.php` (`required_tables`).
3. Modèle avec `LogsActivity`, alias morph dans `AppServiceProvider`, scope `published()` pour le site.
4. Policy, FormRequests (messages en français), Service, contrôleur `Web/Admin`, `routes/web/<module>.php` (`auth`, `active`, `password.changed`, `permission:…`).
5. Vues d'administration au gabarit du thème (§7) ; pages publiques dans `resources/views/site/<module>/` avec `Seo::make()` ; blocs de l'espace apprenant via `ClientAreaSections`.
6. Tests : permissions, règles, audit, rendu public (contenu non publié invisible), calculs financiers.

## 7. Interface

### Administration (gabarit impératif)

En-têtes de carte `card-header card-header-brand` avec titre blanc en majuscules ; tableaux DataTables (libellés français) ; boutons d'action `btn btn-primary btn-sm` à icône (`bx-show`, `bx-edit`, `bx-trash`) ; pastilles `nv-chips` ; **modales** pour créer / modifier / consulter ; suppression confirmée par SweetAlert2 ; fil d'Ariane puis `<hr />`. Les formulaires longs peuvent avoir une page dédiée.

### Site public

**Maquette validée** (`English Training/maquette.jpeg`, 2026-11-17) : implémentée à l'identique pour l'en-tête (`site/partials/header`), l'accueil (`site/pages/home`) et le pied de page (`site/partials/footer`) ; styles dans `public/assets/site/css/ettc-theme.css` (classes `ettc-*`, palette mesurée sur la maquette : bleu `#045EB5` / menu `#116095` / pied `#0D497E`, orange `#F96809`, rouge du titre `#D4351C`, jaune `#F9D232`, vert `#4AB248`), carrousel des témoignages dans `public/assets/site/js/ettc.js`. Images dans `public/assets/site/img/accueil/` : `apprenante.webp` et `arc-ghana.webp` (photos fournies, détourées du fond noir), `toque-livres.svg`, `ordinateur-visio.svg`, `documents-stylo.svg`, `carte-mali.svg` (dessinées d'après la maquette). Textes et tarifs de l'accueil : Pages et paramètres → Accueil. **Tarifs de la maquette (30 000 FCFA en paiement unique, 20 000 FCFA / mois) différents du cahier §3.3 : À VALIDER.** Les entrées Traduction, Excursion, Test de niveau mènent provisoirement au formulaire de demande prérempli ou à la page Formations et services, en attendant leurs modules. Pied de page : « © année Computer Service BARRY. Tous droits réservés. Site de English Training and Translation Center (ETTC). » (droits réservés au développeur, à formaliser dans le protocole, cahier §12).

**Couleurs du logo d'ETTC** (palette de la première version, remplacée par celle de la maquette dans `ettc-theme.css`) (`public/assets/site/img/logo-ettc.png`, mesurées sur le logo le 2026-11-17) : bleu `#0C68B2` (principal, blanc dessus 5,8:1), bleu foncé `#084A80`, bleu moyen `#4B90CD`, bleu ciel `#AFDEF6`, saumon `#F29677` (accent : texte foncé dessus, jamais blanc), saumon foncé `#B4472A` (accent en texte sur blanc, 5,4:1), jaune `#F4EF9B`, vert `#A6CF91`. Variables dans `site.css` (`--site-*`, les noms `--site-orange*` portent le saumon). **Identité distincte de CSB** dans `public/assets/site/css/ettc-theme.css`, chargé en dernier : bandeau d'accueil clair (bleu ciel, bas arrondi), barre du haut claire, liseré tricolore saumon / jaune / vert sous l'en-tête, au pied de page, sous les titres de section et sur l'appel à l'action, bande des chiffres bleu ciel. Toute retouche d'apparence propre à ETTC va dans ce fichier. Pied de page : « Computer Service BARRY © année English Training and Translation Center (ETTC). Tous droits réservés. » Police Inter locale. Appels à l'action prioritaires (cahier §3.1) : « Voir les formations », « S'inscrire », « Passer le test de niveau », « Faire une demande », « Nous contacter ». Une seule balise `h1` par page ; textes alternatifs ; labels de formulaire ; erreurs reliées aux champs (`aria-describedby`) ; contrastes AA (l'orange du texte est `#c8620c`).

## 8. Exigences → où elles vivent

| Exigence (cahier §6) | Implémentation | Test |
|---|---|---|
| HTTPS, sessions, XSS, SQL | middleware `web`, échappement Blade, `Markdown`, Eloquent | `FoundationRulesTest` |
| Sécurisation des comptes | `CredentialChecker` (5 essais), mots de passe temporaires | `LoginTest` |
| Limitation des accès selon les rôles | `permission:…` sur chaque route d'administration | `FoundationRulesTest` |
| Protection des documents transmis | disque privé, téléchargement autorisé seulement | `DemandeFormsTest` |
| SEO | `Seo`, `SeoController` (sitemap, robots), `Redirection` | `SeoAndSearchTest`, `PublicPagesTest` |
| Sauvegardes | `backup:run`, `backup:verify` | `BackupAndDemoTest` |
| Journalisation | `ActivityLogger`, `LogsActivity` | `FoundationRulesTest` |
| Paiements vérifiés côté serveur | à construire (module `paiements`) | — |

## 9. Exploitation

### Installation sur un serveur (Linux)

1. PHP ≥ 8.3 (`pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `zip`), Composer 2, MariaDB 10.4+ ou MySQL 8, domaine pointé sur `public/`, **HTTPS obligatoire**.
2. `composer install --no-dev --optimize-autoloader`, `cp .env.example .env`, `php artisan key:generate`.
3. `.env` : `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://…`, base, `SUPERADMIN_*` (mot de passe fort), `ADMIN_PATH`, SMTP, `SITE_NOTIFICATION_EMAIL`, `BACKUP_MYSQLDUMP`, `BACKUP_COPY_PATH`. Le cron `schedule:run` est obligatoire (sauvegarde, file d'attente, conversion des vidéos — **ffmpeg** conseillé).
4. `php artisan migrate --force && php artisan db:seed --force && php artisan storage:link`.
5. `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
6. Cron : `* * * * * cd /chemin/ETTC && php artisan schedule:run >> /dev/null 2>&1`.
7. À chaque mise à jour : `git pull`, `composer install --no-dev`, `php artisan migrate --force`, `php artisan permissions:sync`, `php artisan optimize`.

### Sauvegarde et restauration

- `php artisan backup:run` : `mysqldump --single-transaction` compressé dans `storage/app/backups/`, `BACKUP_KEEP` versions, copie dans `BACKUP_COPY_PATH`. `php artisan backup:verify [fichier]`.
- Les fichiers (`storage/app/public`, `storage/app/private`) se sauvegardent avec les fichiers du serveur.
- Restauration : `php artisan down`, `php artisan backup:run`, recréer la base, `gunzip -c <fichier> | mysql … db_ettc`, `php artisan migrate --force`, `php artisan up`.

## 10. Plan de développement

Phases du cahier §7. Chaque tâche se termine par ses tests et une vérification dans le navigateur.

| Phase | Contenu | Statut |
|---|---|---|
| 0 | Reprise du socle CSB, retrait des modules hors cahier, identité provisoire ETTC, environnement | **Fait** |
| 1 | Analyse : validation des niveaux, tarifs, durées, règles de gestion, profils (§12) ; maquettes ; logo et couleurs | Avec le centre |
| 2 | **Formations et tarifs** : niveaux, options de durée, tarifs par mode, catalogue public par niveau | **Fait** (2026-11-17) |
| 3 | **Tests de niveau** (gratuit) : présentation, réservation, résultat et niveau attribué | **Fait** (2026-11-17) |
| 4 | **Inscriptions et apprenants** : inscription en ligne et au centre, récapitulatif, compte apprenant enrichi, suivi des dossiers, organisation par niveau / formule / session | **Fait** (2026-11-17) |
| 5 | **Paiements et reçus** : au centre (présentiel) et mobile money (en ligne, déclaré puis vérifié), reçus, impayés, annulations, remboursements ; prestataire avec notification serveur À VALIDER | **Fait** (2026-11-18) |
| 6 | **Séances à distance** : groupes, séances en série, liens Google Meet, supports pédagogiques, calendrier dans l'espace apprenant | **Fait** (2026-11-19) |
| 7 | **Formations personnalisées, traductions, excursion au Ghana** : pages et formulaires dédiés, proposition acceptée en ligne, inscription au tarif accepté | **Fait** (2026-11-19) |
| 8 | **Finances, commission et statistiques** : recettes par période, part du centre / commission, commissions versées et dues, rapports téléchargeables | À faire |
| 9 | Tests et validation avec le centre (calculs financiers, sécurité) ; déploiement HTTPS ; formation du personnel | Avec le centre |
