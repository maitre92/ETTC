<?php

namespace App\Providers;

use App\Http\Controllers\Client\ClientAreaSections;
use App\Models\AppSetting;
use App\Models\Article;
use App\Models\Category;
use App\Models\Client;
use App\Models\ClientDocument;
use App\Models\Demande;
use App\Models\DemandeNote;
use App\Models\Formule;
use App\Models\Inscription;
use App\Models\Media;
use App\Models\Niveau;
use App\Models\Permission;
use App\Models\Prestation;
use App\Models\Redirection;
use App\Models\Temoignage;
use App\Models\TestNiveau;
use App\Models\User;
use App\Services\Users\SuperadminBootstrapper;
use App\Support\SiteSettings;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SuperadminBootstrapper::class);
        $this->app->singleton(SiteSettings::class);
        $this->app->singleton(ClientAreaSections::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Le superadmin contourne l'autorisation (Gate::before), pas la logique métier ni l'audit (CLAUDE.md §4, D16).
        Gate::before(fn ($user, string $ability) => $user instanceof User && $user->isSuperadmin() ? true : null);

        // D14 : 8 caractères au moins, lettres et chiffres.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Page qui suit la connexion : proposer d'installer l'application (partials.pwa-install) — le site pour un
        // client, l'administration pour l'équipe.
        Event::listen(Login::class, function (Login $event) {
            if (in_array($event->guard, ['web', 'client'], true) && app()->bound('session.store')) {
                session()->put('pwa_prompt', $event->guard === 'client' ? 'site' : 'team');
            }
        });

        // Proxy de confiance (config/site.php) : https et nom d'hôte du tunnel repris dans les adresses générées.
        if ($proxies = config('site.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // Superadmins créés automatiquement s'ils n'existent pas (D27) : au lancement du serveur de développement et après
        // chaque migration (déploiement). La première visite de la page de connexion est le filet pour l'hébergement Web.
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if ($event->command === 'serve') {
                app(SuperadminBootstrapper::class)->ensure();
            }
        });
        Event::listen(CommandFinished::class, function (CommandFinished $event) {
            if ($event->exitCode === 0 && in_array($event->command, ['migrate', 'migrate:fresh', 'migrate:refresh', 'permissions:sync'], true)) {
                app(SuperadminBootstrapper::class)->ensure();
            }
        });

        // Formulaires publics (cahier §10 « protection anti-spam ») : 5 envois par minute et 30 par jour par adresse IP.
        RateLimiter::for('demandes', fn (Request $request) => [
            Limit::perMinute(5)->by('demandes-min|'.$request->ip()),
            Limit::perDay(30)->by('demandes-jour|'.$request->ip()),
        ]);

        // Pagination du site public au style Bootstrap 5 (l'administration utilise DataTables).
        Paginator::useBootstrapFive();

        // Paramètres du site (coordonnées, slogan…) disponibles dans toutes les vues du site public et dans les e-mails.
        View::composer(['layouts.site', 'site.*', 'mail.*', 'client.*'], fn ($view) => $view->with('site', app(SiteSettings::class)));

        // Tableau de bord de l'espace apprenant : bloc « Inscriptions » (en attente / total).
        $this->app->make(ClientAreaSections::class)->register(function (Client $client) {
            $inscriptions = Inscription::forClient($client);

            return ['title' => 'Inscriptions', 'icon' => 'bx-book-reader', 'route' => 'client.inscriptions', 'count' => (clone $inscriptions)->count(), 'open' => (clone $inscriptions)->pending()->count(), 'label' => 'en attente'];
        });

        // Tableau de bord de l'espace apprenant : bloc « Tests de niveau » (en cours / total).
        $this->app->make(ClientAreaSections::class)->register(function (Client $client) {
            $tests = TestNiveau::forClient($client);

            return ['title' => 'Tests de niveau', 'icon' => 'bx-target-lock', 'route' => 'client.tests', 'count' => (clone $tests)->count(), 'open' => (clone $tests)->open()->count(), 'label' => 'en cours'];
        });

        // Alias morph stables : ils s'écrivent dans audit_logs et model_has_roles. Chaque module y ajoute ses modèles
        // depuis son propre fournisseur ou ici (CLAUDE.md §6).
        Relation::morphMap([
            'user' => User::class,
            'app_setting' => AppSetting::class,
            'media' => Media::class,
            'category' => Category::class,
            'client' => Client::class,
            'client_document' => ClientDocument::class,
            'article' => Article::class,
            'demande' => Demande::class,
            'demande_note' => DemandeNote::class,
            'niveau' => Niveau::class,
            'formule' => Formule::class,
            'test_niveau' => TestNiveau::class,
            'inscription' => Inscription::class,
            'prestation' => Prestation::class,
            'redirection' => Redirection::class,
            'temoignage' => Temoignage::class,
            'permission' => Permission::class,
            'role' => Role::class,
        ]);
    }
}
