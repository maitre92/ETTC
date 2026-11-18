<?php

namespace Tests\Feature\Modules;

use App\Enums\DemandeStatus;
use App\Enums\InscriptionStatus;
use App\Enums\NeedType;
use App\Mail\PropositionDemande;
use App\Models\ActivityLog;
use App\Models\Demande;
use App\Models\Inscription;
use App\Models\User;
use App\Support\SiteSettings;
use App\Support\SpamGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Formation personnalisée, traduction, excursion au Ghana (cahier ETTC §3.7, §3.9, §3.10) : pages et formulaires dédiés,
 * proposition chiffrée envoyée puis acceptée d'un clic, inscription au tarif accepté, textes modifiables.
 */
class ServicesTest extends TestCase
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

    public function test_the_three_service_pages_are_public_and_editable(): void
    {
        $this->get('/traduction')->assertOk()->assertSee('Traduction professionnelle')->assertSee('name="source_language"', false);
        $this->get('/formation-personnalisee')->assertOk()->assertSee('Vos objectifs');
        $this->get('/excursion-ghana')->assertOk()->assertSee('Excursion au Ghana')->assertSee('Communiquées sur demande')->assertSee('Prestations incluses');
        foreach (['/traduction', '/formation-personnalisee', '/excursion-ghana'] as $uri) {
            $this->assertStringNotContainsString('à compléter', mb_strtolower($this->get($uri)->getContent()));
        }
        $this->get('/')->assertSee(route('site.traduction'), false)->assertSee(route('site.excursion'), false);

        app(SiteSettings::class)->save(['excursion.dates' => 'Du 10 au 20 août 2027', 'excursion.price' => '450 000 FCFA par personne']);
        $this->get('/excursion-ghana')->assertSee('Du 10 au 20 août 2027')->assertSee('450 000 FCFA par personne');
    }

    public function test_a_translation_request_keeps_its_languages_and_document(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->postJson('/devis', $this->human(['need_type' => 'traduction', 'source_language' => 'Français', 'target_language' => 'Français', 'name' => 'Awa', 'phone' => '75 22 91 25']))
            ->assertUnprocessable()->assertJsonValidationErrors('target_language');

        $this->postJson('/devis', $this->human(['need_type' => 'traduction', 'source_language' => 'Français', 'target_language' => 'Anglais', 'document_type' => 'Diplôme',
            'name' => 'Awa', 'phone' => '75 22 91 25', 'attachment' => UploadedFile::fake()->create('diplome.pdf', 300, 'application/pdf')]))->assertOk();
        $demande = Demande::firstOrFail();
        $this->assertSame([NeedType::Traduction, 'Français → Anglais', 'Diplôme'], [$demande->need_type, $demande->languagesLabel(), $demande->document_type]);
        $this->assertNotNull($demande->attachment_path);

        $this->postJson('/devis', $this->human(['need_type' => 'excursion', 'participants' => 3, 'name' => 'Moussa', 'email' => 'moussa@exemple.ml']))->assertOk();
        $this->assertSame(3, Demande::latest('id')->first()->participants);
    }

    public function test_a_personalised_training_goes_from_proposal_to_paid_inscription(): void
    {
        Mail::fake();
        $demande = Demande::factory()->create(['type' => 'devis', 'need_type' => NeedType::FormationPersonnalisee, 'name' => 'Fatou', 'email' => 'fatou@exemple.ml', 'phone' => '+22375000001']);
        $this->actingAs(User::factory()->commercial()->create());

        $this->get("/admin/demandes/{$demande->id}")->assertOk()->assertSee('PROPOSITION')->assertSee('Envoyer la proposition');
        $this->post("/admin/demandes/{$demande->id}/proposition", ['proposal_amount' => '', 'proposal_text' => 'X'])->assertSessionHasErrors('proposal_amount');
        $this->post("/admin/demandes/{$demande->id}/proposition", ['proposal_amount' => 120000, 'proposal_text' => '12 séances en ligne, 2 fois par semaine.'])->assertRedirect();

        $demande->refresh();
        $this->assertSame([DemandeStatus::DevisEnvoye, 120000], [$demande->status, $demande->proposal_amount]);
        Mail::assertSent(PropositionDemande::class, fn ($m) => $m->hasTo('fatou@exemple.ml') && str_contains($m->render(), route('site.proposition.show', $demande->proposal_token)));

        // Le demandeur accepte d'un clic (une seule fois) ; un mauvais lien n'existe pas.
        auth()->logout();
        $this->get('/proposition/mauvais-lien')->assertNotFound();
        $this->get("/proposition/{$demande->proposal_token}")->assertOk()->assertSee('120 000 FCFA')->assertSee('J\'accepte cette proposition', false);
        $this->post("/proposition/{$demande->proposal_token}/accepter")->assertRedirect("/proposition/{$demande->proposal_token}");
        $this->assertSame(DemandeStatus::Gagnee, $demande->fresh()->status);
        $this->get("/proposition/{$demande->proposal_token}")->assertSee('Proposition acceptée')->assertDontSee('J\'accepte cette proposition', false);
        Mail::assertSent(PropositionDemande::class, fn ($m) => $m->kind === 'acceptee' && $m->hasTo('equipe@ettc.test'));
        $this->assertTrue(ActivityLog::where('action', 'demandes.proposal_accepted')->exists());

        // L'équipe l'inscrit au tarif accepté, puis encaisse.
        $this->actingAs(User::factory()->commercial()->create());
        $this->get("/admin/demandes/{$demande->id}")->assertSee('Inscrire');
        $this->post("/admin/demandes/{$demande->id}/inscription", ['mode' => 'en_ligne'])->assertRedirect();
        $inscription = Inscription::firstOrFail();
        $this->assertSame(['Formation personnalisée', 120000, InscriptionStatus::Validee, $demande->id], [$inscription->formule_name, $inscription->total_amount, $inscription->status, $inscription->demande_id]);
        $this->post("/admin/demandes/{$demande->id}/inscription", ['mode' => 'en_ligne']);
        $this->assertSame(1, Inscription::count());

        $this->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 120000, 'method' => 'especes'])->assertRedirect();
        $this->assertSame('Soldée', $inscription->fresh()->paymentState());
        $this->get("/admin/inscriptions/{$inscription->id}")->assertOk();
    }

    public function test_a_contact_message_has_no_proposal(): void
    {
        $demande = Demande::factory()->create(['type' => 'contact']);
        $this->actingAs(User::factory()->administrateur()->create());
        $this->get("/admin/demandes/{$demande->id}")->assertOk()->assertDontSee('Envoyer la proposition');
        $this->post("/admin/demandes/{$demande->id}/proposition", ['proposal_amount' => 1000, 'proposal_text' => 'X'])->assertNotFound();
    }
}
