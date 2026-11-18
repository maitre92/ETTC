<?php

namespace App\Services\Dashboard;

use App\Enums\DemandeStatus;
use App\Enums\DemandeType;
use App\Enums\InscriptionStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\TestStatus;
use App\Models\ActivityLog;
use App\Models\Article;
use App\Models\Demande;
use App\Models\Formule;
use App\Models\Inscription;
use App\Models\Media;
use App\Models\Paiement;
use App\Models\Seance;
use App\Models\Temoignage;
use App\Models\TestNiveau;
use App\Models\User;
use App\Services\Finances\FinanceService;
use App\Services\Statistiques\SiteStats;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\Route;

/**
 * Tableau de bord de l'administration (cahier §7 « résumé des demandes récentes, volumes par statut et raccourcis de
 * gestion ») : chaque rôle ne voit que ce qui le concerne, section par section, chacune derrière une permission
 * (jamais une donnée affichée sans le droit de la consulter) :
 *  - Comptes et accès (users.view) et Exploitation (exploitation.view) : l'administrateur ;
 *  - Supervision (superadmin seul) : activité par agent, journal, contrôle des comptes superadmin.
 * Les modules ajoutent leur section ici (demandes commerciales, contenus publiés).
 * Le superadmin, qui passe toutes les permissions (Gate::before), a donc la vue la plus large.
 */
class RealDashboardData
{
    /** Âge maximal d'une sauvegarde avant alerte (sauvegarde quotidienne + marge). */
    public function __construct(private readonly SiteSettings $settings) {}

    /**
     * @return array{heading: string, alerts: list<array<string, string>>, tasks: list<array<string, mixed>>, quick: list<array<string, mixed>>, chart: array<string, mixed>|null, sections: list<array<string, mixed>>}
     */
    public function for(User $user): array
    {
        $dashboard = ['heading' => $this->heading($user), 'alerts' => [], 'tasks' => [], 'quick' => $this->quick($user), 'chart' => null, 'sections' => []];

        if ($user->can('demandes.view')) {
            $this->demandes($user, $dashboard);
        }
        if ($user->can('inscriptions.view')) {
            $this->inscriptions($dashboard);
        }
        if ($user->can('tests.view')) {
            $this->tests($user, $dashboard);
        }
        if ($user->can('paiements.view')) {
            $this->paiements($dashboard);
        }
        if ($user->can('finances.view')) {
            $overall = app(FinanceService::class)->overall();
            $dashboard['sections'][] = ['title' => 'Commission', 'icon' => 'bx bx-line-chart', 'cards' => [
                $this->card('Commission restant à payer', Formule::money(max(0, $overall['due'])), 'depuis le début', 'bx bx-time-five', $overall['due'] > 0 ? 'warning' : 'success'),
                $this->card('Commission déjà versée', Formule::money($overall['paid']), 'depuis le début', 'bx bx-check-circle', 'secondary'),
            ], 'tables' => []];
        }
        if ($user->can('seances.view')) {
            $this->seances($dashboard);
        }
        if ($user->can('statistiques.view')) {
            $this->frequentation($dashboard);
        }
        $this->contenus($user, $dashboard);
        if ($user->can('users.view')) {
            $this->comptes($user, $dashboard);
        }
        if ($user->can('exploitation.view')) {
            $this->exploitation($dashboard);
        }
        if ($user->isSuperadmin()) {
            $this->supervision($dashboard);
        }

        return $dashboard;
    }

    /**
     * Pastilles du menu : éléments qui attendent une action.
     *
     * @return array<string, int>
     */
    public function sidebarBadges(User $user): array
    {
        return array_filter([
            'demandes' => $user->can('demandes.view') ? Demande::where('status', DemandeStatus::Nouvelle->value)->count() : 0,
            'temoignages' => $user->can('temoignages.update') ? Temoignage::where('source', 'client')->where('is_published', false)->count() : 0,
            'inscriptions' => $user->can('inscriptions.view') ? Inscription::where('status', InscriptionStatus::AValider->value)->count() : 0,
            'tests' => $user->can('tests.view') ? TestNiveau::where('status', TestStatus::Demande->value)->count() : 0,
            'paiements' => $user->can('paiements.verify') ? Paiement::where('status', PaymentStatus::AVerifier->value)->count() : 0,
        ]);
    }

    /**
     * Notifications de la cloche : uniquement ce que l'utilisateur peut traiter ou consulter.
     *
     * @return list<array{icon: string, class: string, text: string, link: string}>
     */
    public function notifications(User $user): array
    {
        $items = [];

        if ($user->can('demandes.view')) {
            $new = Demande::where('status', DemandeStatus::Nouvelle->value)->count();
            if ($new > 0 && Route::has('demandes.index')) {
                $items[] = ['icon' => 'bx-envelope', 'class' => 'text-danger', 'text' => "{$new} nouvelle(s) demande(s) à traiter.", 'link' => route('demandes.index')];
            }
            $failed = Demande::whereNotNull('notification_error')->where('created_at', '>=', now()->subDays(7))->count();
            if ($failed > 0) {
                $items[] = ['icon' => 'bx-error', 'class' => 'text-warning', 'text' => "{$failed} notification(s) e-mail non envoyée(s) cette semaine.", 'link' => route('demandes.index')];
            }
        }

        if ($user->can('inscriptions.view') && Route::has('inscriptions.index')) {
            $new = Inscription::where('status', InscriptionStatus::AValider->value)->count();
            if ($new > 0) {
                $items[] = ['icon' => 'bx-user-check', 'class' => 'text-danger', 'text' => "{$new} inscription(s) à valider.", 'link' => route('inscriptions.index')];
            }
        }

        if ($user->can('paiements.verify') && Route::has('paiements.index')) {
            $toVerify = Paiement::where('status', PaymentStatus::AVerifier->value)->count();
            if ($toVerify > 0) {
                $items[] = ['icon' => 'bx-mobile-alt', 'class' => 'text-warning', 'text' => "{$toVerify} paiement(s) mobile money à vérifier.", 'link' => route('paiements.index')];
            }
        }

        if ($user->can('tests.view') && Route::has('tests.index')) {
            $toPlan = TestNiveau::where('status', TestStatus::Demande->value)->count();
            if ($toPlan > 0) {
                $items[] = ['icon' => 'bx-target-lock', 'class' => 'text-danger', 'text' => "{$toPlan} test(s) de niveau à planifier.", 'link' => route('tests.index')];
            }
        }

        if ($user->can('temoignages.update') && Route::has('temoignages.index')) {
            $pending = Temoignage::where('source', 'client')->where('is_published', false)->count();
            if ($pending > 0) {
                $items[] = ['icon' => 'bx-video', 'class' => 'text-warning', 'text' => "{$pending} témoignage(s) de client à valider.", 'link' => route('temoignages.index')];
            }
        }

        return $items;
    }

    /** Demandes commerciales (cahier §7 « résumé des demandes récentes, volumes par statut ») : le commercial. */
    /** Inscriptions (cahier §5 « suivre les inscriptions en attente ») : dossiers à valider, en attente de paiement, par niveau. */
    private function inscriptions(array &$dashboard): void
    {
        $dashboard['tasks'][] = $this->task('Inscriptions à valider', 'Dossiers reçus', Inscription::where('status', InscriptionStatus::AValider->value)->count(), 'bx bx-user-check', 'danger', 'inscriptions.index');
        $dashboard['tasks'][] = $this->task('En attente de paiement', 'Dossiers validés', Inscription::where('status', InscriptionStatus::Validee->value)->count(), 'bx bx-time-five', 'warning', 'inscriptions.index');

        $byNiveau = Inscription::with('niveau')->whereIn('status', [InscriptionStatus::Validee->value, InscriptionStatus::EnCours->value])->get()
            ->groupBy(fn (Inscription $i) => $i->niveau?->name ?? 'À déterminer');
        $dashboard['sections'][] = ['title' => 'Apprenants', 'icon' => 'bx bx-book-reader', 'cards' => [
            $this->card('En formation', (string) Inscription::where('status', InscriptionStatus::EnCours->value)->count(), 'inscriptions en cours', 'bx bx-book-reader', 'primary'),
            $this->card('Nouvelles (30 jours)', (string) Inscription::where('created_at', '>=', now()->subDays(30))->count(), 'inscriptions reçues', 'bx bx-user-plus', 'info'),
        ], 'tables' => [[
            'title' => 'PAR NIVEAU (VALIDÉES ET EN COURS)', 'icon' => 'bx bx-layer',
            'columns' => ['Niveau', 'Inscriptions'],
            'rows' => $byNiveau->map(fn ($items, $name) => [$name, (string) $items->count()])->values()->all(),
        ]]];
    }

    /** Séances (cahier §5) : séances du jour, apprenants payés sans groupe (à placer). */
    private function seances(array &$dashboard): void
    {
        $dashboard['tasks'][] = $this->task('Séances aujourd\'hui', 'Cours programmés ce jour', Seance::whereDate('starts_at', today())->where('is_cancelled', false)->count(), 'bx bx-calendar', 'primary', 'groupes.index');
        $dashboard['tasks'][] = $this->task('Apprenants sans groupe', 'Inscrits à placer dans un groupe', Inscription::whereNull('groupe_id')->whereIn('status', [InscriptionStatus::Validee->value, InscriptionStatus::EnCours->value])->count(), 'bx bx-group', 'warning', 'groupes.index');
    }

    /** Paiements (cahier §5 « Gestion financière ») : déclarations à vérifier, impayés, encaissé du mois. */
    private function paiements(array &$dashboard): void
    {
        $dashboard['tasks'][] = $this->task('Paiements à vérifier', 'Mobile money déclaré par un apprenant', Paiement::where('status', PaymentStatus::AVerifier->value)->count(), 'bx bx-mobile-alt', 'warning', 'paiements.index');
        $unpaid = Inscription::unpaid()->whereIn('status', [InscriptionStatus::Validee->value, InscriptionStatus::EnCours->value, InscriptionStatus::Terminee->value]);
        $dashboard['tasks'][] = $this->task('Impayés', 'Inscriptions avec un reste à payer', (clone $unpaid)->count(), 'bx bx-time-five', 'danger', 'paiements.index');

        $month = now()->startOfMonth();
        $dashboard['sections'][] = ['title' => 'Finances', 'icon' => 'bx bx-wallet', 'cards' => [
            $this->card('Encaissé ce mois', Formule::money((int) Paiement::confirmed()->where('paid_at', '>=', $month)->sum('amount')), 'frais de formation', 'bx bx-wallet', 'success'),
            $this->card('Reste à encaisser', Formule::money((int) (clone $unpaid)->get()->sum(fn (Inscription $i) => $i->balance())), 'inscriptions validées', 'bx bx-time-five', 'danger'),
        ], 'tables' => []];
    }

    /** Tests de niveau (cahier §5 « suivi des candidats en attente de test ») : à planifier et du jour (test gratuit). */
    private function tests(User $user, array &$dashboard): void
    {
        $dashboard['tasks'][] = $this->task('Tests à planifier', 'Candidats en attente d\'une date', TestNiveau::where('status', TestStatus::Demande->value)->count(), 'bx bx-target-lock', 'danger', 'tests.index');
        $dashboard['tasks'][] = $this->task('Tests aujourd\'hui', 'Planifiés pour la journée', TestNiveau::where('status', TestStatus::Planifie->value)->whereDate('scheduled_at', today())->count(), 'bx bx-calendar-check', 'primary', 'tests.index');
    }

    private function demandes(User $user, array &$dashboard): void
    {
        $byStatus = Demande::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $new = (int) ($byStatus[DemandeStatus::Nouvelle->value] ?? 0);
        $mine = Demande::open()->where('assigned_to', $user->id)->count();

        $dashboard['tasks'][] = $this->task('Nouvelles demandes', 'Reçues, pas encore prises en charge', $new, 'bx bx-envelope', 'danger', 'demandes.index');
        $dashboard['tasks'][] = $this->task('Mes demandes en cours', 'Attribuées à moi, non clôturées', $mine, 'bx bx-user-check', 'primary', 'demandes.index');

        $cards = [];
        foreach ([[DemandeStatus::Nouvelle, 'bx bx-envelope', 'danger'], [DemandeStatus::EnCours, 'bx bx-loader-circle', 'warning'], [DemandeStatus::DevisEnvoye, 'bx bx-send', 'info'], [DemandeStatus::Gagnee, 'bx bx-trophy', 'success']] as [$status, $icon, $color]) {
            $cards[] = $this->card($status->label(), (string) ($byStatus[$status->value] ?? 0), $status->meaning(), $icon, $color, $color === 'warning');
        }

        $month = Demande::where('created_at', '>=', now()->startOfMonth());
        $won = (int) (clone $month)->where('status', DemandeStatus::Gagnee->value)->count();
        $total = (int) (clone $month)->count();

        $tables = [
            [
                'title' => 'DERNIÈRES DEMANDES', 'icon' => 'bx bx-envelope',
                'columns' => ['Reçue le', 'Type', 'Demandeur', 'Statut'],
                'rows' => Demande::latest('id')->limit(8)->get()->map(fn (Demande $d) => [
                    $d->created_at->format('d/m/Y H:i'), $d->type->label(), $d->name, ['badge' => $d->status->label(), 'class' => $d->status->badge()],
                ])->all(),
            ],
            [
                'title' => 'CE MOIS-CI', 'icon' => 'bx bx-calendar',
                'columns' => ['Indicateur', 'Valeur'],
                'rows' => array_merge(
                    [['Demandes reçues', (string) $total], ['Acceptées', (string) $won], ['Taux de conversion', $total ? round($won * 100 / $total).' %' : '—']],
                    collect(DemandeType::cases())->map(fn (DemandeType $t) => [$t->label(), (string) (clone $month)->where('type', $t->value)->count()])->all(),
                ),
            ],
        ];

        $dashboard['sections'][] = ['title' => 'Demandes', 'icon' => 'bx bx-briefcase', 'cards' => $cards, 'tables' => $tables, 'chart' => $this->demandesChart(14)];
    }

    /** Demandes reçues et gagnées par jour (graphique du tableau de bord). */
    private function demandesChart(int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $received = Demande::where('created_at', '>=', $from)->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupBy('day')->pluck('total', 'day');
        $won = Demande::where('status', DemandeStatus::Gagnee->value)->where('status_changed_at', '>=', $from)
            ->selectRaw('DATE(status_changed_at) as day, COUNT(*) as total')->groupBy('day')->pluck('total', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $from->copy()->addDays($i);
            $key = $day->toDateString();
            $series[] = ['date' => $key, 'label' => $day->format('d/m'), 'a' => (int) ($received[$key] ?? 0), 'b' => (int) ($won[$key] ?? 0)];
        }

        return [
            'title' => 'DEMANDES DES '.$days.' DERNIERS JOURS',
            'empty' => 'Aucune demande reçue sur les '.$days.' derniers jours.',
            'series' => ['a' => 'Demandes reçues', 'b' => 'Demandes gagnées'],
            'days' => $series,
            'max' => max(1, ...array_map(fn ($d) => max($d['a'], $d['b']), $series)),
            'totals' => ['a' => array_sum(array_column($series, 'a')), 'b' => array_sum(array_column($series, 'b'))],
        ];
    }

    /** Contenus du site (gestionnaire de contenu) : publiés, brouillons, images sans texte alternatif. */
    private function contenus(User $user, array &$dashboard): void
    {
        $modules = [
            ['articles.view', Article::class, 'Actualités', 'bx bx-news', 'dark', 'articles.index'],
        ];

        $cards = [];
        $drafts = [];
        foreach ($modules as [$permission, $model, $label, $icon, $color, $route]) {
            if (! $user->can($permission)) {
                continue;
            }
            $published = $model::where('is_published', true)->count();
            $draftCount = $model::where('is_published', false)->count();
            $cards[] = $this->card($label, (string) $published, $published > 1 ? 'publiés' : 'publié', $icon, $color);
            $drafts[] = [$label, (string) $draftCount, $route];
        }

        if ($cards === []) {
            return;
        }

        if ($user->can('medias.view')) {
            $withoutAlt = Media::whereNull('alt')->count();
            $dashboard['tasks'][] = $this->task('Images sans texte alternatif', 'Accessibilité et référencement', $withoutAlt, 'bx bx-image', 'warning', 'medias.index');
        }

        $tables = [[
            'title' => 'NON PUBLIÉS', 'icon' => 'bx bx-hide',
            'columns' => ['Contenu', 'Non publiés'],
            'rows' => array_map(fn ($row) => [$row[0], (int) $row[1] > 0 ? ['badge' => $row[1], 'class' => 'bg-warning-subtle'] : '0'], $drafts),
        ]];

        $dashboard['sections'][] = ['title' => 'Contenus du site', 'icon' => 'bx bx-globe', 'cards' => $cards, 'tables' => $tables];
    }

    /** Fréquentation du site (statistiques.view) : visites et comptes clients, détail dans « Statistiques du site ». */
    private function frequentation(array &$dashboard): void
    {
        $s = app(SiteStats::class)->summary();
        $dashboard['sections'][] = ['title' => 'Fréquentation du site', 'icon' => 'bx bx-line-chart', 'tables' => [], 'cards' => [
            $this->card('Visites aujourd\'hui', (string) $s['today'], 'personnes venues sur le site', 'bx bx-user-voice', 'primary'),
            $this->card('Visites (7 jours)', (string) $s['week'], '30 jours : '.$s['month'], 'bx bx-group', 'info'),
            $this->card('Comptes apprenants', (string) $s['clients'], '+'.$s['clients_month'].' en 30 jours', 'bx bx-user-plus', 'success'),
        ]];
    }

    /** Comptes et accès (users.view) : périmètre visible de l'acteur (User::visibleTo). */
    private function comptes(User $user, array &$dashboard): void
    {
        $visible = fn () => User::query()->visibleTo($user);

        $cards = [
            $this->card('Utilisateurs actifs', (string) $visible()->active()->count(), $visible()->count().' au total', 'bx bx-group', 'primary'),
            $this->card('Comptes désactivés', (string) $visible()->where('is_active', false)->count(), null, 'bx bx-lock-alt', 'secondary'),
            $this->card('Mots de passe temporaires', (string) $visible()->where('must_change_password', true)->count(), 'à changer à la connexion', 'bx bx-key', 'warning', true),
        ];

        $tables = [[
            'title' => 'DERNIÈRES CONNEXIONS', 'icon' => 'bx bx-log-in',
            'columns' => ['Utilisateur', 'Rôle', 'Dernière connexion', 'Statut'],
            'rows' => $visible()->with('roles')->whereNotNull('last_login_at')->orderByDesc('last_login_at')->limit(8)->get()->map(fn (User $u) => [
                $u->name, $u->roleName()?->label() ?? '—', $u->last_login_at->format('d/m/Y H:i'),
                $u->is_active ? ['badge' => 'Actif', 'class' => 'bg-success-subtle'] : ['badge' => 'Désactivé', 'class' => 'bg-secondary-subtle'],
            ])->all(),
        ]];

        $dashboard['sections'][] = ['title' => 'Comptes et accès', 'icon' => 'bx bx-user-circle', 'cards' => $cards, 'tables' => $tables];
    }

    /** Exploitation (exploitation.view) : destinataire des notifications e-mail (sauvegardes : assurées par l'hébergeur, pas d'alerte ici). */
    private function exploitation(array &$dashboard): void
    {
        $recipient = $this->settings->notificationEmail();
        $mailer = (string) config('mail.default');
        $mailReal = ! in_array($mailer, ['log', 'array'], true);

        $cards = [
            $this->card('Notifications e-mail', $mailReal ? 'Envoi actif' : 'Non configuré', $recipient ? "vers {$recipient}" : 'aucun destinataire', 'bx bx-envelope', $mailReal && $recipient ? 'info' : 'warning', ! ($mailReal && $recipient)),
        ];

        $dashboard['sections'][] = ['title' => 'Exploitation', 'icon' => 'bx bx-server', 'cards' => $cards, 'tables' => []];
    }

    /** Supervision (superadmin) : comptes superadmin, activité par agent, journal d'audit. */
    private function supervision(array &$dashboard): void
    {
        $activeSuperadmins = User::role(RoleName::Superadmin->value)->active()->count();
        if ($activeSuperadmins < 2) {
            $dashboard['alerts'][] = ['class' => 'alert-warning', 'icon' => 'bx bx-error', 'title' => 'Un seul superadmin actif.', 'text' => 'Créez un second compte superadmin (un compte individuel par développeur) : le dernier superadmin actif est protégé et ne peut pas être désactivé.'];
        }

        $since = now()->subDays(30);
        $actions = ActivityLog::where('created_at', '>=', $since)->whereNotNull('user_id')->where('module', '!=', 'auth')
            ->selectRaw('user_id as uid, COUNT(*) as total')->groupBy('user_id')->pluck('total', 'uid');

        $cards = [
            $this->card('Connexions aujourd\'hui', (string) ActivityLog::where('action', 'auth.login')->where('created_at', '>=', now()->startOfDay())->count(), null, 'bx bx-log-in-circle', 'dark'),
            $this->card('Actions journalisées', (string) ActivityLog::where('created_at', '>=', now()->startOfDay())->count(), 'aujourd\'hui', 'bx bx-list-check', 'primary'),
            $this->card('Superadmins actifs', (string) $activeSuperadmins, null, 'bx bx-shield-quarter', $activeSuperadmins < 2 ? 'warning' : 'success', $activeSuperadmins < 2),
        ];

        $tables = [
            [
                'title' => 'ACTIVITÉ PAR AGENT (30 JOURS)', 'icon' => 'bx bx-user-check',
                'columns' => ['Agent', 'Rôle', 'Actions', 'Dernière connexion'],
                'rows' => User::with('roles')->orderBy('name')->get()->map(fn (User $u) => [
                    $u->name, $u->roleName()?->label() ?? '—', (string) ($actions[$u->id] ?? 0), $u->last_login_at?->format('d/m/Y H:i') ?? 'jamais',
                ])->all(),
            ],
            [
                'title' => 'DERNIÈRES ACTIONS', 'icon' => 'bx bx-list-check',
                'columns' => ['Date', 'Auteur', 'Action'],
                'rows' => ActivityLog::latest('id')->limit(10)->get()->map(fn (ActivityLog $log) => [
                    $log->created_at->format('d/m/Y H:i'), $log->user_name ?? '—', $log->description,
                ])->all(),
            ],
        ];

        $dashboard['sections'][] = ['title' => 'Supervision', 'icon' => 'bx bx-show', 'cards' => $cards, 'tables' => $tables];
    }

    private function heading(User $user): string
    {
        return match ($user->roleName()) {
            RoleName::Superadmin => 'Supervision générale',
            RoleName::Administrateur => 'Administration du site',
            RoleName::Gestionnaire => 'Contenus du site',
            RoleName::Commercial => 'Demandes commerciales',
            default => 'Tableau de bord',
        };
    }

    /**
     * Accès rapides selon les permissions (cahier §7 « raccourcis de gestion ») ; les deux premiers s'affichent aussi à
     * droite du fil d'Ariane.
     *
     * @return list<array<string, mixed>>
     */
    private function quick(User $user): array
    {
        $candidates = [
            ['inscriptions.index', 'inscriptions.view', 'Inscriptions', 'bx bx-user-check'],
            ['paiements.index', 'paiements.view', 'Paiements', 'bx bx-wallet'],
            ['groupes.index', 'seances.view', 'Séances et groupes', 'bx bx-calendar'],
            ['finances.index', 'finances.view', 'Finances', 'bx bx-line-chart'],
            ['tests.index', 'tests.view', 'Tests de niveau', 'bx bx-target-lock'],
            ['demandes.index', 'demandes.view', 'Demandes', 'bx bx-envelope'],
            ['articles.create', 'articles.create', 'Rédiger un article', 'bx bx-pencil'],
            ['medias.index', 'medias.view', 'Médias', 'bx bx-images'],
            ['pages.edit', 'pages.update', 'Pages et paramètres', 'bx bx-file'],
            ['users.index', 'users.view', 'Utilisateurs', 'bx bx-user-circle'],
            ['audit.index', 'audit.view', 'Audit', 'bx bx-list-check'],
            ['system.index', 'system.view', 'Système', 'bx bx-server'],
        ];

        $quick = [];
        foreach ($candidates as [$route, $permission, $label, $icon]) {
            if (Route::has($route) && $user->can($permission)) {
                $quick[] = ['label' => $label, 'icon' => $icon, 'route' => $route, 'params' => []];
            }
        }

        return $quick;
    }

    /**
     * @return array<string, mixed>
     */
    private function task(string $label, string $hint, int $count, string $icon, string $color, string $route, array $params = []): array
    {
        return compact('label', 'hint', 'count', 'icon', 'color', 'route', 'params');
    }

    /**
     * @return array<string, mixed>
     */
    private function card(string $label, string $value, ?string $sub, string $icon, string $color, bool $darkText = false): array
    {
        return ['label' => $label, 'value' => $value, 'sub' => $sub, 'icon' => $icon, 'color' => $color, 'darkText' => $darkText];
    }
}
