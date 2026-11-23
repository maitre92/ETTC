<?php

namespace Tests\Feature\Modules;

use App\Enums\InscriptionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\CommissionVersement;
use App\Models\Inscription;
use App\Models\MouvementCaisse;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Caisse\CaisseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Caisse, moyens de paiement au centre (espèces, mobile money, banque) et journal d'activité de l'équipe
 * (demande du centre, 2026-11-21).
 */
class CaisseTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    public function test_a_bank_payment_at_the_centre_needs_its_reference(): void
    {
        Mail::fake();
        $inscription = Inscription::factory()->create(['status' => InscriptionStatus::Validee]);
        $this->actingAs(User::factory()->commercial()->create());

        $this->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 50000, 'method' => 'banque'])->assertSessionHasErrors('reference');
        $this->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 50000, 'method' => 'banque', 'reference' => 'chq 0012345'])->assertRedirect();
        $paiement = Paiement::firstOrFail();
        $this->assertSame([PaymentMethod::Banque, 'CHQ 0012345'], [$paiement->method, $paiement->reference]);
        $this->get("/admin/inscriptions/{$inscription->id}")->assertOk()->assertSee('<optgroup label="Banque">', false);
    }

    public function test_the_cash_book_gathers_payments_revenues_expenses_and_transfers(): void
    {
        Storage::fake('local');
        $inscription = Inscription::factory()->create(['status' => InscriptionStatus::EnCours]);
        Paiement::factory()->create(['inscription_id' => $inscription->id, 'amount' => 50000, 'method' => PaymentMethod::Especes, 'status' => PaymentStatus::Confirme, 'paid_at' => now()]);
        Paiement::factory()->create(['inscription_id' => $inscription->id, 'amount' => 20000, 'method' => PaymentMethod::Wave, 'status' => PaymentStatus::Confirme, 'paid_at' => now()]);
        Paiement::factory()->create(['inscription_id' => $inscription->id, 'amount' => 9000, 'method' => PaymentMethod::Especes, 'status' => PaymentStatus::AVerifier, 'paid_at' => now()]);
        CommissionVersement::create(['period' => now()->format('Y-m'), 'amount' => 4000, 'paid_on' => now(), 'method' => 'banque']);
        $secretaire = User::factory()->commercial()->create(['name' => 'Fanta Secrétaire']);
        $this->actingAs($secretaire);

        $this->get('/admin/caisse')->assertOk()->assertSee('JOURNAL DE CAISSE')->assertSee('Revenu')->assertSee('Dépense');
        $this->post('/admin/caisse', ['type' => 'entree', 'category' => 'solde_ouverture', 'label' => 'Banque au départ', 'amount' => 10000, 'method' => 'banque', 'occurred_on' => now()->toDateString()])->assertRedirect();
        $this->post('/admin/caisse', ['type' => 'sortie', 'category' => 'electricite_eau', 'label' => 'Facture EDM', 'amount' => 15000, 'method' => 'especes', 'occurred_on' => now()->toDateString(),
            'proof' => UploadedFile::fake()->create('facture.pdf', 50, 'application/pdf')])->assertRedirect();
        $this->post('/admin/caisse', ['type' => 'transfert', 'category' => 'transfert', 'label' => 'Dépôt banque', 'amount' => 30000, 'method' => 'especes', 'to_method' => 'banque', 'occurred_on' => now()->toDateString()])->assertRedirect();
        // Garde-fous : catégorie d'un autre type, transfert vers le même moyen, date future.
        $this->post('/admin/caisse', ['type' => 'sortie', 'category' => 'vente', 'label' => 'X', 'amount' => 1, 'method' => 'especes', 'occurred_on' => now()->toDateString()])->assertSessionHasErrors('category');
        $this->post('/admin/caisse', ['type' => 'transfert', 'category' => 'transfert', 'label' => 'X', 'amount' => 1, 'method' => 'banque', 'to_method' => 'banque', 'occurred_on' => now()->toDateString()])->assertSessionHasErrors('to_method');
        $this->post('/admin/caisse', ['type' => 'sortie', 'category' => 'loyer', 'label' => 'X', 'amount' => 1, 'method' => 'especes', 'occurred_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('occurred_on');

        // Espèces : 50 000 − 15 000 − 30 000 ; mobile money : 20 000 ; banque : 10 000 + 30 000 − 4 000 (commission).
        $this->assertSame(['especes' => 5000, 'mobile_money' => 20000, 'banque' => 36000, 'total' => 61000], app(CaisseService::class)->balances());
        $journal = app(CaisseService::class)->journal(now()->toImmutable()->startOfDay(), now()->toImmutable()->endOfDay());
        $this->assertSame(['in' => 80000, 'out' => 19000, 'result' => 61000], array_slice(app(CaisseService::class)->totals($journal), 0, 3));
        $this->assertTrue(ActivityLog::where('action', 'caisse.recorded')->where('user_name', 'Fanta Secrétaire')->exists());

        // La secrétaire n'annule pas ; l'administrateur annule avec un motif (jamais de suppression).
        $facture = MouvementCaisse::where('label', 'Facture EDM')->firstOrFail();
        $this->put("/admin/caisse/{$facture->id}/annulation", ['reason' => 'Doublon'])->assertForbidden();
        $this->get("/admin/caisse/{$facture->id}/justificatif")->assertOk();
        $this->actingAs(User::factory()->administrateur()->create());
        $this->put("/admin/caisse/{$facture->id}/annulation", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->put("/admin/caisse/{$facture->id}/annulation", ['reason' => 'Doublon'])->assertRedirect();
        $this->assertSame(20000, app(CaisseService::class)->balances()['especes']);
        $this->assertTrue(MouvementCaisse::whereKey($facture->id)->exists());

        $this->get('/admin/caisse/export/excel?periode=mois')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->get('/admin/caisse/export/pdf?periode=mois')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_administrator_sees_the_actions_of_his_team_but_not_the_superadmin(): void
    {
        $admin = User::factory()->administrateur()->create(['name' => 'Chef']);
        $secretaire = User::factory()->commercial()->create(['name' => 'Fanta']);
        $other = User::factory()->administrateur()->create(['name' => 'Autre chef']);
        $logger = app(ActivityLogger::class);
        $logger->log('test.secretaire', 'test', 'Action de la secrétaire', actor: $secretaire);
        $logger->log('test.admin', 'test', 'Action du chef', actor: $admin);
        $logger->log('test.autre', 'test', 'Action d un autre administrateur', actor: $other);
        $logger->log('test.super', 'test', 'Action du superadmin', actor: $this->superadmin());

        $this->actingAs($admin)->get('/admin/journal-activite')->assertOk()
            ->assertSee('Action de la secrétaire')->assertSee('Action du chef')
            ->assertDontSee('Action d un autre administrateur')->assertDontSee('Action du superadmin');
        $this->get('/admin/audit')->assertForbidden();
        $this->actingAs($secretaire)->get('/admin/journal-activite')->assertForbidden();
        $this->actingAs($this->superadmin())->get('/admin/journal-activite')->assertOk()->assertSee('Action du superadmin')->assertSee('Action d un autre administrateur');
    }

    public function test_only_the_administrator_cancels_a_payment_the_secretary_requests_it(): void
    {
        Mail::fake();
        $inscription = Inscription::factory()->create(['status' => InscriptionStatus::EnCours]);
        $paiement = Paiement::factory()->create(['inscription_id' => $inscription->id, 'amount' => 50000, 'status' => PaymentStatus::Confirme, 'paid_at' => now()]);
        $inscription->update(['amount_paid' => 50000]);
        $secretaire = User::factory()->commercial()->create(['name' => 'Fanta']);
        $admin = User::factory()->administrateur()->create();

        $this->actingAs($secretaire)->put("/admin/paiements/{$paiement->id}/annulation", ['reason' => 'Erreur'])->assertForbidden();
        $this->get("/admin/paiements/{$paiement->id}")->assertOk()->assertSee('DEMANDER UNE ANNULATION');
        $this->put("/admin/paiements/{$paiement->id}/demande-annulation", ['type' => 'annulation', 'reason' => 'Saisi deux fois'])->assertRedirect();
        $this->put("/admin/paiements/{$paiement->id}/demande-annulation", ['type' => 'annulation', 'reason' => 'Encore'])->assertSessionHasErrors('reason');
        $this->put("/admin/paiements/{$paiement->id}/demande-annulation/validation")->assertForbidden();
        $this->assertSame(PaymentStatus::Confirme, $paiement->fresh()->status);

        // L'administrateur voit la demande (pastille, liste) et la valide.
        $this->actingAs($admin)->get('/admin/paiements')->assertOk()->assertSee('demande(s) d\'annulation à valider', false)->assertSee('Saisi deux fois');
        $this->put("/admin/paiements/{$paiement->id}/demande-annulation/validation")->assertRedirect();
        $paiement->refresh();
        $this->assertSame(PaymentStatus::Annule, $paiement->status);
        $this->assertStringContainsString('demandé par Fanta', $paiement->cancel_reason);
        $this->assertSame(0, $inscription->fresh()->amount_paid);
        $this->assertTrue(ActivityLog::where('action', 'paiements.cancel_requested')->exists());

        // Refus : le paiement reste confirmé.
        $other = Paiement::factory()->create(['inscription_id' => $inscription->id, 'amount' => 10000, 'status' => PaymentStatus::Confirme, 'paid_at' => now()]);
        $this->actingAs($secretaire)->put("/admin/paiements/{$other->id}/demande-annulation", ['type' => 'remboursement', 'reason' => 'Abandon'])->assertRedirect();
        $this->actingAs($admin)->put("/admin/paiements/{$other->id}/demande-annulation/refus", ['reject_reason' => 'Pas de remboursement'])->assertRedirect();
        $this->assertSame(PaymentStatus::Confirme, $other->fresh()->status);
        $this->assertFalse($other->fresh()->hasCancelRequest());
    }

    public function test_the_administrator_grants_his_own_rights_to_his_team_only(): void
    {
        $admin = User::factory()->administrateur()->create();
        $secretaire = User::factory()->commercial()->create(['name' => 'Fanta']);
        $other = User::factory()->administrateur()->create(['name' => 'Autre chef']);

        $this->actingAs($admin)->get('/admin/equipe/permissions?user='.$secretaire->id)->assertOk()
            ->assertSee('Fanta')->assertDontSee('Autre chef')->assertSee('value="paiements.refund"', false)->assertDontSee('value="audit.view"', false);

        // Un droit de l'administrateur : donné ; un droit réservé au superadmin : refusé ; un pair : interdit.
        $this->put("/admin/equipe/permissions/{$secretaire->id}", ['permissions' => ['paiements.refund', 'caisse.cancel']])->assertRedirect();
        $this->assertTrue($secretaire->fresh()->can('paiements.refund'));
        $this->put("/admin/equipe/permissions/{$secretaire->id}", ['permissions' => ['audit.view']])->assertSessionHasErrors('permissions');
        $this->put("/admin/equipe/permissions/{$other->id}", ['permissions' => []])->assertForbidden();
        $this->assertTrue(ActivityLog::where('action', 'equipe.permissions_updated')->exists());

        // Retirer : la case décochée enlève le droit.
        $this->put("/admin/equipe/permissions/{$secretaire->id}", ['permissions' => ['caisse.cancel']])->assertRedirect();
        $this->assertFalse($secretaire->fresh()->can('paiements.refund'));
        $this->actingAs($secretaire)->get('/admin/equipe/permissions')->assertForbidden();
    }
}
