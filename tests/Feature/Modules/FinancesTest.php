<?php

namespace Tests\Feature\Modules;

use App\Enums\Billing;
use App\Enums\InscriptionStatus;
use App\Models\ActivityLog;
use App\Models\CommissionVersement;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use App\Services\Finances\FinanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Finances et commission (cahier ETTC §5, §8) : commission de 20 % figée sur chaque paiement de formation confirmé,
 * remboursements exclus, formations personnalisées hors commission par défaut, versements, rapports, droits.
 */
class FinancesTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        Mail::fake();
    }

    private function inscription(array $attributes = []): Inscription
    {
        return Inscription::factory()->create($attributes + ['status' => InscriptionStatus::Validee, 'billing' => Billing::Mensuel, 'unit_price' => 50000, 'total_amount' => 100000]);
    }

    public function test_the_commission_is_frozen_on_each_confirmed_payment_and_refunds_are_excluded(): void
    {
        $admin = User::factory()->administrateur()->create();
        $formation = $this->inscription();
        $this->actingAs($admin)->post("/admin/inscriptions/{$formation->id}/paiements", ['amount' => 50000, 'method' => 'especes'])->assertRedirect();
        $this->post("/admin/inscriptions/{$formation->id}/paiements", ['amount' => 50000, 'method' => 'especes'])->assertRedirect();
        [$first, $second] = Paiement::orderBy('id')->get()->all();
        $this->assertSame([20.0, 10000], [$first->commission_rate, $first->commission_amount]);

        // Formation personnalisée : hors commission tant que le centre ne l'a pas convenu.
        $perso = $this->inscription(['formule_id' => null, 'formule_name' => 'Formation personnalisée', 'billing' => Billing::Unique, 'total_amount' => 80000, 'unit_price' => 80000]);
        $this->post("/admin/inscriptions/{$perso->id}/paiements", ['amount' => 80000, 'method' => 'especes'])->assertRedirect();
        $this->assertSame(0, Paiement::latest('id')->first()->commission_amount);

        // Un remboursement sort le paiement des recettes et de la commission.
        $this->put("/admin/paiements/{$second->id}/remboursement", ['reason' => 'Renonce'])->assertRedirect();

        $this->get('/admin/finances')->assertOk()->assertSee('FCFA', false)->assertSee('Commission du développeur')->assertSee('MOIS PAR MOIS');
        $totals = app(FinanceService::class)->totals(now()->toImmutable()->startOfMonth(), now()->toImmutable()->endOfDay());
        $this->assertSame([130000, 10000, 120000, 10000], [$totals['formation'], $totals['commission'], $totals['centre'], $totals['due']]);
    }

    public function test_a_rate_change_only_applies_to_new_payments_and_payouts_reduce_the_balance(): void
    {
        $admin = $this->superadmin();
        $inscription = $this->inscription();
        $this->actingAs($admin)->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 50000, 'method' => 'especes']);

        $this->put('/admin/finances/reglages', ['rate' => 25])->assertRedirect();
        $this->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 50000, 'method' => 'especes']);
        $this->assertSame([10000, 12500], Paiement::orderBy('id')->pluck('commission_amount')->all());
        $this->assertTrue(ActivityLog::where('action', 'finances.settings_updated')->exists());

        $this->post('/admin/finances/versements', ['period' => now()->format('Y-m'), 'amount' => 20000, 'paid_on' => now()->format('Y-m-d'), 'reference' => 'VIR-001'])->assertRedirect();
        $this->assertSame(1, CommissionVersement::count());
        $overall = app(FinanceService::class)->overall();
        $this->assertSame([22500, 20000, 2500], [$overall['commission'], $overall['paid'], $overall['due']]);
        $this->post('/admin/finances/versements', ['period' => 'mauvais', 'amount' => 1, 'paid_on' => now()->format('Y-m-d')])->assertSessionHasErrors('period');
    }

    public function test_reports_and_permissions(): void
    {
        $inscription = $this->inscription(['name' => 'Awa Rapport']);
        $admin = User::factory()->administrateur()->create();
        $this->actingAs($admin)->post("/admin/inscriptions/{$inscription->id}/paiements", ['amount' => 50000, 'method' => 'especes']);

        $this->get('/admin/finances/rapport?periode=annee')->assertOk()->assertHeader('content-type', 'application/pdf');
        $csv = $this->get('/admin/finances/export?periode=tout')->assertOk()->streamedContent();
        $this->assertStringContainsString('Awa Rapport', $csv);
        $this->assertStringContainsString('10000', $csv);
        $this->get('/admin/finances?periode=dates&du=2026-01-01&au=2026-12-31')->assertOk();

        $this->put('/admin/finances/reglages', ['rate' => 30])->assertForbidden();
        $this->actingAs(User::factory()->commercial()->create())->get('/admin/finances')->assertForbidden();
        $this->actingAs(User::factory()->gestionnaire()->create())->get('/admin/finances')->assertForbidden();
    }
}
