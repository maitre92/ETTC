<?php

namespace Tests\Feature\Web;

use App\Enums\InscriptionStatus;
use App\Models\Client;
use App\Models\Groupe;
use App\Models\Inscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/** Comptes apprenants (cahier §5 « Gestion des apprenants ») : liste, fiche avec inscriptions, groupe et paiement. */
class ComptesApprenantsTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    public function test_the_learner_sheet_shows_inscriptions_group_and_payment_state(): void
    {
        $this->syncPermissions();
        $client = Client::factory()->create(['name' => 'Awa Diallo']);
        $groupe = Groupe::factory()->create(['name' => 'Soir A']);
        Inscription::factory()->create(['client_id' => $client->id, 'number' => 'INS-2026-0777', 'groupe_id' => $groupe->id, 'status' => InscriptionStatus::EnCours]);
        $this->actingAs(User::factory()->administrateur()->create());

        $this->get('/admin/clients')->assertOk()->assertSee('COMPTES APPRENANTS')->assertSee('Awa Diallo');
        $this->get("/admin/clients/{$client->id}")->assertOk()->assertSee("FICHE DE L'APPRENANT", false)
            ->assertSee('INS-2026-0777')->assertSee('groupe Soir A')->assertSee('Non payée');
    }
}
