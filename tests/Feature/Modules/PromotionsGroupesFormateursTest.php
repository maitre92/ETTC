<?php

namespace Tests\Feature\Modules;

use App\Enums\Billing;
use App\Enums\FormationMode;
use App\Enums\InscriptionStatus;
use App\Enums\RoleName;
use App\Mail\MessageGroupe;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\Formule;
use App\Models\Groupe;
use App\Models\GroupeMessage;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Finances\CommissionCalculator;
use App\Services\Formations\Tarification;
use App\Support\SiteSettings;
use App\Support\SpamGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Demande du centre (2026-11-21) : frais d'inscription, promotions (automatiques et codes promo), répartition des
 * apprenants en groupes, espace formateur et discussion du groupe, paiement du reliquat facilité.
 */
class PromotionsGroupesFormateursTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        config(['site.notification_email' => 'equipe@ettc.test']);
    }

    private function formule(): Formule
    {
        return Formule::factory()->create(['name' => 'Formule 2 mois', 'duration_months' => 2, 'price_online' => 50000, 'price_onsite' => 20000, 'billing' => Billing::Mensuel]);
    }

    /** @param  array<string, mixed>  $data */
    private function human(array $data): array
    {
        return $data + ['consent' => '1', SpamGuard::TOKEN => Crypt::encryptString((string) now()->subSeconds(20)->getTimestamp())];
    }

    private function formateur(): User
    {
        return User::factory()->withRole(RoleName::Formateur)->create(['name' => 'Mme Traoré']);
    }

    public function test_the_registration_fee_is_shown_and_frozen_and_kept_out_of_the_commission(): void
    {
        $formule = $this->formule();
        $this->get('/formations')->assertOk()->assertSee('de frais d\'inscription', false);
        $this->get('/inscription')->assertOk()->assertSee('5'."\u{202F}".'000 FCFA de frais d\'inscription', false);

        $this->getJson('/inscription/tarif?formule_id='.$formule->id.'&mode=en_ligne')->assertOk()
            ->assertJsonPath('total', 105000)->assertJsonPath('first', 55000)
            ->assertJsonPath('lines.1.0', "Frais d'inscription (une seule fois)");

        // Réglage modifiable, audité ; 0 = aucun frais.
        $admin = User::factory()->administrateur()->create();
        $this->actingAs($admin)->put('/admin/formations/frais-inscription', ['registration_fee' => '0'])->assertRedirect();
        $this->assertSame(0, app(Tarification::class)->registrationFee());
        $this->assertTrue(ActivityLog::where('action', 'formations.fee_updated')->exists());
        $this->getJson('/inscription/tarif?formule_id='.$formule->id.'&mode=en_ligne')->assertJsonPath('total', 100000);

        // Commission : les frais d'inscription (payés en premier) n'y entrent pas.
        app(SiteSettings::class)->save(['formation.registration_fee' => '5000']);
        $inscription = Inscription::factory()->create(['formule_id' => $formule->id, 'registration_fee' => 5000, 'formation_amount' => 100000, 'total_amount' => 105000]);
        $first = app(CommissionCalculator::class)->forPayment($inscription, 55000);
        $this->assertSame(10000, $first['commission_amount']);
    }

    public function test_automatic_promotion_and_promo_code_reduce_only_the_training_fees(): void
    {
        $formule = $this->formule();
        $admin = User::factory()->administrateur()->create();
        $this->actingAs($admin)->get('/admin/formations/promotions')->assertOk()->assertSee('PROMOTIONS');
        $this->post('/admin/formations/promotions', ['name' => 'Rentrée', 'kind' => 'percent', 'value' => '10', 'is_active' => '1'])->assertRedirect();
        $this->post('/admin/formations/promotions', ['name' => 'Partenaires', 'code' => 'ami 2027', 'kind' => 'amount', 'value' => '30 000', 'mode' => 'en_ligne', 'is_active' => '1'])->assertRedirect();
        $this->post('/admin/formations/promotions', ['name' => 'Trop', 'kind' => 'percent', 'value' => '150', 'is_active' => '1'])->assertSessionHasErrors('value');
        $this->assertSame('AMI2027', Promotion::where('name', 'Partenaires')->value('code'));
        auth()->logout();

        // Automatique : -10 % de 100 000 ; le code (−30 000) est meilleur en ligne ; un code inconnu est signalé.
        $this->getJson('/inscription/tarif?formule_id='.$formule->id.'&mode=en_ligne')->assertJsonPath('total', 95000);
        $this->getJson('/inscription/tarif?formule_id='.$formule->id.'&mode=en_ligne&promo_code=ami2027')->assertJsonPath('total', 75000)->assertJsonPath('code_ok', true);
        $this->getJson('/inscription/tarif?formule_id='.$formule->id.'&mode=en_ligne&promo_code=FAUX')->assertJsonPath('total', 95000)->assertJsonPath('code_error', fn ($e) => str_contains($e, 'pas valable'));
        $this->get('/formations')->assertSee('Rentrée (-10 %)', false);

        $this->postJson('/inscription', $this->human(['formule_id' => $formule->id, 'mode' => 'en_ligne', 'payment_choice' => 'en_ligne', 'promo_code' => 'FAUX', 'name' => 'Awa', 'phone' => '75 22 91 25']))
            ->assertUnprocessable()->assertJsonValidationErrors('promo_code');
        $this->postJson('/inscription', $this->human(['formule_id' => $formule->id, 'mode' => 'en_ligne', 'payment_choice' => 'en_ligne', 'promo_code' => 'ami2027', 'name' => 'Awa', 'phone' => '75 22 91 25']))->assertOk();

        $inscription = Inscription::firstOrFail();
        $this->assertSame([100000, 30000, 'AMI2027', 5000, 75000], [$inscription->formation_amount, $inscription->discount_amount, $inscription->promo_code, $inscription->registration_fee, $inscription->total_amount]);
        $this->assertSame(1, Promotion::where('code', 'AMI2027')->value('uses_count'));
        // Premier paiement : frais d'inscription + une échéance de la formation réduite (70 000 / 2).
        $this->assertSame(40000, $inscription->nextInstallment());

        // Promotion terminée : plus appliquée, l'inscription garde sa réduction.
        Promotion::query()->each(fn ($p) => $p->update(['ends_on' => now()->subDay()]));
        $this->getJson('/inscription/tarif?formule_id='.$formule->id.'&mode=en_ligne')->assertJsonPath('total', 105000);
        $this->assertSame(75000, $inscription->fresh()->total_amount);
    }

    public function test_learners_are_split_into_balanced_groups_and_a_full_group_refuses_more(): void
    {
        $niveau = Niveau::factory()->create(['name' => 'Débutant']);
        Inscription::factory()->count(23)->create(['mode' => FormationMode::EnLigne, 'niveau_id' => $niveau->id, 'status' => InscriptionStatus::EnCours]);
        Inscription::factory()->create(['mode' => FormationMode::EnLigne, 'niveau_id' => null, 'status' => InscriptionStatus::EnCours]);
        $this->actingAs(User::factory()->gestionnaire()->create());

        $this->get('/admin/groupes')->assertOk()->assertSee('Répartir automatiquement');
        $this->post('/admin/groupes/repartition', ['capacity' => 15])->assertRedirect();

        $groupes = Groupe::withCount('members')->orderBy('name')->get();
        $this->assertSame(['Débutant — En ligne A', 'Débutant — En ligne B'], $groupes->pluck('name')->all());
        $this->assertSame([12, 11], $groupes->pluck('members_count')->all());
        $this->assertSame(1, Inscription::whereNull('groupe_id')->count());

        $full = $groupes->first();
        $full->update(['capacity' => 12]);
        $extra = Inscription::factory()->create(['mode' => FormationMode::EnLigne, 'niveau_id' => $niveau->id, 'status' => InscriptionStatus::EnCours]);
        $this->post("/admin/groupes/{$full->id}/apprenants", ['inscriptions' => [$extra->id]])->assertSessionHasErrors('inscriptions');
        $this->assertNull($extra->fresh()->groupe_id);
    }

    public function test_a_teacher_sees_only_his_groups_and_talks_with_his_learners(): void
    {
        Mail::fake();
        $formateur = $this->formateur();
        $mine = Groupe::factory()->create(['name' => 'Débutants soir', 'formateur_id' => $formateur->id]);
        $other = Groupe::factory()->create(['name' => 'Avancés matin']);
        $client = Client::factory()->create(['name' => 'Awa']);
        Inscription::factory()->create(['client_id' => $client->id, 'groupe_id' => $mine->id, 'status' => InscriptionStatus::EnCours, 'email' => 'awa@exemple.ml', 'name' => 'Awa']);

        // Connexion du formateur : son espace, ses groupes seulement, rien d'autre de l'administration.
        $this->actingAs($formateur)->get('/admin')->assertRedirect(route('formateur.index'));
        $this->get('/admin/mon-espace-formateur')->assertOk()->assertSee('Débutants soir')->assertDontSee('Avancés matin');
        $this->get("/admin/mon-espace-formateur/groupes/{$other->id}")->assertNotFound();
        $this->get('/admin/paiements')->assertForbidden();
        $this->get('/admin/groupes')->assertForbidden();

        // Il écrit au groupe : les apprenants sont prévenus par e-mail ; il programme une séance et dépose un lien.
        $this->post("/admin/mon-espace-formateur/groupes/{$mine->id}/messages", ['body' => 'Révisez la leçon 2.', 'email' => '1'])->assertRedirect();
        Mail::assertSent(MessageGroupe::class, fn ($m) => $m->hasTo('awa@exemple.ml') && str_contains($m->render(), 'Révisez la leçon 2.'));
        $this->post("/admin/mon-espace-formateur/groupes/{$mine->id}/seances", ['starts_at' => now()->addDays(2)->format('Y-m-d\TH:i'), 'duration_minutes' => 90, 'title' => 'Leçon 3'])->assertRedirect();
        $this->assertSame(1, $mine->seances()->count());
        $this->post("/admin/mon-espace-formateur/groupes/{$mine->id}/supports", ['title' => 'Vidéo', 'url' => 'youtu.be/abc'])->assertRedirect();
        $this->post("/admin/mon-espace-formateur/groupes/{$other->id}/messages", ['body' => 'Intrus'])->assertNotFound();

        // L'apprenant lit et répond depuis son espace ; le formateur voit le nouveau message.
        auth()->logout();
        $this->actingAs($client, 'client')->get('/espace-client/groupes')->assertRedirect(route('client.groupes.show', $mine));
        $this->get("/espace-client/groupes/{$mine->id}")->assertOk()->assertSee('Révisez la leçon 2.')->assertSee('Mme Traoré');
        $this->get("/espace-client/groupes/{$other->id}")->assertNotFound();
        $this->post("/espace-client/groupes/{$mine->id}/messages", ['body' => 'Merci, bien reçu !'])->assertRedirect();
        $this->post("/espace-client/groupes/{$other->id}/messages", ['body' => 'Intrus'])->assertNotFound();

        $this->assertSame(1, GroupeMessage::unreadCount('user:'.$formateur->id, $mine->id));
        $this->actingAs($formateur)->get('/admin/mon-espace-formateur')->assertSee('1 nouveau(x) message(s)');
        $this->get("/admin/mon-espace-formateur/groupes/{$mine->id}")->assertOk()->assertSee('Merci, bien reçu !');
        $this->assertSame(0, GroupeMessage::unreadCount('user:'.$formateur->id, $mine->id));

        // L'équipe choisit le formateur dans la fiche du groupe et lit la discussion.
        $this->actingAs(User::factory()->gestionnaire()->create())->get("/admin/groupes/{$mine->id}")->assertOk()->assertSee('DISCUSSION DU GROUPE')->assertSee('Merci, bien reçu !');
    }

    public function test_paying_the_remainder_is_one_click_away(): void
    {
        $client = Client::factory()->create();
        app(SiteSettings::class)->save(['paiement.orange_money' => '75 22 91 25']);
        $inscription = Inscription::factory()->create(['client_id' => $client->id, 'mode' => FormationMode::EnLigne, 'status' => InscriptionStatus::EnCours,
            'unit_price' => 50000, 'formation_amount' => 100000, 'registration_fee' => 5000, 'total_amount' => 105000, 'amount_paid' => 5000]);

        $this->actingAs($client, 'client')->get('/espace-client')->assertOk()->assertSee('À payer maintenant')->assertSee('#inscription-'.$inscription->id, false);
        $this->get('/espace-client/paiements')->assertOk()
            ->assertSee('data-fill-amount="50000"', false)->assertSee('data-fill-amount="100000"', false)->assertSee('Détail du tarif')->assertSee("Frais d'inscription (une seule fois)");
        $this->assertStringContainsString(urlencode(route('client.paiements')), (string) $inscription->whatsappMessageUrl());
    }
}
