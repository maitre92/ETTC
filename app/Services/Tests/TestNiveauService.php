<?php

namespace App\Services\Tests;

use App\Enums\PaymentMethod;
use App\Enums\TestMode;
use App\Enums\TestStatus;
use App\Mail\TestNiveauAccuse;
use App\Mail\TestNiveauConvocation;
use App\Mail\TestNiveauDemande;
use App\Models\Formule;
use App\Models\Niveau;
use App\Models\TestNiveau;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Numbering\SequenceService;
use App\Support\PhoneNumber;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Cycle d'un test de niveau (cahier §3.4, §5) : demande (site ou centre), planification, encaissement des frais,
 * résultat et niveau attribué, annulation. Chaque étape est enregistrée en transaction puis journalisée ; les e-mails
 * partent APRÈS l'enregistrement et un échec d'envoi ne perd jamais rien (D-4). Une seule ligne d'audit par geste :
 * la ligne métier (anciennes et nouvelles valeurs) ; la journalisation automatique est coupée pour ces mises à jour.
 */
class TestNiveauService
{
    public const PREFIX = 'TST';

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly SequenceService $sequences,
        private readonly SiteSettings $settings,
    ) {}

    /** Frais en vigueur (Formations → Formules et tarifs), figés sur chaque nouvelle demande. */
    public function currentFee(): int
    {
        return (int) $this->settings->get('tarifs.test_niveau');
    }

    /**
     * Demande envoyée depuis le site.
     *
     * @param  array<string, mixed>  $data  données validées
     */
    public function request(array $data, Request $request): TestNiveau
    {
        $test = DB::transaction(function () use ($data, $request) {
            $test = TestNiveau::create($this->identity($data) + [
                'number' => $this->sequences->next(self::PREFIX),
                'status' => TestStatus::Demande,
                'source' => 'site',
                'fee' => $this->currentFee(),
                'consent_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'client_id' => $request->user('client')?->id,
            ]);
            $this->logger->log('tests.requested', 'tests', "Test de niveau demandé — {$test->number} ({$test->name})", $test);

            return $test;
        });

        $this->notifyTeam($test);
        $this->acknowledge($test);

        return $test;
    }

    /**
     * Candidat inscrit par l'équipe (accueil du centre, téléphone).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): TestNiveau
    {
        return DB::transaction(function () use ($data, $actor) {
            $test = TestNiveau::create($this->identity($data) + [
                'number' => $this->sequences->next(self::PREFIX),
                'status' => TestStatus::Demande,
                'source' => 'centre',
                'fee' => $this->currentFee(),
            ]);
            $this->logger->log('tests.created', 'tests', "Test de niveau enregistré au centre — {$test->number} ({$test->name})", $test, actor: $actor);

            return $test;
        });
    }

    /** Planification (ou report) : date et heure, lieu ou lien de connexion ; convocation par e-mail si possible. */
    public function schedule(TestNiveau $test, Carbon $at, TestMode $mode, ?string $location, User $actor): TestNiveau
    {
        $this->ensureNot($test, [TestStatus::Passe, TestStatus::Annule], 'Ce test est déjà passé ou annulé : il ne peut plus être planifié.');

        $rescheduled = $test->scheduled_at !== null;
        DB::transaction(function () use ($test, $at, $mode, $location, $actor, $rescheduled) {
            $old = ['scheduled_at' => $test->scheduled_at?->toDateTimeString(), 'status' => $test->status->value];
            TestNiveau::withoutActivityLog(fn () => $test->update(['status' => TestStatus::Planifie, 'scheduled_at' => $at, 'test_mode' => $mode, 'location' => $location]));
            $this->logger->log($rescheduled ? 'tests.rescheduled' : 'tests.scheduled', 'tests',
                ($rescheduled ? 'Test reporté' : 'Test planifié')." — {$test->number} : {$at->format('d/m/Y H:i')} ({$mode->label()})",
                $test, $old, ['scheduled_at' => $at->toDateTimeString(), 'status' => TestStatus::Planifie->value], $actor);
        });

        $this->convoke($test);

        return $test;
    }

    /** Encaissement des frais du test (montant figé à la demande), une seule fois. */
    public function recordPayment(TestNiveau $test, PaymentMethod $method, ?string $reference, User $actor): TestNiveau
    {
        if ($test->paid_at !== null) {
            throw ValidationException::withMessages(['payment_method' => 'Les frais de ce test sont déjà enregistrés comme payés.']);
        }
        $this->ensureNot($test, [TestStatus::Annule], 'Ce test est annulé : ses frais ne peuvent pas être encaissés.');

        return DB::transaction(function () use ($test, $method, $reference, $actor) {
            TestNiveau::withoutActivityLog(fn () => $test->update(['paid_at' => now(), 'payment_method' => $method, 'payment_reference' => $reference ?: null, 'payment_recorded_by' => $actor->id]));
            $this->logger->log('tests.payment_recorded', 'tests', 'Frais du test encaissés — '.$test->number.' : '.Formule::money($test->fee).' ('.$method->label().')',
                $test, [], ['fee' => $test->fee, 'payment_method' => $method->value, 'payment_reference' => $reference], $actor);

            return $test;
        });
    }

    /** Résultat et niveau attribué (le test passe à « Passé ») ; corrigeable ensuite, chaque version auditée. */
    public function recordResult(TestNiveau $test, Niveau $niveau, ?string $score, ?string $notes, User $actor): TestNiveau
    {
        $this->ensureNot($test, [TestStatus::Annule], 'Ce test est annulé : aucun résultat ne peut être enregistré.');

        return DB::transaction(function () use ($test, $niveau, $score, $notes, $actor) {
            $old = ['niveau' => $test->niveau?->name, 'score' => $test->score];
            TestNiveau::withoutActivityLog(fn () => $test->update(['status' => TestStatus::Passe, 'niveau_id' => $niveau->id, 'score' => $score ?: null, 'result_notes' => $notes ?: null, 'result_at' => now(), 'result_by' => $actor->id]));
            $this->logger->log('tests.result_recorded', 'tests', "Résultat du test — {$test->number} : niveau {$niveau->name}".($score ? " ({$score})" : ''),
                $test, $old, ['niveau' => $niveau->name, 'score' => $score], $actor);

            return $test;
        });
    }

    public function cancel(TestNiveau $test, string $reason, User $actor): TestNiveau
    {
        $this->ensureNot($test, [TestStatus::Passe, TestStatus::Annule], 'Un test passé ou déjà annulé ne peut pas être annulé.');

        return DB::transaction(function () use ($test, $reason, $actor) {
            $old = $test->status;
            TestNiveau::withoutActivityLog(fn () => $test->update(['status' => TestStatus::Annule, 'cancel_reason' => $reason]));
            $this->logger->log('tests.cancelled', 'tests', "Test annulé — {$test->number} : {$reason}", $test, ['status' => $old->value], ['status' => TestStatus::Annule->value, 'reason' => $reason], $actor);

            return $test;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function identity(array $data): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => isset($data['phone']) && $data['phone'] !== '' ? (PhoneNumber::normalize($data['phone']) ?? $data['phone']) : null,
            'test_mode' => $data['test_mode'] ?? TestMode::Centre->value,
            'preferred_slot' => $data['preferred_slot'] ?? null,
            'message' => $data['message'] ?? null,
        ];
    }

    /** @param  list<TestStatus>  $forbidden */
    private function ensureNot(TestNiveau $test, array $forbidden, string $message): void
    {
        if (in_array($test->status, $forbidden, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    private function notifyTeam(TestNiveau $test): void
    {
        $recipient = $this->settings->notificationEmail();
        try {
            if (! $recipient) {
                throw new RuntimeException('Aucun destinataire configuré (SITE_NOTIFICATION_EMAIL ou e-mail de contact).');
            }
            Mail::to($recipient)->send(new TestNiveauDemande($test));
            $test->forceFill(['notified_at' => now(), 'notification_error' => null])->saveQuietly();
        } catch (Throwable $e) {
            $message = Str::limit($e->getMessage(), 480);
            $test->forceFill(['notification_error' => $message])->saveQuietly();
            Log::error("Notification du test {$test->number} impossible : {$message}");
        }
    }

    private function acknowledge(TestNiveau $test): void
    {
        $this->mailCandidate($test, new TestNiveauAccuse($test), 'accusé de réception');
    }

    private function convoke(TestNiveau $test): void
    {
        $this->mailCandidate($test, new TestNiveauConvocation($test), 'convocation');
    }

    private function mailCandidate(TestNiveau $test, Mailable $mail, string $what): void
    {
        if (! $test->email) {
            return;
        }
        try {
            Mail::to($test->email, $test->name)->send($mail);
        } catch (Throwable $e) {
            $message = Str::limit($e->getMessage(), 480);
            Log::error("Envoi de la {$what} du test {$test->number} impossible : {$message}");
            $this->logger->log('tests.mail_failed', 'tests', "Échec de l'envoi ({$what}) — {$test->number}", $test, new: ['error' => $message]);
        }
    }
}
