<?php

namespace Tests\Feature\Modules;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/** Guide d'utilisation : chacun ne voit que les chapitres liés à ses droits (demande du centre, 2026-11-23). */
class AideTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    public function test_each_role_sees_only_the_help_chapters_of_its_rights(): void
    {
        $formateur = User::factory()->withRole(RoleName::Formateur)->create();
        $this->actingAs($formateur)->get('/admin/aide')->assertOk()
            ->assertSee('Mon espace formateur')->assertSee('Se connecter')
            ->assertDontSee('Encaisser un paiement')->assertDontSee('Gérer les comptes de l&#039;équipe', false)->assertDontSee('La caisse');

        $secretaire = User::factory()->commercial()->create();
        $this->actingAs($secretaire)->get('/admin/aide')->assertOk()
            ->assertSee('Encaisser un paiement')->assertSee('Demander l&#039;annulation d&#039;un paiement', false)
            ->assertDontSee('Annuler ou rembourser un paiement')->assertDontSee('Mon espace formateur')->assertDontSee('Donner des droits à son équipe');

        // Un droit ajouté par l'administrateur fait apparaître son chapitre.
        $secretaire->givePermissionTo('paiements.refund');
        $this->get('/admin/aide')->assertSee('Annuler ou rembourser un paiement');

        $this->actingAs(User::factory()->administrateur()->create())->get('/admin/aide')->assertOk()
            ->assertSee('Donner des droits à son équipe')->assertSee('La caisse')->assertSee('Régler l&#039;attestation', false)->assertDontSee('Mon espace formateur');
    }
}
