<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Admin\TestQuestionRequest;
use App\Models\TestQuestion;
use App\Services\Audit\ActivityLogger;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Banque de questions du test de niveau en ligne (demande du centre, 2026-11-23) : saisie une par une, import d'un
 * fichier (Saisie en lot / import), réglages du test (nombre de questions, de sujets oraux, durée).
 */
class TestQuestionController extends Controller
{
    public function index(SiteSettings $settings): View
    {
        Gate::authorize('viewAny', TestQuestion::class);

        return view('tests.questions', ['questions' => TestQuestion::latest('id')->get(), 'settings' => $settings]);
    }

    public function store(TestQuestionRequest $request): RedirectResponse
    {
        TestQuestion::create($request->questionData());

        return redirect()->route('questions.index')->with('status', 'Question ajoutée.');
    }

    public function update(TestQuestionRequest $request, TestQuestion $question): RedirectResponse
    {
        $question->update($request->questionData());

        return redirect()->route('questions.index')->with('status', 'Question modifiée.');
    }

    public function destroy(TestQuestion $question): RedirectResponse
    {
        Gate::authorize('delete', $question);
        $question->delete();

        return redirect()->route('questions.index')->with('status', 'Question supprimée.');
    }

    public function settings(Request $request, SiteSettings $settings, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('viewAny', TestQuestion::class);
        $data = $request->validate([
            'count' => ['required', 'integer', 'between:5,60'],
            'speaking' => ['required', 'integer', 'between:0,5'],
            'minutes' => ['required', 'integer', 'between:5,120'],
        ], [], ['count' => 'nombre de questions', 'speaking' => 'sujets oraux', 'minutes' => 'durée']);
        $values = ['test.online_count' => (string) $data['count'], 'test.online_speaking' => (string) $data['speaking'], 'test.online_minutes' => (string) $data['minutes']];
        $settings->save($values);
        $logger->log('tests.online_settings', 'tests', "Test en ligne : {$data['count']} questions, {$data['speaking']} sujet(s) oral(aux), {$data['minutes']} min", new: $values);

        return redirect()->route('questions.index')->with('status', 'Réglages du test en ligne enregistrés.');
    }

    /**
     * Questionnaire Word (.docx) : une question par bloc (« 1. … »), réponses « A) … » avec « * » devant la bonne,
     * « Speaking: … » pour un sujet oral ; un titre « Grammaire », « Vocabulaire », « Compréhension » change la partie.
     */
    public function importWord(Request $request, ActivityLogger $logger): RedirectResponse
    {
        Gate::authorize('create', TestQuestion::class);
        $file = $request->validate(['document' => ['required', 'file', 'mimes:docx', 'max:10240']], [], ['document' => 'questionnaire'])['document'];
        $paragraphs = self::docxParagraphs($file->getRealPath());
        [$questions, $skipped] = self::parseQuestionnaire($paragraphs);
        if ($questions === []) {
            return redirect()->route('questions.index')->withErrors(['document' => 'Aucune question reconnue : suivez la présentation indiquée (1. question, A) réponses, * devant la bonne).']);
        }
        DB::transaction(function () use ($questions) {
            foreach ($questions as $question) {
                TestQuestion::create($question + ['is_active' => true]);
            }
        });
        $logger->log('tests.questions_imported', 'tests', count($questions).' question(s) importée(s) du questionnaire Word « '.$file->getClientOriginalName().' »');

        return redirect()->route('questions.index')->with('status', count($questions).' question(s) importée(s).'.($skipped ? ' Ignorées (sans bonne réponse marquée « * ») : '.implode(', ', $skipped).'.' : ''));
    }

    /** Texte des paragraphes d'un .docx (sans bibliothèque : le document est un zip XML). @return list<string> */
    public static function docxParagraphs(string $path): array
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true || ($xml = $zip->getFromName('word/document.xml')) === false) {
            return [];
        }
        $zip->close();
        preg_match_all('~<w:p[ >].*?</w:p>~s', $xml, $blocks);

        return array_values(array_filter(array_map(function (string $p) {
            preg_match_all('~<w:t[^>]*>(.*?)</w:t>~s', $p, $texts);

            return trim(html_entity_decode(implode('', $texts[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }, $blocks[0]), fn (string $line) => $line !== ''));
    }

    /**
     * @param  list<string>  $lines
     * @return array{0: list<array<string, mixed>>, 1: list<string>}
     */
    public static function parseQuestionnaire(array $lines): array
    {
        $sections = ['grammaire' => 'grammaire', 'grammar' => 'grammaire', 'vocabulaire' => 'vocabulaire', 'vocabulary' => 'vocabulaire', 'compréhension' => 'comprehension', 'comprehension' => 'comprehension', 'reading' => 'comprehension'];
        $section = 'grammaire';
        $questions = [];
        $skipped = [];
        $current = null;
        $flush = function () use (&$current, &$questions, &$skipped) {
            if ($current) {
                if ($current['answer'] === null || count($current['choices']) < 2) {
                    $skipped[] = Str::limit($current['question'], 40);
                } else {
                    $questions[] = $current;
                }
            }
            $current = null;
        };
        foreach ($lines as $line) {
            $key = mb_strtolower(trim($line, " :\t"));
            if (isset($sections[$key])) {
                $flush();
                $section = $sections[$key];
            } elseif (preg_match('/^(speaking|oral|expression orale)\s*:\s*(.+)$/iu', $line, $m)) {
                $flush();
                $questions[] = ['section' => 'speaking', 'difficulty' => 1, 'question' => trim($m[2]), 'choices' => [], 'answer' => 0];
            } elseif (preg_match('/^\s*(\*)?\s*([A-Ha-h])\s*[\)\.:-]\s*(.+)$/u', $line, $m) && $current) {
                if ($m[1] === '*') {
                    $current['answer'] = count($current['choices']);
                }
                $current['choices'][] = trim(ltrim($m[3], '*'));
            } elseif (preg_match('/^\s*\d+\s*[\.\)]\s*(.+)$/u', $line, $m)) {
                $flush();
                $current = ['section' => $section, 'difficulty' => 1, 'question' => trim($m[1]), 'choices' => [], 'answer' => null];
            }
        }
        $flush();

        return [$questions, $skipped];
    }
}
