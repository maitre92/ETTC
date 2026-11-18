<?php

namespace Tests\Feature\Web;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/** Flèche « Retour » : sur toutes les pages intérieures (site, espace apprenant, administration), pas sur l'accueil. */
class BackArrowTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    public function test_inner_public_pages_have_a_back_arrow_but_not_the_home(): void
    {
        foreach (['/a-propos', '/formations', '/traduction', '/excursion-ghana', '/temoignages', '/contact'] as $uri) {
            $this->get($uri)->assertOk()->assertSee('class="page-back" data-back', false);
        }
        $this->get('/')->assertOk()->assertDontSee('class="page-back"', false);
    }

    public function test_the_learner_area_and_the_admin_have_it_too(): void
    {
        $client = Client::factory()->create();
        $this->actingAs($client, 'client')->get('/espace-client/paiements')->assertOk()->assertSee('class="page-back" data-back', false);
        $this->get('/espace-client')->assertOk()->assertDontSee('class="page-back"', false);

        $this->syncPermissions();
        $this->actingAs(User::factory()->administrateur()->create())->get('/admin/inscriptions')->assertOk()->assertSee('nv-back', false);
    }
}
