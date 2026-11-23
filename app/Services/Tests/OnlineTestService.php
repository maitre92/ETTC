<?php

namespace App\Services\Tests;

use App\Enums\TestMode;
use App\Enums\TestStatus;
use App\Mail\TestEnLigneResultat;
use App\Models\Niveau;
use App\Models\TestNiveau;
use App\Models\TestQuestion;
use App\Services\Audit\ActivityLogger;
use App\Services\Numbering\SequenceService;
use App\Support\PhoneNumber;
use App\Support\SiteSettings;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Test de niveau EN LIGNE (demande du centre, 2026-11-23), ouvert à tout visiteur :
 *  - départ : identité, questions tirées au sort (QCM de chaque partie, du plus facile au plus difficile) et sujets
 *    d'expression orale ; un lien secret (jeton) ;
 *  - envoi : QCM corrigés AUSSITÔT → score et niveau provisoire (niveaux.min_score) ; enregistrements oraux gardés
 *    (fichiers privés) pour qu'un formateur les écoute et confirme le niveau ;
 *  - durée limitée (réglage), contrôlée par le serveur ; une seule soumission.
 * Les bonnes réponses ne quittent jamais le serveur.
 */
class OnlineTestService
{
    public const GRACE_MINUTES = 2;

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly SequenceService $sequences,
        private readonly SiteSettings $settings,
    ) {}

    public function minutes(): int
    {
        return max(5, (int) $this->settings->get('test.online_minutes'));
    }

    public function available(): bool
    {
        return TestQuestion::active()->where('section', '!=', 'speaking')->count() >= 5;
    }

    /** @param  array{name: string, phone?: ?string, email?: ?string}  $data */
    public function start(array $data, Request $request): TestNiveau
    {
        if (! $this->available()) {
            throw ValidationException::withMessages(['name' => 'Le test en ligne n\'est pas encore disponible : demandez un test au centre.']);
        }
        $count = max(5, (int) $this->settings->get('test.online_count'));
        $speaking = max(0, (int) $this->settings->get('test.online_speaking'));
        $qcm = TestQuestion::active()->where('section', '!=', 'speaking')->inRandomOrder()->limit($count)->get()->sortBy('difficulty')->pluck('id');
        $oral = $speaking ? TestQuestion::active()->where('section', 'speaking')->inRandomOrder()->limit($speaking)->pluck('id') : collect();

        return DB::transaction(function () use ($data, $request, $qcm, $oral) {
            $test = TestNiveau::create([
                'number' => $this->sequences->next(TestNiveauService::PREFIX),
                'status' => TestStatus::Planifie,
                'source' => 'en_ligne',
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => filled($data['phone'] ?? null) ? (PhoneNumber::normalize($data['phone']) ?? $data['phone']) : null,
                'test_mode' => TestMode::Distance,
                'scheduled_at' => now(),
                'online_token' => Str::random(40),
                'online_questions' => [...$qcm->all(), ...$oral->all()],
                'online_started_at' => now(),
                'consent_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'client_id' => $request->user('client')?->id,
            ]);
            $this->logger->log('tests.online_started', 'tests', "Test en ligne commencé — {$test->number} ({$test->name})", $test);

            return $test;
        });
    }

    /** Questions du test, dans l'ordre (QCM puis oral). @return \Illuminate\Support\Collection<int, TestQuestion> */
    public function questions(TestNiveau $test)
    {
        $ids = $test->online_questions ?? [];

        return TestQuestion::withTrashed()->whereIn('id', $ids)->get()->sortBy(fn ($q) => array_search($q->id, $ids))->values();
    }

    public function deadline(TestNiveau $test): CarbonInterface
    {
        return $test->online_started_at->copy()->addMinutes($this->minutes());
    }

    /**
     * @param  array<int|string, int|string>  $answers  id de question => index choisi
     * @param  array<int|string, UploadedFile>  $recordings  id du sujet oral => enregistrement
     */
    public function submit(TestNiveau $test, array $answers, array $recordings): TestNiveau
    {
        if ($test->status !== TestStatus::Planifie || ! $test->isOnline()) {
            throw ValidationException::withMessages(['test' => 'Ce test a déjà été envoyé.']);
        }
        $late = now()->isAfter($this->deadline($test)->copy()->addMinutes(self::GRACE_MINUTES));

        $questions = $this->questions($test);
        $qcm = $questions->reject->isSpeaking();
        $given = [];
        $good = 0;
        foreach ($qcm as $question) {
            $choice = array_key_exists($question->id, $answers) && $answers[$question->id] !== '' ? (int) $answers[$question->id] : null;
            $correct = $choice !== null && $choice === $question->answer;
            $good += $correct ? 1 : 0;
            $given[$question->id] = ['choice' => $choice, 'correct' => $correct];
        }
        foreach ($questions->filter->isSpeaking() as $question) {
            $file = $recordings[$question->id] ?? null;
            if ($file instanceof UploadedFile && $file->isValid()) {
                $given[$question->id] = ['recording' => $file->storeAs('tests/'.$test->id, 'oral-'.$question->id.'-'.Str::random(8).'.'.($file->extension() ?: 'webm'), 'local')];
            }
        }
        $total = max(1, $qcm->count());
        $percent = (int) round($good * 100 / $total);
        $niveau = Niveau::published()->whereNotNull('min_score')->where('min_score', '<=', $percent)->orderByDesc('min_score')->first()
            ?? Niveau::published()->ordered()->first();
        $hasOral = collect($given)->contains(fn ($g) => isset($g['recording']));

        DB::transaction(function () use ($test, $given, $good, $total, $percent, $niveau, $hasOral, $late) {
            TestNiveau::withoutActivityLog(fn () => $test->update([
                'status' => TestStatus::Passe,
                'online_answers' => $given,
                'score' => "{$good}/{$total} ({$percent} %)",
                'niveau_id' => $niveau?->id,
                'result_notes' => 'Test en ligne : écrit corrigé automatiquement'.($hasOral ? ' ; expression orale à écouter' : '').($late ? ' ; envoyé après le temps imparti' : '').'.',
                'result_at' => now(),
            ]));
            $this->logger->log('tests.online_submitted', 'tests', "Test en ligne envoyé — {$test->number} ({$test->name}) : {$good}/{$total}, niveau provisoire ".($niveau?->name ?? '—'), $test,
                new: ['score' => $percent, 'niveau' => $niveau?->name, 'oral' => $hasOral]);
        });
        $this->notify($test->fresh(['niveau']));

        return $test;
    }

    /** Résultat au candidat (s'il a un e-mail) et alerte à l'équipe. */
    private function notify(TestNiveau $test): void
    {
        foreach (array_filter([$test->email ? [$test->email, 'candidat'] : null, $this->settings->notificationEmail() ? [$this->settings->notificationEmail(), 'equipe'] : null]) as [$to, $kind]) {
            try {
                Mail::to($to)->send(new TestEnLigneResultat($test, $kind));
            } catch (Throwable $e) {
                Log::error("Résultat du test {$test->number} non envoyé ({$kind}) : ".Str::limit($e->getMessage(), 200));
            }
        }
    }
}
