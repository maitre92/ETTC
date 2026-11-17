<?php

namespace Tests\Feature\Modules;

use App\Enums\Billing;
use App\Enums\FormationMode;
use App\Enums\InscriptionStatus;
use App\Enums\PaymentChoice;
use App\Enums\PaymentStatus;
use App\Mail\PaiementConfirme;
use App\Mail\PaiementDeclare;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Paiements (cahier ETTC §3.8, §4, §5 ; décision du centre 2026-11-18) : en présentiel au centre (enregistré par l'équipe),
 * en ligne par mobile money (déclaré par l'apprenant, vérifié par l'équipe : jamais confirmé par le navigateur), reçus,
 * impayés, annulation et remboursement, droits par rôle, espace apprenant.
 */
class PaiementsTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        config(['site.notification_email' => 'equipe@ettc.test']);
    }

    /** Formule de 2 mois à 50 000 FCFA / mois : 100 000 FCFA. */
    private function inscription(array $attributes = []): Inscription
    {
        return Inscription::factory()->create($attributes + [
            'status' => InscriptionStatus::Validee, 'billing' => Billing::Mensuel, 'unit_price' => 50000, 'total_amount' => 100000,
            'duration_months' => 2, 'email' => 'awa@exemple.ml',
        ]);
    }

    public function test_a_payment_at_the_centre_gives_a_receipt_and_updates_the_inscription(): void
    {
        Mail::fake();
        $inscription = $this->inscription(['mode' => FormationMode::Presentiel]);
        $this->actingAs(User::factory()->commercial()->create());

        $this->get("/admin/inscriptions/{$inscription->id}")->assertOk()->assertSee('Enregistrer un paiement reçu au centre')->assertSee('Non payée');
        $this->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 50000, 'method' => 'especes', 'period' => 'Mois 1'])->assertRedirect();

        $paiement = Paiement::firstOrFail();
        $this->assertMatchesRegularExpression('/^REC-\d{4}-\d{4}$/', $paiement->number);
        $this->assertSame(PaymentStatus::Confirme, $paiement->status);
        $fresh = $inscription->fresh();
        $this->assertSame([50000, 50000, InscriptionStatus::EnCours, 'Partiellement payée'], [$fresh->amount_paid, $fresh->balance(), $fresh->status, $fresh->paymentState()]);
        Mail::assertSent(PaiementConfirme::class, fn ($m) => $m->hasTo('awa@exemple.ml') && str_contains($m->render(), $paiement->number));
        $this->assertTrue(ActivityLog::where('action', 'paiements.recorded')->exists());
        $this->assertTrue(ActivityLog::where('action', 'inscriptions.paid')->exists());

        $this->get("/admin/paiements/{$paiement->id}/recu")->assertOk()->assertSee('REÇU')->assertSee($paiement->number)->assertSee('Frais de formation')->assertSee('Espèces');

        // Mobile money au guichet : la référence est obligatoire ; on ne paie pas plus que le reste.
        $this->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 50000, 'method' => 'wave'])->assertSessionHasErrors('reference');
        $this->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 60000, 'method' => 'especes'])->assertSessionHasErrors('amount');
        $this->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 50000, 'method' => 'especes'])->assertRedirect();
        $this->assertSame('Soldée', $inscription->fresh()->paymentState());
    }

    public function test_an_online_learner_declares_a_mobile_money_payment_that_the_team_verifies(): void
    {
        Mail::fake();
        app(SiteSettings::class)->save(['paiement.orange_money' => '75 22 91 25', 'paiement.wave' => '']);
        $client = Client::factory()->create(['email' => 'awa@exemple.ml', 'email_verified_at' => now()]);
        $inscription = $this->inscription(['mode' => FormationMode::EnLigne, 'payment_choice' => PaymentChoice::EnLigne, 'client_id' => $client->id]);

        $this->actingAs($client, 'client')->get('/espace-client/paiements')->assertOk()
            ->assertSee('Orange Money')->assertSee('75 22 91 25')->assertSee('Déclarer mon paiement')->assertDontSee('Wave</strong>', false);

        $this->postJson('/espace-client/paiements', ['inscription_id' => $inscription->id, 'payment_method' => 'wave', 'amount' => 50000, 'reference' => 'ABC12345'])
            ->assertUnprocessable()->assertJsonValidationErrors('payment_method');
        $this->postJson('/espace-client/paiements', ['inscription_id' => $inscription->id, 'payment_method' => 'orange_money', 'amount' => 50000, 'reference' => 'mp241118.a1'])
            ->assertOk()->assertJsonPath('title', 'Paiement déclaré');

        $paiement = Paiement::firstOrFail();
        $this->assertSame([PaymentStatus::AVerifier, 'MP241118.A1', 0], [$paiement->status, $paiement->reference, $inscription->fresh()->amount_paid]);
        Mail::assertSent(PaiementDeclare::class, fn ($m) => $m->hasTo('equipe@ettc.test') && str_contains($m->render(), 'MP241118.A1'));
        $this->postJson('/espace-client/paiements', ['inscription_id' => $inscription->id, 'payment_method' => 'orange_money', 'amount' => 10000, 'reference' => 'MP241118.A1'])
            ->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->get("/espace-client/paiements/{$paiement->id}/recu")->assertNotFound();

        $admin = User::factory()->administrateur()->create();
        $this->actingAs($admin)->get('/admin/paiements')->assertOk()->assertSee('paiement(s) mobile money')->assertSee($paiement->number);
        $this->get('/admin')->assertOk()->assertSee('Paiements à vérifier');
        $this->put("/admin/paiements/{$paiement->id}/confirmation")->assertRedirect();

        $this->assertSame(PaymentStatus::Confirme, $paiement->fresh()->status);
        $this->assertSame([50000, InscriptionStatus::EnCours], [$inscription->fresh()->amount_paid, $inscription->fresh()->status]);
        Mail::assertSent(PaiementConfirme::class);
        $this->actingAs($client, 'client')->get("/espace-client/paiements/{$paiement->id}/recu")->assertOk()->assertSee($paiement->number);
    }

    public function test_a_rejected_declaration_shows_its_reason_to_the_learner(): void
    {
        $client = Client::factory()->create();
        $inscription = $this->inscription(['client_id' => $client->id, 'mode' => FormationMode::EnLigne]);
        $paiement = Paiement::factory()->create(['inscription_id' => $inscription->id]);
        $this->actingAs(User::factory()->commercial()->create());

        $this->put("/admin/paiements/{$paiement->id}/rejet", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->put("/admin/paiements/{$paiement->id}/rejet", ['reason' => 'Transaction introuvable'])->assertRedirect();
        $this->put("/admin/paiements/{$paiement->id}/confirmation")->assertSessionHasErrors('status');
        $this->assertSame(0, $inscription->fresh()->amount_paid);

        $this->actingAs($client, 'client')->get('/espace-client/paiements')->assertOk()->assertSee('Rejeté')->assertSee('Transaction introuvable');
    }

    public function test_a_presential_learner_pays_at_the_centre_and_cannot_declare_online(): void
    {
        app(SiteSettings::class)->save(['paiement.orange_money' => '75 22 91 25']);
        $client = Client::factory()->create();
        $inscription = $this->inscription(['client_id' => $client->id, 'mode' => FormationMode::Presentiel, 'payment_choice' => PaymentChoice::AuCentre]);

        $this->actingAs($client, 'client')->get('/espace-client/paiements')->assertOk()->assertSee('au centre')->assertDontSee('Déclarer mon paiement');
        $this->postJson('/espace-client/paiements', ['inscription_id' => $inscription->id, 'payment_method' => 'orange_money', 'amount' => 50000, 'reference' => 'MP0001'])->assertNotFound();

        // L'inscription d'un autre apprenant n'est jamais accessible.
        $other = $this->inscription(['mode' => FormationMode::EnLigne, 'email' => 'autre@exemple.ml']);
        $this->postJson('/espace-client/paiements', ['inscription_id' => $other->id, 'payment_method' => 'orange_money', 'amount' => 50000, 'reference' => 'MP0002'])->assertNotFound();
        $this->assertSame(0, Paiement::count());
    }

    public function test_a_refund_or_a_cancellation_lowers_the_amount_paid_and_needs_the_permission(): void
    {
        Mail::fake();
        $inscription = $this->inscription();
        $commercial = User::factory()->commercial()->create();
        $this->actingAs($commercial)->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 100000, 'method' => 'especes'])->assertRedirect();
        $paiement = Paiement::firstOrFail();

        $this->put("/admin/paiements/{$paiement->id}/remboursement", ['reason' => 'Renonce'])->assertForbidden();

        $this->actingAs(User::factory()->administrateur()->create());
        $this->put("/admin/paiements/{$paiement->id}/remboursement", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->put("/admin/paiements/{$paiement->id}/remboursement", ['reason' => 'L\'apprenant renonce'])->assertRedirect();
        $this->assertSame([PaymentStatus::Rembourse, 0], [$paiement->fresh()->status, $inscription->fresh()->amount_paid]);
        $this->put("/admin/paiements/{$paiement->id}/annulation", ['reason' => 'X'])->assertSessionHasErrors('status');
        $this->get("/admin/paiements/{$paiement->id}/recu")->assertOk()->assertSee('Remboursé');
        $this->assertTrue(ActivityLog::where('action', 'paiements.refunded')->exists());

        // Aucune route ne supprime un paiement.
        $this->delete("/admin/paiements/{$paiement->id}")->assertStatus(405);
    }

    public function test_unpaid_inscriptions_and_payment_settings(): void
    {
        $this->inscription(['name' => 'Moussa Impayé', 'status' => InscriptionStatus::EnCours]);
        $this->inscription(['name' => 'Fatou Annulée', 'status' => InscriptionStatus::Annulee]);
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($admin)->get('/admin/paiements')->assertOk()->assertSee('IMPAYÉS')->assertSee('Moussa Impayé')->assertDontSee('Fatou Annulée');
        $this->put('/admin/paiements/moyens', ['orange_money' => '75 22 91 25', 'wave' => '70 00 00 00', 'moov_money' => '', 'instructions' => 'Envoyez puis déclarez.'])->assertRedirect('/admin/paiements');
        $this->assertSame('70 00 00 00', app(SiteSettings::class)->get('paiement.wave'));
        $this->assertTrue(ActivityLog::where('action', 'paiements.settings_updated')->exists());

        $this->actingAs(User::factory()->commercial()->create())->put('/admin/paiements/moyens', ['orange_money' => 'x'])->assertForbidden();
        $this->actingAs(User::factory()->gestionnaire()->create())->get('/admin/paiements')->assertForbidden();
    }
}
