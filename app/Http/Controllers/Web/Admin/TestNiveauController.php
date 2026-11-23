<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\TestMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Site\TestNiveauRequest;
use App\Models\ActivityLog;
use App\Models\Formule;
use App\Models\Inscription;
use App\Models\Niveau;
use App\Models\TestNiveau;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Tests\TestNiveauService;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tests de niveau (cahier §3.4, §5) : suivi des candidats (à planifier, planifiés, passés, annulés), fiche de
 * traitement (planification, résultat, annulation) et présentation du test sur le site. Test gratuit (2026-11-17).
 * Contrôleur mince : les règles vivent dans TestNiveauService. Formulaires envoyés en AJAX (crud-ajax.js).
 */
class TestNiveauController extends Controller
{
    public function __construct(private readonly TestNiveauService $service) {}

    public function index(SiteSettings $settings): View
    {
        Gate::authorize('viewAny', TestNiveau::class);

        return view('tests.index', [
            'tests' => TestNiveau::with('niveau')->latest('id')->get(),
            'presentation' => $settings->get('test.presentation'),
        ]);
    }

    public function store(TestNiveauRequest $request): RedirectResponse
    {
        Gate::authorize('create', TestNiveau::class);

        $test = $this->service->create($request->validated(), $request->user());

        return redirect()->route('tests.show', $test)->with('status', "Candidat inscrit au test : {$test->number}.");
    }

    public function show(TestNiveau $test): View
    {
        Gate::authorize('view', $test);

        return view('tests.show', [
            'test' => $test->load(['niveau', 'client', 'evaluator']),
            'niveaux' => Niveau::ordered()->get(),
            'formules' => $test->niveau ? Formule::published()->ordered()->get() : collect(),
            'inscriptionsDuTest' => Inscription::where('test_niveau_id', $test->id)->where('status', '!=', 'annulee')->get(['id', 'number']),
            'history' => ActivityLog::where('subject_type', $test->getMorphClass())->where('subject_id', $test->id)->latest('id')->limit(30)->get(),
            // Planification proposée : le prochain jour ouvré à 9 h, au dernier lieu utilisé pour ce mode.
            'suggestedSlot' => ($next = now()->addDay()->setTime(9, 0))->isSunday() ? $next->addDay()->format('Y-m-d\TH:i') : $next->format('Y-m-d\TH:i'),
            'recentLocations' => TestNiveau::where('test_mode', $test->test_mode->value)->whereNotNull('location')->latest('scheduled_at')->limit(30)->pluck('location')->unique()->take(5)->values(),
        ]);
    }

    public function schedule(Request $request, TestNiveau $test): RedirectResponse
    {
        Gate::authorize('schedule', $test);
        $data = $request->validate([
            'scheduled_at' => ['required', 'date', 'after:now'],
            'test_mode' => ['required', Rule::enum(TestMode::class)],
            'location' => ['nullable', 'string', 'max:500'],
            'examiner_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')],
        ], ['scheduled_at.after' => 'Choisissez une date et une heure à venir.'], ['scheduled_at' => 'date et heure', 'test_mode' => 'mode', 'location' => 'lieu ou lien', 'examiner_id' => 'formateur']);

        $this->service->schedule($test, Carbon::parse($data['scheduled_at']), TestMode::from($data['test_mode']), $data['location'] ?? null, $request->user(), isset($data['examiner_id']) ? (int) $data['examiner_id'] : null);

        return redirect()->route('tests.show', $test)->with('status', 'Test planifié le '.$test->scheduled_at->format('d/m/Y à H:i').($test->email ? ' : convocation envoyée par e-mail.' : '.'));
    }

    public function result(Request $request, TestNiveau $test): RedirectResponse
    {
        Gate::authorize('result', $test);
        $data = $request->validate([
            'niveau_id' => ['required', Rule::exists('niveaux', 'id')->whereNull('deleted_at')],
            'score' => ['nullable', 'string', 'max:50'],
            'result_notes' => ['nullable', 'string', 'max:3000'],
        ], [], ['niveau_id' => 'niveau attribué', 'score' => 'score', 'result_notes' => 'appréciation']);

        $niveau = Niveau::findOrFail($data['niveau_id']);
        $this->service->recordResult($test, $niveau, $data['score'] ?? null, $data['result_notes'] ?? null, $request->user());

        return redirect()->route('tests.show', $test)->with('status', "Résultat enregistré : niveau {$niveau->name}.");
    }

    public function cancel(Request $request, TestNiveau $test): RedirectResponse
    {
        Gate::authorize('schedule', $test);
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'max:300']], [], ['cancel_reason' => 'motif d\'annulation']);

        $this->service->cancel($test, $data['cancel_reason'], $request->user());

        return redirect()->route('tests.show', $test)->with('status', 'Test annulé.');
    }

    public function destroy(TestNiveau $test): RedirectResponse
    {
        Gate::authorize('delete', $test);

        $test->delete();

        return redirect()->route('tests.index')->with('status', "Test {$test->number} supprimé.");
    }

    /** Présentation du test sur le site (format, durée, déroulement : définis par le centre, cahier §3.4, §12). */
    public function presentation(Request $request, SiteSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($request->user()->can('tests.configure'), 403);
        $data = $request->validate(['presentation' => ['required', 'string', 'max:5000']], [], ['presentation' => 'présentation du test']);

        $settings->save(['test.presentation' => str_replace("\r\n", "\n", trim($data['presentation']))]);
        $logger->log('tests.presentation_updated', 'tests', 'Présentation du test de niveau modifiée');

        return redirect()->route('tests.index')->with('status', 'Présentation du test mise à jour sur le site.');
    }

    /** Enregistrement de l'oral d'un test en ligne (fichier privé). */
    public function recording(TestNiveau $test, int $question): StreamedResponse
    {
        Gate::authorize('view', $test);
        $path = $test->online_answers[$question]['recording'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    /** Test en ligne : formateur qui écoute l'oral et confirme le niveau (frais d'inscription à la clé). */
    public function assignExaminer(Request $request, TestNiveau $test, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('schedule', $test);
        $id = $request->validate(['examiner_id' => ['nullable', Rule::exists('users', 'id')->whereNull('deleted_at')]])['examiner_id'] ?? null;
        TestNiveau::withoutActivityLog(fn () => $test->update(['examiner_id' => $id ?: null]));
        $logger->log('tests.examiner_assigned', 'tests', "Test {$test->number} confié à ".($id ? User::find($id)?->name : 'l\'équipe du centre'), $test, actor: $request->user());

        return redirect()->route('tests.show', $test)->with('status', $id ? 'Test confié au formateur : il le retrouve dans son espace.' : 'Test repris par l\'équipe.');
    }

    /** Questionnaire en document (PDF, Word, Excel, image) pour ce test : téléchargé par le candidat. */
    public function questionnaire(Request $request, TestNiveau $test, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('schedule', $test);
        $file = $request->validate(['questionnaire' => ['required', 'file', 'mimes:pdf,doc,docx,odt,xls,xlsx,jpg,jpeg,png', 'max:10240']], [], ['questionnaire' => 'questionnaire'])['questionnaire'];
        if ($test->questionnaire_path) {
            Storage::disk('local')->delete($test->questionnaire_path);
        }
        TestNiveau::withoutActivityLog(fn () => $test->update([
            'questionnaire_path' => $file->storeAs('tests/'.$test->id, 'questionnaire-'.Str::random(8).'.'.$file->extension(), 'local'),
            'questionnaire_name' => Str::limit($file->getClientOriginalName(), 200, ''),
        ]));
        $logger->log('tests.questionnaire_added', 'tests', "Questionnaire déposé pour le test {$test->number} : {$test->questionnaire_name}", $test, actor: $request->user());

        return redirect()->route('tests.show', $test)->with('status', 'Questionnaire déposé : le candidat le télécharge depuis le lien de sa convocation.');
    }

    /** Questionnaire ou réponses du candidat (fichiers privés). */
    public function paperFile(TestNiveau $test, string $which): StreamedResponse
    {
        Gate::authorize('view', $test);
        [$path, $name] = $which === 'reponses' ? [$test->answers_path, $test->answers_name] : [$test->questionnaire_path, $test->questionnaire_name];
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $name);
    }
}
