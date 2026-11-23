<?php

namespace App\Http\Controllers\Site;

use App\Enums\TestStatus;
use App\Http\Controllers\Controller;
use App\Models\TestNiveau;
use App\Services\Audit\ActivityLogger;
use App\Services\Tests\OnlineTestService;
use App\Support\Seo;
use App\Support\SpamGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Test de niveau en ligne, pour tout visiteur (demande du centre, 2026-11-23) : identité → questions (QCM, oral au
 * micro) avec compte à rebours → résultat instantané (niveau provisoire, « M'inscrire »). Lien secret par test.
 */
class TestEnLigneController extends Controller
{
    public function __construct(private readonly OnlineTestService $service) {}

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 .\-()]{6,28}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'consent' => ['accepted'],
        ], ['consent.accepted' => 'Cochez la case pour accepter que le centre conserve votre résultat.'], ['name' => 'nom', 'phone' => 'téléphone', 'email' => 'e-mail']);
        if (SpamGuard::isSpam($request)) {
            return redirect()->route('site.tests.show');
        }
        $test = $this->service->start($data, $request);

        return redirect()->route('site.tests.online.take', $test->online_token);
    }

    public function take(string $token): View|RedirectResponse
    {
        $test = TestNiveau::where('online_token', $token)->firstOrFail();
        if ($test->status === TestStatus::Passe) {
            return redirect()->route('site.tests.online.result', $token);
        }

        return view('site.tests.en-ligne', [
            'test' => $test,
            'questions' => $this->service->questions($test),
            'deadline' => $this->service->deadline($test),
            'seo' => Seo::make('Test de niveau en ligne')->noIndex(),
        ]);
    }

    public function submit(Request $request, string $token): RedirectResponse
    {
        $test = TestNiveau::where('online_token', $token)->firstOrFail();
        $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*' => ['nullable', 'integer', 'between:0,9'],
            'speaking' => ['nullable', 'array'],
            'speaking.*' => ['nullable', 'file', 'max:15360', 'mimetypes:audio/webm,audio/ogg,audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,audio/aac,video/webm,video/mp4,video/3gpp,audio/3gpp'],
        ], ['speaking.*.max' => 'Enregistrement trop long (15 Mo au plus).', 'speaking.*.mimetypes' => 'Enregistrement audio non reconnu.']);
        $this->service->submit($test, $request->input('answers', []), $request->file('speaking', []));

        return redirect()->route('site.tests.online.result', $token);
    }

    public function result(string $token): View
    {
        $test = TestNiveau::with('niveau')->where('online_token', $token)->where('status', TestStatus::Passe->value)->firstOrFail();

        return view('site.tests.resultat', [
            'test' => $test,
            'hasOral' => collect($test->online_answers ?? [])->contains(fn ($a) => isset($a['recording'])),
            'seo' => Seo::make('Résultat du test de niveau')->noIndex(),
        ]);
    }

    /** Questionnaire en document : page du candidat (lien de sa convocation) — télécharger, renvoyer ses réponses. */
    public function paper(TestNiveau $test, string $key): View
    {
        abort_unless(hash_equals($test->candidateKey(), $key) && $test->questionnaire_path, 404);

        return view('site.tests.questionnaire', ['test' => $test, 'key' => $key, 'seo' => Seo::make('Questionnaire du test de niveau')->noIndex()]);
    }

    public function paperDownload(TestNiveau $test, string $key): StreamedResponse
    {
        abort_unless(hash_equals($test->candidateKey(), $key) && $test->questionnaire_path && Storage::disk('local')->exists($test->questionnaire_path), 404);

        return Storage::disk('local')->download($test->questionnaire_path, $test->questionnaire_name);
    }

    public function paperAnswers(Request $request, TestNiveau $test, string $key, ActivityLogger $logger): RedirectResponse
    {
        abort_unless(hash_equals($test->candidateKey(), $key) && $test->questionnaire_path, 404);
        $file = $request->validate(['answers' => ['required', 'file', 'mimes:pdf,doc,docx,odt,jpg,jpeg,png,webp', 'max:10240']], ['answers.required' => 'Choisissez le fichier de vos réponses (photo, PDF ou Word).'], ['answers' => 'réponses'])['answers'];
        if ($test->answers_path) {
            Storage::disk('local')->delete($test->answers_path);
        }
        TestNiveau::withoutActivityLog(fn () => $test->update([
            'answers_path' => $file->storeAs('tests/'.$test->id, 'reponses-'.Str::random(8).'.'.$file->extension(), 'local'),
            'answers_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'answers_at' => now(),
        ]));
        $logger->log('tests.answers_received', 'tests', "Réponses reçues pour le test {$test->number} ({$test->name})", $test);

        return redirect()->route('site.tests.paper', ['test' => $test->id, 'key' => $key])->with('status', 'Vos réponses sont bien envoyées : le centre les corrige et vous communique votre niveau.');
    }
}
