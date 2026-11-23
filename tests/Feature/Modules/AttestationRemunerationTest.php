<?php

namespace Tests\Feature\Modules;

use App\Enums\FormationMode;
use App\Enums\InscriptionStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Mail\AttestationDelivree;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\FormateurVersement;
use App\Models\Formule;
use App\Models\Groupe;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Attestations\AttestationAssets;
use App\Services\Attestations\AttestationService;
use App\Services\Caisse\CaisseService;
use App\Services\Paiements\PaiementService;
use App\Services\Remunerations\RemunerationService;
use App\Support\SiteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Demande du centre (2026-11-22) : paiement par code marchand expliqué pas à pas, attestation de fin de formation
 * (modèle « Certificate of Training »), rémunération des formateurs, qui est en ligne.
 */
class AttestationRemunerationTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    public function test_the_merchant_code_is_explained_step_by_step(): void
    {
        $ussd = app(PaiementService::class)->merchantUssd('25 000 FCFA')['orange_money'];
        $this->assertSame('754830', $ussd['merchant']);
        $this->assertSame('#144#8*754830*', $ussd['steps'][0]['code']);
        $this->assertSame('#144#8*754830*••••#', $ussd['steps'][1]['code']);
        $this->assertStringContainsString('25 000 FCFA', $ussd['steps'][2]['text']);

        Niveau::factory()->create();
        Formule::factory()->create();
        $this->get('/inscription')->assertOk()->assertSee('data-steps-slider', false)->assertSee('#144#8*754830*');

        // Formule refusée si elle contient autre chose que des chiffres, * et # (et « code secret »).
        $this->actingAs(User::factory()->administrateur()->create())
            ->put('/admin/paiements/moyens', ['orange_money_ussd' => '#144# rm -rf'])->assertSessionHasErrors('orange_money_ussd');
    }

    public function test_the_certificate_is_issued_once_then_only_as_a_duplicate(): void
    {
        $niveau = Niveau::factory()->create(['name' => 'Intermédiaire']);
        $inscription = Inscription::factory()->create(['name' => 'Amos Dembele', 'niveau_id' => $niveau->id, 'duration_months' => 3, 'mode' => FormationMode::EnLigne, 'status' => InscriptionStatus::EnCours]);
        $this->actingAs(User::factory()->administrateur()->create());

        $this->post("/admin/inscriptions/{$inscription->id}/attestation")->assertRedirect()->assertSessionHasErrors('attestation');
        $inscription->update(['status' => InscriptionStatus::Terminee, 'started_at' => '2025-12-02', 'finished_at' => '2026-02-27']);
        // Conditions : payée en totalité (et fin prévue atteinte).
        $this->post("/admin/inscriptions/{$inscription->id}/attestation")->assertSessionHasErrors('attestation');
        $inscription->update(['amount_paid' => $inscription->total_amount]);

        $this->get("/admin/inscriptions/{$inscription->id}")->assertOk()->assertSee('Délivrer l\'attestation', false);
        $this->post("/admin/inscriptions/{$inscription->id}/attestation")->assertOk()->assertHeader('content-type', 'application/pdf');
        $inscription->refresh();
        $this->assertMatchesRegularExpression('~^ETTC/CERT/\d{4}/0001$~', $inscription->attestation_number);
        $this->assertTrue(ActivityLog::where('action', 'inscriptions.attestation_issued')->exists());

        $service = app(AttestationService::class);
        $this->assertSame('Intermediate English Training', $service->trainingName($inscription));
        $this->assertSame('from December 2025 to February 2026', $service->period($inscription));
        $this->assertSame('three (3) months', $service->duration($inscription));

        $this->post("/admin/inscriptions/{$inscription->id}/attestation")->assertSessionHasErrors('attestation');
        $this->post("/admin/inscriptions/{$inscription->id}/attestation/duplicata")->assertOk();
        $this->assertSame(1, $inscription->fresh()->attestation_copies);
        $this->actingAs(User::factory()->commercial()->create())->post("/admin/inscriptions/{$inscription->id}/attestation/duplicata")->assertForbidden();
    }

    public function test_teachers_earn_a_third_for_personalised_and_twenty_percent_for_groups(): void
    {
        $formateur = User::factory()->withRole(RoleName::Formateur)->create(['name' => 'Mme Traoré']);
        $groupe = Groupe::factory()->create(['formateur_id' => $formateur->id]);
        // En groupe : 5 000 de frais d'inscription + 50 000 → base 50 000, 20 % = 10 000.
        $enGroupe = Inscription::factory()->create(['groupe_id' => $groupe->id, 'registration_fee' => 5000, 'total_amount' => 105000, 'status' => InscriptionStatus::EnCours]);
        Paiement::factory()->create(['inscription_id' => $enGroupe->id, 'amount' => 55000, 'status' => PaymentStatus::Confirme, 'paid_at' => now()]);
        // Personnalisée (sans formule) : 30 000 → 1/3 = 10 000.
        $perso = Inscription::factory()->create(['formule_id' => null, 'formule_name' => 'Formation personnalisée', 'total_amount' => 90000, 'status' => InscriptionStatus::EnCours]);
        Paiement::factory()->create(['inscription_id' => $perso->id, 'amount' => 30000, 'status' => PaymentStatus::Confirme, 'paid_at' => now()]);
        Paiement::factory()->create(['inscription_id' => $perso->id, 'amount' => 30000, 'status' => PaymentStatus::AVerifier, 'paid_at' => now()]);

        $admin = User::factory()->administrateur()->create();
        $this->actingAs($admin);
        $this->get('/admin/remunerations')->assertOk()->assertSee('paiement(s) sans formateur');
        $this->put("/admin/inscriptions/{$perso->id}/formateur", ['formateur_id' => $formateur->id])->assertRedirect();

        $summary = app(RemunerationService::class)->forTeacher($formateur, now()->format('Y-m'));
        $this->assertSame([20000, 0, 20000], [$summary['due'], $summary['paid'], $summary['remaining']]);

        $this->post('/admin/remunerations/versements', ['user_id' => $formateur->id, 'period' => now()->format('Y-m'), 'amount' => 25000, 'paid_on' => now()->toDateString(), 'method' => 'especes'])->assertSessionHasErrors('amount');
        $this->post('/admin/remunerations/versements', ['user_id' => $formateur->id, 'period' => now()->format('Y-m'), 'amount' => 20000, 'paid_on' => now()->toDateString(), 'method' => 'especes'])->assertRedirect();
        $this->assertSame(0, app(RemunerationService::class)->forTeacher($formateur, now()->format('Y-m'))['remaining']);
        // Caisse : les paiements de la fabrique sont en Orange Money ; la rémunération versée sort des espèces.
        $this->assertSame(-20000, app(CaisseService::class)->balances()['especes']);
        $this->get('/admin/remunerations/fiche/'.$formateur->id.'?mois='.now()->format('Y-m'))->assertOk()->assertHeader('content-type', 'application/pdf');

        $versement = FormateurVersement::firstOrFail();
        $this->put("/admin/remunerations/versements/{$versement->id}/annulation", ['reason' => 'Erreur'])->assertRedirect();
        $this->assertSame(20000, app(RemunerationService::class)->forTeacher($formateur, now()->format('Y-m'))['remaining']);

        // Taux réglables (« 1/3 » ou un pourcentage).
        $this->assertEqualsWithDelta(1 / 3, RemunerationService::parseRate('1/3'), 0.0001);
        $this->assertEqualsWithDelta(0.2, RemunerationService::parseRate('20 %'), 0.0001);

        // Le formateur voit sa rémunération dans son espace.
        $this->actingAs($formateur)->get('/admin/mon-espace-formateur')->assertOk()->assertSee('MA RÉMUNÉRATION')->assertSee($perso->name);
    }

    public function test_the_administrator_sees_who_is_online(): void
    {
        $admin = User::factory()->administrateur()->create(['name' => 'Chef']);
        $secretaire = User::factory()->commercial()->create(['name' => 'Fanta', 'last_login_at' => now()->subHour(), 'last_seen_at' => now()->subMinute()]);
        User::factory()->administrateur()->create(['name' => 'Autre chef', 'last_seen_at' => now()]);
        $client = Client::factory()->create(['name' => 'Awa Apprenante', 'last_login_at' => now()->subDay(), 'last_seen_at' => now()->subDay()]);

        $this->actingAs($secretaire)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/connexions')->assertOk()
            ->assertSee('Fanta')->assertSee('En ligne')->assertDontSee('Autre chef')->assertSee('Awa Apprenante')->assertSee('Hors ligne');
        $this->assertNotNull($admin->fresh()->last_seen_at);
        $this->actingAs($secretaire)->get('/admin/connexions')->assertForbidden();

        $this->actingAs($client, 'client')->post('/espace-client/deconnexion');
        $this->assertNotNull($client->fresh()->last_logout_at);
    }

    public function test_signer_signature_and_stamp_are_set_with_a_preview_first(): void
    {
        Storage::fake('local');
        $admin = User::factory()->administrateur()->create();
        $this->actingAs($admin)->get('/admin/attestations')->assertOk()->assertSee('SIGNATAIRE')->assertSee('data-pad', false);

        $this->put('/admin/attestations/signataire', ['director_name' => 'Mrs. Awa TRAORÉ', 'director_title' => 'The Director', 'city' => 'Ségou, Mali'])->assertRedirect();
        $this->assertSame('Mrs. Awa TRAORÉ', app(SiteSettings::class)->get('attestation.director_name'));

        // Sceau : photo d'un cachet bleu sur papier beige → fond retiré, aperçu, puis enregistrement.
        $img = imagecreatetruecolor(200, 200);
        imagefill($img, 0, 0, imagecolorallocate($img, 228, 220, 200));
        imageellipse($img, 100, 100, 160, 160, imagecolorallocate($img, 40, 70, 170));
        imagefilledellipse($img, 100, 100, 60, 60, imagecolorallocate($img, 40, 70, 170));
        ob_start();
        imagejpeg($img);
        $file = UploadedFile::fake()->createWithContent('cachet.jpg', (string) ob_get_clean());
        $this->post('/admin/attestations/stamp')->assertSessionHasErrors('file');
        $this->postJson('/admin/attestations/apercu', ['kind' => 'stamp', 'file' => $file, 'ink' => 'bleu'])->assertOk()
            ->assertJsonStructure(['processed', 'full', 'zoom']);
        $this->post('/admin/attestations/stamp')->assertRedirect();
        $this->assertTrue(app(AttestationAssets::class)->isCustom('stamp'));
        $processed = imagecreatefromstring((string) file_get_contents(app(AttestationAssets::class)->stampPath()));
        $this->assertSame(127, (imagecolorat($processed, 2, 2) >> 24) & 0x7F, 'Le fond du cachet doit être transparent.');

        // Signature tracée à l'écran.
        $pad = imagecreatetruecolor(300, 100);
        imagealphablending($pad, false);
        imagesavealpha($pad, true);
        imagefill($pad, 0, 0, imagecolorallocatealpha($pad, 0, 0, 0, 127));
        imagesetthickness($pad, 4);
        imageline($pad, 20, 70, 280, 30, imagecolorallocate($pad, 27, 63, 160));
        ob_start();
        imagepng($pad);
        $drawing = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
        $this->postJson('/admin/attestations/apercu', ['kind' => 'signature', 'drawing' => $drawing])->assertOk();
        $this->post('/admin/attestations/signature')->assertRedirect();
        $this->assertTrue(app(AttestationAssets::class)->isCustom('signature'));
        $this->get('/admin/attestations/exemple')->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->delete('/admin/attestations/stamp')->assertRedirect();
        $this->assertFalse(app(AttestationAssets::class)->isCustom('stamp'));
        $this->assertTrue(ActivityLog::where('action', 'attestations.stamp_updated')->exists());
        $this->actingAs(User::factory()->commercial()->create())->get('/admin/attestations')->assertForbidden();
    }

    public function test_a_certificate_can_be_verified_online(): void
    {
        $inscription = Inscription::factory()->create(['name' => 'Amos Dembele', 'status' => InscriptionStatus::Terminee, 'finished_at' => now(), 'amount_paid' => 100000, 'total_amount' => 100000, 'started_at' => now()->subMonths(3)]);
        app(AttestationService::class)->issue($inscription, User::factory()->administrateur()->create());
        $url = app(AttestationService::class)->verificationUrl($inscription->fresh());

        $this->get($url)->assertOk()->assertSee('Attestation authentique')->assertSee('Amos Dembele');
        $this->get('/certificat/'.$inscription->id.'/faussecle12345678901')->assertOk()->assertSee('Attestation introuvable');
    }

    public function test_an_online_learner_receives_the_certificate_remotely_and_onsite_learners_collect_it(): void
    {
        Mail::fake();
        $client = Client::factory()->create(['email' => 'awa@exemple.ml']);
        $online = Inscription::factory()->create(['client_id' => $client->id, 'email' => 'awa@exemple.ml', 'mode' => FormationMode::EnLigne, 'status' => InscriptionStatus::Terminee, 'finished_at' => now(), 'amount_paid' => 100000, 'total_amount' => 100000, 'started_at' => now()->subMonths(3)]);
        $onsite = Inscription::factory()->create(['client_id' => $client->id, 'email' => 'awa@exemple.ml', 'mode' => FormationMode::Presentiel, 'status' => InscriptionStatus::Terminee, 'finished_at' => now(), 'amount_paid' => 100000, 'total_amount' => 100000, 'started_at' => now()->subMonths(3)]);
        $admin = User::factory()->administrateur()->create();
        app(AttestationService::class)->issue($online, $admin);
        app(AttestationService::class)->issue($onsite, $admin);

        Mail::assertSent(AttestationDelivree::class, fn ($m) => $m->inscription->is($online) && count($m->attachments()) === 1 && str_contains($m->render(), 'jointe'));
        Mail::assertSent(AttestationDelivree::class, fn ($m) => $m->inscription->is($onsite) && $m->attachments() === [] && str_contains($m->render(), 'retirer au centre'));
        $this->assertNotNull($online->fresh()->attestation_sent_at);

        $this->actingAs($client, 'client')->get('/espace-client/inscriptions')->assertOk()->assertSee('Télécharger mon attestation')->assertSee('à retirer au centre');
        $this->get("/espace-client/inscriptions/{$online->id}/attestation")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get("/espace-client/inscriptions/{$onsite->id}/attestation")->assertNotFound();
        $stranger = Inscription::factory()->create(['mode' => FormationMode::EnLigne, 'status' => InscriptionStatus::Terminee, 'finished_at' => now(), 'amount_paid' => 100000, 'total_amount' => 100000, 'started_at' => now()->subMonths(3)]);
        app(AttestationService::class)->issue($stranger, $admin);
        $this->get("/espace-client/inscriptions/{$stranger->id}/attestation")->assertNotFound();
    }

    public function test_a_whole_group_gets_its_certificates_in_one_pdf(): void
    {
        Mail::fake();
        $groupe = Groupe::factory()->create();
        $done = Inscription::factory()->count(2)->create(['groupe_id' => $groupe->id, 'status' => InscriptionStatus::Terminee, 'finished_at' => now(), 'amount_paid' => 100000, 'total_amount' => 100000, 'started_at' => now()->subMonths(3)]);
        $running = Inscription::factory()->create(['groupe_id' => $groupe->id, 'status' => InscriptionStatus::EnCours, 'amount_paid' => 100000, 'total_amount' => 100000, 'started_at' => now()->subMonths(3)]);
        $unpaid = Inscription::factory()->create(['groupe_id' => $groupe->id, 'status' => InscriptionStatus::Terminee, 'finished_at' => now(), 'started_at' => now()->subMonths(3)]);
        $this->actingAs(User::factory()->administrateur()->create());

        $this->get("/admin/groupes/{$groupe->id}")->assertOk()->assertSee('ATTESTATIONS DU GROUPE')->assertSee('2 prête(s) à délivrer')->assertSee('1 en attente (paiement ou fin prévue)');
        $this->post("/admin/groupes/{$groupe->id}/attestations")->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertSame(2, Inscription::whereNotNull('attestation_number')->count());
        $this->assertNull($running->fresh()->attestation_number);

        // « Terminer d'abord » : l'apprenant en cours est terminé puis reçoit la sienne ; plus rien ensuite.
        $this->post("/admin/groupes/{$groupe->id}/attestations", ['finish' => '1'])->assertOk();
        $this->assertSame(InscriptionStatus::Terminee, $running->fresh()->status);
        $this->assertNotNull($running->fresh()->attestation_number);
        $this->post("/admin/groupes/{$groupe->id}/attestations")->assertRedirect()->assertSessionHasErrors('attestations');
        Mail::assertSent(AttestationDelivree::class, 3);
    }

    public function test_a_certificate_needs_full_payment_and_the_planned_end_which_can_be_changed(): void
    {
        $inscription = Inscription::factory()->create(['status' => InscriptionStatus::Terminee, 'finished_at' => now(), 'started_at' => now()->subWeeks(2), 'duration_months' => 3, 'total_amount' => 100000, 'amount_paid' => 60000]);
        $service = app(AttestationService::class);
        $blockers = $service->blockers($inscription);
        $this->assertCount(2, $blockers);
        $this->assertStringContainsString('reste', $blockers[0]);
        $this->assertStringContainsString('fin prévue', $blockers[1]);

        $this->actingAs(User::factory()->administrateur()->create());
        $this->get("/admin/inscriptions/{$inscription->id}")->assertOk()->assertSee('Fin prévue (modifiable)')->assertSee('Frais payés en totalité — reste');
        // La fin prévue se modifie (audit) ; le paiement intégral reste obligatoire.
        $this->put("/admin/inscriptions/{$inscription->id}/fin-prevue", ['planned_end_on' => now()->subDay()->toDateString()])->assertRedirect();
        $this->assertSame(['il reste 40'."\u{202F}".'000 FCFA à payer'], $service->blockers($inscription->fresh()));
        $this->assertTrue(ActivityLog::where('action', 'inscriptions.planned_end_changed')->exists());
        $inscription->update(['amount_paid' => 100000]);
        $this->assertSame([], $service->blockers($inscription->fresh()));
    }

    public function test_the_header_contacts_of_the_certificate_are_configurable(): void
    {
        $assets = app(AttestationAssets::class);
        app(SiteSettings::class)->save(['contact.city' => 'Ségou, Mali', 'contact.phone_1' => '+223 75 22 91 25', 'contact.email' => 'contact@ettc-mali.org']);
        $this->assertSame(['Ségou, Mali', '+223 75 22 91 25', 'contact@ettc-mali.org', 'www.ettc-mali.org'], $assets->contacts());

        $this->actingAs(User::factory()->administrateur()->create());
        $this->get('/admin/attestations')->assertOk()->assertSee('Coordonnées en haut de l\'attestation', false);
        $this->put('/admin/attestations/signataire', ['director_name' => 'Mr. Cheick O. KONATÉ', 'director_title' => 'The General Director', 'city' => 'Ségou, Mali',
            'contact_city' => 'Bamako, Mali', 'contact_phone' => '+223 70 00 00 00', 'contact_email' => '', 'contact_website' => 'ettc.ml'])->assertRedirect();
        // Vide = celle du site.
        $this->assertSame(['Bamako, Mali', '+223 70 00 00 00', 'contact@ettc-mali.org', 'ettc.ml'], $assets->contacts());
        $this->get('/admin/attestations/exemple')->assertOk()->assertHeader('content-type', 'application/pdf');
    }
}
