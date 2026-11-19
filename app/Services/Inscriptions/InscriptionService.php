<?php

namespace App\Services\Inscriptions;

use App\Enums\Billing;
use App\Enums\FormationMode;
use App\Enums\InscriptionStatus;
use App\Enums\PaymentChoice;
use App\Mail\InscriptionRecapitulatif;
use App\Mail\InscriptionRecue;
use App\Models\Formule;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\TestNiveau;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Numbering\SequenceService;
use App\Services\Paiements\PaiementService;
use App\Support\PhoneNumber;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Cycle d'une inscription (cahier §3.5, §5) : demande en ligne ou au centre (tarif FIGÉ), validation du dossier,
 * début et fin de formation, session, annulation. Une ligne d'audit métier par geste (anciennes et nouvelles valeurs) ;
 * e-mails après l'enregistrement, un échec d'envoi ne perd jamais l'inscription (D-4).
 */
class InscriptionService
{
    public const PREFIX = 'INS';

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly SequenceService $sequences,
        private readonly SiteSettings $settings,
    ) {}

    /**
     * Tarif applicable à une formule dans un mode, figé sur l'inscription.
     *
     * @return array{formule_name: string, duration_months: int, billing: Billing, unit_price: int, total_amount: int}
     */
    public function quote(Formule $formule, FormationMode $mode): array
    {
        $price = $formule->{$mode->priceColumn()};
        if (! $formule->is_published || $price === null) {
            throw ValidationException::withMessages(['mode' => "La formule « {$formule->name} » n'est pas proposée ".mb_strtolower($mode->label()).'.']);
        }

        return [
            'formule_name' => $formule->name,
            'duration_months' => $formule->duration_months,
            'billing' => $formule->billing,
            'unit_price' => (int) $price,
            'total_amount' => (int) $formule->total($mode->priceColumn()),
        ];
    }

    /**
     * Inscription envoyée depuis le site.
     *
     * @param  array<string, mixed>  $data  données validées
     */
    public function submit(array $data, Request $request): Inscription
    {
        // Paiement en ligne déjà fait : référence donnée à l'inscription, contrôlée AVANT tout enregistrement.
        $payments = app(PaiementService::class);
        $declared = ($data['payment_choice'] ?? null) === PaymentChoice::EnLigne->value && filled($data['pay_reference'] ?? null);
        if ($declared) {
            $quote = $this->quote(Formule::findOrFail($data['formule_id']), FormationMode::from($data['mode']));
            $payments->assertDeclarable((string) $data['pay_method'], (string) $data['pay_reference'], (int) $data['pay_amount'], $quote['total_amount']);
        }

        $paiement = null;
        $inscription = DB::transaction(function () use ($data, $request, $declared, $payments, &$paiement) {
            $inscription = Inscription::create($this->attributes($data) + [
                'number' => $this->sequences->next(self::PREFIX),
                'status' => InscriptionStatus::AValider,
                'source' => 'site',
                'consent_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'client_id' => $request->user('client')?->id,
            ]);
            $this->logger->log('inscriptions.submitted', 'inscriptions', "Inscription reçue — {$inscription->number} ({$inscription->name}) : {$inscription->summary()}", $inscription);
            if ($declared) {
                $paiement = $payments->declareAtInscription($inscription, [
                    'method' => $data['pay_method'], 'amount' => (int) $data['pay_amount'], 'reference' => $data['pay_reference'], 'payer_phone' => $data['pay_phone'] ?? $data['phone'] ?? null,
                ], $request);
            }

            return $inscription;
        });

        // Paiement déclaré : UN e-mail à la personne qui vérifie ; l'apprenant reçoit le sien à la confirmation (avec
        // son reçu). Sinon : alerte à l'équipe et récapitulatif à l'apprenant (numéros et QR pour payer plus tard).
        $this->notifyTeam($inscription, $paiement);
        if (! $paiement) {
            $this->mailLearner($inscription, new InscriptionRecapitulatif($inscription), 'récapitulatif');
        }
        $inscription->setRelation('declaredPayment', $paiement);

        return $inscription;
    }

    /**
     * Inscription saisie par l'équipe (au centre), éventuellement à la suite d'un test de niveau.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor, ?TestNiveau $test = null): Inscription
    {
        return DB::transaction(function () use ($data, $actor, $test) {
            $inscription = Inscription::create($this->attributes($data) + [
                'number' => $this->sequences->next(self::PREFIX),
                'status' => InscriptionStatus::AValider,
                'source' => 'centre',
                'test_niveau_id' => $test?->id,
                'client_id' => $data['client_id'] ?? $test?->client_id,
            ]);
            $this->logger->log('inscriptions.created', 'inscriptions', "Inscription enregistrée au centre — {$inscription->number} ({$inscription->name}) : {$inscription->summary()}", $inscription, actor: $actor);

            return $inscription;
        });
    }

    /** Dossier validé par le centre : l'inscription attend le paiement (cahier §3.8 B). */
    public function validateFile(Inscription $inscription, User $actor): Inscription
    {
        $this->ensureStatus($inscription, [InscriptionStatus::AValider], 'Seule une inscription « À valider » peut être validée.');

        return $this->transition($inscription, InscriptionStatus::Validee, ['validated_at' => now(), 'validated_by' => $actor->id], 'inscriptions.validated', 'Dossier validé', $actor);
    }

    /** Formation commencée (en attendant le module Paiements, l'équipe confirme le début à la main). */
    public function start(Inscription $inscription, User $actor): Inscription
    {
        $this->ensureStatus($inscription, [InscriptionStatus::AValider, InscriptionStatus::Validee], 'Seule une inscription en attente peut commencer.');

        return $this->transition($inscription, InscriptionStatus::EnCours, ['started_at' => now(), 'validated_at' => $inscription->validated_at ?? now(), 'validated_by' => $inscription->validated_by ?? $actor->id], 'inscriptions.started', 'Formation commencée', $actor);
    }

    public function finish(Inscription $inscription, User $actor): Inscription
    {
        $this->ensureStatus($inscription, [InscriptionStatus::EnCours], 'Seule une formation en cours peut être terminée.');

        return $this->transition($inscription, InscriptionStatus::Terminee, ['finished_at' => now()], 'inscriptions.finished', 'Formation terminée', $actor);
    }

    public function cancel(Inscription $inscription, string $reason, User $actor): Inscription
    {
        $this->ensureStatus($inscription, [InscriptionStatus::AValider, InscriptionStatus::Validee, InscriptionStatus::EnCours], 'Une inscription terminée ou déjà annulée ne peut pas être annulée.');

        return $this->transition($inscription, InscriptionStatus::Annulee, ['cancel_reason' => $reason], 'inscriptions.cancelled', "Inscription annulée : {$reason}", $actor);
    }

    /** Session (organisation par session, cahier §5) et notes internes. */
    public function organize(Inscription $inscription, ?string $session, ?string $notes, User $actor): Inscription
    {
        return DB::transaction(function () use ($inscription, $session, $notes, $actor) {
            $old = ['session' => $inscription->session, 'internal_notes' => $inscription->internal_notes];
            $new = ['session' => $session ?: null, 'internal_notes' => $notes ?: null];
            if ($old === $new) {
                return $inscription;
            }
            Inscription::withoutActivityLog(fn () => $inscription->update($new));
            $this->logger->log('inscriptions.organized', 'inscriptions', "Session / notes — {$inscription->number}".($session ? " : session {$session}" : ''), $inscription, $old, $new, $actor);

            return $inscription;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $formule = Formule::findOrFail($data['formule_id']);
        $mode = FormationMode::from($data['mode']);

        return $this->quote($formule, $mode) + [
            'formule_id' => $formule->id,
            'mode' => $mode,
            'niveau_id' => $data['niveau_id'] ?? null,
            'payment_choice' => $data['payment_choice'],
            'name' => $data['name'],
            'birth_date' => $data['birth_date'] ?? null,
            'gender' => $data['gender'] ?? null,
            'phone' => isset($data['phone']) && $data['phone'] !== '' ? (PhoneNumber::normalize($data['phone']) ?? $data['phone']) : null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'message' => $data['message'] ?? null,
        ];
    }

    /** @param  array<string, mixed>  $extra */
    private function transition(Inscription $inscription, InscriptionStatus $to, array $extra, string $action, string $what, User $actor): Inscription
    {
        return DB::transaction(function () use ($inscription, $to, $extra, $action, $what, $actor) {
            $old = $inscription->status;
            Inscription::withoutActivityLog(fn () => $inscription->update(['status' => $to] + $extra));
            $this->logger->log($action, 'inscriptions', "{$what} — {$inscription->number} ({$inscription->name})", $inscription,
                ['status' => $old->value], ['status' => $to->value] + array_intersect_key($extra, ['cancel_reason' => true]), $actor);

            return $inscription;
        });
    }

    /** @param  list<InscriptionStatus>  $allowed */
    private function ensureStatus(Inscription $inscription, array $allowed, string $message): void
    {
        if (! in_array($inscription->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    private function notifyTeam(Inscription $inscription, ?Paiement $paiement = null): void
    {
        $recipient = $paiement ? app(PaiementService::class)->verifierEmail() : $this->settings->notificationEmail();
        try {
            if (! $recipient) {
                throw new RuntimeException('Aucun destinataire configuré (SITE_NOTIFICATION_EMAIL ou e-mail de contact).');
            }
            Mail::to($recipient)->send(new InscriptionRecue($inscription, $paiement));
            $inscription->forceFill(['notified_at' => now(), 'notification_error' => null])->saveQuietly();
        } catch (Throwable $e) {
            $message = Str::limit($e->getMessage(), 480);
            $inscription->forceFill(['notification_error' => $message])->saveQuietly();
            Log::error("Notification de l'inscription {$inscription->number} impossible : {$message}");
        }
    }

    private function mailLearner(Inscription $inscription, Mailable $mail, string $what): void
    {
        if (! $inscription->email) {
            return;
        }
        try {
            Mail::to($inscription->email, $inscription->name)->send($mail);
        } catch (Throwable $e) {
            $message = Str::limit($e->getMessage(), 480);
            Log::error("Envoi du {$what} de l'inscription {$inscription->number} impossible : {$message}");
            $this->logger->log('inscriptions.mail_failed', 'inscriptions', "Échec de l'envoi ({$what}) — {$inscription->number}", $inscription, new: ['error' => $message]);
        }
    }
}
