<?php

namespace Tests\Feature\Database;

use App\Models\Article;
use App\Models\Client;
use App\Models\Demande;
use App\Models\Temoignage;
use App\Models\TestNiveau;
use Database\Seeders\ContenuInitialSeeder;
use Database\Seeders\DonneesTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/** Jeu de données de test : actualités, témoignages, apprenants, demandes ; idempotent, jamais en production. */
class DonneesTestSeederTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    public function test_it_fills_every_screen_and_can_be_run_twice(): void
    {
        $this->superadmin();
        $this->seed([ContenuInitialSeeder::class, DonneesTestSeeder::class, DonneesTestSeeder::class]);

        $this->assertSame(3, Article::count());
        $this->assertSame(3, Temoignage::count());
        $this->assertSame(3, Client::count());
        $this->assertSame(5, Demande::count());
        $this->assertSame(3, TestNiveau::count());
        $this->assertSame(1, TestNiveau::whereNotNull('niveau_id')->count());

        $this->get('/')->assertOk()->assertSee('Aminata Coulibaly');
        $this->get('/actualites')->assertOk()->assertSee('Ouverture des inscriptions')->assertSee('Club de conversation du samedi (test)');
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(RuntimeException::class);
        // Appel direct : en production, db:seed demanderait d'abord une confirmation interactive.
        $this->app->call([new DonneesTestSeeder, 'run']);
    }
}
