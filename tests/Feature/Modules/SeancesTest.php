<?php

namespace Tests\Feature\Modules;

use App\Enums\FormationMode;
use App\Enums\InscriptionStatus;
use App\Mail\AnnonceCentre;
use App\Mail\SeancesCalendrier;
use App\Models\ActivityLog;
use App\Models\Annonce;
use App\Models\Client;
use App\Models\Groupe;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Seance;
use App\Models\Support;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/**
 * Séances (cahier ETTC §3.6, §4, §5) : groupes, apprenants proposés, séances en série (calendrier envoyé), annulation
 * avec prévenance, supports privés, espace apprenant (« Rejoindre » 15 min avant), droits par rôle.
 */
class SeancesTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
    }

    public function test_the_team_creates_a_group_adds_learners_and_plans_a_series(): void
    {
        Mail::fake();
        $niveau = Niveau::factory()->create(['name' => 'Débutant']);
        $awa = Inscription::factory()->create(['name' => 'Awa', 'mode' => FormationMode::EnLigne, 'niveau_id' => $niveau->id, 'status' => InscriptionStatus::EnCours, 'email' => 'awa@exemple.ml']);
        $moussa = Inscription::factory()->create(['name' => 'Moussa', 'mode' => FormationMode::EnLigne, 'niveau_id' => null, 'status' => InscriptionStatus::Validee]);
        $other = Inscription::factory()->create(['name' => 'Présentiel', 'mode' => FormationMode::Presentiel, 'status' => InscriptionStatus::EnCours]);
        $this->actingAs(User::factory()->gestionnaire()->create());

        $this->get('/admin/groupes')->assertOk()->assertSee("SÉANCES D'AUJOURD'HUI", false)->assertSee('3 apprenant(s)', false);
        $this->post('/admin/groupes', ['name' => 'Débutants soir', 'mode' => 'en_ligne', 'niveau_id' => $niveau->id])->assertSessionHasErrors('meet_url');
        $this->post('/admin/groupes', ['name' => 'Débutants soir', 'mode' => 'en_ligne', 'niveau_id' => $niveau->id, 'meet_url' => 'meet.google.com/abc-defg-hij', 'teacher' => 'M. Traoré'])->assertRedirect();
        $groupe = Groupe::firstOrFail();
        $this->assertSame('https://meet.google.com/abc-defg-hij', $groupe->meet_url);

        // Proposés : même mode, niveau du groupe ou à déterminer ; jamais le présentiel.
        $this->get("/admin/groupes/{$groupe->id}")->assertOk()->assertSee('QUE FAIRE MAINTENANT')->assertSee('Awa')->assertSee('Moussa')->assertDontSee('Présentiel');
        $this->post("/admin/groupes/{$groupe->id}/apprenants", ['inscriptions' => [$awa->id, $moussa->id]])->assertRedirect();
        $this->assertSame([$groupe->id, 'Débutants soir'], [$awa->fresh()->groupe_id, $awa->fresh()->session]);
        $this->assertNull($other->fresh()->groupe_id);

        $monday = now()->next('Monday')->toDateString();
        $this->post("/admin/groupes/{$groupe->id}/serie", ['days' => [1, 3], 'time' => '18:00', 'duration_minutes' => 90, 'from' => $monday, 'to' => now()->next('Monday')->addDays(13)->toDateString()])->assertRedirect();
        $this->assertSame(4, Seance::count());
        Mail::assertSent(SeancesCalendrier::class, fn ($m) => $m->hasTo('awa@exemple.ml') && str_contains($m->render(), 'Voir mon calendrier'));
        // Rejouer la même série ne double pas les séances.
        $this->post("/admin/groupes/{$groupe->id}/serie", ['days' => [1, 3], 'time' => '18:00', 'duration_minutes' => 90, 'from' => $monday, 'to' => now()->next('Monday')->addDays(13)->toDateString()]);
        $this->assertSame(4, Seance::count());
        $this->post("/admin/groupes/{$groupe->id}/serie", ['days' => [7], 'time' => '18:00', 'duration_minutes' => 90, 'from' => $monday, 'to' => $monday])->assertSessionHasErrors('days');

        // Renommer le groupe met à jour le libellé « session » des inscriptions.
        $this->put("/admin/groupes/{$groupe->id}", ['name' => 'Débutants A', 'mode' => 'en_ligne', 'meet_url' => 'https://meet.google.com/abc-defg-hij', 'is_active' => 1])->assertRedirect();
        $this->assertSame('Débutants A', $awa->fresh()->session);
        $this->assertTrue(ActivityLog::where('action', 'seances.planned')->exists());
    }

    public function test_a_cancelled_session_warns_learners(): void
    {
        Mail::fake();
        $groupe = Groupe::factory()->create();
        Inscription::factory()->create(['groupe_id' => $groupe->id, 'email' => 'fatou@exemple.ml', 'status' => InscriptionStatus::EnCours]);
        $seance = Seance::factory()->create(['groupe_id' => $groupe->id]);
        $this->actingAs(User::factory()->administrateur()->create());

        $this->put("/admin/groupes/{$groupe->id}/seances/{$seance->id}/annulation", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->put("/admin/groupes/{$groupe->id}/seances/{$seance->id}/annulation", ['reason' => 'Formateur malade'])->assertRedirect();
        $this->assertTrue($seance->fresh()->is_cancelled);
        Mail::assertSent(SeancesCalendrier::class, fn ($m) => $m->kind === 'annulee' && str_contains($m->render(), 'Formateur malade'));
    }

    public function test_the_learner_sees_the_calendar_joins_on_time_and_downloads_materials(): void
    {
        Storage::fake('local');
        $client = Client::factory()->create();
        $groupe = Groupe::factory()->create(['name' => 'Intermédiaires', 'meet_url' => 'https://meet.google.com/xyz-abcd-efg']);
        Inscription::factory()->create(['client_id' => $client->id, 'groupe_id' => $groupe->id, 'status' => InscriptionStatus::EnCours]);
        Seance::factory()->create(['groupe_id' => $groupe->id, 'starts_at' => now()->addMinutes(10), 'title' => 'Présent simple']);
        $later = Seance::factory()->create(['groupe_id' => $groupe->id, 'starts_at' => now()->addDays(2)]);
        $other = Groupe::factory()->create(['name' => 'Autre groupe']);
        Seance::factory()->create(['groupe_id' => $other->id, 'title' => 'Séance des autres']);

        $admin = User::factory()->administrateur()->create();
        $this->actingAs($admin)->post("/admin/groupes/{$groupe->id}/supports", ['title' => 'Leçon 1', 'file' => UploadedFile::fake()->create('lecon1.pdf', 200, 'application/pdf')])->assertRedirect();
        $this->post("/admin/groupes/{$groupe->id}/supports", ['title' => 'Vidéo', 'url' => 'youtu.be/abc'])->assertRedirect();
        $this->post("/admin/groupes/{$groupe->id}/supports", ['title' => 'Rien'])->assertSessionHasErrors('file');
        $pdf = Support::where('title', 'Leçon 1')->firstOrFail();
        $this->assertSame(['document', 'video'], [$pdf->kind, Support::where('title', 'Vidéo')->value('kind')]);

        $this->actingAs($client, 'client')->get('/espace-client/calendrier')->assertOk()
            ->assertSee('Présent simple')->assertSee('Rejoindre la séance')->assertSee('https://meet.google.com/xyz-abcd-efg', false)
            ->assertSee('Leçon 1')->assertDontSee('Séance des autres');
        $this->get("/espace-client/calendrier/supports/{$pdf->id}")->assertOk();
        $foreign = Support::create(['groupe_id' => $other->id, 'title' => 'Privé', 'kind' => 'document', 'path' => 'supports/x.pdf']);
        $this->get("/espace-client/calendrier/supports/{$foreign->id}")->assertNotFound();
        $this->get('/espace-client')->assertOk()->assertSee('Mon calendrier');
        $this->assertFalse($later->isJoinable());
    }

    public function test_permissions_follow_the_roles(): void
    {
        $groupe = Groupe::factory()->create();
        $this->actingAs(User::factory()->commercial()->create());
        $this->get('/admin/groupes')->assertOk();
        $this->post('/admin/groupes', ['name' => 'X', 'mode' => 'presentiel'])->assertForbidden();
        $this->post("/admin/groupes/{$groupe->id}/supports", ['title' => 'X', 'url' => 'https://example.org'])->assertForbidden();
        $this->get('/admin')->assertOk()->assertSee('Séances aujourd');
    }

    public function test_announcements_reach_the_right_learners(): void
    {
        Mail::fake();
        $groupe = Groupe::factory()->create(['name' => 'Débutants']);
        $other = Groupe::factory()->create(['name' => 'Avancés']);
        $client = Client::factory()->create();
        Inscription::factory()->create(['client_id' => $client->id, 'groupe_id' => $groupe->id, 'status' => InscriptionStatus::EnCours, 'email' => 'awa@exemple.ml']);
        Inscription::factory()->create(['groupe_id' => $other->id, 'status' => InscriptionStatus::EnCours, 'email' => 'moussa@exemple.ml']);
        $this->actingAs(User::factory()->gestionnaire()->create());

        $this->get('/admin/groupes')->assertOk()->assertSee('ANNONCES AUX APPRENANTS');
        $this->post('/admin/groupes/annonces', ['title' => '', 'body' => 'X'])->assertSessionHasErrors('title');
        $this->post('/admin/groupes/annonces', ['title' => 'Pas de cours lundi', 'body' => 'Jour férié.', 'email' => '1'])->assertRedirect('/admin/groupes');
        $this->post('/admin/groupes/annonces', ['title' => 'Réservé aux avancés', 'body' => 'Sortie.', 'groupe_id' => $other->id, 'back' => $other->id])->assertRedirect("/admin/groupes/{$other->id}");
        $this->post('/admin/groupes/annonces', ['title' => 'Ancienne annonce', 'body' => 'Expirée.']);
        Annonce::where('title', 'Ancienne annonce')->update(['expires_on' => now()->subDay()]);

        Mail::assertSent(AnnonceCentre::class, 2);
        $this->assertSame(2, Annonce::where('title', 'Pas de cours lundi')->value('emailed_count'));
        $this->assertTrue(ActivityLog::where('action', 'seances.annonce_published')->exists());

        $this->actingAs($client, 'client')->get('/espace-client/calendrier')->assertOk()
            ->assertSee('Pas de cours lundi')->assertDontSee('Réservé aux avancés')->assertDontSee('Ancienne annonce');
        $this->get('/espace-client')->assertOk()->assertSee('Annonces du centre');

        $this->actingAs(User::factory()->commercial()->create())->post('/admin/groupes/annonces', ['title' => 'X', 'body' => 'Y'])->assertForbidden();
    }

    public function test_a_learner_is_placed_in_a_group_from_the_inscription_page(): void
    {
        $niveau = Niveau::factory()->create(['name' => 'Intermédiaire']);
        $a = Groupe::factory()->create(['name' => 'Soir A']);
        $b = Groupe::factory()->create(['name' => 'Soir B', 'niveau_id' => $niveau->id]);
        $inscription = Inscription::factory()->create(['status' => InscriptionStatus::EnCours, 'amount_paid' => 20000]);
        $this->actingAs(User::factory()->administrateur()->create());

        $this->get("/admin/inscriptions/{$inscription->id}")->assertOk()->assertSee('Placer dans un groupe')->assertSee('Soir A')->assertSee('Soir B');
        $this->put("/admin/inscriptions/{$inscription->id}/dossier", ['groupe_id' => $a->id])->assertRedirect();
        $this->assertSame([$a->id, 'Soir A'], [$inscription->fresh()->groupe_id, $inscription->fresh()->session]);
        $this->get("/admin/inscriptions/{$inscription->id}")->assertSee('Groupe :')->assertDontSee('Placer dans un groupe');

        $this->put("/admin/inscriptions/{$inscription->id}/dossier", ['groupe_id' => $b->id, 'internal_notes' => 'Changement de niveau'])->assertRedirect();
        $this->assertSame([$b->id, 'Soir B', 'Changement de niveau'], [$inscription->fresh()->groupe_id, $inscription->fresh()->session, $inscription->fresh()->internal_notes]);
        $this->put("/admin/inscriptions/{$inscription->id}/dossier", ['groupe_id' => ''])->assertRedirect();
        $this->assertNull($inscription->fresh()->groupe_id);
        $this->assertTrue(ActivityLog::where('action', 'seances.members_added')->exists());
    }
}
