@extends('layouts.admin')

@section('title', 'Aide')

@section('content')
    <div class="page-breadcrumb d-flex flex-wrap gap-2 align-items-center mb-3">
        <div class="breadcrumb-title pe-3">Aide</div>
        <div class="ps-3">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 p-0">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}"><i class="bx bx-home-alt"></i></a></li>
                    <li class="breadcrumb-item active" aria-current="page">Guide d'utilisation</li>
                </ol>
            </nav>
        </div>
    </div>
    <hr />

    @php
        // Guide d'utilisation (cahier §15.1 « Transfert ») : un chapitre par tâche ; chacun ne voit QUE les chapitres de ce
        // qu'il peut faire (droits de son rôle et ceux ajoutés) — demande du centre, 2026-11-23. Chapitre :
        // [icône, titre, droits (un seul suffit ; [] = tout le monde), étapes].
        $all = [
            ['bx bx-log-in', 'Se connecter et se déplacer', [], [
                'Connectez-vous avec votre numéro de téléphone et votre mot de passe. À la première connexion, choisissez un nouveau mot de passe.',
                'Le menu de gauche ne montre que les écrans de votre rôle ; une pastille orange signale ce qui attend votre action, la cloche (en haut) aussi.',
                'Votre profil (en haut à droite) : nom, numéro, mot de passe, « Installer l\'application » sur le téléphone.',
            ]],
            ['bx bx-chalkboard', 'Mon espace formateur', ['formateurs.space'], [
                'À la connexion, vous arrivez sur « Mes groupes » : vos séances du jour (bouton « Ouvrir Google Meet »), vos groupes, vos formations personnalisées, votre rémunération.',
                'Sur chaque groupe : « Déposer un support de cours » (fichier PDF, Word, audio, vidéo — 20 Mo — ou lien YouTube / Drive), « Écrire au groupe » (vos apprenants peuvent être prévenus par e-mail), « Séance » (ajouter une séance).',
                'Les messages de vos apprenants arrivent dans la discussion du groupe ; une pastille rouge les signale.',
                'Tests de niveau qui vous sont confiés : faites passer le test (ou écoutez l\'oral d\'un test en ligne), puis choisissez le niveau et « Enregistrer le résultat ». Les frais d\'inscription de l\'apprenant vous reviennent.',
                'Ma rémunération : formation personnalisée 1/3 de la mensualité, en groupe 20 %, test de niveau les frais d\'inscription ; sur ce que vos apprenants ont payé.',
            ]],
            ['bx bx-user-plus', 'Inscrire un apprenant au centre', ['inscriptions.create'], [
                'Tableau de bord → « Inscrire un apprenant » (ou Inscriptions → Ajouter) : tapez d\'abord le téléphone — une personne déjà connue est remplie toute seule.',
                'Choisissez le niveau, la formule et le mode : le total s\'affiche (frais de formation, promotion, frais d\'inscription). Un code promo ? Saisissez-le, ou cliquez sur « Appliquer » si une promotion est proposée.',
                '« Sans frais d\'inscription » : pour un ancien apprenant qui se réinscrit.',
                'S\'il paie tout de suite : laissez « L\'apprenant paie maintenant », indiquez le montant et le moyen (espèces, mobile money, banque) ; le reçu est créé.',
            ]],
            ['bx bx-id-card', 'Suivre une inscription', ['inscriptions.view'], [
                'Inscriptions → œil : la fiche montre le tarif détaillé, les paiements, le groupe et « Que faire maintenant ? » (une seule action conseillée).',
                'Formation personnalisée : choisissez son formateur sur la fiche.',
                'Fin de formation : « Terminer la formation ». La fin prévue (début + durée) se modifie sur la fiche si besoin.',
            ]],
            ['bx bx-money', 'Encaisser un paiement', ['paiements.record'], [
                'Paiements → « Impayés » (ou la fiche de l\'inscription) → « Encaisser » : « Prochaine échéance » ou « Tout le reste » remplissent le montant.',
                'Choisissez le moyen : espèces, mobile money (Orange Money, Wave, Moov) ou banque ; une référence est demandée hors espèces.',
                'Le reçu (A5 ou ticket) s\'imprime aussitôt ; l\'apprenant le reçoit aussi par e-mail et dans son espace.',
                'Un paiement déjà déclaré en ligne n\'est jamais encaissé une seconde fois : seul le reliquat est proposé.',
            ]],
            ['bx bx-check-shield', 'Vérifier un paiement déclaré en ligne', ['paiements.verify'], [
                'Un apprenant qui paie par mobile money (code marchand, QR ou numéro) donne la référence du SMS : le paiement arrive « À vérifier » (pastille, cloche, e-mail).',
                'Retrouvez la référence sur le compte marchand du centre, puis « Confirmer » (reçu envoyé, inscription confirmée) ou « Rejeter » avec un motif.',
            ]],
            ['bx bx-undo', 'Annuler ou rembourser un paiement', ['paiements.refund'], [
                'Fiche du paiement → « Annuler (erreur) » si aucun argent n\'a été reçu, « Rembourser » si l\'argent a été rendu ; un motif est obligatoire.',
                'Les demandes de la secrétaire arrivent en haut de Paiements (et dans la cloche) : « Valider la demande » ou « Refuser ».',
            ]],
            ['bx bx-undo', 'Demander l\'annulation d\'un paiement', ['paiements.request_cancel'], [
                'Fiche du paiement → « Demander une annulation » : annulation (erreur) ou remboursement, avec le motif.',
                'L\'administrateur valide ou refuse ; la fiche indique « En attente de la validation de l\'administrateur ».',
            ]],
            ['bx bx-mobile-alt', 'Moyens de paiement en ligne', ['paiements.configure'], [
                'Paiements → « Moyens de paiement » : pour chaque opérateur, le code marchand (ex. #144#8*754830*code secret#), le numéro et le QR à scanner.',
                'Le code marchand est expliqué pas à pas aux apprenants (diaporama) ; « Qui reçoit les paiements à vérifier » : l\'adresse de la personne qui contrôle.',
            ]],
            ['bx bx-transfer', 'La caisse', ['caisse.view'], [
                'Finances → Caisse : soldes du jour (espèces, mobile money, banque) et journal ; les paiements des apprenants y entrent tout seuls.',
                '« Revenu » (traduction, vente…), « Dépense » (loyer, électricité… avec la photo de la facture), « Transfert » (ex. dépôt des espèces à la banque).',
                'Première utilisation : enregistrez un revenu « Solde d\'ouverture » pour l\'argent déjà en caisse.',
            ]],
            ['bx bx-x-circle', 'Annuler un mouvement de caisse, rapports', ['caisse.cancel', 'caisse.export'], [
                'Un mouvement ne se supprime jamais : bouton « × » et un motif (il reste visible, barré).',
                'PDF et Excel : choisissez la période puis « PDF » ou « Excel ».',
            ]],
            ['bx bx-target-lock', 'Les tests de niveau', ['tests.view'], [
                'Tests de niveau : les demandes du site arrivent « À planifier ». Ouvrez-en une, fixez la date, le mode (au centre ou à distance) et, si besoin, le formateur qui fait passer le test (les frais d\'inscription lui reviendront).',
                'Questionnaire en document (PDF, Word) : déposez-le sur la fiche ; le candidat le télécharge depuis sa convocation et renvoie ses réponses (photo, PDF).',
                'Tests passés EN LIGNE : le score et le niveau provisoire sont déjà là ; écoutez l\'oral (ou confiez-le à un formateur) puis confirmez le niveau.',
                'Après le test : « Inscrire à une formation » reprend l\'identité et le niveau.',
            ]],
            ['bx bx-list-check', 'Le test en ligne et ses questions', ['tests.configure'], [
                'Tests de niveau → Questions : saisissez une question (réponses une par ligne, « * » devant la bonne) ou un sujet oral (partie « speaking »).',
                'Importer un questionnaire : « Saisie en lot / import » (Excel, CSV, modèle fourni) ou un fichier Word (1. question, A) réponses, * devant la bonne, « Speaking: » sujet oral).',
                'Réglages : nombre de questions, de sujets oraux et durée. Score minimal de chaque niveau : Formations → Niveaux.',
            ]],
            ['bx bx-group', 'Groupes et séances', ['seances.view'], [
                'Séances et groupes : « Répartir automatiquement » place les apprenants sans groupe (par niveau et mode, capacité au choix).',
                'Fiche d\'un groupe : formateur, lien Google Meet, capacité, apprenants, séances (une ou une série), supports, discussion, annonces.',
            ]],
            ['bx bx-award', 'Délivrer les attestations', ['inscriptions.attestation'], [
                'Conditions : formation terminée, payée en totalité, fin prévue atteinte (la fiche les montre cochées ou non).',
                'Un apprenant : fiche de l\'inscription → « Délivrer l\'attestation ». Tout un groupe : fiche du groupe → « Attestations » (un seul PDF).',
                'Formation en ligne : l\'apprenant la reçoit par e-mail et dans son espace ; en présentiel : « à retirer au centre ». Une réimpression porte « DUPLICATE ».',
            ]],
            ['bx bx-pen', 'Régler l\'attestation', ['attestations.configure'], [
                'Formations → Attestations : signataire, coordonnées de l\'en-tête, signature (tracée à l\'écran ou déposée) et sceau (photo du cachet, traité pour être lisible).',
                'Regardez l\'aperçu avant « Enregistrer » ; signature et sceau servent aussi sur les reçus et les fiches de rémunération.',
            ]],
            ['bx bx-purchase-tag', 'Formules, tarifs et promotions', ['formations.update', 'formations.create'], [
                'Formations → Formules et tarifs : tarif en ligne et en présentiel, par mois ou unique ; frais d\'inscription (bloc du bas).',
                'Formations → Promotions : réduction en % ou en montant, formule, dates, places, code (généré si vide), affiche facultative ; elle défile sur le site.',
                'Formations → Niveaux : présentation et score minimal au test en ligne.',
            ]],
            ['bx bx-envelope', 'Traiter les demandes', ['demandes.view'], [
                'Demandes : messages et demandes du site (contact, traduction, formation personnalisée, excursion). Une pastille signale les nouvelles.',
                'Statuts : En cours, Proposition envoyée (montant + détail : le visiteur l\'accepte d\'un clic), Acceptée, Perdue. Notes internes invisibles du visiteur.',
                'Formation personnalisée acceptée : « Inscrire » crée l\'inscription au prix accepté.',
            ]],
            ['bx bx-user', 'Comptes apprenants', ['clients.view'], [
                'Comptes apprenants : créez un compte (une invitation part par e-mail), déposez des documents dans l\'espace de l\'apprenant.',
            ]],
            ['bx bx-message-square-dots', 'Témoignages', ['temoignages.view'], [
                'Les témoignages envoyés par les apprenants arrivent « À modérer » : regardez-les puis « Valider » ; « Mis en avant » les place en premier.',
            ]],
            ['bx bx-news', 'Actualités et pages du site', ['articles.view', 'pages.update'], [
                'Actualités : rédigez, prévisualisez, publiez (ou programmez la date).',
                'Pages et paramètres : textes de l\'accueil, à propos, services, pages légales, coordonnées, logo.',
            ]],
            ['bx bx-line-chart', 'Finances et commission', ['finances.view'], [
                'Finances et commission : recettes par période, part du centre, commission du développeur, versements ; rapport PDF et Excel.',
            ]],
            ['bx bx-user-voice', 'Rémunération des formateurs', ['remunerations.view'], [
                'Finances → Rémunération des formateurs : choisissez le mois ; pour chaque formateur, le dû (détail par paiement), le versé, le reste.',
                '« Payer » enregistre le versement (il sort de la caisse) ; « Fiche » donne le PDF à signer. Paiements sans formateur : attribuez-le.',
            ]],
            ['bx bx-bar-chart-alt-2', 'Statistiques du site', ['statistiques.view'], [
                'Statistiques du site : visites, pages vues, provenance, appareils, comptes créés et conversion.',
            ]],
            ['bx bx-user-circle', 'Gérer les comptes de l\'équipe', ['users.view'], [
                'Utilisateurs → « Ajouter » : nom, téléphone et rôle (Secrétaire, Gestionnaire, Formateur…). Un mot de passe temporaire s\'affiche une fois : transmettez-le.',
                'Un compte inutilisé se désactive ; un mot de passe oublié se réinitialise depuis la liste.',
            ]],
            ['bx bx-key', 'Donner des droits à son équipe', ['equipe.permissions'], [
                'Administration → Permissions de l\'équipe : choisissez la personne et cochez les droits à ajouter (parmi les vôtres) ; ceux de son rôle sont grisés.',
            ]],
            ['bx bx-history', 'Surveiller l\'activité', ['activite.view', 'connexions.view'], [
                'Journal d\'activité : chaque action de votre équipe (qui, quand, quoi).',
                'Qui est en ligne : l\'équipe et les apprenants connectés, la date et l\'heure de connexion, la dernière activité.',
            ]],
        ];
        $user = auth()->user();
        $chapters = array_values(array_filter($all, fn ($c) => $c[2] === [] || collect($c[2])->contains(fn ($p) => $user->can($p))));
    @endphp

    <div class="card">
        <div class="card-header card-header-brand">
            <h6 class="mb-0 text-white"><i class='bx bx-help-circle me-2'></i>GUIDE D'UTILISATION DE L'ADMINISTRATION</h6>
        </div>
        <div class="card-body">
            <p class="text-muted">Ce guide ne montre que ce que vous pouvez faire avec votre rôle ({{ auth()->user()->roleName()?->label() }}) : {{ count($chapters) }} chapitre(s).</p>
            <div class="accordion" id="aide">
                @foreach ($chapters as $n => [$icon, $title, $rights, $steps])
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="aide-h{{ $n }}">
                            <button class="accordion-button {{ $n ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#aide-c{{ $n }}" aria-expanded="{{ $n ? 'false' : 'true' }}" aria-controls="aide-c{{ $n }}">
                                <i class='{{ $icon }} me-2'></i>{{ $n + 1 }}. {{ $title }}
                            </button>
                        </h2>
                        <div id="aide-c{{ $n }}" class="accordion-collapse collapse {{ $n ? '' : 'show' }}" aria-labelledby="aide-h{{ $n }}" data-bs-parent="#aide">
                            <div class="accordion-body">
                                <ol class="mb-0">
                                    @foreach ($steps as $step)
                                        <li class="mb-1">{{ $step }}</li>
                                    @endforeach
                                </ol>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
