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
        // Chapitres : une tâche courante de l'administration par chapitre (cahier §15.1 « Transfert »). Chaque étape est
        // une phrase courte. Chaque module ajoute son chapitre.
        $chapters = [
            ['bx bx-log-in', 'Se connecter et se déplacer', [
                'L\'administration s\'ouvre à l\'adresse du site suivie de /'.config('site.admin_path').'. Connectez-vous avec votre numéro de téléphone et votre mot de passe. À la première connexion, choisissez un nouveau mot de passe (8 caractères au moins, lettres et chiffres).',
                'Le menu de gauche ne montre que les écrans auxquels votre rôle donne accès. Une pastille orange signale ce qui attend votre action.',
                'Le tableau de bord résume la situation ; le bouton « Voir le site » ouvre le site public dans un nouvel onglet.',
                'Votre profil (en haut à droite) permet de changer votre nom, votre numéro et votre mot de passe.',
            ]],
            ['bx bx-envelope', 'Traiter les demandes', [
                'Chaque formulaire du site (contact, demande avec pièce jointe) crée une demande « Nouvelle » et envoie un e-mail à l\'équipe. Une pastille dans le menu et la cloche signalent les nouvelles demandes.',
                'Demandes → bouton « œil » : la fiche montre le demandeur, son message, le contenu concerné et la pièce jointe éventuelle (à télécharger).',
                'Faites évoluer le statut : En cours (prise en charge, la demande vous est attribuée), Devis envoyé, Gagnée, ou Perdue / clôturée avec un motif.',
                'Les notes internes (échanges, relances) ne sont visibles que par l\'équipe. « Répondre par e-mail » et « Appeler » ouvrent directement votre messagerie ou votre téléphone.',
                'Si l\'e-mail de notification n\'a pas pu partir, la fiche l\'indique avec un bouton « Réessayer » : la demande, elle, n\'est jamais perdue.',
                '« Exporter (Excel) » télécharge les demandes filtrées (type, statut, période) dans un fichier qui s\'ouvre dans Excel.',
            ]],
            ['bx bx-purchase-tag', 'Modifier les formations et les tarifs', [
                'Formations → Formules et tarifs : chaque formule (2 mois, 3 mois…) a un tarif en ligne et un tarif en présentiel, par mois ou en paiement unique. Bouton crayon : changez un montant puis « Enregistrer » ; il est en ligne aussitôt sur l\'accueil et la page Nos formations, sans recharger l\'écran.',
                'Laissez un tarif vide si le mode n\'est pas proposé (par exemple une formule seulement au centre). Les montants se saisissent librement : « 100 000 », « 100000 » ou « 100 000 FCFA ».',
                'Formations → Niveaux : nom, résumé, objectifs, compétences visées et prérequis (un élément par ligne). Chaque niveau publié a sa fiche sur le site.',
                'Chaque changement de tarif est enregistré dans le journal avec l\'ancien et le nouveau montant.',
            ]],
            ['bx bx-target-lock', 'Suivre les tests de niveau', [
                'Les candidats réservent leur test sur la page « Test de niveau » du site ; ils arrivent « À planifier » (pastille du menu, cloche, e-mail). Un candidat venu au centre ou qui a appelé : Tests de niveau → « Inscrire un candidat ».',
                'Bouton « œil » : la fiche du candidat. « Planifier » fixe la date, le mode (au centre ou à distance) et le lieu ou le lien ; une convocation part par e-mail si le candidat en a laissé un. « Reporter » change la date.',
                'Le test de niveau est gratuit : seule l\'inscription à une formation est payante.',
                'Après le test : niveau attribué, score et appréciation, puis « Enregistrer le résultat ». La fiche propose les formules du niveau et le lien de sa fiche à transmettre au candidat, qui retrouve aussi son résultat dans son espace.',
                'La présentation du test sur le site (format, durée, déroulement) se modifie en bas de la liste des tests.',
            ]],
            ['bx bx-id-card', 'Gérer les inscriptions', [
                'Un apprenant s\'inscrit sur le site (« S\'inscrire maintenant ») : niveau, formule, en ligne ou en présentiel, coordonnées. L\'inscription arrive « À valider » (pastille du menu, cloche, e-mail) avec son numéro INS-….',
                'Le prix est figé au moment de l\'inscription : si vous changez un tarif ensuite, les inscriptions déjà faites gardent le prix accepté par l\'apprenant.',
                'Un apprenant venu au centre : Inscriptions → « Nouvelle inscription ». Après un test de niveau passé, la fiche du candidat propose « Inscrire à une formation » (nom, téléphone et niveau repris).',
                'Sur la fiche : « Valider le dossier » (en attente de paiement), puis « Démarrer la formation » et « Terminer la formation ». La session (ex. Janvier 2027) et les notes internes se règlent dans la fiche.',
                'Annuler demande un motif. Chaque étape est inscrite dans l\'historique de la fiche ; l\'apprenant suit son inscription dans son espace (« Mes inscriptions »).',
            ]],
            ['bx bx-wallet', 'Encaisser les paiements et délivrer les reçus', [
                'Formation en présentiel : l\'apprenant paie au centre. Dans la liste des inscriptions (ou sur sa fiche), cliquez sur le bouton vert « Encaisser » : vérifiez le montant (une mensualité est proposée), choisissez « Espèces » ou le mobile money (la référence du SMS est alors demandée), puis « Encaisser et créer le reçu ». L\'inscription est validée toute seule.',
                'Chaque fiche d\'inscription commence par « Que faire maintenant ? » : un seul bouton, celui de l\'étape suivante. Les actions plus rares (session, notes, annulation, valider sans paiement) sont rangées sous « Autres actions ».',
                'Reçus : bouton « A5 » (feuille, avec cachet et signature) ou « Ticket » (imprimante thermique 80 mm). Chaque reçu porte un QR code : scanné, il affiche la page « Reçu authentique » du site.',
                'Formation en ligne : l\'apprenant paie par mobile money (Orange Money, Wave, Moov Money) puis déclare la référence de la transaction dans son espace. Le paiement arrive « À vérifier » (pastille du menu, cloche, e-mail). Retrouvez la transaction sur le compte marchand, puis cliquez « Confirmer » directement dans la liste (une question vous est posée) ; sinon ouvrez-le et « Rejeter » avec un motif que l\'apprenant verra. Quand le paiement automatique est activé (voir l\'écran Paiements), plus rien n\'est à vérifier : l\'opérateur confirme et le reçu part tout seul.',
                'Les numéros marchands et les consignes se règlent en bas de l\'écran Paiements. Un numéro vide : ce moyen n\'est pas proposé aux apprenants.',
                'Un paiement confirmé fait passer l\'inscription « En cours ». Le tableau « Impayés » liste les inscriptions qui ont encore un reste à payer.',
                'Un paiement ne se supprime jamais : « Annuler » s\'il a été saisi par erreur, « Rembourser » si l\'argent a été rendu (motif obligatoire). Le reçu reste consultable avec son statut. Bouton « Imprimer / PDF » sur chaque reçu.',
            ]],
            ['bx bx-calendar', 'Organiser les groupes et les séances', [
                'Séances et groupes → « Nouveau groupe » : un nom, en ligne ou au centre, le lien Google Meet (un lien collé sans son début est complété tout seul) ou la salle, le niveau et le formateur.',
                'Sur la fiche du groupe, « Que faire maintenant ? » indique l\'étape suivante. « Ajouter des apprenants » propose les inscrits qui correspondent (même mode, même niveau, sans groupe), déjà cochés.',
                '« Programmer les séances » crée toutes les séances d\'un coup : cochez les jours (ex. lundi et mercredi), l\'heure, la durée et la période. Chaque apprenant reçoit son calendrier par e-mail ; relancer la même série ne crée pas de doublon.',
                'Annuler une séance : bouton « Annuler » puis le motif, envoyé aux apprenants. Supports : un fichier (PDF, Word, audio… 20 Mo au plus) ou un lien (YouTube, Drive) ; visibles seulement par les apprenants du groupe.',
                'L\'apprenant voit « Mon calendrier » dans son espace : prochaine séance, bouton « Rejoindre » actif 15 minutes avant, supports à télécharger.',
            ]],
            ['bx bx-file', 'Modifier les pages et les coordonnées', [
                'Pages et paramètres → onglets Accueil, À propos, Pages légales : textes du site. Les textes longs utilisent le Markdown : ## Titre, **gras**, - liste.',
                'Les onglets Entreprise, Coordonnées, Réseaux sociaux et Référencement sont réservés à l\'administrateur : téléphones, WhatsApp, e-mail, adresse, horaires, liens sociaux.',
                'Un champ vidé disparaît du site (par exemple un second téléphone). Chaque modification est enregistrée dans le journal.',
            ]],
            ['bx bx-images', 'Ajouter des images', [
                'Médias → « Téléverser » : images JPEG, PNG ou WebP de 5 Mo au plus. Elles sont redimensionnées automatiquement et leurs données de localisation retirées.',
                'Renseignez le texte alternatif (description de l\'image) : il sert aux personnes malvoyantes et au référencement. La pastille « Sans texte alternatif » les retrouve.',
                'Dans les fiches (formation ou service, article, témoignage), choisissez une image de la bibliothèque ou envoyez-en une nouvelle directement. Une image utilisée ne peut pas être supprimée.',
            ]],
            ['bx bx-news', 'Rédiger une actualité', [
                'Actualités → « Rédiger » : titre, catégorie, texte en Markdown (onglet « Aperçu » pour voir la mise en forme), image de couverture.',
                'Laissez la date de publication vide pour publier tout de suite, ou choisissez une date future pour programmer l\'article.',
                'Le bouton « œil » de la liste montre l\'article tel qu\'il apparaîtra sur le site, même s\'il n\'est pas publié.',
            ]],
            ['bx bx-search-alt', 'Soigner le référencement', [
                'Chaque fiche a un bloc « Référencement » : titre (60 caractères), méta-description (160 caractères) et image de partage (aperçu WhatsApp, Facebook). Vides, des valeurs sont déduites du contenu.',
                'Changer l\'adresse d\'une fiche crée automatiquement une redirection : les anciens liens continuent de fonctionner.',
                'Le plan du site (sitemap.xml) se met à jour seul ; déclarez-le dans Google Search Console (code de vérification dans Pages et paramètres → Référencement).',
            ]],
            ['bx bx-list-plus', 'Saisir plusieurs éléments d\'un coup', [
                'Dans les listes (services, témoignages, catégories, apprenants), « Saisie en lot / import » ouvre une grille : « Ajouter une ligne » pour en saisir plusieurs, ou collez des lignes copiées depuis Excel.',
                '« Modèle Excel » télécharge un fichier prêt à remplir (listes déroulantes, mode d\'emploi) ; « Importer un fichier » l\'envoie. Tout ou rien : la moindre erreur est signalée ligne par ligne et rien n\'est enregistré.',
            ]],
            ['bx bx-video', 'Témoignages en vidéo', [
                'Témoignages → « Ajouter » : nom, fonction, organisation, note, texte, photo, et une vidéo (envoyée depuis votre ordinateur ou téléphone, 300 Mo au plus) ou un lien YouTube / Facebook / TikTok.',
                'La vidéo envoyée est convertie en quelques minutes (format lisible sur tous les téléphones, image d\'aperçu, durée) : « Préparation… » s\'affiche en attendant.',
                'Vos clients peuvent envoyer eux-mêmes leur témoignage depuis la page « Partager mon expérience » du site : il arrive « À modérer » (pastille, cloche, e-mail, WhatsApp). Regardez-le, puis cliquez sur le bouton vert « Valider » (au téléphone : sous le nom de l\'auteur) : il est publié aussitôt. La pastille « À modérer » n\'affiche que ceux qui attendent. Leur téléphone ou e-mail n\'est jamais publié.',
                '« Mis en avant » place un témoignage en premier sur le site et l\'accueil.',
            ]],
            ['bx bx-support', 'Espace apprenant', [
                'Les apprenants créent leur compte sur le site (« Mon espace » en haut à droite) ; vous pouvez aussi les créer depuis Clients (une invitation leur est envoyée) et déposer des documents dans leur espace.',
            ]],
            ['bx bx-user-circle', 'Gérer les comptes', [
                'Paramètres → Utilisateurs → « Ajouter » : nom, numéro de téléphone et rôle (Administrateur, Gestionnaire de contenu, Commercial).',
                'Sans mot de passe saisi, un mot de passe temporaire est généré et affiché une seule fois : transmettez-le à la personne, elle le changera à sa première connexion.',
                'Un compte qui n\'est plus utilisé se désactive (accès coupé immédiatement) ; un mot de passe oublié se réinitialise depuis la liste.',
            ]],
        ];
    @endphp

    <div class="card">
        <div class="card-header card-header-brand">
            <h6 class="mb-0 text-white"><i class='bx bx-help-circle me-2'></i>GUIDE D'UTILISATION DE L'ADMINISTRATION</h6>
        </div>
        <div class="card-body">
            <p class="text-muted">Ce guide reprend les tâches courantes de l'administration du site, dans l'ordre où on les découvre.</p>
            <div class="accordion" id="aide">
                @foreach ($chapters as $n => [$icon, $title, $steps])
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
