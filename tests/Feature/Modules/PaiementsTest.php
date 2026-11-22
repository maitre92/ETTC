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
use App\Services\Paiements\PaiementService;
use App\Services\Paiements\ReceiptService;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

        $this->get("/admin/inscriptions/{$inscription->id}")->assertOk()->assertSee('Encaisser un paiement')->assertSee('Non payée');
        $this->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 50000, 'method' => 'especes', 'period' => 'Mois 1'])->assertRedirect();

        $paiement = Paiement::firstOrFail();
        $this->assertMatchesRegularExpression('/^REC-\d{4}-\d{4}$/', $paiement->number);
        $this->assertSame(PaymentStatus::Confirme, $paiement->status);
        $fresh = $inscription->fresh();
        $this->assertSame([50000, 50000, InscriptionStatus::EnCours, 'Partiellement payée'], [$fresh->amount_paid, $fresh->balance(), $fresh->status, $fresh->paymentState()]);
        Mail::assertSent(PaiementConfirme::class, fn ($m) => $m->hasTo('awa@exemple.ml') && str_contains($m->render(), $paiement->number));
        $this->assertTrue(ActivityLog::where('action', 'paiements.recorded')->exists());
        $this->assertTrue(ActivityLog::where('action', 'inscriptions.paid')->exists());

        // Reçus PDF : A5 (téléchargé) et ticket thermique 80 mm (affiché pour être imprimé).
        $this->get("/admin/paiements/{$paiement->id}/recu")->assertOk()->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'attachment; filename=Recu_'.$paiement->number.'.pdf');
        $this->get("/admin/paiements/{$paiement->id}/recu?format=thermique&imprimer=1")->assertOk()->assertHeader('content-type', 'application/pdf')
            ->assertHeader('content-disposition', 'inline; filename=Ticket_'.$paiement->number.'.pdf');

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
        $this->actingAs($client, 'client')->get("/espace-client/paiements/{$paiement->id}/recu?format=thermique")->assertOk()->assertHeader('content-type', 'application/pdf');
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

    public function test_a_presential_learner_can_pay_at_the_centre_or_online(): void
    {
        app(SiteSettings::class)->save(['paiement.orange_money' => '75 22 91 25']);
        $client = Client::factory()->create();
        $inscription = $this->inscription(['client_id' => $client->id, 'mode' => FormationMode::Presentiel, 'payment_choice' => PaymentChoice::AuCentre]);

        // Présentiel (demande du centre) : au centre, ou en ligne si l'apprenant le souhaite.
        $this->actingAs($client, 'client')->get('/espace-client/paiements')->assertOk()->assertSee('au centre')->assertSee('Déclarer mon paiement')->assertSee('75 22 91 25');
        $this->postJson('/espace-client/paiements', ['inscription_id' => $inscription->id, 'payment_method' => 'orange_money', 'amount' => 20000, 'reference' => 'MP0001'])->assertOk();

        // L'inscription d'un autre apprenant n'est jamais accessible.
        $other = $this->inscription(['mode' => FormationMode::EnLigne, 'email' => 'autre@exemple.ml']);
        $this->postJson('/espace-client/paiements', ['inscription_id' => $other->id, 'payment_method' => 'orange_money', 'amount' => 50000, 'reference' => 'MP0002'])->assertNotFound();
        $this->assertSame(1, Paiement::count());
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
        $this->get("/admin/paiements/{$paiement->id}/recu")->assertOk()->assertHeader('content-type', 'application/pdf');
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

    public function test_the_qr_code_of_a_receipt_proves_it_is_authentic(): void
    {
        Mail::fake();
        $inscription = $this->inscription();
        $this->actingAs(User::factory()->administrateur()->create())->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 50000, 'method' => 'especes'])->assertRedirect();
        $paiement = Paiement::firstOrFail();
        $url = app(ReceiptService::class)->verificationUrl($paiement);

        // Hors ligne (localhost) : le QR ne contient que le reçu en clair ; en ligne (HTTPS) : le lien de vérification en plus.
        $receipts = app(ReceiptService::class);
        $this->assertStringContainsString('50 000 FCFA', $receipts->qrText($paiement));
        $this->assertStringNotContainsString('http', $receipts->qrText($paiement));
        config(['app.url' => 'https://ettc-mali.org']);
        $this->assertStringContainsString('Vérifier : ', $receipts->qrText($paiement));

        auth()->logout();
        $this->get($url)->assertOk()->assertSee('Reçu authentique')->assertSee($paiement->number);
        $this->get("/recu/{$paiement->number}/faussecle1234567890")->assertOk()->assertSee('Reçu introuvable');
    }

    public function test_an_automatic_payment_is_confirmed_only_after_reading_the_operator_status(): void
    {
        Mail::fake();
        config(['services.paiement.gateway' => 'simulation']);
        app(SiteSettings::class)->save(['paiement.orange_money_ussd' => '']);
        $client = Client::factory()->create();
        $inscription = $this->inscription(['client_id' => $client->id, 'mode' => FormationMode::EnLigne, 'payment_choice' => PaymentChoice::EnLigne]);

        $this->actingAs($client, 'client')->get('/espace-client/paiements')->assertOk()->assertSee('Payer maintenant par mobile money');
        $checkout = $this->post('/espace-client/paiements/en-ligne', ['inscription_id' => $inscription->id, 'amount' => 50000])->assertRedirect()->headers->get('Location');
        $paiement = Paiement::firstOrFail();
        $this->assertSame([PaymentStatus::EnAttente, 'simulation'], [$paiement->status, $paiement->gateway]);

        // Le retour du navigateur, avant que l'opérateur ait accepté, ne confirme rien.
        $this->get("/espace-client/paiements/{$paiement->id}/retour")->assertRedirect('/espace-client/paiements');
        $this->assertSame(PaymentStatus::EnAttente, $paiement->fresh()->status);

        $this->get($checkout)->assertOk()->assertSee('Mode test');
        $decide = URL::signedRoute('site.paiement-simule.decide', ['transaction' => $paiement->gateway_transaction_id, 'accept' => 1]);
        $this->post($decide)->assertRedirect();

        $fresh = $paiement->fresh();
        $this->assertSame([PaymentStatus::Confirme, 50000], [$fresh->status, $inscription->fresh()->amount_paid]);
        $this->assertSame(InscriptionStatus::EnCours, $inscription->fresh()->status);
        Mail::assertSent(PaiementConfirme::class);
        $this->assertTrue(ActivityLog::where('action', 'paiements.online_confirmed')->exists());

        // Une notification rejouée ne change plus rien.
        $this->post('/paiements/notification/simulation', ['transaction_id' => $paiement->gateway_transaction_id])->assertOk();
        $this->assertSame(1, Paiement::confirmed()->count());
    }

    public function test_the_cinetpay_notification_is_only_a_signal_and_the_amount_is_checked(): void
    {
        Mail::fake();
        config(['services.paiement.gateway' => 'cinetpay', 'services.cinetpay.api_key' => 'test-key', 'services.cinetpay.site_id' => '123456']);
        $client = Client::factory()->create();
        $inscription = $this->inscription(['client_id' => $client->id, 'mode' => FormationMode::EnLigne]);

        Http::fake(['*/v2/payment' => Http::response(['code' => '201', 'data' => ['payment_url' => 'https://checkout.cinetpay.com/payment/abc']])]);
        $this->actingAs($client, 'client')->post('/espace-client/paiements/en-ligne', ['inscription_id' => $inscription->id, 'amount' => 50000])
            ->assertRedirect('https://checkout.cinetpay.com/payment/abc');
        $paiement = Paiement::firstOrFail();
        Http::assertSent(fn ($request) => $request['transaction_id'] === $paiement->gateway_transaction_id && $request['amount'] === 50000 && $request['currency'] === 'XOF'
            && str_ends_with($request['notify_url'], '/paiements/notification/cinetpay'));

        // L'état est toujours relu chez CinetPay : « refusé » pour la 1re transaction, « accepté » (mauvais montant) pour la 2de.
        Http::fake(['*/v2/payment/check' => fn ($request) => Http::response($request['transaction_id'] === 'ETTCTEST2'
            ? ['code' => '00', 'data' => ['status' => 'ACCEPTED', 'amount' => '100', 'payment_method' => 'OM', 'operator_id' => 'OP123']]
            : ['code' => '00', 'data' => ['status' => 'REFUSED']])]);
        // Notification falsifiée (montant annoncé) : l'opérateur dit « refusé », le paiement n'est pas confirmé.
        $this->post('/paiements/notification/cinetpay', ['cpm_trans_id' => $paiement->gateway_transaction_id, 'cpm_amount' => 50000])->assertOk();
        $this->assertSame(PaymentStatus::Rejete, $paiement->fresh()->status);

        // Accepté mais pour un autre montant : à contrôler par l'équipe.
        $second = Paiement::factory()->create(['inscription_id' => $inscription->id, 'status' => PaymentStatus::EnAttente, 'gateway' => 'cinetpay', 'gateway_transaction_id' => 'ETTCTEST2', 'amount' => 50000]);
        $this->post('/paiements/notification/cinetpay', ['cpm_trans_id' => 'ETTCTEST2'])->assertOk();
        $this->assertSame([PaymentStatus::AVerifier, 0], [$second->fresh()->status, $inscription->fresh()->amount_paid]);
    }

    public function test_pending_online_payments_are_rechecked_and_expire(): void
    {
        config(['services.paiement.gateway' => 'simulation']);
        $inscription = $this->inscription();
        $old = Paiement::factory()->create(['inscription_id' => $inscription->id, 'status' => PaymentStatus::EnAttente, 'gateway' => 'simulation', 'gateway_transaction_id' => 'ETTCOLD']);
        $old->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();
        Cache::put('paiement-simule.ETTCOLD', ['state' => 'pending', 'amount' => 20000, 'return' => '/', 'notify' => '/'], now()->addDay());

        $this->artisan('paiements:sync')->assertSuccessful();
        $this->assertSame(PaymentStatus::Rejete, $old->fresh()->status);
    }

    public function test_the_simplified_screens_show_one_main_action(): void
    {
        $inscription = $this->inscription();
        $this->actingAs(User::factory()->administrateur()->create());

        $this->get("/admin/inscriptions/{$inscription->id}")->assertOk()->assertSee('QUE FAIRE MAINTENANT')->assertSee('Encaisser un paiement')->assertSee('Autres actions');
        $this->get('/admin/inscriptions')->assertOk()->assertSee('Encaisser');
        Paiement::factory()->create(['inscription_id' => $inscription->id]);
        $this->get('/admin/paiements')->assertOk()->assertSee('Confirmer')->assertSee('data-confirm=', false);
    }

    public function test_merchant_qr_codes_are_uploaded_and_shown_to_the_learner(): void
    {
        Storage::fake('public');
        $admin = User::factory()->administrateur()->create();
        $client = Client::factory()->create();
        $inscription = $this->inscription(['client_id' => $client->id, 'mode' => FormationMode::EnLigne, 'payment_choice' => PaymentChoice::EnLigne]);

        // QR Wave seul (sans numéro) : Wave est proposé à l'apprenant, avec son QR à scanner.
        $this->actingAs($admin)->put('/admin/paiements/moyens', ['orange_money' => '75 22 91 25', 'wave' => '', 'moov_money' => '',
            'wave_qr' => UploadedFile::fake()->image('qr-wave.png', 400, 400)])->assertRedirect('/admin/paiements');
        $qr = app(PaiementService::class)->merchantQrCodes();
        $this->assertArrayHasKey('wave', $qr);
        $this->get('/admin/paiements')->assertSee('QR marchand à scanner')->assertSee($qr['wave'], false);

        $this->actingAs($client, 'client')->get('/espace-client/paiements')->assertOk()
            ->assertSee('QR marchand Wave à scanner')->assertSee($qr['wave'], false)->assertSee('75 22 91 25');
        $this->postJson('/espace-client/paiements', ['inscription_id' => $inscription->id, 'payment_method' => 'wave', 'amount' => 50000, 'reference' => 'WV123456'])->assertOk();

        // « Retirer » : plus de QR, et Wave (sans numéro) n'est plus proposé.
        $this->actingAs($admin)->put('/admin/paiements/moyens', ['orange_money' => '75 22 91 25', 'wave_qr_remove' => '1'])->assertRedirect();
        $this->assertArrayNotHasKey('wave', app(PaiementService::class)->merchantNumbers());
        $this->put('/admin/paiements/moyens', ['wave_qr' => UploadedFile::fake()->create('virus.exe', 10)])->assertSessionHasErrors('wave_qr');
    }

    public function test_the_qr_codes_stay_visible_even_with_the_automatic_payment(): void
    {
        config(['services.paiement.gateway' => 'simulation']);
        app(SiteSettings::class)->save(['paiement.orange_money' => '75 22 91 25']);
        $client = Client::factory()->create();
        $this->inscription(['client_id' => $client->id, 'mode' => FormationMode::EnLigne, 'payment_choice' => PaymentChoice::EnLigne]);

        $this->actingAs($client, 'client')->get('/espace-client/paiements')->assertOk()
            ->assertSeeInOrder(['Payer par mobile money', '75 22 91 25', 'Déclarer mon paiement', 'Ou payer directement en ligne'])
            ->assertDontSee('J\'ai déjà envoyé l\'argent', false);
    }

    public function test_an_amount_paid_online_is_never_cashed_again_only_the_remainder(): void
    {
        Mail::fake();
        $inscription = $this->inscription(['status' => InscriptionStatus::AValider, 'unit_price' => 20000, 'total_amount' => 60000, 'duration_months' => 3]);
        $declared = Paiement::factory()->create(['inscription_id' => $inscription->id, 'amount' => 20000, 'reference' => 'WV123', 'method' => 'wave']);
        $this->assertSame([40000, 20000, 'Paiement à vérifier'], [$inscription->payableNow(), $inscription->nextInstallment(), $inscription->paymentState()]);

        $this->actingAs(User::factory()->administrateur()->create());
        $this->get("/admin/inscriptions/{$inscription->id}")->assertOk()->assertSee('Vérifier le paiement déclaré')->assertSee('WV123')->assertSee('déjà payé en ligne par l', false);

        // L'accueil ne peut pas encaisser le montant déjà payé en ligne : seulement le reliquat.
        $this->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 60000, 'method' => 'especes'])->assertSessionHasErrors('amount');
        $this->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 40000, 'method' => 'especes'])->assertRedirect();
        $this->assertSame(0, $inscription->fresh()->payableNow());
        $this->get('/admin/paiements')->assertSee('Vérifier');

        // Rejeté : le montant redevient à encaisser.
        $this->put("/admin/paiements/{$declared->id}/rejet", ['reason' => 'Introuvable'])->assertRedirect();
        $this->assertSame(20000, $inscription->fresh()->payableNow());
    }
}
