<?php

namespace Tests\Feature\Modules;

use App\Enums\Billing;
use App\Enums\FormationMode;
use App\Enums\InscriptionStatus;
use App\Enums\TestStatus;
use App\Mail\InscriptionRecapitulatif;
use App\Mail\InscriptionRecue;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\TestNiveau;
use App\Models\User;
use App\Support\SiteSettings;
use App\Support\SpamGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Inscriptions (cahier ETTC §3.5, §5) : inscription en ligne avec tarif FIGÉ, inscription au centre ou depuis un test
 * passé, validation, début et fin de formation, annulation, session, droits par rôle, espace apprenant, tableau de bord.
 */
class InscriptionsTest extends TestCase
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

    private function formule(array $attributes = []): Formule
    {
        return Formule::factory()->create($attributes + ['name' => 'Formule 2 mois', 'duration_months' => 2, 'price_online' => 50000, 'price_onsite' => 20000, 'billing' => Billing::Mensuel]);
    }

    public function test_a_visitor_registers_online_and_the_price_is_frozen(): void
    {
        Mail::fake();
        $niveau = Niveau::factory()->create(['name' => 'Débutant']);
        $formule = $this->formule();

        $this->get('/inscription?niveau='.$niveau->slug)->assertOk()->assertSee('Formule 2 mois')->assertSee('data-ettc-wizard', false);

        $this->postJson('/inscription', $this->human([
            'niveau_id' => $niveau->id, 'formule_id' => $formule->id, 'mode' => 'presentiel', 'payment_choice' => 'au_centre',
            'name' => 'Awa Diallo', 'phone' => '75 22 91 25', 'email' => 'awa@exemple.ml', 'birth_date' => '2000-05-01', 'gender' => 'femme',
        ]))->assertOk()->assertJsonStructure(['title', 'message']);

        $inscription = Inscription::firstOrFail();
        $this->assertMatchesRegularExpression('/^INS-\d{4}-\d{4}$/', $inscription->number);
        $this->assertSame(InscriptionStatus::AValider, $inscription->status);
        $this->assertSame(FormationMode::Presentiel, $inscription->mode);
        $this->assertSame([20000, 40000, '+22375229125'], [$inscription->unit_price, $inscription->total_amount, $inscription->phone]);
        Mail::assertSent(InscriptionRecue::class, fn ($m) => $m->hasTo('equipe@ettc.test') && str_contains($m->render(), 'Awa Diallo'));
        Mail::assertSent(InscriptionRecapitulatif::class, fn ($m) => $m->hasTo('awa@exemple.ml') && str_contains($m->render(), $inscription->number));
        $this->assertTrue(ActivityLog::where('action', 'inscriptions.submitted')->exists());

        // Le centre change ses tarifs : l'inscription garde le prix accepté.
        $formule->update(['price_onsite' => 35000]);
        $this->assertSame(40000, $inscription->fresh()->total_amount);
    }

    public function test_an_unavailable_formula_or_mode_is_refused(): void
    {
        $online = $this->formule(['price_onsite' => null]);
        $hidden = Formule::factory()->hidden()->create();
        $base = ['payment_choice' => 'au_centre', 'name' => 'Moussa', 'phone' => '76 00 00 00'];

        $this->postJson('/inscription', $this->human($base + ['formule_id' => $online->id, 'mode' => 'presentiel']))->assertUnprocessable()->assertJsonValidationErrors('mode');
        $this->postJson('/inscription', $this->human($base + ['formule_id' => $hidden->id, 'mode' => 'en_ligne']))->assertUnprocessable()->assertJsonValidationErrors('formule_id');
        $this->postJson('/inscription', $this->human(['formule_id' => $online->id, 'mode' => 'en_ligne', 'payment_choice' => 'au_centre']))->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone']);
        $this->postJson('/inscription', ['formule_id' => $online->id, 'mode' => 'en_ligne', 'payment_choice' => 'au_centre', 'name' => 'Robot', 'phone' => '70000000', 'consent' => '1', SpamGuard::HONEYPOT => 'x'])->assertOk();
        $this->assertSame(0, Inscription::count());
    }

    public function test_the_team_follows_the_whole_cycle(): void
    {
        $inscription = Inscription::factory()->create();
        $this->actingAs(User::factory()->administrateur()->create());

        $this->get('/admin/inscriptions')->assertOk()->assertSee($inscription->number)->assertSee('data-crud="inscriptions"', false);
        $this->get("/admin/inscriptions/{$inscription->id}")->assertOk()->assertSee($inscription->name);

        $this->put("/admin/inscriptions/{$inscription->id}/fin")->assertSessionHasErrors('status');
        $this->put("/admin/inscriptions/{$inscription->id}/validation")->assertRedirect();
        $this->assertSame(InscriptionStatus::Validee, $inscription->fresh()->status);
        $this->put("/admin/inscriptions/{$inscription->id}/dossier", ['session' => 'Janvier 2027', 'internal_notes' => 'Paie en deux fois'])->assertRedirect();
        $this->put("/admin/inscriptions/{$inscription->id}/debut")->assertRedirect();
        $this->put("/admin/inscriptions/{$inscription->id}/fin")->assertRedirect();

        $fresh = $inscription->fresh();
        $this->assertSame([InscriptionStatus::Terminee, 'Janvier 2027'], [$fresh->status, $fresh->session]);
        $this->put("/admin/inscriptions/{$inscription->id}/annulation", ['cancel_reason' => 'Trop tard'])->assertSessionHasErrors('status');
        foreach (['validated', 'organized', 'started', 'finished'] as $action) {
            $this->assertTrue(ActivityLog::where('action', "inscriptions.{$action}")->exists(), $action);
        }
        // Une seule ligne d'historique par geste (pas de « Modification » générique en double).
        $this->assertSame(0, ActivityLog::where('subject_id', $inscription->id)->where('action', 'like', '%.updated')->count());
    }

    public function test_a_cancellation_needs_a_reason(): void
    {
        $inscription = Inscription::factory()->create();
        $this->actingAs(User::factory()->administrateur()->create());

        $this->put("/admin/inscriptions/{$inscription->id}/annulation", ['cancel_reason' => ''])->assertSessionHasErrors('cancel_reason');
        $this->put("/admin/inscriptions/{$inscription->id}/annulation", ['cancel_reason' => 'Déménagement'])->assertRedirect();
        $this->assertSame(InscriptionStatus::Annulee, $inscription->fresh()->status);
        $this->put("/admin/inscriptions/{$inscription->id}/validation")->assertSessionHasErrors('status');
    }

    public function test_a_candidate_is_registered_from_a_passed_test(): void
    {
        $niveau = Niveau::factory()->create();
        $formule = $this->formule();
        $test = TestNiveau::factory()->create(['name' => 'Ibrahim Touré', 'phone' => '+22376000000', 'status' => TestStatus::Passe, 'niveau_id' => $niveau->id]);
        $this->actingAs(User::factory()->commercial()->create());

        $this->get("/admin/tests/{$test->id}")->assertOk()->assertSee('Inscrire à une formation');
        $this->post("/admin/inscriptions/depuis-test/{$test->id}", ['formule_id' => $formule->id, 'mode' => 'en_ligne', 'payment_choice' => 'en_ligne'])->assertRedirect();

        $inscription = Inscription::firstOrFail();
        $this->assertSame(['Ibrahim Touré', $niveau->id, $test->id, 'centre', 100000], [$inscription->name, $inscription->niveau_id, $inscription->test_niveau_id, $inscription->source, $inscription->total_amount]);

        $pending = TestNiveau::factory()->create();
        $this->post("/admin/inscriptions/depuis-test/{$pending->id}", ['formule_id' => $formule->id, 'mode' => 'en_ligne', 'payment_choice' => 'en_ligne'])->assertNotFound();
    }

    public function test_permissions_follow_the_roles(): void
    {
        $inscription = Inscription::factory()->create();
        $gestionnaire = User::factory()->gestionnaire()->create();

        $this->actingAs($gestionnaire)->get('/admin/inscriptions')->assertOk();
        $this->put("/admin/inscriptions/{$inscription->id}/annulation", ['cancel_reason' => 'X'])->assertForbidden();
        $this->delete("/admin/inscriptions/{$inscription->id}")->assertForbidden();

        $this->actingAs(User::factory()->administrateur()->create())->delete("/admin/inscriptions/{$inscription->id}")->assertRedirect('/admin/inscriptions');
        $this->assertSoftDeleted($inscription);
    }

    public function test_the_learner_sees_own_inscriptions_and_completes_the_profile(): void
    {
        $niveau = Niveau::factory()->create(['name' => 'Avancé']);
        $client = Client::factory()->create(['email' => 'fatou@exemple.ml', 'email_verified_at' => now()]);
        Inscription::factory()->create(['email' => 'fatou@exemple.ml', 'niveau_id' => $niveau->id, 'number' => 'INS-2026-0042', 'session' => 'Mars 2027']);
        Inscription::factory()->create(['email' => 'autre@exemple.ml', 'number' => 'INS-2026-0043']);

        $this->actingAs($client, 'client')->get('/espace-client/inscriptions')->assertOk()
            ->assertSee('INS-2026-0042')->assertSee('Avancé')->assertSee('Mars 2027')->assertDontSee('INS-2026-0043');
        $this->get('/espace-client')->assertOk()->assertSee('Inscriptions');

        $this->put('/espace-client/profil', ['name' => 'Fatou Keïta', 'birth_date' => '1999-03-02', 'gender' => 'femme', 'address' => 'Hamdallaye', 'city' => 'Bamako'])->assertRedirect();
        $fresh = $client->fresh();
        $this->assertSame(['1999-03-02', 'femme'], [$fresh->birth_date->format('Y-m-d'), $fresh->gender->value]);
        $this->get('/espace-client/profil')->assertOk()->assertSee('Date de naissance')->assertDontSee('livraison');
    }

    public function test_the_dashboard_lists_pending_inscriptions(): void
    {
        Inscription::factory()->count(2)->create();
        $this->actingAs(User::factory()->administrateur()->create())->get('/admin')->assertOk()->assertSee('Inscriptions');
    }

    public function test_at_the_centre_a_presential_inscription_is_paid_in_the_same_step(): void
    {
        Mail::fake();
        $formule = $this->formule();
        $this->actingAs(User::factory()->commercial()->create());

        $this->get('/admin/inscriptions')->assertOk()->assertSee('Inscrire et encaisser')->assertSee('data-lookup-phone', false);
        $base = ['name' => 'Oumar Sidibé', 'phone' => '76 11 22 33', 'formule_id' => $formule->id, 'mode' => 'presentiel', 'pay_now' => '1'];

        $this->post('/admin/inscriptions', $base + ['pay_amount' => 50000, 'pay_method' => 'especes'])->assertSessionHasErrors('pay_amount');
        $this->post('/admin/inscriptions', $base + ['pay_amount' => 20000, 'pay_method' => 'wave'])->assertSessionHasErrors('pay_reference');
        $this->assertSame(0, Inscription::count());

        $response = $this->post('/admin/inscriptions', $base + ['pay_amount' => 20000, 'pay_method' => 'especes'])->assertRedirect();
        $inscription = Inscription::firstOrFail();
        $response->assertSessionHas('receipt_id');
        $this->assertSame([InscriptionStatus::EnCours, 20000, 'au_centre'], [$inscription->status, $inscription->amount_paid, $inscription->payment_choice->value]);
        $this->assertSame('Mois 1', $inscription->paiements()->first()->period);
        $this->get("/admin/inscriptions/{$inscription->id}")->assertSee('Imprimez-le pour l');

        // En ligne, sans paiement immédiat : mobile money plus tard.
        $this->post('/admin/inscriptions', ['name' => 'Awa', 'phone' => '76 99 88 77', 'formule_id' => $formule->id, 'mode' => 'en_ligne'])->assertRedirect();
        $this->assertSame(['en_ligne', 0], [Inscription::latest('id')->first()->payment_choice->value, Inscription::latest('id')->first()->amount_paid]);
    }

    public function test_a_known_person_is_found_by_phone(): void
    {
        $niveau = Niveau::factory()->create(['name' => 'Intermédiaire']);
        TestNiveau::factory()->create(['name' => 'Fatou Keïta', 'phone' => '+22375000001', 'email' => 'fatou@exemple.ml', 'status' => TestStatus::Passe, 'niveau_id' => $niveau->id]);
        $this->actingAs(User::factory()->commercial()->create());

        $this->getJson('/admin/inscriptions/personne?phone=75 00 00 01')->assertOk()
            ->assertJsonPath('found', true)->assertJsonPath('fields.name', 'Fatou Keïta')->assertJsonPath('fields.niveau_id', $niveau->id)->assertJsonPath('fields.email', 'fatou@exemple.ml');
        $this->getJson('/admin/inscriptions/personne?phone=70 00 00 00')->assertOk()->assertJsonPath('found', false);

        Inscription::factory()->create(['phone' => '+22375000001', 'status' => InscriptionStatus::EnCours]);
        $this->getJson('/admin/inscriptions/personne?phone=75000001')->assertJsonPath('found', true)->assertJsonFragment(['found' => true])
            ->assertJson(fn ($json) => $json->where('warning', fn ($w) => str_contains($w, 'déjà une inscription en cours'))->etc());
    }

    public function test_the_message_survives_an_ajax_form_that_opens_another_page(): void
    {
        $inscription = Inscription::factory()->create();
        $this->actingAs(User::factory()->administrateur()->create());

        // crud-ajax.js : le navigateur suit la redirection en arrière-plan (X-Crud-From), puis ouvre la page.
        $this->withSession(['status' => 'Inscription enregistrée.', '_flash' => ['old' => [], 'new' => ['status']]])
            ->withHeader('X-Crud-From', '/admin/inscriptions')->get("/admin/inscriptions/{$inscription->id}")->assertSee('Inscription enregistrée.');
        $this->flushHeaders()->get("/admin/inscriptions/{$inscription->id}")->assertSee('Inscription enregistrée.');
        $this->get("/admin/inscriptions/{$inscription->id}")->assertDontSee('Inscription enregistrée.');
    }

    public function test_an_online_learner_gets_the_payment_numbers_and_qr_on_confirmation_and_by_email(): void
    {
        Mail::fake();
        Storage::fake('public');
        app(SiteSettings::class)->save(['paiement.orange_money' => '75 22 91 25']);
        $formule = $this->formule();

        $online = $this->postJson('/inscription', $this->human(['formule_id' => $formule->id, 'mode' => 'en_ligne', 'payment_choice' => 'en_ligne',
            'name' => 'Awa', 'phone' => '75 22 91 25', 'email' => 'awa@exemple.ml']))->assertOk();
        $online->assertJsonPath('payment.methods.0.label', 'Orange Money')->assertJsonPath('payment.methods.0.number', '75 22 91 25')
            ->assertJsonPath('payment.amount', Formule::money(50000));
        $this->assertStringContainsString(Inscription::firstOrFail()->number, $online->json('payment.instructions'));
        Mail::assertSent(InscriptionRecapitulatif::class, fn ($m) => str_contains($m->render(), 'Payer maintenant par mobile money') && str_contains($m->render(), '75 22 91 25'));

        // En présentiel : on paie au centre, pas de numéro affiché.
        $this->postJson('/inscription', $this->human(['formule_id' => $formule->id, 'mode' => 'presentiel', 'payment_choice' => 'au_centre',
            'name' => 'Moussa', 'phone' => '76 00 00 00']))->assertOk()->assertJsonPath('payment', null);
    }
}
