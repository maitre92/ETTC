<?php

namespace Tests\Feature\Modules;

use App\Enums\InscriptionStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\TestStatus;
use App\Mail\TestEnLigneResultat;
use App\Mail\TestNiveauConvocation;
use App\Models\Formule;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\Paiement;
use App\Models\TestNiveau;
use App\Models\TestQuestion;
use App\Models\User;
use App\Services\Remunerations\RemunerationService;
use App\Support\SpamGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsFoundation;
use Tests\TestCase;

/** Test de niveau en ligne, banque de questions, test confié à un formateur (demande du centre, 2026-11-23). */
class TestEnLigneTest extends TestCase
{
    use BuildsFoundation, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncPermissions();
        config(['site.notification_email' => 'equipe@ettc.test']);
    }

    public function test_a_visitor_takes_the_online_test_and_gets_an_instant_level(): void
    {
        Mail::fake();
        Storage::fake('local');
        $debutant = Niveau::factory()->create(['name' => 'Débutant', 'min_score' => 0, 'sort_order' => 1]);
        $avance = Niveau::factory()->create(['name' => 'Avancé', 'min_score' => 60, 'sort_order' => 2]);
        TestQuestion::query()->delete();
        $questions = collect(range(1, 6))->map(fn ($i) => TestQuestion::create(['section' => 'grammaire', 'difficulty' => 1, 'question' => "Question {$i}", 'choices' => ['a', 'b', 'c'], 'answer' => 1, 'is_active' => true]));
        $oral = TestQuestion::create(['section' => 'speaking', 'difficulty' => 1, 'question' => 'Introduce yourself', 'choices' => [], 'answer' => 0, 'is_active' => true]);

        $this->get('/test-de-niveau')->assertOk()->assertSee('Passer le test en ligne maintenant');
        $this->post('/test-de-niveau/en-ligne', ['name' => 'Awa', 'phone' => '75 22 91 25', 'email' => 'awa@exemple.ml', 'consent' => '1', SpamGuard::TOKEN => Crypt::encryptString((string) now()->subSeconds(20)->getTimestamp())])->assertRedirect();
        $test = TestNiveau::firstOrFail();
        $this->assertSame('en_ligne', $test->source);

        $page = $this->get("/test-de-niveau/en-ligne/{$test->online_token}")->assertOk()->assertSee('Question 1')->assertSee('Introduce yourself');
        $this->assertStringNotContainsString('"answer"', $page->getContent());

        // 5 bonnes réponses sur 6 (83 %) → Avancé ; enregistrement de l'oral gardé.
        $answers = $questions->mapWithKeys(fn ($q, $i) => [$q->id => $i === 0 ? 0 : 1])->all();
        $this->post("/test-de-niveau/en-ligne/{$test->online_token}", ['answers' => $answers, 'speaking' => [$oral->id => UploadedFile::fake()->create('oral.webm', 50, 'audio/webm')]])
            ->assertRedirect("/test-de-niveau/en-ligne/{$test->online_token}/resultat");
        $test->refresh();
        $this->assertSame([TestStatus::Passe, $avance->id, '5/6 (83 %)'], [$test->status, $test->niveau_id, $test->score]);
        $this->assertNotNull($test->online_answers[$oral->id]['recording'] ?? null);
        $this->get("/test-de-niveau/en-ligne/{$test->online_token}/resultat")->assertOk()->assertSee('Avancé')->assertSee('écoutée par un formateur');
        Mail::assertSent(TestEnLigneResultat::class, 2);
        // Une seule soumission.
        $this->post("/test-de-niveau/en-ligne/{$test->online_token}", ['answers' => []])->assertSessionHasErrors('test');

        // L'équipe voit les réponses et écoute l'oral.
        $this->actingAs(User::factory()->administrateur()->create())->get("/admin/tests/{$test->id}")->assertOk()->assertSee('TEST EN LIGNE')->assertSee('Introduce yourself');
        $this->get("/admin/tests/{$test->id}/oral/{$oral->id}")->assertOk();
    }

    public function test_questions_are_typed_or_imported_from_a_word_file(): void
    {
        $this->actingAs(User::factory()->administrateur()->create());
        $this->get('/admin/questions')->assertOk()->assertSee('QUESTIONS DU TEST EN LIGNE');
        $this->post('/admin/questions', ['section' => 'grammaire', 'difficulty' => 1, 'question' => 'I ___ happy.', 'choices_text' => "*am\nis\nare", 'is_active' => '1'])->assertRedirect();
        $this->assertSame(['am', 'is', 'are'], TestQuestion::where('question', 'I ___ happy.')->first()->choices);
        $this->post('/admin/questions', ['section' => 'grammaire', 'difficulty' => 1, 'question' => 'Sans étoile', 'choices_text' => "a\nb"])->assertSessionHasErrors('choices_text');

        // Questionnaire Word (.docx) : questions, réponses, « * » devant la bonne, sujet oral.
        $path = tempnam(sys_get_temp_dir(), 'docx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $paragraphs = ['Vocabulaire', '1. The opposite of hot is…', 'A) warm', '*B) cold', 'C) big', '2. Sans bonne réponse', 'A) x', 'B) y', 'Speaking: Describe your city.'];
        $zip->addFromString('word/document.xml', '<w:document xmlns:w="w"><w:body>'.collect($paragraphs)->map(fn ($p) => '<w:p><w:r><w:t>'.htmlspecialchars($p).'</w:t></w:r></w:p>')->implode('').'</w:body></w:document>');
        $zip->close();
        $before = TestQuestion::count();
        $this->post('/admin/questions/word', ['document' => new UploadedFile($path, 'questionnaire.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true)])
            ->assertRedirect()->assertSessionHas('status', fn ($s) => str_contains($s, '2 question(s) importée(s)') && str_contains($s, 'Ignorées'));
        $this->assertSame($before + 2, TestQuestion::count());
        $this->assertSame([['vocabulaire', 1], ['speaking', 0]], TestQuestion::latest('id')->take(2)->get()->reverse()->map(fn ($q) => [$q->section, $q->answer])->values()->all());

        $this->get('/admin/questions/lot')->assertOk();
        $this->actingAs(User::factory()->commercial()->create())->get('/admin/questions')->assertForbidden();
    }

    public function test_the_teacher_who_runs_the_test_receives_the_registration_fee(): void
    {
        Mail::fake();
        $formateur = User::factory()->withRole(RoleName::Formateur)->create();
        $niveau = Niveau::factory()->create();
        $test = TestNiveau::factory()->create(['phone' => '+22375229125', 'status' => TestStatus::Planifie, 'examiner_id' => $formateur->id]);

        // Dans son espace : il saisit le résultat.
        $this->actingAs($formateur)->get('/admin/mon-espace-formateur')->assertOk()->assertSee('MES TESTS DE NIVEAU À FAIRE PASSER')->assertSee($test->name);
        $this->put("/admin/mon-espace-formateur/tests/{$test->id}/resultat", ['niveau_id' => $niveau->id, 'score' => '14/20'])->assertRedirect();
        $this->assertSame(TestStatus::Passe, $test->fresh()->status);

        // L'apprenant s'inscrit (même téléphone : test lié) et paie : les 5 000 F de frais d'inscription vont au formateur.
        $inscription = Inscription::factory()->create(['test_niveau_id' => $test->id, 'formule_id' => Formule::factory(), 'registration_fee' => 5000, 'total_amount' => 105000, 'status' => InscriptionStatus::EnCours]);
        Paiement::factory()->create(['inscription_id' => $inscription->id, 'amount' => 55000, 'status' => PaymentStatus::Confirme, 'paid_at' => now()]);
        $lines = app(RemunerationService::class)->forTeacher($formateur, now()->format('Y-m'))['lines'];
        $this->assertSame([['test', 5000]], $lines->map(fn ($l) => [$l['kind'], $l['share']])->all());
    }

    public function test_a_questionnaire_document_is_downloaded_and_answered_by_the_candidate(): void
    {
        Mail::fake();
        Storage::fake('local');
        $test = TestNiveau::factory()->create(['email' => 'awa@exemple.ml', 'status' => TestStatus::Demande]);
        $this->actingAs(User::factory()->administrateur()->create());
        $this->post("/admin/tests/{$test->id}/questionnaire", ['questionnaire' => UploadedFile::fake()->create('questionnaire.pdf', 100, 'application/pdf')])->assertRedirect();
        $test->refresh();
        $this->assertSame('questionnaire.pdf', $test->questionnaire_name);
        $this->put("/admin/tests/{$test->id}/planification", ['scheduled_at' => now()->addDay()->format('Y-m-d H:i'), 'test_mode' => 'distance'])->assertRedirect();
        Mail::assertSent(TestNiveauConvocation::class, fn ($m) => str_contains($m->render(), 'Mon questionnaire'));
        auth()->logout();

        $this->get($test->candidateUrl())->assertOk()->assertSee('Télécharger le questionnaire');
        $this->get("/test-de-niveau/{$test->id}/".str_repeat('a', 24))->assertNotFound();
        $this->get(route('site.tests.paper.download', ['test' => $test->id, 'key' => $test->candidateKey()]))->assertOk();
        $this->post(route('site.tests.paper.answers', ['test' => $test->id, 'key' => $test->candidateKey()]), ['answers' => UploadedFile::fake()->image('reponses.jpg')])->assertRedirect();
        $this->assertNotNull($test->fresh()->answers_at);

        $this->actingAs(User::factory()->administrateur()->create())->get("/admin/tests/{$test->id}")->assertOk()->assertSee('Réponses reçues');
        $this->get("/admin/tests/{$test->id}/fichier/reponses")->assertOk();
    }
}
