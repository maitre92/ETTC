<?php

namespace Tests\Feature\Modules;

use App\Enums\TestStatus;
use App\Mail\TestNiveauAccuse;
use App\Mail\TestNiveauConvocation;
use App\Mail\TestNiveauDemande;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Niveau;
use App\Models\TestNiveau;
use App\Models\User;
use App\Support\SiteSettings;
use App\Support\SpamGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Tests de niveau (cahier ETTC §3.4, §5) : demande sur le site, frais figés, planification avec convocation,
 * encaissement des frais (hors commission), résultat et niveau attribué, orientation, annulation, droits par rôle,
 * espace apprenant.
 */
class TestsNiveauTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        config(['site.notification_email' => 'equipe@ettc.test']);
    }

    /** @param  array<string, mixed>  $data */
    private function human(array $data): array
    {
        return $data + ['consent' => '1', SpamGuard::TOKEN => Crypt::encryptString((string) now()->subSeconds(20)->getTimestamp())];
    }

    public function test_a_visitor_books_a_test_with_the_current_fee_frozen(): void
    {
        Mail::fake();
        app(SiteSettings::class)->save(['tarifs.test_niveau' => '5000']);

        $this->get('/test-de-niveau')->assertOk()->assertSee('Réserver mon test')->assertSee("5\u{202F}000 FCFA")->assertSee('Pourquoi un test de niveau');

        $this->postJson('/test-de-niveau', $this->human(['name' => 'Awa Diallo', 'phone' => '75 22 91 25', 'email' => 'awa@exemple.ml', 'test_mode' => 'distance', 'preferred_slot' => 'Samedi matin']))
            ->assertOk()->assertJsonPath('title', 'Demande de test envoyée');

        $test = TestNiveau::firstOrFail();
        $this->assertMatchesRegularExpression('/^TST-\d{4}-\d{4}$/', $test->number);
        $this->assertSame(TestStatus::Demande, $test->status);
        $this->assertSame('+22375229125', $test->phone);
        $this->assertSame(5000, $test->fee);
        $this->assertFalse($test->isPaid());
        Mail::assertSent(TestNiveauDemande::class, fn ($m) => $m->hasTo('equipe@ettc.test') && str_contains($m->render(), 'Awa Diallo'));
        Mail::assertSent(TestNiveauAccuse::class, fn ($m) => $m->hasTo('awa@exemple.ml') && str_contains($m->render(), $test->number));
        $this->assertTrue(ActivityLog::where('action', 'tests.requested')->exists());

        // Le prix change ensuite : le candidat garde le montant annoncé.
        app(SiteSettings::class)->save(['tarifs.test_niveau' => '7500']);
        $this->assertSame(5000, $test->fresh()->fee);

        $this->postJson('/test-de-niveau', $this->human(['name' => 'Sans contact', 'test_mode' => 'centre']))->assertUnprocessable()->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_robots_get_a_success_but_nothing_is_saved(): void
    {
        $this->postJson('/test-de-niveau', ['name' => 'Robot', 'phone' => '70000000', 'test_mode' => 'centre', 'consent' => '1', SpamGuard::HONEYPOT => 'http://spam.example'])->assertOk();
        $this->assertSame(0, TestNiveau::count());
    }

    public function test_the_full_cycle_schedule_pay_result_and_orientation(): void
    {
        Mail::fake();
        $niveau = Niveau::factory()->create(['name' => 'Intermédiaire']);
        Formule::factory()->create(['name' => 'Formule 3 mois', 'price_onsite' => 20000]);
        $test = TestNiveau::factory()->create(['email' => 'moussa@exemple.ml']);
        $agent = User::factory()->administrateur()->create();
        $this->actingAs($agent);

        $this->get('/admin/tests')->assertOk()->assertSee('CANDIDATS AU TEST DE NIVEAU')->assertSee($test->number)->assertSee('data-crud="tests"', false);

        $at = now()->addDays(3)->setTime(10, 0);
        $this->put("/admin/tests/{$test->id}/planification", ['scheduled_at' => $at->format('Y-m-d\TH:i'), 'test_mode' => 'centre', 'location' => 'Salle 2'])
            ->assertRedirect("/admin/tests/{$test->id}")->assertSessionHas('status');
        $this->assertSame(TestStatus::Planifie, $test->fresh()->status);
        Mail::assertSent(TestNiveauConvocation::class, fn ($m) => $m->hasTo('moussa@exemple.ml') && str_contains($m->render(), 'Salle 2'));
        $this->put("/admin/tests/{$test->id}/planification", ['scheduled_at' => now()->subDay()->format('Y-m-d\TH:i'), 'test_mode' => 'centre'])->assertSessionHasErrors('scheduled_at');

        $this->put("/admin/tests/{$test->id}/paiement", ['payment_method' => 'orange_money'])->assertSessionHasErrors('payment_reference');
        $this->put("/admin/tests/{$test->id}/paiement", ['payment_method' => 'orange_money', 'payment_reference' => 'OM-123'])->assertRedirect();
        $test->refresh();
        $this->assertTrue($test->isPaid());
        $this->assertSame($agent->id, $test->payment_recorded_by);
        $this->put("/admin/tests/{$test->id}/paiement", ['payment_method' => 'especes'])->assertSessionHasErrors('payment_method');

        $this->put("/admin/tests/{$test->id}/resultat", ['niveau_id' => $niveau->id, 'score' => '34 / 50', 'result_notes' => 'Bonne compréhension orale.'])->assertRedirect();
        $test->refresh();
        $this->assertSame(TestStatus::Passe, $test->status);
        $this->assertSame('Intermédiaire', $test->niveau->name);

        $this->get("/admin/tests/{$test->id}")->assertOk()->assertSee('Niveau attribué')->assertSee('Formule 3 mois')->assertSee('/formations/'.$niveau->slug, false);
        foreach (['tests.scheduled', 'tests.payment_recorded', 'tests.result_recorded'] as $action) {
            $this->assertTrue(ActivityLog::where('action', $action)->exists(), $action);
        }
        // Une seule ligne par geste : pas de « Modification — Test de niveau » générique en doublon.
        $this->assertFalse(ActivityLog::where('subject_type', 'test_niveau')->where('description', 'like', 'Modification%')->exists());

        // Un test passé ne s'annule plus.
        $this->put("/admin/tests/{$test->id}/annulation", ['cancel_reason' => 'Erreur'])->assertSessionHasErrors('status');
    }

    public function test_a_cancelled_test_accepts_neither_payment_nor_result(): void
    {
        $test = TestNiveau::factory()->create();
        $this->actingAs(User::factory()->administrateur()->create());

        $this->put("/admin/tests/{$test->id}/annulation", ['cancel_reason' => ''])->assertSessionHasErrors('cancel_reason');
        $this->put("/admin/tests/{$test->id}/annulation", ['cancel_reason' => 'Candidat indisponible'])->assertRedirect();
        $this->assertSame(TestStatus::Annule, $test->fresh()->status);

        $this->put("/admin/tests/{$test->id}/paiement", ['payment_method' => 'especes'])->assertSessionHasErrors('status');
        $this->put("/admin/tests/{$test->id}/resultat", ['niveau_id' => Niveau::factory()->create()->id])->assertSessionHasErrors('status');
    }

    public function test_the_team_registers_a_walk_in_candidate_and_permissions_follow_the_roles(): void
    {
        $commercial = User::factory()->commercial()->create();
        $this->actingAs($commercial)->post('/admin/tests', ['name' => 'Ibrahim Touré', 'phone' => '76 00 00 00', 'test_mode' => 'centre'])
            ->assertRedirect();
        $test = TestNiveau::firstOrFail();
        $this->assertSame('centre', $test->source);

        $this->put("/admin/tests/{$test->id}/paiement", ['payment_method' => 'especes'])->assertRedirect();
        $this->put("/admin/tests/{$test->id}/resultat", ['niveau_id' => Niveau::factory()->create()->id])->assertForbidden();
        $this->delete("/admin/tests/{$test->id}")->assertForbidden();
        $this->put('/admin/tests/presentation', ['presentation' => 'X'])->assertForbidden();

        $gestionnaire = User::factory()->gestionnaire()->create();
        $this->actingAs($gestionnaire)->put('/admin/tests/presentation', ['presentation' => "## Déroulement\n\nUn QCM de 45 minutes."])->assertRedirect('/admin/tests');
        $this->get('/test-de-niveau')->assertSee('Un QCM de 45 minutes.');
        $this->put("/admin/tests/{$test->id}/paiement", ['payment_method' => 'especes'])->assertForbidden();
    }

    public function test_the_learner_sees_tests_made_with_the_confirmed_email(): void
    {
        $niveau = Niveau::factory()->create(['name' => 'Débutant']);
        $client = Client::factory()->create(['email' => 'fatou@exemple.ml', 'email_verified_at' => now()]);
        TestNiveau::factory()->create(['email' => 'fatou@exemple.ml', 'status' => TestStatus::Passe, 'niveau_id' => $niveau->id, 'number' => 'TST-2026-0042']);
        TestNiveau::factory()->create(['email' => 'autre@exemple.ml', 'number' => 'TST-2026-0043']);

        $this->actingAs($client, 'client')->get('/espace-client/tests-de-niveau')->assertOk()
            ->assertSee('TST-2026-0042')->assertSee('Débutant')->assertSee('Voir ma formation et les tarifs')->assertDontSee('TST-2026-0043');
        $this->get('/espace-client')->assertOk()->assertSee('Tests de niveau');
    }

    public function test_the_dashboard_lists_candidates_waiting_for_a_test(): void
    {
        TestNiveau::factory()->count(2)->create();
        $this->actingAs(User::factory()->administrateur()->create())->get('/admin')->assertOk()->assertSee('Tests à planifier');
    }
}
