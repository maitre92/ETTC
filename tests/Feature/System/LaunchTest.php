<?php

namespace Tests\Feature\System;

use App\Models\Article;
use App\Models\Demande;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\Temoignage;
use Database\Seeders\ContenuInitialSeeder;
use Database\Seeders\DonneesTestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Mise en ligne : liste de contrôle (écran Système, `site:verifier`) et suppression des seules données de test.
 */
class LaunchTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    public function test_the_launch_checklist_shows_what_remains_to_do(): void
    {
        $superadmin = $this->superadmin();
        $this->actingAs($superadmin)->get('/admin/system')->assertOk()->assertSee('PRÊT POUR LA MISE EN LIGNE')->assertSee('Mode production');
        $this->artisan('site:verifier')->expectsOutputToContain('Mode production')->assertExitCode(1);
    }

    public function test_only_test_data_is_purged(): void
    {
        $this->superadmin();
        $this->seed([ContenuInitialSeeder::class, DonneesTestSeeder::class]);
        $real = Demande::factory()->create(['email' => 'vrai@client.ml', 'name' => 'Vrai client']);
        $realInscription = Inscription::factory()->create(['email' => 'vrai@apprenant.ml']);
        $this->assertGreaterThan(0, Paiement::count());

        $this->artisan('donnees-test:supprimer', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Inscription::withTrashed()->where('email', 'like', '%@test.ettc.ml')->count());
        $this->assertSame(0, Paiement::withTrashed()->count());
        $this->assertSame(0, Article::withTrashed()->where('title', 'like', '%(test)%')->count());
        $this->assertSame(0, Temoignage::withTrashed()->count(), 'Tous les témoignages fictifs, même sans « (test) » dans l\'organisation.');
        $this->assertNotNull($real->fresh());
        $this->assertNotNull($realInscription->fresh());
        $this->artisan('donnees-test:supprimer', ['--force' => true])->expectsOutput('Aucune donnée de test à supprimer.');
    }
}
