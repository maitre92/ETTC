<?php

namespace Tests\Feature\Modules;

use App\Enums\Billing;
use App\Models\ActivityLog;
use App\Models\Formule;
use App\Models\Niveau;
use App\Models\User;
use Database\Seeders\ContenuInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Formations et tarifs (cahier ETTC §3.2, §3.3, §3.4, §5) : niveaux, formules et tarifs par mode, modifiables à tout moment depuis l'administration, audités, et lus par l'accueil et le catalogue public.
 */
class FormationsTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    public function test_the_initial_catalogue_follows_the_specification_and_never_overwrites_a_price(): void
    {
        $this->seed(ContenuInitialSeeder::class);

        $this->assertSame(['Débutant', 'Intermédiaire', 'Avancé'], Niveau::ordered()->pluck('name')->all());
        $deux = Formule::where('duration_months', 2)->firstOrFail();
        $this->assertSame([100000, 50000], [$deux->price_online, $deux->price_onsite]);
        $this->assertSame(Billing::Mensuel, $deux->billing);
        $this->assertSame(200000, $deux->total('price_online'));

        $deux->update(['price_online' => 90000]);
        $this->seed(ContenuInitialSeeder::class);
        $this->assertSame(90000, $deux->fresh()->price_online, 'Un tarif réglé par le centre n\'est jamais écrasé.');
        $this->assertSame(2, Formule::count());
    }

    public function test_an_administrator_changes_a_price_and_the_site_shows_it_at_once(): void
    {
        $formule = Formule::factory()->create(['name' => 'Formule 2 mois', 'duration_months' => 2, 'price_online' => 100000, 'price_onsite' => 50000]);
        $this->actingAs(User::factory()->administrateur()->create());

        $this->get('/admin/formations/formules')->assertOk()->assertSee('FORMULES ET TARIFS')->assertSee('Formule 2 mois')
            ->assertSee('data-crud="formations-formules"', false)->assertSee('#edit-formations-formules-'.$formule->id, false);

        $this->put("/admin/formations/formules/{$formule->id}", [
            'formule_id' => $formule->id, 'name' => 'Formule 2 mois', 'duration_months' => 2, 'points' => "Formation intensive\nSuivi personnalisé",
            'price_online' => '120 000 FCFA', 'price_onsite' => '', 'billing' => 'unique', 'sort_order' => 1, 'is_published' => '1',
        ])->assertRedirect('/admin/formations/formules')->assertSessionHas('status');

        $formule->refresh();
        $this->assertSame(120000, $formule->price_online);
        $this->assertNull($formule->price_onsite, 'Tarif vide = mode non proposé.');
        $this->assertSame(Billing::Unique, $formule->billing);

        $log = ActivityLog::where('subject_type', 'formule')->where('action', 'like', '%updated%')->latest('id')->firstOrFail();
        $this->assertStringContainsString('100000', json_encode($log->old_values));
        $this->assertStringContainsString('120000', json_encode($log->new_values));

        $home = $this->get('/')->assertOk();
        $home->assertSee("120\u{202F}000 FCFA")->assertSee('(paiement unique)')->assertDontSee("100\u{202F}000 FCFA");
        $this->get('/formations')->assertOk()->assertSee("120\u{202F}000 FCFA");
    }

    public function test_at_least_one_price_is_required_and_errors_come_back_as_json_for_ajax(): void
    {
        $this->actingAs(User::factory()->gestionnaire()->create());

        $this->postJson('/admin/formations/formules', ['name' => 'Sans prix', 'duration_months' => 2, 'billing' => 'mois', 'sort_order' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors(['price_online', 'price_onsite']);
        $this->postJson('/admin/formations/formules', ['name' => 'Durée folle', 'duration_months' => 99, 'price_online' => '1000', 'billing' => 'trimestre', 'sort_order' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors(['duration_months', 'billing']);
        $this->assertSame(0, Formule::count());

        // Requête AJAX réussie : la redirection mène à la liste à jour, avec le message relu par crud-ajax.js.
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->followingRedirects()
            ->post('/admin/formations/formules', ['name' => 'Formule 1 mois', 'duration_months' => 1, 'price_onsite' => '30000', 'billing' => 'unique', 'sort_order' => 0])
            ->assertOk()->assertSee('data-crud-status', false)->assertSee('Formule « Formule 1 mois » créée.');
    }

    public function test_the_level_test_is_free_everywhere(): void
    {
        Formule::factory()->create();

        $this->get('/')->assertOk()->assertSee('Gratuit');
        $this->get('/formations')->assertOk()->assertSee('Gratuit');
        $this->get('/test-de-niveau')->assertOk()->assertSee('Un test gratuit')->assertDontSee('FCFA</span>', false);
        $this->actingAs(User::factory()->administrateur()->create())->get('/admin/formations/formules')->assertOk()->assertDontSee('FRAIS DU TEST');
        $this->assertFalse(Route::has('formations.test-fee.update'), 'Plus de réglage des frais du test.');
        $this->assertFalse(Route::has('tests.payment'), 'Plus d\'encaissement du test.');
    }

    public function test_no_public_page_shows_a_placeholder_to_complete(): void
    {
        foreach (['/', '/a-propos', '/formations', '/test-de-niveau', '/services', '/contact', '/mentions-legales', '/confidentialite'] as $uri) {
            $html = mb_strtolower($this->get($uri)->assertOk()->getContent());
            $this->assertStringNotContainsString('à compléter', $html, "$uri affiche un texte « à compléter ».");
        }
    }

    public function test_permissions_by_role(): void
    {
        $formule = Formule::factory()->create();

        $commercial = User::factory()->commercial()->create();
        $this->actingAs($commercial)->get('/admin/formations/formules')->assertOk()->assertDontSee('data-bs-target="#create-formations-formules"', false);
        $this->put("/admin/formations/formules/{$formule->id}", ['name' => 'X'])->assertForbidden();

        $this->actingAs(User::factory()->gestionnaire()->create())->delete("/admin/formations/formules/{$formule->id}")->assertForbidden();
        $this->actingAs(User::factory()->administrateur()->create())->delete("/admin/formations/formules/{$formule->id}")->assertRedirect();
        $this->assertSoftDeleted($formule);
    }

    public function test_levels_have_public_sheets_and_hidden_content_stays_private(): void
    {
        $niveau = Niveau::factory()->create(['name' => 'Intermédiaire', 'objectives' => "Tenir une conversation\nDonner son avis", 'prerequisites' => null]);
        Niveau::factory()->hidden()->create(['name' => 'Niveau secret']);
        Formule::factory()->create(['name' => 'Formule visible']);
        Formule::factory()->hidden()->create(['name' => 'Formule cachée']);

        $this->get('/formations')->assertOk()->assertSee('Intermédiaire')->assertSee('Formule visible')
            ->assertDontSee('Niveau secret')->assertDontSee('Formule cachée');
        $this->get('/formations/'.$niveau->slug)->assertOk()->assertSee('Anglais — niveau Intermédiaire')
            ->assertSee('Tenir une conversation')->assertSee('Aucun prérequis')->assertSee('Formule visible');
        $this->get('/formations/niveau-secret')->assertNotFound();

        // Adresse changée : l'ancienne redirige (301).
        $this->actingAs(User::factory()->administrateur()->create())->put("/admin/formations/niveaux/{$niveau->id}", [
            'niveau_id' => $niveau->id, 'name' => 'Intermédiaire', 'summary' => 'Résumé', 'sort_order' => 0, 'is_published' => '1',
        ])->assertRedirect('/admin/formations/niveaux');
        $niveau->update(['slug' => 'intermediaire-b1']);
        $this->get('/formations/intermediaire')->assertRedirect('/formations/intermediaire-b1');
    }

    public function test_the_planned_module_disappears_now_that_it_is_built(): void
    {
        $this->actingAs($this->superadmin())->get('/admin')->assertOk()->assertDontSee('/modules/formations"', false)
            ->assertDontSee('/modules/formations_personnalisees"', false)->assertDontSee('/modules/traductions"', false)->assertDontSee('/modules/finances"', false);
        $this->get('/admin/modules/formations')->assertNotFound();
    }
}
