<?php

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/** Gros boutons de l'accueil de l'administration : seulement les gestes permis, chacun vers la bonne fenêtre. */
class DashboardActionsTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    public function test_the_secretary_sees_the_everyday_buttons(): void
    {
        $this->actingAs(User::factory()->commercial()->create())->get('/admin')->assertOk()
            ->assertSee('Inscrire un apprenant')->assertSee('/admin/inscriptions#create-inscription', false)
            ->assertSee('Encaisser un paiement')->assertSee('/admin/paiements#impayes', false)
            ->assertSee('Inscrire au test de niveau')->assertSee('Séances du jour');
        $this->get('/admin/paiements')->assertSee('id="impayes"', false);
    }

    public function test_a_content_manager_does_not_see_cash_buttons(): void
    {
        $this->actingAs(User::factory()->gestionnaire()->create())->get('/admin')->assertOk()
            ->assertDontSee('Encaisser un paiement');
    }
}
